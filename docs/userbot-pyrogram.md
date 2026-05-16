# Userbot Pyrogram Plan

Project ini memakai dua jalur Telegram:

- Bot API Laravel untuk menerima command pelanggan seperti `/start`, nomor, OTP, tambah grup, dan `/share`.
- Pyrogram worker untuk login akun Telegram pelanggan dan mengirim pesan dari akun tersebut ke daftar grup yang pelanggan simpan.

## Data Model

- `telegram_client_accounts`: akun Telegram pelanggan, status login, nomor, nama session, masa langganan.
- `telegram_client_groups`: daftar grup tujuan per akun pelanggan.
- `share_messages`: antrean pesan promosi yang akan dikirim.
- `share_message_deliveries`: hasil kirim per grup.

## Environment

Tambahkan ke `.env`:

```env
PYROGRAM_API_ID=
PYROGRAM_API_HASH=
```

Nilai `api_id` dan `api_hash` didapat dari aplikasi Telegram developer milik owner sistem.

## Worker Commands

Install dependency Python:

```powershell
cd userbot_worker
python -m venv .venv
.\.venv\Scripts\pip install -r requirements.txt
```

Kirim OTP login ke nomor akun:

```powershell
.\.venv\Scripts\python worker.py send-code 1
```

Login dengan kode OTP:

```powershell
.\.venv\Scripts\python worker.py sign-in 1 12345
```

Jika akun punya 2FA password:

```powershell
.\.venv\Scripts\python worker.py sign-in 1 12345 --password "password-2fa"
```

Kirim satu job share:

```powershell
.\.venv\Scripts\python worker.py share 1 --delay 5
```

Proses antrean share:

```powershell
.\.venv\Scripts\python worker.py share-pending --limit 5 --delay 5
```

## Next Laravel Flow

Webhook Bot API berikutnya perlu diarahkan ke state machine:

1. `/start`: buat atau ambil `telegram_client_accounts` berdasarkan `bot_chat_id`.
2. User kirim nomor: simpan `phone_number`, buat `session_name`, jalankan worker `send-code`.
3. User kirim OTP: jalankan worker `sign-in`.
4. User kirim link grup: simpan ke `telegram_client_groups`, lalu worker bisa join/verify grup.
5. `/share pesan`: buat `share_messages`, lalu worker `share-pending` mengirim ke semua group aktif.

Delay antar grup wajib dipakai supaya tidak terlalu agresif dan akun pelanggan tidak cepat terkena limit Telegram.
