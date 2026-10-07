# JobAgent

JobAgent adalah marketplace lowongan kerja dengan dua jenis akun publik—pencari kerja dan perusahaan—serta portal administrator terpisah. Aplikasi dibuat sebagai Laravel monolith dengan Blade, PostgreSQL di Railway, Firebase Authentication (custom token), Mailtarget untuk OTP email, dan PWA responsif.

## Fitur

- Cari dan filter lowongan berdasarkan kata kunci, lokasi, dan kategori.
- Daftar/masuk kandidat atau perusahaan dengan OTP email 6 digit.
- Foto profil kandidat dan logo perusahaan (maks. 3 MB); file pendaftaran disimpan privat sampai OTP benar.
- Perusahaan membuat lowongan dan meninjau/memperbarui status lamaran; kandidat melihat riwayat serta status lamarannya.
- Admin terpisah yang hanya dapat dibuat lewat Artisan, login OTP, dashboard statistik, pencarian, CRUD akun kandidat/perusahaan, dan perlindungan terhadap akun admin.
- Pemeriksaan status database, Firebase, dan email; indikator proses dan sukses; PWA dapat dipasang di ponsel.

## Menjalankan lokal

Persyaratan: PHP 8.4.1+, Composer, ekstensi PHP `pdo_sqlite`, `pdo_pgsql`, `curl`, `fileinfo`, dan `mbstring`.

```powershell
Copy-Item .env.example .env
New-Item -ItemType File -Path database\database.sqlite
composer install
php artisan key:generate
```

Isi `.env` dengan konfigurasi Firebase Admin, Mailtarget, dan `OTP_SECRET`, lalu:

```powershell
php artisan migrate
php artisan storage:link
php artisan serve
```

Buka `http://localhost:8000`. Untuk mendaftar sebagai pengguna biasa, buka form di situs. Untuk membuat admin utama secara terpisah setelah migrasi:

```powershell
php artisan jobagent:make-admin admin@domain-anda.id "Nama Admin"
```

Perintah tersebut menolak email yang sudah dipakai akun lain. Akun admin masuk menggunakan OTP email, bukan kata sandi; buat dengan alamat email yang dapat Anda akses. Admin tidak dapat mendaftar melalui halaman publik; pilih tab Admin pada form Masuk di beranda. URL lama `/admin/login` akan mengarahkan ke form masuk yang sama.

## Konfigurasi Firebase dan OTP

Firebase Web config yang bersifat publik sudah dipasang di `config/services.php` melalui environment variable dan `.env.example`. Kunci API Web bukan kredensial Admin, tetapi tetap batasi domain/API key di Google Cloud dan aktifkan App Check sebelum peluncuran publik.

Atur environment berikut:

```text
FIREBASE_PROJECT_ID=jobsagent-f4fda
FIREBASE_API_KEY=<Firebase Web API key>
FIREBASE_AUTH_DOMAIN=jobsagent-f4fda.firebaseapp.com
FIREBASE_STORAGE_BUCKET=jobsagent-f4fda.firebasestorage.app
FIREBASE_MESSAGING_SENDER_ID=493405453619
FIREBASE_APP_ID=<Firebase Web app ID>
FIREBASE_MEASUREMENT_ID=<Firebase measurement ID>
FIREBASE_CREDENTIALS=<path file service account yang tersedia pada runtime; opsional>
FIREBASE_SERVICE_ACCOUNT_JSON=<seluruh isi JSON service account sebagai secret; alternatif>
MAILTARGET_API_KEY=<Mailtarget API token>
MAILTARGET_ENDPOINT=https://transmission.mailtarget.co/v1/layang/transmissions
MAIL_FROM_ADDRESS=<sender sandbox untuk lokal atau sender terverifikasi untuk produksi>
MAIL_FROM_NAME=JobAgent
OTP_SECRET=<secret acak minimal 32 karakter>
```

Kredensial Firebase Admin hanya untuk server. Gunakan salah satu: `FIREBASE_SERVICE_ACCOUNT_JSON` berisi seluruh JSON sebagai Railway secret (direkomendasikan untuk Railway), atau `FIREBASE_CREDENTIALS` berisi path file JSON yang sudah tersedia dan dapat dibaca di runtime. Path relatif dihitung dari root proyek, misalnya `storage/app/firebase-service-account.json`; mengunduh file ke komputer lokal saja tidak membuatnya tersedia di Railway. Jangan taruh file service-account JSON di `public/`, `resources/`, Git, atau image/build log. Batasi pengiriman email pada domain pengirim terverifikasi. OTP berlaku lima menit, dibatasi lima percobaan dan cooldown pengiriman; endpoint juga dibatasi per IP.

OTP numerik dikirim oleh Mailtarget karena Firebase Email Link/Password tidak mengirim kode numerik 6 digit. Setelah OTP lolos, Laravel menerbitkan Firebase custom token memakai Firebase Admin SDK dan membuat sesi aplikasi Laravel. Data profil, lowongan, dan lamaran pada starter ini disimpan di PostgreSQL; Firebase dipakai untuk autentikasi, bukan Firestore. Sender sandbox biasanya dibatasi untuk pengujian—atur sender/domain yang sudah diverifikasi di Mailtarget sebelum mengirim OTP ke email pengguna umum.

## Railway

`railway.json` menyiapkan Composer build, migrasi PostgreSQL sebelum deploy, cache Laravel saat start, storage link, dan health check `/api/health`. Hubungkan repository GitHub sebagai Railway service, tambahkan service PostgreSQL, lalu set environment pada **service aplikasi**:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://jobapps-production-a6c9.up.railway.app
APP_KEY=<hasil php artisan key:generate --show; simpan sebagai secret>
DB_CONNECTION=pgsql
DATABASE_URL=${{Postgres.DATABASE_URL}}
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
FIREBASE_SERVICE_ACCOUNT_JSON=<seluruh isi JSON Admin SDK, simpan sebagai secret>
FIREBASE_PROJECT_ID=jobsagent-f4fda
FIREBASE_API_KEY=<Firebase Web API key>
FIREBASE_AUTH_DOMAIN=jobsagent-f4fda.firebaseapp.com
FIREBASE_STORAGE_BUCKET=jobsagent-f4fda.firebasestorage.app
FIREBASE_MESSAGING_SENDER_ID=493405453619
FIREBASE_APP_ID=<Firebase Web app ID>
FIREBASE_MEASUREMENT_ID=G-DT2X00ZV2M
MAILTARGET_API_KEY=<secret Mailtarget API token>
MAILTARGET_ENDPOINT=https://transmission.mailtarget.co/v1/layang/transmissions
MAIL_FROM_ADDRESS=<sender terverifikasi Mailtarget untuk produksi>
MAIL_FROM_NAME=JobAgent
OTP_SECRET=<random secret 32+ karakter>
```

Ganti `Postgres` pada referensi Railway dengan nama service database yang sebenarnya. Gunakan URL PostgreSQL privat dari Railway; cukup set `DB_CONNECTION=pgsql` dan `DATABASE_URL` pada service aplikasi. Variabel `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD`, `POSTGRES_USER`, `POSTGRES_PASSWORD`, dan `PGDATA` tetap dikelola oleh service database—jangan menyalin nilai password database ke repository.

Buat `APP_KEY` sekali, lalu simpan nilainya di Railway Variables sebagai secret. Dapatkan nilainya dengan `php artisan key:generate --show`. Buat `OTP_SECRET` lokal, misalnya `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`, lalu simpan hasilnya sebagai secret Railway. Jangan membuat APP_KEY baru setiap deployment karena itu akan membatalkan sesi pengguna.

Pasang Railway Volume pada `/app/storage/app` agar foto profil dan logo perusahaan tidak hilang ketika service di-redeploy atau restart. URL publik gambar dilayani melalui `/storage`. Sesudah deployment pertama dan migrasi berhasil, buat admin utama melalui Railway Shell:

```sh
php artisan jobagent:make-admin admin@domain-anda.id "Nama Admin"
```

Health check memeriksa koneksi database aktual serta keberadaan konfigurasi Firebase/Mailtarget; ia tidak mengirim email uji atau membuat panggilan autentikasi berbayar.

## Keamanan dan batasan

Kredensial database dan token Mailtarget yang dibagikan di percakapan perlu **segera dirotasi** (password di Railway PostgreSQL Variables, token di Mailtarget), kemudian perbarui secret Railway dan deploy ulang. Jangan gunakan lagi kredensial yang sudah dibagikan. Semua secret Firebase Admin, Mailtarget, APP_KEY, dan OTP_SECRET harus dimasukkan sebagai Railway Variables bertipe secret.

Sebelum peluncuran umum, lengkapi kebijakan privasi/retensi, verifikasi perusahaan, moderasi lowongan, audit log admin, pemantauan error, backup PostgreSQL, pemindaian gambar, serta aturan akses dan App Check Firebase. PWA meng-cache aset statis saja; data akun dan endpoint API tidak disimpan offline.
