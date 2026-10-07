# JobAgent

JobAgent adalah marketplace lowongan kerja dengan dua jenis akun publik—pencari kerja dan perusahaan—serta portal administrator. Aplikasi menggunakan Laravel, PostgreSQL di Railway, Firebase Authentication untuk email/kata sandi dan Google, dan PWA responsif.

## Fitur

- Cari dan filter lowongan berdasarkan kata kunci, lokasi, dan kategori.
- Daftar dengan email/kata sandi atau Google; masuk menggunakan email/kata sandi atau Google.
- Verifikasi email Firebase; reset kata sandi memakai halaman JobAgent khusus dan setelah reset pengguna masuk ke dashboard.
- Foto profil kandidat dan logo perusahaan (maks. 3 MB).
- Perusahaan membuat lowongan dan mengelola lamaran; kandidat melihat riwayat lamarannya.
- Admin dibuat lewat Artisan, dengan dashboard statistik dan pengelolaan akun.

## Menjalankan lokal

Persyaratan: PHP 8.4.1+, Composer, ekstensi PHP `pdo_sqlite`, `pdo_pgsql`, `curl`, `fileinfo`, dan `mbstring`.

```powershell
Copy-Item .env.example .env
New-Item -ItemType File -Path database\database.sqlite
composer install
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

Konfigurasikan Firebase seperti pada bagian berikut, lalu buka `http://localhost:8000`.

Buat akun admin utama setelah migrasi:

```powershell
php artisan jobagent:make-admin admin@domain-anda.id "Nama Admin"
```

Buat juga akun di Firebase Authentication dengan email yang sama, verifikasi emailnya, lalu masuk melalui halaman utama. Peran admin yang tersimpan di Laravel tetap digunakan; pengguna tidak dapat memilih peran admin saat mendaftar.

## Firebase Authentication

Di Firebase Console untuk project `jobsagent-f4fda`:

1. Buka **Authentication → Sign-in method**, aktifkan **Email/Password** (password sign-in) dan **Google**.
2. Pada **Authentication → Settings → Authorized domains**, tambahkan domain lokal dan domain publik Railway aplikasi.
3. Atur template **Email address verification** dan **Password reset** sesuai bahasa yang diinginkan.
4. Biarkan action handler/template email reset memakai handler standar Firebase. Aplikasi mengatur `ActionCodeSettings.url` ke `https://<domain-aplikasi>/password/reset?status=complete`; Firebase menangani tautan reset terlebih dahulu, lalu mengarahkan pengguna ke halaman konfirmasi JobAgent. Tidak perlu memverifikasi kepemilikan DNS domain Railway pada template. Domain aplikasi tetap harus ada di Authorized domains.
5. Di **Project settings → Service accounts**, buat kredensial Firebase Admin untuk server. Simpan file JSON dengan aman; jangan pernah masukkan ke Git atau direktori publik.

Firebase Web config bukan rahasia Admin. Variabel berikut memasok konfigurasi Web dan verifikasi token di backend:

```text
FIREBASE_PROJECT_ID=jobsagent-f4fda
FIREBASE_API_KEY=<Firebase Web API key>
FIREBASE_AUTH_DOMAIN=jobsagent-f4fda.firebaseapp.com
FIREBASE_STORAGE_BUCKET=jobsagent-f4fda.firebasestorage.app
FIREBASE_MESSAGING_SENDER_ID=493405453619
FIREBASE_APP_ID=<Firebase Web app ID>
FIREBASE_MEASUREMENT_ID=<Firebase measurement ID>
FIREBASE_SERVICE_ACCOUNT_JSON=<seluruh isi JSON Admin SDK; simpan sebagai secret>
```

Frontend memakai Firebase SDK untuk pendaftaran, login email/kata sandi, login Google, verifikasi email, dan reset kata sandi. Backend hanya memverifikasi Firebase ID token menggunakan service account, membuat sesi Laravel, dan mengambil peran dari database. Email verifikasi/reset dikirim langsung oleh Firebase; Mailtarget dan OTP tidak lagi digunakan. Firebase menangani form penggantian password pada hosted action handler, lalu mengarahkan pengguna ke halaman konfirmasi Laravel `/password/reset?status=complete`. Pengguna kemudian masuk memakai kata sandi baru untuk membuka dashboard sesuai perannya.

Akun lama yang hanya terdaftar di database JobAgent perlu dibuat/ditautkan di Firebase Authentication menggunakan email yang sama. Pengguna dapat mendaftar lewat form JobAgent dengan email yang sama, verifikasi email, lalu masuk; backend mempertahankan peran lama yang sudah ada. Untuk admin, buat akun Firebase Authentication dengan email admin yang sama dan verifikasi sebelum login.

## Railway

`railway.json` menyiapkan Composer build, migrasi sebelum deploy, cache Laravel saat start, storage link, dan health check `/api/health`. Hubungkan repository sebagai Railway service, tambahkan PostgreSQL, lalu atur variables pada **service aplikasi**:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<domain-aplikasi-railway>
APP_KEY=<hasil php artisan key:generate --show; simpan sebagai secret>
DB_CONNECTION=pgsql
DATABASE_URL=${{Postgres.DATABASE_URL}}
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
FIREBASE_SERVICE_ACCOUNT_JSON=<JSON Admin SDK lengkap; simpan sebagai secret>
FIREBASE_PROJECT_ID=jobsagent-f4fda
FIREBASE_API_KEY=<Firebase Web API key>
FIREBASE_AUTH_DOMAIN=jobsagent-f4fda.firebaseapp.com
FIREBASE_STORAGE_BUCKET=jobsagent-f4fda.firebasestorage.app
FIREBASE_MESSAGING_SENDER_ID=493405453619
FIREBASE_APP_ID=<Firebase Web app ID>
FIREBASE_MEASUREMENT_ID=<Firebase measurement ID>
```

Ganti `Postgres` pada referensi Railway sesuai nama service database. Pastikan domain Railway juga ada di Firebase **Authorized domains**. Buat `APP_KEY` sekali dan jangan menggantinya setiap deployment karena hal itu membatalkan sesi. Pasang Railway Volume pada `/app/storage/app` agar foto profil/logo tidak hilang saat service di-redeploy.

Health check memeriksa koneksi database serta konfigurasi Firebase; ia tidak mengirim email atau membuat panggilan autentikasi berbayar.

## Keamanan dan batasan

Rotasi kredensial database, Mailtarget, dan Firebase Admin yang pernah dibagikan di percakapan; cabut key Mailtarget karena OTP tidak lagi digunakan. Jangan gunakan nilai yang sudah terekspos. Simpan Firebase Admin JSON dan `APP_KEY` sebagai Railway Variables bertipe secret, bukan source code, Git, build log, atau file publik.

Sebelum peluncuran umum, lengkapi kebijakan privasi/retensi, verifikasi perusahaan, moderasi lowongan, pemantauan error, backup PostgreSQL, pemindaian gambar, serta aturan akses dan App Check Firebase. PWA hanya meng-cache aset statis; data akun dan endpoint API tidak disimpan offline.
