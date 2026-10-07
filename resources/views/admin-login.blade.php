<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#0c2824">
    <link rel="icon" type="image/svg+xml" href="{{ asset('jobagent-mark.svg') }}"><link rel="stylesheet" href="{{ asset('css/app.css') }}"><link rel="stylesheet" href="{{ asset('css/effects.css') }}">
    <title>Admin JobAgent — Secure access</title>
</head>
<body class="admin-login-page">
    <main class="admin-login-shell">
        <a class="brand" href="/"><img src="{{ asset('jobagent-mark.svg') }}" alt=""><span>jobagent<span class="brand-dot">.</span></span></a>
        <div class="admin-lock"><span>✳</span></div><span class="section-kicker">SECURE ADMIN PORTAL</span>
        <h1>Selamat datang,<br><span>Admin.</span></h1><p class="admin-login-intro">Akses terbatas untuk pengelola JobAgent. Verifikasi identitas melalui kode OTP email.</p><div class="process-indicator" id="admin-processing" hidden><span class="spinner"></span><span id="admin-progress-label">Memeriksa akun admin...</span></div>
        <form id="admin-login-form" class="auth-form">
            <label>Email admin<input type="email" name="email" required maxlength="254" autocomplete="username" placeholder="admin@perusahaan.id"></label>
            <label id="admin-code-label" hidden>Kode verifikasi<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000"></label>
            <p class="form-error" id="admin-login-error" hidden></p>
            <button class="button auth-submit" id="admin-login-submit">Kirim kode verifikasi <span>→</span></button>
            <button class="back-email" id="admin-resend" type="button" hidden>Gunakan email lain atau kirim ulang</button>
        </form>
        <div class="admin-login-foot">⌑ Akses dilindungi OTP · Admin tidak dapat mendaftar secara publik</div>
    </main>
    <script>window.JobAgent = {config: @json(['apiKey'=>config('services.firebase.api_key'),'authDomain'=>config('services.firebase.auth_domain'),'projectId'=>config('services.firebase.project_id'),'appId'=>config('services.firebase.app_id')])};</script>
    <script src="{{ asset('js/admin-login.js') }}" defer></script>
</body>
</html>
