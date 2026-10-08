<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#f7f8f5">
    <link rel="icon" type="image/svg+xml" href="{{ asset('jobagent-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/effects.css') }}">
    <title>{{ $resetComplete ? 'Kata sandi diperbarui' : 'Reset kata sandi' }} — JobAgent</title>
</head>
<body>
    <main class="reset-page">
        <section class="reset-card" aria-labelledby="reset-title">
            <a class="brand" href="/"><img src="{{ asset('jobagent-mark.svg') }}" alt=""><span>jobagent<span class="brand-dot">.</span></span></a>
            <span class="section-kicker">{{ $resetComplete ? 'RESET BERHASIL' : 'KEAMANAN AKUN' }}</span>
            <h1 id="reset-title">{{ $resetComplete ? 'Kata sandi diperbarui.' : 'Reset kata sandi.' }}</h1>
            <p class="reset-intro">{{ $resetComplete ? 'Kata sandi Anda berhasil diubah. Masuk kembali dengan email dan kata sandi baru untuk melanjutkan menggunakan JobAgent.' : 'Untuk mereset kata sandi, gunakan tautan resmi yang kami kirim ke email Anda. Setelah berhasil, Anda akan kembali ke halaman ini.' }}</p>
            <a class="button auth-submit reset-login-link" href="/?login=account">{{ $resetComplete ? 'Masuk ke JobAgent' : 'Kembali ke halaman masuk' }} <span>→</span></a>
        </section>
    </main>
</body>
</html>
