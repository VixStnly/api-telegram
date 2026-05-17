from __future__ import annotations

import argparse
import json
import os
import time
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Any, IO
from urllib.parse import urlparse

import pymysql
from dotenv import load_dotenv
from pyrogram import Client, filters
from pyrogram.errors import SessionPasswordNeeded
from pyrogram.handlers import MessageHandler


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


def execute_affected(conn, query: str, params: tuple[Any, ...]) -> int:
    with conn.cursor() as cursor:
        cursor.execute(query, params)
        return int(cursor.rowcount or 0)


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


def try_send_bot_message(chat_id: str | None, text: str) -> bool:
    if not chat_id:
        return False

    try:
        send_bot_message(chat_id, text)
        return True
    except Exception as exc:
        print(f"bot status message failed chat_id={chat_id}: {exc}", flush=True)
        return False


def installed_message() -> str:
    return "\n".join([
        "✅ Userbot berhasil dipasang.",
        "",
        "🚀 Untuk mulai share:",
        "1. Buka bot utama dari akun yang membuat userbot ini.",
        "2. Pilih 📌 Add Grup, lalu ceklis grup target.",
        "3. Di akun userbot ini, reply pesan yang mau dibagikan.",
        "4. Ketik !share pada reply tersebut.",
        "",
        "⚡ Pesan akan diforward ke grup yang sudah diceklis.",
    ])


def send_install_notice_to_saved_messages(app: Client) -> None:
    try:
        app.send_message("me", installed_message())
    except Exception as exc:
        print(f"saved messages notice failed: {exc}", flush=True)


def account_meta(account: dict[str, Any]) -> dict[str, Any]:
    meta = account.get("meta")

    if isinstance(meta, dict):
        return meta

    if isinstance(meta, str) and meta.strip():
        try:
            parsed = json.loads(meta)
            return parsed if isinstance(parsed, dict) else {}
        except json.JSONDecodeError:
            return {}

    return {}


def consume_access_code_quota(conn, account: dict[str, Any]) -> None:
    meta = account_meta(account)
    access_code = meta.get("access_code") if isinstance(meta.get("access_code"), dict) else {}
    access_code_id = access_code.get("id")

    if not access_code_id or access_code.get("consumed_at"):
        return

    affected = execute_affected(
        conn,
        """
        update telegram_access_codes
        set used_count = used_count + 1,
            last_used_at = now(),
            updated_at = now()
        where id = %s
          and is_active = 1
          and (expires_at is null or expires_at > now())
          and (max_uses is null or used_count < max_uses)
        """,
        (access_code_id,),
    )

    if affected < 1:
        print(f"access code quota was not consumed id={access_code_id}", flush=True)
        return

    access_code["consumed_at"] = time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())
    meta["access_code"] = access_code

    execute(
        conn,
        """
        update telegram_client_accounts
        set meta = %s,
            updated_at = now()
        where id = %s
        """,
        (json.dumps(meta), account["id"]),
    )


def is_auth_key_error(exc: Exception) -> bool:
    text = str(exc).upper()

    return (
        "AUTH_KEY_UNREGISTERED" in text
        or "AUTHKEYUNREGISTERED" in text
        or "AUTH_KEY_DUPLICATED" in text
        or "AUTHKEYDUPLICATED" in text
    )


def mark_account_session_error(conn, account: dict[str, Any], exc: Exception) -> None:
    error = str(exc)

    execute(
        conn,
        """
        update telegram_client_accounts
        set last_error = %s,
            updated_at = now()
        where id = %s
        """,
        (error, account["id"]),
    )

    if account.get("bot_chat_id"):
        send_bot_message(account["bot_chat_id"], "\n".join([
            "<b>⚠️ Userbot tidak bisa dipakai.</b>",
            "",
            "<blockquote>Session Telegram akun ini sudah tidak valid, jadi <code>!share</code> tidak bisa diproses dari akun userbot.</blockquote>",
            "",
            "Silakan klik <b>🚀 Buat Userbot Baru</b> lalu login ulang.",
            "",
            f"Detail: <code>{error[:500]}</code>",
        ]))

    execute(conn, "delete from telegram_client_accounts where id = %s", (account["id"],))


def wait_for_2fa_password(conn, account_id: int, login_token: str, deadline: float) -> str | None:
    print("waiting for 2fa password", flush=True)

    while time.time() < deadline:
        latest = fetch_one(
            conn,
            """
            select pending_2fa_password, pending_login_token
            from telegram_client_accounts
            where id = %s
            limit 1
            """,
            (account_id,),
        )

        if latest and latest.get("pending_login_token") != login_token:
            print("login token changed while waiting for 2fa; exiting old worker", flush=True)
            return None

        if latest and latest.get("pending_2fa_password"):
            password = str(latest["pending_2fa_password"])
            execute(
                conn,
                """
                update telegram_client_accounts
                set pending_2fa_password = null,
                    updated_at = now()
                where id = %s
                  and pending_login_token = %s
                """,
                (account_id, login_token),
            )

            return password

        time.sleep(2)

    return None


def client_for(account: dict[str, Any], config: dict[str, Any], use_session_string: bool = True) -> Client:
    SESSION_DIR.mkdir(parents=True, exist_ok=True)

    session_string = None

    if use_session_string:
        session_string = account.get("session_string") or account.get("pending_session_string")

    return Client(
        name=account["session_name"],
        api_id=config["api_id"],
        api_hash=config["api_hash"],
        workdir=str(SESSION_DIR),
        session_string=session_string,
        sleep_threshold=0,
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

        account["session_string"] = None
        account["pending_session_string"] = None
        app = client_for(account, config, use_session_string=False)
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
                send_install_notice_to_saved_messages(app)
                session_string = None
                try:
                    session_string = app.export_session_string()
                except Exception as exc:
                    print(f"session string export failed: {exc}", flush=True)
            finally:
                app.disconnect()

            execute(
                conn,
                """
                update telegram_client_accounts
                set auth_status = 'authorized',
                    bot_username = coalesce(bot_username, %s),
                    session_string = %s,
                    pending_session_string = %s,
                    last_login_at = now(),
                    last_seen_at = now(),
                    phone_code_hash = null,
                    last_error = null,
                    updated_at = now()
                where id = %s
                """,
                (getattr(me, "username", None), session_string, session_string, account_id),
            )
            consume_access_code_quota(conn, account)
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
            send_install_notice_to_saved_messages(app)
            session_string = None
            try:
                session_string = app.export_session_string()
            except Exception as exc:
                print(f"session string export failed: {exc}", flush=True)
        finally:
            app.disconnect()

        print(json.dumps({
            "status": "authorized",
            "telegram_user_id": getattr(me, "id", None),
            "telegram_username": getattr(me, "username", None),
            "telegram_first_name": getattr(me, "first_name", None),
            "session_string": session_string,
        }))
    except Exception as exc:
        print(json.dumps({
            "status": "error",
            "error": str(exc),
        }))
        raise


def login_flow(account_id: int, login_token: str, timeout_seconds: int = 300) -> None:
    print(f"login_flow started account_id={account_id} token={login_token[:8]}", flush=True)
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

        if account.get("pending_login_token") != login_token:
            print("login token is no longer current", flush=True)
            return

        if not account.get("phone_number"):
            raise RuntimeError("phone_number is empty")

        print(f"sending code phone={account['phone_number']} session={account['session_name']}", flush=True)
        clear_session_files(account["session_name"])

        account["session_string"] = None
        account["pending_session_string"] = None
        app = client_for(account, config, use_session_string=False)

        try:
            app.connect()
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
                  and pending_login_token = %s
                """,
                (sent_code.phone_code_hash, account_id, login_token),
            )

            send_bot_message(account["bot_chat_id"], "\n".join([
                "<b>📩 Kode OTP sudah dikirim oleh Telegram.</b>",
                "",
                "<blockquote>Buka tombol <b>🔐 Input OTP</b> di pesan sebelumnya, lalu masukkan kode terbaru di halaman web.</blockquote>",
                "",
                "🚫 Jangan kirim kode OTP langsung di chat bot agar tidak diblokir sistem keamanan Telegram.",
            ]))

            deadline = time.time() + timeout_seconds
            otp_code = None

            print("waiting for otp code", flush=True)
            while time.time() < deadline:
                latest = fetch_one(
                    conn,
                    "select pending_otp_code, pending_login_token from telegram_client_accounts where id = %s limit 1",
                    (account_id,),
                )

                if latest and latest.get("pending_login_token") != login_token:
                    print("login token changed while waiting; exiting old worker", flush=True)
                    return

                if latest and latest.get("pending_otp_code"):
                    otp_code = str(latest["pending_otp_code"])
                    break

                time.sleep(2)

            if not otp_code:
                print("otp wait timeout", flush=True)
                execute(
                    conn,
                    """
                    delete from telegram_client_accounts
                    where id = %s
                      and pending_login_token = %s
                    """,
                    (account_id, login_token),
                )
                send_bot_message(account["bot_chat_id"], "⚠️ Kode OTP kedaluwarsa. Klik <b>🚀 Buat Userbot</b> untuk mencoba lagi.")
                return

            print("otp code received, signing in", flush=True)
            execute(
                conn,
                """
                update telegram_client_accounts
                set pending_otp_code = null,
                    pending_2fa_password = null,
                    updated_at = now()
                where id = %s
                  and pending_login_token = %s
                """,
                (account_id, login_token),
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
                        pending_2fa_password = null,
                        last_error = null,
                        updated_at = now()
                    where id = %s
                      and pending_login_token = %s
                    """,
                    (account_id, login_token),
                )
                send_bot_message(account["bot_chat_id"], "\n".join([
                    "<b>🔐 Akun memakai password 2FA.</b>",
                    "",
                    "<blockquote>Kirim password 2FA akun Telegram kamu di chat ini untuk menyelesaikan login.</blockquote>",
                ]))

                password_ok = False

                while time.time() < deadline:
                    password = wait_for_2fa_password(conn, account_id, login_token, deadline)

                    if not password:
                        break

                    print("2fa password received, checking password", flush=True)

                    try:
                        app.check_password(password)
                        password_ok = True
                        break
                    except Exception as exc:
                        print(f"2fa password failed: {exc}", flush=True)
                        execute(
                            conn,
                            """
                            update telegram_client_accounts
                            set auth_status = 'awaiting_password',
                                last_error = %s,
                                updated_at = now()
                            where id = %s
                              and pending_login_token = %s
                            """,
                            (str(exc), account_id, login_token),
                        )
                        send_bot_message(account["bot_chat_id"], "⚠️ Password 2FA belum cocok. Kirim ulang password 2FA yang benar di chat ini.")

                if not password_ok:
                    execute(
                        conn,
                        """
                        delete from telegram_client_accounts
                        where id = %s
                          and pending_login_token = %s
                        """,
                        (account_id, login_token),
                    )
                    send_bot_message(account["bot_chat_id"], "⚠️ Password 2FA kedaluwarsa. Klik <b>🚀 Buat Userbot</b> untuk mencoba lagi.")
                    return

            me = app.get_me()
            session_string = None
            try:
                session_string = app.export_session_string()
            except Exception as exc:
                print(f"session string export failed: {exc}", flush=True)

            if not session_string:
                raise RuntimeError("SESSION_STRING_EXPORT_FAILED")

            print("authorized", flush=True)

            execute(
                conn,
                """
                update telegram_client_accounts
                set auth_status = 'authorized',
                    phone_code_hash = null,
                    pending_otp_code = null,
                    pending_2fa_password = null,
                    session_file = %s,
                    session_string = %s,
                    pending_session_string = %s,
                    pending_login_token = null,
                    last_login_at = now(),
                    last_seen_at = now(),
                    last_error = null,
                    updated_at = now()
                where id = %s
                  and pending_login_token = %s
                """,
                (
                    str(SESSION_DIR / f"{account['session_name']}.session"),
                    session_string,
                    session_string,
                    account_id,
                    login_token,
                ),
            )
            consume_access_code_quota(conn, account)
            send_install_notice_to_saved_messages(app)

            send_bot_message(account["bot_chat_id"], "\n".join([
                "<b>✅ Userbot berhasil dibuat.</b>",
                "",
                f"Akun Telegram <code>{getattr(me, 'username', None) or account['phone_number']}</code> sudah terhubung.",
                "<blockquote>Langkah berikutnya: buka 📌 Add Grup untuk memilih target share.</blockquote>",
            ]))
        except Exception as exc:
            print(f"login_flow failed: {exc}", flush=True)
            execute(
                conn,
                """
                delete from telegram_client_accounts
                where id = %s
                """,
                (account_id,),
            )
            send_bot_message(account["bot_chat_id"], "\n".join([
                "<b>⚠️ Login belum berhasil.</b>",
                "",
                f"Alasan: <code>{str(exc)[:350]}</code>",
                "Klik <b>🚀 Buat Userbot</b> untuk mencoba ulang.",
            ]))
            raise
        finally:
            try:
                app.disconnect()
            except Exception:
                pass


def target_for_group(group: dict[str, Any]) -> int | str:
    if group.get("chat_id"):
        chat_id = str(group["chat_id"]).strip()

        if chat_id.lstrip("-").isdigit():
            return int(chat_id)

        return chat_id
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
        meta = share_meta(share)

        with client_for(account, config) as app:
            for group in groups:
                delivery_id = create_delivery(conn, share_id, group)

                try:
                    if meta.get("source_chat_id") and meta.get("reply_message_id"):
                        message = forward_source_message_to_group(
                            app,
                            group,
                            meta["source_chat_id"],
                            int(meta["reply_message_id"]),
                        )
                    else:
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


def list_groups(account_id: int) -> None:
    config = load_config()

    with db_connect(config) as conn:
        account = fetch_one(
            conn,
            "select * from telegram_client_accounts where id = %s limit 1",
            (account_id,),
        )

        if not account or account["auth_status"] != "authorized":
            raise RuntimeError("Account not found or not authorized")

    groups = []

    try:
        with client_for(account, config) as app:
            for dialog in app.get_dialogs():
                chat = dialog.chat
                chat_type = str(getattr(chat, "type", "")).lower()

                if "group" not in chat_type:
                    continue

                groups.append({
                    "chat_id": str(chat.id),
                    "title": getattr(chat, "title", None) or getattr(chat, "first_name", None) or str(chat.id),
                    "username": getattr(chat, "username", None),
                    "type": chat_type,
                })

                if len(groups) >= 80:
                    break
    except Exception as exc:
        if is_auth_key_error(exc):
            with db_connect(config) as conn:
                mark_account_session_error(conn, account, exc)

        raise

    print(json.dumps({
        "status": "ok",
        "groups": groups,
    }, ensure_ascii=False))


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


def create_share_record_from_reply(conn, account_id: int, message, reply, client: Client | None = None) -> int:
    message_text = reply_text_for_share(reply)
    source_chat_id = source_chat_for_copy(client, message)

    execute(
        conn,
        """
        insert into share_messages
            (telegram_client_account_id, requested_by_chat_id, message_text, status, started_at, created_at, updated_at, meta)
        values (%s, %s, %s, 'running', now(), now(), now(), %s)
        """,
        (
            account_id,
            str(message.chat.id),
            message_text,
            json.dumps({
                "source": "userbot_reply_command",
                "command_chat_id": str(message.chat.id),
                "command_message_id": getattr(message, "id", None),
                "source_chat_id": source_chat_id,
                "reply_message_id": getattr(reply, "id", None),
                "delivery_method": "forward",
            }),
        ),
    )

    with conn.cursor() as cursor:
        cursor.execute("select last_insert_id() as id")
        return int(cursor.fetchone()["id"])


def update_share_totals(conn, share_id: int, total: int, sent: int, failed: int, status: str) -> None:
    execute(
        conn,
        """
        update share_messages
        set total_groups = %s,
            sent_count = %s,
            failed_count = %s,
            status = %s,
            completed_at = now(),
            updated_at = now()
        where id = %s
        """,
        (total, sent, failed, status, share_id),
    )


def update_share_progress(conn, share_id: int, total: int, sent: int, failed: int) -> None:
    execute(
        conn,
        """
        update share_messages
        set total_groups = %s,
            sent_count = %s,
            failed_count = %s,
            status = 'running',
            updated_at = now()
        where id = %s
        """,
        (total, sent, failed, share_id),
    )


def mark_share_interrupted(conn, share_id: int, total: int, sent: int, failed: int, error: Exception) -> None:
    status = "partial" if sent > 0 else "failed"

    execute(
        conn,
        """
        update share_messages
        set total_groups = %s,
            sent_count = %s,
            failed_count = %s,
            status = %s,
            completed_at = now(),
            last_error = %s,
            updated_at = now()
        where id = %s
          and status = 'running'
        """,
        (total, sent, failed, status, short_error(str(error), 1000), share_id),
    )


def command_text(message) -> str:
    return (getattr(message, "text", None) or getattr(message, "caption", None) or "").strip()


def is_share_command(message) -> bool:
    text = command_text(message).lower()

    return text == "!share" or text.startswith("!share ")


def is_ping_command(message) -> bool:
    text = command_text(message).lower()

    return text == "!ping" or text.startswith("!ping ")


def is_userbot_command(message) -> bool:
    return is_share_command(message) or is_ping_command(message)


def reply_text_for_share(reply) -> str:
    return (
        getattr(reply, "text", None)
        or getattr(reply, "caption", None)
        or "[copied telegram message]"
    )


def is_self_command_message(message) -> bool:
    if not is_userbot_command(message):
        return False

    # Pyrogram does not consistently mark outgoing/self messages across all
    # chat surfaces, especially Saved Messages, comments, and channel-linked
    # discussions. The watcher is already logged in as the userbot account, so
    # keep the command permissive after matching the exact !share text.
    if getattr(message, "outgoing", False):
        return True

    from_user = getattr(message, "from_user", None)

    if getattr(from_user, "is_self", False):
        return True

    chat = getattr(message, "chat", None)
    chat_type = str(getattr(chat, "type", "")).lower()

    return chat_type in ("chattype.private", "private")


def self_share_command_filter(_, __, message) -> bool:
    return is_self_command_message(message)


def get_replied_message(client: Client, message):
    reply = getattr(message, "reply_to_message", None)

    if reply:
        return reply

    reply_id = getattr(message, "reply_to_message_id", None)

    if not reply_id:
        return None

    return client.get_messages(chat_id=message.chat.id, message_ids=reply_id)


def source_chat_for_copy(client: Client, message):
    chat = getattr(message, "chat", None)
    chat_id = getattr(chat, "id", None)

    if client is None:
        return "me" if is_saved_messages_chat(message) else chat_id

    try:
        me = client.get_me()

        if chat_id is not None and str(chat_id) == str(getattr(me, "id", "")):
            return "me"
    except Exception:
        pass

    return chat_id


def is_saved_messages_chat(message) -> bool:
    chat = getattr(message, "chat", None)
    chat_type = str(getattr(chat, "type", "")).lower()

    if chat_type not in ("chattype.private", "private"):
        return False

    from_user = getattr(message, "from_user", None)

    return bool(getattr(from_user, "is_self", False) or getattr(message, "outgoing", False))


def share_meta(share: dict[str, Any]) -> dict[str, Any]:
    meta = share.get("meta")

    if isinstance(meta, dict):
        return meta

    if not meta:
        return {}

    try:
        decoded = json.loads(meta)
    except (TypeError, ValueError):
        return {}

    return decoded if isinstance(decoded, dict) else {}


def short_error(text: str, limit: int = 450) -> str:
    text = " ".join(str(text).split())

    if len(text) <= limit:
        return text

    return text[: limit - 3] + "..."


def user_friendly_delivery_error(text: str) -> str:
    upper = str(text).upper()

    if "PEER_ID_INVALID" in upper or "PEER ID INVALID" in upper or "PEER_ID" in upper:
        return "Userbot belum bisa mengakses grup itu atau data grup sudah berubah."

    if "CHAT_WRITE_FORBIDDEN" in upper or "USER_BANNED_IN_CHANNEL" in upper:
        return "Userbot tidak punya izin kirim pesan di grup itu."

    if "SLOWMODE" in upper:
        return "Grup sedang memakai slow mode."

    if "FLOOD" in upper:
        return "Telegram membatasi pengiriman sementara."

    if "AUTH_KEY" in upper:
        return "Session userbot bermasalah, silakan login ulang."

    return "Grup itu gagal menerima pesan."


def is_peer_invalid_error(exc: Exception) -> bool:
    text = str(exc).upper()

    return "PEER_ID_INVALID" in text or "PEER ID INVALID" in text or "PEER_ID" in text


def warm_group_peer(client: Client, group: dict[str, Any]):
    target = target_for_group(group)

    if not isinstance(target, int):
        return target

    target_text = str(target)

    for dialog in client.get_dialogs(limit=200):
        chat = getattr(dialog, "chat", None)

        if chat is not None and str(getattr(chat, "id", "")) == target_text:
            return getattr(chat, "id")

    return target


def retry_resolved_group_target(client: Client, group: dict[str, Any]):
    target = target_for_group(group)

    if not isinstance(target, int):
        return target

    warmed_target = warm_group_peer(client, group)

    if str(warmed_target) == str(target):
        title = group.get("title") or group.get("chat_id") or target
        raise RuntimeError(f"PEER_ID_INVALID: userbot belum bisa resolve grup {title}. Buka Add Grup ulang lalu pilih grup ini lagi.")

    return warmed_target


def forward_source_message_to_group(client: Client, group: dict[str, Any], source_chat_id, message_id: int):
    target = target_for_group(group)

    try:
        forwarded = client.forward_messages(
            chat_id=target,
            from_chat_id=source_chat_id,
            message_ids=message_id,
        )

        if isinstance(forwarded, list):
            return forwarded[0]

        return forwarded
    except Exception as forward_exc:
        if is_peer_invalid_error(forward_exc):
            target = retry_resolved_group_target(client, group)

            try:
                forwarded = client.forward_messages(
                    chat_id=target,
                    from_chat_id=source_chat_id,
                    message_ids=message_id,
                )

                if isinstance(forwarded, list):
                    return forwarded[0]

                return forwarded
            except Exception as retry_forward_exc:
                forward_exc = retry_forward_exc

        raise forward_exc


def send_replied_message_to_group(client: Client, group: dict[str, Any], command_message, reply):
    target = target_for_group(group)
    source = source_chat_for_copy(client, command_message)
    reply_text = (getattr(reply, "text", None) or "").strip()

    try:
        return forward_source_message_to_group(client, group, source, reply.id)
    except Exception as forward_exc:
        print(f"forward failed, trying fallback: {forward_exc}", flush=True)

    if reply_text and not getattr(reply, "media", None):
        try:
            return client.send_message(chat_id=target, text=reply_text)
        except Exception as send_exc:
            if is_peer_invalid_error(send_exc):
                target = retry_resolved_group_target(client, group)

                try:
                    return client.send_message(chat_id=target, text=reply_text)
                except Exception as retry_send_exc:
                    send_exc = retry_send_exc

            raise send_exc

    try:
        return client.copy_message(
            chat_id=target,
            from_chat_id=source,
            message_id=reply.id,
        )
    except Exception as copy_exc:
        if is_peer_invalid_error(copy_exc):
            target = retry_resolved_group_target(client, group)

            try:
                return client.copy_message(
                    chat_id=target,
                    from_chat_id=source,
                    message_id=reply.id,
                )
            except Exception as retry_exc:
                copy_exc = retry_exc

        fallback_text = (reply_text or getattr(reply, "caption", None) or "").strip()

        if fallback_text == "":
            raise copy_exc

        try:
            return client.send_message(chat_id=target, text=fallback_text)
        except Exception as send_exc:
            if is_peer_invalid_error(send_exc):
                target = retry_resolved_group_target(client, group)

                try:
                    return client.send_message(chat_id=target, text=fallback_text)
                except Exception as retry_send_exc:
                    send_exc = retry_send_exc

            raise RuntimeError(f"copy failed: {copy_exc}; text fallback failed: {send_exc}") from send_exc


def notify_share_status(client: Client, message, text: str, status_message=None):
    target_message = status_message or message

    try:
        edited = client.edit_message_text(
            chat_id=target_message.chat.id,
            message_id=target_message.id,
            text=text,
        )
        return edited or target_message
    except Exception:
        if status_message is not None:
            return status_message

    try:
        return client.send_message(
            chat_id=message.chat.id,
            text=text,
            reply_to_message_id=message.id,
        )
    except Exception:
        try:
            return client.send_message(message.chat.id, text)
        except Exception:
            return None


def build_share_result_text(sent_count: int, failed_count: int, failed_errors: list[str]) -> str:
    result_text = f"✅ Share selesai.\n\nBerhasil: {sent_count}\nGagal: {failed_count}"

    if sent_count:
        result_text += "\n\n⚡ Pesan berhasil dikirim ke grup target."

    if failed_errors:
        result_text += "\n\n⚠️ Gagal pertama:\n" + short_error(failed_errors[0])

    return result_text


def handle_ping_command(client: Client, message, account_id: int) -> None:
    if not is_self_command_message(message) or not is_ping_command(message):
        return

    started_at = time.monotonic()
    print(
        f"!ping command received account_id={account_id} chat_id={getattr(getattr(message, 'chat', None), 'id', None)} message_id={getattr(message, 'id', None)}",
        flush=True,
    )

    notify_share_status(
        client,
        message,
        "\n".join([
            "pong",
            f"Watcher: aktif",
            f"Account ID: {account_id}",
            f"Waktu: {time.strftime('%Y-%m-%d %H:%M:%S')} UTC",
        ]),
    )
    print(f"!ping responded account_id={account_id} seconds={time.monotonic() - started_at:.2f}", flush=True)


def handle_share_command(client: Client, message, account_id: int, delay_seconds: float) -> None:
    if not is_self_command_message(message):
        return

    print(
        f"!share command received account_id={account_id} chat_id={getattr(getattr(message, 'chat', None), 'id', None)} message_id={getattr(message, 'id', None)}",
        flush=True,
    )

    reply = get_replied_message(client, message)

    if not reply:
        notify_share_status(client, message, "⚠️ Gagal: reply pesan yang mau dishare, lalu ketik !share.")
        return

    config = load_config()

    with db_connect(config) as conn:
        account = fetch_one(
            conn,
            "select * from telegram_client_accounts where id = %s limit 1",
            (account_id,),
        )

        if not account or account["auth_status"] != "authorized":
            notify_share_status(client, message, "⚠️ Gagal: userbot belum authorized.")
            return

        with conn.cursor() as cursor:
            cursor.execute(
                """
                select * from telegram_client_groups
                where telegram_client_account_id = %s and status = 'active'
                order by id asc
                """,
                (account_id,),
            )
            groups = cursor.fetchall()

        if not groups:
            notify_share_status(client, message, "📭 Gagal: belum ada grup aktif. Pilih grup dulu dari menu 📌 Add Grup.")
            return

        share_id = create_share_record_from_reply(conn, account_id, message, reply, client)
        sent_count = 0
        failed_count = 0
        failed_errors = []

        try:
            try_send_bot_message(
                account.get("bot_chat_id"),
                f"⚡ Memproses share ke {len(groups)} grup...",
            )

            for index, group in enumerate(groups, start=1):
                group_name = group.get("title") or group.get("chat_id") or f"grup #{index}"
                delivery_id = create_delivery(conn, share_id, group)
                started_at = time.monotonic()

                try:
                    copied = send_replied_message_to_group(client, group, message, reply)
                    sent_count += 1
                    duration = time.monotonic() - started_at
                    print(f"share delivery sent account_id={account_id} group={group_name} seconds={duration:.2f}", flush=True)
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
                        (str(copied.id), delivery_id),
                    )
                    update_share_progress(conn, share_id, len(groups), sent_count, failed_count)
                except Exception as exc:
                    failed_count += 1
                    duration = time.monotonic() - started_at
                    error_text = str(exc)
                    print(f"share delivery failed account_id={account_id} group={group_name} seconds={duration:.2f} error={error_text}", flush=True)
                    failed_errors.append(f"{group.get('title') or group.get('chat_id')}: {user_friendly_delivery_error(error_text)}")
                    execute(
                        conn,
                        """
                        update share_message_deliveries
                        set status = 'failed',
                            error_message = %s,
                            updated_at = now()
                        where id = %s
                        """,
                        (error_text, delivery_id),
                    )
                    update_share_progress(conn, share_id, len(groups), sent_count, failed_count)
                    execute(
                        conn,
                        """
                        delete from telegram_client_groups
                        where id = %s
                        """,
                        (group["id"],),
                    )

                if delay_seconds > 0 and index < len(groups):
                    time.sleep(delay_seconds)

            status = "sent" if failed_count == 0 else "partial"
            if sent_count == 0:
                status = "failed"

            update_share_totals(conn, share_id, len(groups), sent_count, failed_count, status)
        except Exception as exc:
            print(f"share command interrupted account_id={account_id} share_id={share_id}: {exc}", flush=True)
            mark_share_interrupted(conn, share_id, len(groups), sent_count, failed_count, exc)
            raise
        result_text = build_share_result_text(sent_count, failed_count, failed_errors)
        bot_status_sent = try_send_bot_message(account.get("bot_chat_id"), result_text)

    if not bot_status_sent:
        notify_share_status(client, message, result_text)


def handle_userbot_command(client: Client, message, account_id: int, delay_seconds: float) -> None:
    if is_ping_command(message):
        handle_ping_command(client, message, account_id)
        return

    handle_share_command(client, message, account_id, delay_seconds)


def message_process_key(message) -> str | None:
    message_id = getattr(message, "id", None)
    chat = getattr(message, "chat", None)
    chat_id = getattr(chat, "id", None)

    if message_id is None or chat_id is None:
        return None

    return f"{chat_id}:{message_id}"


def handle_userbot_command_once(
    client: Client,
    message,
    account_id: int,
    delay_seconds: float,
    processed_message_keys: set[str],
) -> None:
    if not is_userbot_command(message):
        return

    message_key = message_process_key(message)

    if message_key is not None:
        if message_key in processed_message_keys:
            print(f"duplicate userbot command ignored account_id={account_id} key={message_key}", flush=True)
            return

        processed_message_keys.add(message_key)

    try:
        handle_userbot_command(client, message, account_id, delay_seconds)
    except Exception as exc:
        print(f"userbot command handling failed account_id={account_id} key={message_key}: {exc}", flush=True)
        notify_share_status(client, message, f"Gagal memproses command: {short_error(str(exc), 350)}")


def seed_saved_message_commands(client: Client, account_id: int, processed_message_keys: set[str]) -> None:
    try:
        messages = list(client.get_chat_history("me", limit=50))
    except Exception as exc:
        print(f"saved messages seed failed account_id={account_id}: {exc}", flush=True)
        return

    seeded = 0

    for message in messages:
        if not is_userbot_command(message):
            continue

        message_key = message_process_key(message)

        if message_key is None:
            continue

        processed_message_keys.add(message_key)
        seeded += 1

    print(f"seeded saved-message commands account_id={account_id} total={seeded}", flush=True)


def poll_saved_messages_for_commands(
    client: Client,
    account_id: int,
    delay_seconds: float,
    processed_message_keys: set[str],
) -> None:
    try:
        messages = list(client.get_chat_history("me", limit=10))
    except Exception as exc:
        print(f"saved messages poll failed account_id={account_id}: {exc}", flush=True)
        return

    for message in reversed(messages):
        message_key = message_process_key(message)

        if message_key is None or message_key in processed_message_keys:
            continue

        if not is_userbot_command(message):
            continue

        print(f"userbot command found by saved-messages poll account_id={account_id} key={message_key} text={command_text(message)}", flush=True)

        try:
            handle_userbot_command_once(client, message, account_id, delay_seconds, processed_message_keys)
        except Exception as exc:
            print(f"saved messages command handling failed account_id={account_id} key={message_key}: {exc}", flush=True)
            notify_share_status(client, message, f"Gagal memproses command: {short_error(str(exc), 350)}")


def mark_account_authorized_from_running_client(conn, account: dict[str, Any], client: Client) -> None:
    if account.get("auth_status") == "authorized":
        return

    me = client.get_me()
    session_string = account.get("session_string") or account.get("pending_session_string")

    try:
        session_string = client.export_session_string()
    except Exception as exc:
        print(f"watcher session string export failed account_id={account.get('id')}: {exc}", flush=True)

    execute(
        conn,
        """
        update telegram_client_accounts
        set auth_status = 'authorized',
            bot_username = coalesce(bot_username, %s),
            session_string = coalesce(%s, session_string),
            pending_session_string = coalesce(%s, pending_session_string),
            phone_code_hash = null,
            pending_otp_code = null,
            pending_2fa_password = null,
            pending_login_token = null,
            last_login_at = coalesce(last_login_at, now()),
            last_seen_at = now(),
            last_error = null,
            updated_at = now()
        where id = %s
        """,
        (
            getattr(me, "username", None),
            session_string,
            session_string,
            account["id"],
        ),
    )

    account["auth_status"] = "authorized"
    account["session_string"] = session_string
    account["pending_session_string"] = session_string
    print(f"recovered authorized userbot account_id={account.get('id')}", flush=True)


def acquire_watcher_lock() -> IO[str] | None:
    SESSION_DIR.mkdir(parents=True, exist_ok=True)
    lock_path = SESSION_DIR / "share-watcher.lock"
    lock_file = lock_path.open("w")

    try:
        import fcntl

        fcntl.flock(lock_file.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
    except ImportError:
        pass
    except BlockingIOError:
        print("share watcher already running; exiting duplicate", flush=True)
        lock_file.close()

        return None

    lock_file.write(str(os.getpid()))
    lock_file.flush()

    return lock_file


def has_session_material(account: dict[str, Any]) -> bool:
    if account.get("session_string") or account.get("pending_session_string"):
        return True

    session_name = account.get("session_name")

    if not session_name:
        return False

    return (SESSION_DIR / f"{session_name}.session").exists()


def mark_account_missing_session(conn, account: dict[str, Any]) -> None:
    execute(
        conn,
        """
        delete from telegram_client_accounts
        where id = %s
        """,
        (account["id"],),
    )
    print(f"deleted authorized account missing session account_id={account.get('id')}", flush=True)


def watch_shares(delay_seconds: float = 0.0, refresh_seconds: int = 1) -> None:
    watcher_lock = acquire_watcher_lock()

    if watcher_lock is None:
        return

    config = load_config()
    clients: dict[int, Client] = {}
    processed_message_keys_by_account: dict[int, set[str]] = {}
    last_heartbeat_at = 0.0

    print("share watcher started", flush=True)

    while True:
        try:
            now = time.time()

            if now - last_heartbeat_at >= 60:
                print(f"share watcher heartbeat active_clients={len(clients)}", flush=True)
                last_heartbeat_at = now

            with db_connect(config) as conn:
                with conn.cursor() as cursor:
                    cursor.execute(
                        """
                        select * from telegram_client_accounts
                        where is_active = 1
                          and auth_status = 'authorized'
                        order by id asc
                        """
                    )
                    accounts = cursor.fetchall()

            active_ids = {int(account["id"]) for account in accounts}

            for account_id, client in list(clients.items()):
                if account_id not in active_ids:
                    print(f"stopping inactive userbot watcher account_id={account_id}", flush=True)
                    try:
                        client.stop()
                    except Exception as exc:
                        print(f"watcher stop failed account_id={account_id}: {exc}", flush=True)
                    clients.pop(account_id, None)
                    processed_message_keys_by_account.pop(account_id, None)

            for account in accounts:
                account_id = int(account["id"])

                if account_id in clients:
                    continue

                if not has_session_material(account):
                    with db_connect(config) as conn:
                        mark_account_missing_session(conn, account)
                    continue

                try:
                    client = client_for(account, config)
                    client.start()
                    processed_message_keys = processed_message_keys_by_account.setdefault(account_id, set())
                    seed_saved_message_commands(client, account_id, processed_message_keys)

                    with db_connect(config) as conn:
                        mark_account_authorized_from_running_client(conn, account, client)

                    client.add_handler(MessageHandler(
                        lambda client, message, account_id=account_id, processed_message_keys=processed_message_keys: handle_userbot_command_once(
                            client,
                            message,
                            account_id,
                            delay_seconds,
                            processed_message_keys,
                        ),
                        filters.all,
                    ))
                    clients[account_id] = client
                    print(f"watching userbot account_id={account_id} phone={account.get('phone_number')}", flush=True)
                except Exception as exc:
                    print(f"watcher start failed account_id={account_id}: {exc}", flush=True)
                    if is_auth_key_error(exc):
                        try:
                            with db_connect(config) as conn:
                                mark_account_session_error(conn, account, exc)
                        except Exception as notify_exc:
                            print(f"watcher session error notify failed account_id={account_id}: {notify_exc}", flush=True)

            for account_id, client in list(clients.items()):
                try:
                    poll_saved_messages_for_commands(
                        client,
                        account_id,
                        delay_seconds,
                        processed_message_keys_by_account.setdefault(account_id, set()),
                    )
                except Exception as exc:
                    print(f"watcher poll failed account_id={account_id}: {exc}", flush=True)

        except Exception as exc:
            print(f"share watcher loop failed: {exc}", flush=True)

        time.sleep(max(1, min(refresh_seconds, 5)))


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
    login_flow_parser.add_argument("--token", required=True)
    login_flow_parser.add_argument("--timeout", type=int, default=300)

    share_parser = subparsers.add_parser("share")
    share_parser.add_argument("share_id", type=int)
    share_parser.add_argument("--delay", type=float, default=0.0)

    pending_parser = subparsers.add_parser("share-pending")
    pending_parser.add_argument("--limit", type=int, default=5)
    pending_parser.add_argument("--delay", type=float, default=0.0)

    list_groups_parser = subparsers.add_parser("list-groups")
    list_groups_parser.add_argument("account_id", type=int)

    watch_shares_parser = subparsers.add_parser("watch-shares")
    watch_shares_parser.add_argument("--delay", type=float, default=0.0)
    watch_shares_parser.add_argument("--refresh", type=int, default=1)

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
        login_flow(args.account_id, args.token, args.timeout)
    elif args.command == "share":
        process_share(args.share_id, args.delay)
    elif args.command == "share-pending":
        process_pending(args.limit, args.delay)
    elif args.command == "list-groups":
        list_groups(args.account_id)
    elif args.command == "watch-shares":
        watch_shares(args.delay, args.refresh)


if __name__ == "__main__":
    main()
