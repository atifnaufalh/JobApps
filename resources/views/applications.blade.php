<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f7f8f5">
    <link rel="icon" type="image/svg+xml" href="{{ asset('jobagent-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/effects.css') }}">
    <title>{{ $user->role === 'employer' ? 'Lamaran masuk' : 'Lamaran saya' }} — JobAgent</title>
</head>
<body class="applications-page" data-role="{{ $user->role }}">
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="/" aria-label="JobAgent beranda"><img src="{{ asset('jobagent-mark.svg') }}" alt=""><span>jobagent<span class="brand-dot">.</span></span></a>
            <nav class="main-nav"><a href="/">Beranda</a><a href="/#lowongan">Cari kerja</a><a href="{{ route('applications') }}">{{ $user->role === 'employer' ? 'Lamaran masuk' : 'Lamaran saya' }}</a></nav>
            <div class="header-actions"><span class="applications-user">{{ $user->name }}</span><button class="text-button" id="applications-logout">Keluar</button></div>
        </div>
    </header>
    <main class="applications-main">
        <span class="section-kicker">{{ $user->role === 'employer' ? 'TALENTA UNTUK TIMMU' : 'LANGKAH BERIKUTNYA' }}</span>
        <h1>{{ $user->role === 'employer' ? 'Lamaran masuk.' : 'Lamaran saya.' }}</h1>
        <p class="applications-intro">{{ $user->role === 'employer' ? 'Tinjau kandidat yang melamar dan perbarui progres seleksi.' : 'Pantau perkembangan lamaran pekerjaanmu.' }}</p>
        <div class="application-list" id="application-list" aria-live="polite"><div class="application-message">Memuat lamaran...</div></div>
    </main>
    <div class="toast" id="applications-toast" hidden><span class="toast-check">✓</span><span id="applications-toast-message"></span></div>
    <script>window.JobAgent = {config: @json($firebaseConfig)};</script>
    <script src="{{ asset('js/applications.js') }}" defer></script>
</body>
</html>
