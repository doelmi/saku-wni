<img src="https://s6.imgcdn.dev/hYHaF8.webp" width="100" height="100">

# Saku WNI untuk WNI Simulator

### 📱 Saku WNI: Asisten Finansial Warga di Tengah Badai Takdir

Kalkulator Keuangan Pendamping Non-Resmi (Companion App) untuk Board Game WNI Simulator.

### 📝 Deskripsi Aplikasi

Saku WNI adalah aplikasi dompet digital dan kalkulator skor pintar yang dirancang khusus untuk membebaskan Anda dari kerumitan menghitung uang kertas fisik saat bermain board game WNI Simulator. Layaknya aplikasi fintech di dunia nyata, Saku WNI hadir dengan antarmuka yang bersih dan modern untuk melacak pasang surut kekayaan Anda secara real-time.

Mulai dari menghitung bonus gajian, membayar denda tilang mendadak, hingga kalkulasi otomatis Pajak Luar Biasa yang rumit, semua bisa diselesaikan secara SATSET tanpa perlu kertas coret-coretan. Cukup fokus pada strategi bertahan hidup dan biarkan Saku WNI yang mengurus birokrasi keuangan Anda!

> [!IMPORTANT]
> Repository ini merupakan Backend API, untuk Frontend bisa cek pada repository berikut: https://github.com/doelmi/saku-wni-fe

## Dokumentasi API

Swagger UI interaktif bisa dibuka di:

```text
http://localhost:8000/docs
```

Dokumen OpenAPI mentahnya tersedia di:

```text
http://localhost:8000/api/openapi
```

Swagger UI sudah menyediakan tombol `Try it out` untuk semua endpoint autentikasi dan games.
Semua endpoint games membutuhkan access token dari
`POST /api/auth/verify-otp`:

```http
Authorization: Bearer atk_...
```

Data game dibatasi sesuai user yang sedang login. User hanya bisa melihat,
mengubah nama, menutup, atau soft delete game yang dia buat sendiri.

Peserta bisa dikelola di dalam game milik user:

```text
GET    /api/games/{game_id}/participants
POST   /api/games/{game_id}/participants
PATCH  /api/games/{game_id}/participants/{participant_id}
DELETE /api/games/{game_id}/participants/{participant_id}
```

Setiap peserta punya `public_token` unik untuk akses publik tanpa login.
Token ini berbeda untuk tiap peserta di tiap game.

```text
GET /api/public/participants/{token}
GET /api/public/participants/{token}/transfers
```

Endpoint publik hanya menampilkan informasi peserta pemilik token tersebut,
termasuk `balance`, dan history transfer yang melibatkan peserta itu.

Hapus peserta memakai soft delete. Data peserta punya field audit
`created_at`, `updated_at`, dan `deleted_at` yang boleh `null`.
Setiap peserta juga punya `balance` berupa angka integer, dengan nilai awal `0`.
Peserta `Negara` adalah peserta sistem: tidak bisa dihapus, tidak bisa
diganti namanya, dan nama peserta baru tidak boleh `Negara`.
Status peserta bernilai `active` atau `bankrupt`.
Untuk mengubah status peserta:

```text
PATCH /api/games/{game_id}/participants/{participant_id}/status
```

Jika status diubah menjadi `bankrupt`, seluruh saldo peserta otomatis
ditransfer ke peserta `Negara`.
Uang hanya bisa dipindahkan antar peserta lewat endpoint ini:

```text
POST /api/games/{game_id}/transfers
```

dengan request body seperti ini:

```json
{
  "from_participant_id": 1,
  "to_participant_id": 2,
  "amount": 500
}
```

Transfer berjalan atomik, saldo pengirim harus cukup, dan riwayatnya
dicatat di tabel `participant_transfers`.

History transfer bisa dilihat per game:

```text
GET /api/games/{game_id}/transfers
```

Untuk melihat history satu peserta saja, pakai filter:

```text
GET /api/games/{game_id}/transfers?participant_id=1
```

Filter peserta ini mencakup transfer masuk dan transfer keluar.

### Login email tanpa password

Jalankan migration dan isi konfigurasi SMTP di `.env`, lalu pakai endpoint
JSON berikut:

```http
POST /api/auth/request-otp
Content-Type: application/json

{"email":"user@example.com"}
```

Response akan berisi `challenge_id`. Kirim identifier itu bersama kode
6 digit yang diterima lewat email:

```http
POST /api/auth/verify-otp
Content-Type: application/json

{"challenge_id":123,"otp":"123456"}
```

Kalau berhasil, API akan mengembalikan opaque bearer token:

```json
{
  "access_token": "atk_...",
  "token_type": "Bearer",
  "expires_in": 2592000
}
```

Untuk logout dan membuat token aktif tidak valid lagi:

```http
POST /api/auth/logout
Authorization: Bearer atk_...
```

Secara default, kode OTP kedaluwarsa setelah 10 menit, bisa dicoba maksimal
5 kali, dan bisa diminta lagi setelah 60 detik. Nilai-nilai ini bisa diatur
di `app/config/config.php` atau `.env`.

Contoh konfigurasi SMTP:

```env
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-account@example.com
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_TIMEOUT=15
MAIL_FROM=no-reply@example.com
MAIL_FROM_NAME=Saku WNI
```

Pakai `tls` untuk STARTTLS di port `587`, `ssl` untuk implicit TLS di port
`465`, atau `none` hanya kalau server SMTP memang jelas mendukung koneksi
tanpa enkripsi.


### Command yang berguna

| Command | Kegunaan |
|---------|---------|
| `composer start` | Menjalankan PHP built-in server di port 8000 |
| `composer test` | PHPUnit |
| `composer analyse` | PHPStan level 8 |
| `composer check` | PHPUnit + PHPStan |
| `php runway migrate` | Menjalankan migration untuk driver aktif (`.sql` / `.mysql.sql`) |
| `php runway --help` | Melihat daftar command CLI |
| `php runway config:get` / `config:set` | Helper untuk config file |

Pakai hanya command yang memang muncul di `php runway --help` pada instalasimu.

## Lisensi

MIT — lihat [LICENSE](LICENSE).
