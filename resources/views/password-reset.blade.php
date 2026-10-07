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
    <title>Atur ulang kata sandi — JobAgent</title>
</head>
<body>
    <main class="reset-page">
        <section class="reset-card" aria-labelledby="reset-title">
            <a class="brand" href="/"><img src="{{ asset('jobagent-mark.svg') }}" alt=""><span>jobagent<span class="brand-dot">.</span></span></a>
            <span class="section-kicker">KEAMANAN AKUN</span>
            <h1 id="reset-title">Atur ulang kata sandi.</h1>
            <p class="reset-intro" id="reset-message">Memeriksa tautan reset dari email...</p>
            <form class="auth-form" id="reset-form" hidden>
                <label>Email akun<input id="reset-email" type="email" readonly autocomplete="email"></label>
                <label>Kata sandi baru<input id="new-password" type="password" required minlength="6" maxlength="128" autocomplete="new-password" placeholder="Minimal 6 karakter"></label>
                <label>Konfirmasi kata sandi<input id="confirm-password" type="password" required minlength="6" maxlength="128" autocomplete="new-password" placeholder="Ulangi kata sandi baru"></label>
                <p class="form-error" id="reset-error" hidden></p>
                <button class="button auth-submit" id="reset-submit" type="submit">Simpan kata sandi <span>→</span></button>
            </form>
            <p class="reset-footer"><a href="/?login=account">Kembali ke halaman masuk</a></p>
        </section>
    </main>
    <script>window.JobAgent = {config: @json($firebaseConfig)};</script>
    <script src="{{ asset('js/password-reset.js') }}" defer></script>
</body>
</html>
