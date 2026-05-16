from __future__ import annotations

import argparse
import json
import os
import time
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Any
from urllib.parse import urlparse

import pymysql
from dotenv import load_dotenv
from pyrogram import Client
from pyrogram.errors import SessionPasswordNeeded


ROOT_DIR = Path(__file__).resolve().parents[1]
SESSION_DIR = ROOT_DIR / "storage" / "app" / "telegram-sessions"


def load_config() -> dict[str, Any]:
    load_dotenv(ROOT_DIR / ".env")

    api_id = os.getenv("PYROGRAM_API_ID")
    api_hash = os.getenv("PYROGRAM_API_HASH")

    if not api_id or not api_hash:
        raise RuntimeError("PYROGRAM_API_ID and PYROGRAM_API_HASH must be set in .env")

    return {
        "api_id": int(api_id),
        "api_hash": api_hash,
        "db": database_config(),
    }


def database_config() -> dict[str, Any]:
    db_host = os.getenv("DB_HOST")
    db_database = os.getenv("DB_DATABASE")

    if db_host and db_database:
        return {
            "host": db_host,
            "port": int(os.getenv("DB_PORT") or "3306"),
            "user": os.getenv("DB_USERNAME") or "root",
            "password": os.getenv("DB_PASSWORD") or "",
            "database": db_database,
            "charset": "utf8mb4",
            "cursorclass": pymysql.cursors.DictCursor,
            "autocommit": True,
            "connect_timeout": 15,
        }

    database_url = os.getenv("DATABASE_URL") or os.getenv("MYSQL_URL")

    if database_url:
        parsed = urlparse(database_url)

        return {
            "host": parsed.hostname or "127.0.0.1",
            "port": parsed.port or 3306,
            "user": parsed.username or "root",
            "password": parsed.password or "",
            "database": (parsed.path or "/").lstrip("/") or "telegram_autoreply_engine",
            "charset": "utf8mb4",
            "cursorclass": pymysql.cursors.DictCursor,
            "autocommit": True,
            "connect_timeout": 15,
        }

    return {
        "host": os.getenv("DB_HOST") or os.getenv("MYSQLHOST") or "127.0.0.1",
        "port": int(os.getenv("DB_PORT") or os.getenv("MYSQLPORT") or "3306"),
        "user": os.getenv("DB_USERNAME") or os.getenv("MYSQLUSER") or "root",
        "password": os.getenv("DB_PASSWORD") or os.getenv("MYSQLPASSWORD") or "",
        "database": os.getenv("DB_DATABASE") or os.getenv("MYSQLDATABASE") or "telegram_autoreply_engine",
        "charset": "utf8mb4",
        "cursorclass": pymysql.cursors.DictCursor,
        "autocommit": True,
        "connect_timeout": 15,
    }


def db_connect(config: dict[str, Any]):
    try:
        return pymysql.connect(**config["db"])
    except Exception as exc:
        db = config["db"]
        safe_target = f"{db.get('user')}@{db.get('host')}:{db.get('port')}/{db.get('database')}"
        raise RuntimeError(f"Database connection failed for {safe_target}: {exc}") from exc


def fetch_one(conn, query: str, params: tuple[Any, ...]) -> dict[str, Any] | None:
    with conn.cursor() as cursor:
        cursor.execute(query, params)
        return cursor.fetchone()


def execute(conn, query: str, params: tuple[Any, ...]) -> None:
    with conn.cursor() as cursor:
        cursor.execute(query, params)


def send_bot_message(chat_id: str, text: str) -> None:
    token = os.getenv("TELEGRAM_BOT_TOKEN")

    if not token:
        return

    payload = urllib.parse.urlencode({
        "chat_id": chat_id,
        "text": text,
        "parse_mode": "HTML",
    }).encode()

    request = urllib.request.Request(
        f"https://api.telegram.org/bot{token}/sendMessage",
        data=payload,
        method="POST",
    )

    with urllib.request.urlopen(request, timeout=20):
        pass


def client_for(account: dict[str, Any], config: dict[str, Any]) -> Client:
    SESSION_DIR.mkdir(parents=True, exist_ok=True)

    session_string = account.get("session_string")

    return Client(
        name=account["session_name"],
        api_id=config["api_id"],
        api_hash=config["api_hash"],
        workdir=str(SESSION_DIR),
        session_string=session_string,
    )


def send_code(account_id: int) -> None:
    config = load_config()

    with db_connect(config) as conn:
        account = fetch_one(
            conn,
            "select * from telegram_client_accounts where id = %s limit 1",
            (account_id,),
        )
        if not account or not account.get("phone_number"):
            raise RuntimeError("Account not found or phone_number is empty")

        app = client_for(account, config)
        app.connect()
        try:
            sent_code = app.send_code(account["phone_number"])
        finally:
            app.disconnect()

        execute(
            conn,
            """
            update telegram_client_accounts
            set auth_status = 'awaiting_code',
                phone_code_hash = %s,
                session_file = %s,
                last_error = null,
                updated_at = now()
            where id = %s
            """,
            (
                sent_code.phone_code_hash,
                str(SESSION_DIR / f"{account['session_name']}.session"),
                account_id,
            ),
        )

    print("code_sent")


def send_code_direct(phone_number: str, session_name: str) -> None:
    config = load_config()
    account = {
        "session_name": session_name,
    }

    clear_session_files(session_name)

    app = client_for(account, config)
    app.connect()
    try:
        sent_code = app.send_code(phone_number)
    finally:
        app.disconnect()

    print(json.dumps({
        "status": "code_sent",
        "phone_code_hash": sent_code.phone_code_hash,
        "phone_code_hash_length": len(sent_code.phone_code_hash),
        "session_file": str(SESSION_DIR / f"{session_name}.session"),
    }))


def clear_session_files(session_name: str) -> None:
    SESSION_DIR.mkdir(parents=True, exist_ok=True)

    for suffix in (".session", ".session-journal"):
        path = SESSION_DIR / f"{session_name}{suffix}"

        if path.exists():
            path.unlink()


def sign_in(account_id: int, code: str, password: str | None = None) -> None:
    config = load_config()

    with db_connect(config) as conn:
        account = fetch_one(
            conn,
            "select * from telegram_client_accounts where id = %s limit 1",
            (account_id,),
        )
        if not account or not account.get("phone_number"):
            raise RuntimeError("Account not found or phone_number is empty")
        if not account.get("phone_code_hash"):
            raise RuntimeError("phone_code_hash is empty. Run send-code first.")

        try:
            app = client_for(account, config)
            app.connect()
            try:
                if password and not code:
                    app.check_password(password)
                else:
                    try:
                        app.sign_in(
                            phone_number=account["phone_number"],
                            phone_code_hash=account["phone_code_hash"],
                            phone_code=code,
                        )
                    except SessionPasswordNeeded:
                        if not password:
                            execute(
                                conn,
                                """
                                update telegram_client_accounts
                                set auth_status = 'awaiting_password',
                                    updated_at = now()
                                where id = %s
                                """,
                                (account_id,),
                            )
                            print("password_required")
                            return

                        app.check_password(password)

                me = app.get_me()
            finally:
                app.disconnect()

            execute(
                conn,
                """
                update telegram_client_accounts
                set auth_status = 'authorized',
                    bot_username = coalesce(bot_username, %s),
                    last_login_at = now(),
                    last_seen_at = now(),
                    phone_code_hash = null,
                    last_error = null,
                    updated_at = now()
                where id = %s
                """,
                (getattr(me, "username", None), account_id),
            )
            print("authorized")
        except Exception as exc:
            execute(
                conn,
                """
                update telegram_client_accounts
                set auth_status = 'error',
                    last_error = %s,
                    updated_at = now()
                where id = %s
                """,
                (str(exc), account_id),
            )
            raise


def sign_in_direct(
    phone_number: str,
    session_name: str,
    phone_code_hash: str,
    code: str,
    password: str | None = None,
) -> None:
    config = load_config()
    account = {
        "session_name": session_name,
    }

    try:
        app = client_for(account, config)
        app.connect()
        try:
            if password and not code:
                app.check_password(password)
            else:
                try:
                    app.sign_in(
                        phone_number=phone_number,
                        phone_code_hash=phone_code_hash,
                        phone_code=code,
                    )
                except SessionPasswordNeeded:
                    if not password:
                        print(json.dumps({"status": "password_required"}))
                        return

                    app.check_password(password)

            me = app.get_me()
        finally:
            app.disconnect()

        print(json.dumps({
            "status": "authorized",
            "telegram_user_id": getattr(me, "id", None),
            "telegram_username": getattr(me, "username", None),
            "telegram_first_name": getattr(me, "first_name", None),
        }))
    except Exception as exc:
        print(json.dumps({
            "status": "error",
            "error": str(exc),
        }))
        raise


def login_flow(account_id: int, timeout_seconds: int = 300) -> None:
    print(f"login_flow started account_id={account_id}", flush=True)
    config = load_config()
    db = config["db"]
    print(
        f"database target={db.get('user')}@{db.get('host')}:{db.get('port')}/{db.get('database')}",
        flush=True,
    )

    with db_connect(config) as conn:
        account = fetch_one(
            conn,
            "select * from telegram_client_accounts where id = %s limit 1",
            (account_id,),
        )

        if not account:
            raise RuntimeError("Account not found")

        if not account.get("phone_number"):
            raise RuntimeError("phone_number is empty")

        print(f"sending code phone={account['phone_number']} session={account['session_name']}", flush=True)
        clear_session_files(account["session_name"])

        app = client_for(account, config)
        app.connect()

        try:
            sent_code = app.send_code(account["phone_number"])
            print("code sent by telegram", flush=True)

            execute(
                conn,
                """
                update telegram_client_accounts
                set auth_status = 'awaiting_code',
                    phone_code_hash = %s,
                    pending_otp_code = null,
                    pending_otp_requested_at = now(),
                    last_error = null,
                    updated_at = now()
                where id = %s
                """,
                (sent_code.phone_code_hash, account_id),
            )

            send_bot_message(account["bot_chat_id"], "\n".join([
                "<b>Kode OTP sudah dikirim oleh Telegram.</b>",
                "",
                "Silakan kirim kode OTP terbaru ke chat ini.",
                "Contoh: <code>12345</code>",
            ]))

            deadline = time.time() + timeout_seconds
            otp_code = None

            print("waiting for otp code", flush=True)
            while time.time() < deadline:
                latest = fetch_one(
                    conn,
                    "select pending_otp_code from telegram_client_accounts where id = %s limit 1",
                    (account_id,),
                )

                if latest and latest.get("pending_otp_code"):
                    otp_code = str(latest["pending_otp_code"])
                    break

                time.sleep(2)

            if not otp_code:
                print("otp wait timeout", flush=True)
                execute(
                    conn,
                    """
                    update telegram_client_accounts
                    set auth_status = 'awaiting_phone',
                        pending_otp_code = null,
                        last_error = 'OTP_TIMEOUT',
                        updated_at = now()
                    where id = %s
                    """,
                    (account_id,),
                )
                send_bot_message(account["bot_chat_id"], "Kode OTP kedaluwarsa. Klik <b>Buat Userbot</b> untuk mencoba lagi.")
                return

            print("otp code received, signing in", flush=True)
            execute(
                conn,
                """
                update telegram_client_accounts
                set pending_otp_code = null,
                    updated_at = now()
                where id = %s
                """,
                (account_id,),
            )

            try:
                app.sign_in(
                    phone_number=account["phone_number"],
                    phone_code_hash=sent_code.phone_code_hash,
                    phone_code=otp_code,
                )
            except SessionPasswordNeeded:
                execute(
                    conn,
                    """
                    update telegram_client_accounts
                    set auth_status = 'awaiting_password',
                        last_error = null,
                        updated_at = now()
                    where id = %s
                    """,
                    (account_id,),
                )
                send_bot_message(account["bot_chat_id"], "Akun ini memakai password 2FA. Fitur input password akan disambungkan berikutnya.")
                return

            me = app.get_me()
            print("authorized", flush=True)

            execute(
                conn,
                """
                update telegram_client_accounts
                set auth_status = 'authorized',
                    phone_code_hash = null,
                    pending_otp_code = null,
                    pending_session_string = null,
                    last_login_at = now(),
                    last_seen_at = now(),
                    last_error = null,
                    updated_at = now()
                where id = %s
                """,
                (account_id,),
            )

            send_bot_message(account["bot_chat_id"], "\n".join([
                "<b>Userbot berhasil dibuat.</b>",
                "",
                f"Akun Telegram <code>{getattr(me, 'username', None) or account['phone_number']}</code> sudah terhubung.",
                "Langkah berikutnya: kita setting daftar grup.",
            ]))
        except Exception as exc:
            print(f"login_flow failed: {exc}", flush=True)
            execute(
                conn,
                """
                update telegram_client_accounts
                set auth_status = 'awaiting_phone',
                    pending_otp_code = null,
                    last_error = %s,
                    updated_at = now()
                where id = %s
                """,
                (str(exc), account_id),
            )
            send_bot_message(account["bot_chat_id"], "\n".join([
                "<b>Login belum berhasil.</b>",
                "",
                f"Alasan: <code>{str(exc)[:350]}</code>",
                "Klik <b>Buat Userbot</b> untuk mencoba ulang.",
            ]))
            raise
        finally:
            app.disconnect()


def target_for_group(group: dict[str, Any]) -> str:
    if group.get("chat_id"):
        return group["chat_id"]
    if group.get("username"):
        return group["username"]
    if group.get("invite_link"):
        return group["invite_link"]
    raise RuntimeError(f"Group {group['id']} has no target")


def process_share(share_id: int, delay_seconds: float) -> None:
    config = load_config()

    with db_connect(config) as conn:
        share = fetch_one(conn, "select * from share_messages where id = %s limit 1", (share_id,))
        if not share:
            raise RuntimeError("Share message not found")

        account = fetch_one(
            conn,
            "select * from telegram_client_accounts where id = %s limit 1",
            (share["telegram_client_account_id"],),
        )
        if not account or account["auth_status"] != "authorized":
            raise RuntimeError("Account not found or not authorized")

        with conn.cursor() as cursor:
            cursor.execute(
                """
                select * from telegram_client_groups
                where telegram_client_account_id = %s and status = 'active'
                order by id asc
                """,
                (account["id"],),
            )
            groups = cursor.fetchall()

        execute(
            conn,
            """
            update share_messages
            set status = 'running',
                total_groups = %s,
                started_at = coalesce(started_at, now()),
                updated_at = now()
            where id = %s
            """,
            (len(groups), share_id),
        )

        sent_count = 0
        failed_count = 0

        with client_for(account, config) as app:
            for group in groups:
                delivery_id = create_delivery(conn, share_id, group)

                try:
                    message = app.send_message(target_for_group(group), share["message_text"])
                    sent_count += 1
                    execute(
                        conn,
                        """
                        update share_message_deliveries
                        set status = 'sent',
                            telegram_message_id = %s,
                            sent_at = now(),
                            updated_at = now()
                        where id = %s
                        """,
                        (str(message.id), delivery_id),
                    )
                except Exception as exc:
                    failed_count += 1
                    execute(
                        conn,
                        """
                        update share_message_deliveries
                        set status = 'failed',
                            error_message = %s,
                            updated_at = now()
                        where id = %s
                        """,
                        (str(exc), delivery_id),
                    )

                execute(
                    conn,
                    """
                    update share_messages
                    set sent_count = %s,
                        failed_count = %s,
                        updated_at = now()
                    where id = %s
                    """,
                    (sent_count, failed_count, share_id),
                )

                if delay_seconds > 0:
                    time.sleep(delay_seconds)

        status = "sent" if failed_count == 0 else "partial"
        if sent_count == 0 and failed_count > 0:
            status = "failed"

        execute(
            conn,
            """
            update share_messages
            set status = %s,
                completed_at = now(),
                updated_at = now()
            where id = %s
            """,
            (status, share_id),
        )

    print(status)


def create_delivery(conn, share_id: int, group: dict[str, Any]) -> int:
    execute(
        conn,
        """
        insert into share_message_deliveries
            (share_message_id, telegram_client_group_id, chat_id, status, created_at, updated_at)
        values (%s, %s, %s, 'queued', now(), now())
        """,
        (share_id, group["id"], group.get("chat_id")),
    )

    with conn.cursor() as cursor:
        cursor.execute("select last_insert_id() as id")
        return int(cursor.fetchone()["id"])


def process_pending(limit: int, delay_seconds: float) -> None:
    config = load_config()

    with db_connect(config) as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                select id from share_messages
                where status = 'queued'
                order by id asc
                limit %s
                """,
                (limit,),
            )
            shares = cursor.fetchall()

    for share in shares:
        process_share(int(share["id"]), delay_seconds)


def main() -> None:
    parser = argparse.ArgumentParser(description="Pyrogram worker for Telegram client accounts")
    subparsers = parser.add_subparsers(dest="command", required=True)

    send_code_parser = subparsers.add_parser("send-code")
    send_code_parser.add_argument("account_id", type=int)

    send_code_direct_parser = subparsers.add_parser("send-code-direct")
    send_code_direct_parser.add_argument("phone_number")
    send_code_direct_parser.add_argument("session_name")

    sign_in_parser = subparsers.add_parser("sign-in")
    sign_in_parser.add_argument("account_id", type=int)
    sign_in_parser.add_argument("code")
    sign_in_parser.add_argument("--password")

    sign_in_direct_parser = subparsers.add_parser("sign-in-direct")
    sign_in_direct_parser.add_argument("phone_number")
    sign_in_direct_parser.add_argument("session_name")
    sign_in_direct_parser.add_argument("phone_code_hash")
    sign_in_direct_parser.add_argument("code")
    sign_in_direct_parser.add_argument("--password")

    login_flow_parser = subparsers.add_parser("login-flow")
    login_flow_parser.add_argument("account_id", type=int)
    login_flow_parser.add_argument("--timeout", type=int, default=300)

    share_parser = subparsers.add_parser("share")
    share_parser.add_argument("share_id", type=int)
    share_parser.add_argument("--delay", type=float, default=5.0)

    pending_parser = subparsers.add_parser("share-pending")
    pending_parser.add_argument("--limit", type=int, default=5)
    pending_parser.add_argument("--delay", type=float, default=5.0)

    args = parser.parse_args()

    if args.command == "send-code":
        send_code(args.account_id)
    elif args.command == "send-code-direct":
        send_code_direct(args.phone_number, args.session_name)
    elif args.command == "sign-in":
        sign_in(args.account_id, args.code, args.password)
    elif args.command == "sign-in-direct":
        sign_in_direct(
            args.phone_number,
            args.session_name,
            args.phone_code_hash,
            args.code,
            args.password,
        )
    elif args.command == "login-flow":
        login_flow(args.account_id, args.timeout)
    elif args.command == "share":
        process_share(args.share_id, args.delay)
    elif args.command == "share-pending":
        process_pending(args.limit, args.delay)


if __name__ == "__main__":
    main()
