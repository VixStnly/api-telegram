from __future__ import annotations

import argparse
import os
import time
from pathlib import Path
from typing import Any

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
        "db": {
            "host": os.getenv("DB_HOST", "127.0.0.1"),
            "port": int(os.getenv("DB_PORT", "3306")),
            "user": os.getenv("DB_USERNAME", "root"),
            "password": os.getenv("DB_PASSWORD", ""),
            "database": os.getenv("DB_DATABASE", "telegram_autoreply_engine"),
            "charset": "utf8mb4",
            "cursorclass": pymysql.cursors.DictCursor,
            "autocommit": True,
        },
    }


def db_connect(config: dict[str, Any]):
    return pymysql.connect(**config["db"])


def fetch_one(conn, query: str, params: tuple[Any, ...]) -> dict[str, Any] | None:
    with conn.cursor() as cursor:
        cursor.execute(query, params)
        return cursor.fetchone()


def execute(conn, query: str, params: tuple[Any, ...]) -> None:
    with conn.cursor() as cursor:
        cursor.execute(query, params)


def client_for(account: dict[str, Any], config: dict[str, Any]) -> Client:
    SESSION_DIR.mkdir(parents=True, exist_ok=True)

    return Client(
        name=account["session_name"],
        api_id=config["api_id"],
        api_hash=config["api_hash"],
        workdir=str(SESSION_DIR),
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

        with client_for(account, config) as app:
            sent_code = app.send_code(account["phone_number"])

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
            with client_for(account, config) as app:
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

    sign_in_parser = subparsers.add_parser("sign-in")
    sign_in_parser.add_argument("account_id", type=int)
    sign_in_parser.add_argument("code")
    sign_in_parser.add_argument("--password")

    share_parser = subparsers.add_parser("share")
    share_parser.add_argument("share_id", type=int)
    share_parser.add_argument("--delay", type=float, default=5.0)

    pending_parser = subparsers.add_parser("share-pending")
    pending_parser.add_argument("--limit", type=int, default=5)
    pending_parser.add_argument("--delay", type=float, default=5.0)

    args = parser.parse_args()

    if args.command == "send-code":
        send_code(args.account_id)
    elif args.command == "sign-in":
        sign_in(args.account_id, args.code, args.password)
    elif args.command == "share":
        process_share(args.share_id, args.delay)
    elif args.command == "share-pending":
        process_pending(args.limit, args.delay)


if __name__ == "__main__":
    main()
