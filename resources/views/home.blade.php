<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f7f8f5">
    <meta name="description" content="Temukan pekerjaan yang cocok dan bangun kariermu bersama JobAgent.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('jobagent-mark.svg') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/effects.css') }}">
    <title>JobAgent — Temukan langkah karier berikutnya</title>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="/" aria-label="JobAgent beranda"><img src="{{ asset('jobagent-mark.svg') }}" alt=""><span>jobagent<span class="brand-dot">.</span></span></a>
            <nav class="main-nav" id="main-nav">
                <a href="#lowongan">Cari kerja</a>
                <a href="#cara-kerja">Cara kerja</a>
                <a href="#perusahaan">Untuk perusahaan</a>
                @if($currentUser && in_array($currentUser['role'], ['candidate', 'employer'], true))
                    <a href="{{ route('applications') }}">{{ $currentUser['role'] === 'employer' ? 'Lamaran masuk' : 'Lamaran saya' }}</a>
                @endif
                <button class="mobile-nav-cta" data-auth="login">Masuk</button>
                <button class="mobile-nav-cta" data-auth="register" data-role="candidate">Buat akun gratis</button>
            </nav>
            <div class="header-actions">
                @if($currentUser && $currentUser['role'] === 'employer')
                    <button class="text-button post-desktop" data-open-job>Pasang lowongan</button>
                @endif
                @if($currentUser && $currentUser['role'] === 'admin')
                    <a class="text-button post-desktop" href="{{ route('admin.dashboard') }}">Dashboard admin</a>
                @endif
                @if($currentUser)
                    <button class="avatar-button" data-logout title="Keluar dari akun">@if($currentUser['photoUrl'])<img src="{{ $currentUser['photoUrl'] }}" alt="">@else{{ mb_substr($currentUser['companyName'] ?: $currentUser['name'], 0, 1) }}@endif<span class="online-dot"></span></button>
                @else
                    <button class="text-button login-button" data-auth="login">Masuk</button>
                    <button class="button button-small" data-auth="register" data-role="candidate">Mulai gratis <span aria-hidden="true">→</span></button>
                @endif
                <button class="mobile-menu" aria-label="Buka navigasi" aria-expanded="false" data-menu><span>☰</span></button>
            </div>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="hero-orb orb-one"></div><div class="hero-orb orb-two"></div>
            <div class="hero-grid">
                <div class="hero-copy">
                    <div class="eyebrow"><span class="eyebrow-pulse"></span> KERJA YANG BERARTI, DIMULAI DI SINI</div>
                    <h1>Temukan kerja<br>yang terasa <span>lebih kamu.</span></h1>
                    <p class="hero-lead">Peluang yang tepat bukan cuma soal pekerjaan. Tapi tentang masa depan yang ingin kamu bangun.</p>
                    <div class="hero-actions"><a class="button" href="#lowongan">Jelajahi lowongan <span aria-hidden="true">→</span></a><a class="hero-secondary" href="#cara-kerja"><span class="play-icon">↗</span> Kenali JobAgent</a></div>
                    <div class="hero-proof"><div class="avatar-stack" aria-hidden="true"><span class="person p1">A</span><span class="person p2">D</span><span class="person p3">R</span><span class="avatar-plus">+</span></div><div><strong>Tempat karier bertumbuh</strong><span>Untuk talenta dan tim terbaik</span></div></div>
                </div>
                <div class="hero-art" aria-label="Ilustrasi peluang karier">
                    <div class="art-glow"></div><div class="orbit orbit-back"></div><div class="orbit orbit-front"></div>
                    <div class="art-sphere"><div class="sphere-shine"></div><div class="sphere-mark">J<span>.</span></div></div>
                    <div class="float-card job-float"><div class="mini-logo purple">N</div><div class="float-card-copy"><strong>Product Designer</strong><span>Northstar Studio · Remote</span></div><span class="match-chip">98% cocok</span></div>
                    <div class="float-card salary-float"><span class="salary-icon">↗</span><div class="float-card-copy"><strong>Karier berkembang</strong><span>Kesempatan baru setiap hari</span></div></div>
                    <div class="float-tag tag-one">✳ Peluang baru</div><div class="float-tag tag-two"><span class="live-dot"></span> Dibuat untukmu</div><div class="art-base"></div>
                </div>
            </div>
            <div class="hero-bottom"><span>LANGKAH PERTAMA MENUJU</span><div><span class="hero-bottom-line"></span> KARIER BERIKUTNYA</div><a href="#lowongan" aria-label="Lihat lowongan">↓</a></div>
        </section>

        <section class="search-section" id="lowongan">
            <div class="search-heading"><div><span class="section-kicker">PILIHAN UNTUK LANGKAH BERIKUTNYA</span><h2>Kesempatan yang pas<br>sedang menunggumu.</h2></div><p>Dari tim kecil penuh ide sampai perusahaan yang mengubah industri—temukan ruang untuk berkembang.</p></div>
            <form class="search-box" id="job-search"><label class="search-input"><span>⌕</span><input id="search-query" placeholder="Posisi, perusahaan, atau keahlian"><span class="shortcut">⌘ K</span></label><span class="search-divider"></span><label class="search-input location-input"><span>⌖</span><input id="search-location" placeholder="Kota atau lokasi kerja"></label><button class="button search-submit">Cari pekerjaan <span>→</span></button></form>
            <div class="search-meta"><span><span class="live-dot"></span> Pilihan baru, setiap hari</span><span class="meta-right">Filter bidang <span>⌄</span></span></div>
            <div class="category-tabs" id="category-tabs">@foreach(['Semua','Teknologi','Desain','Pemasaran','Bisnis','Operasional'] as $category)<button class="{{ $loop->first ? 'category-active' : '' }}" data-category="{{ $category }}">{{ $category }}</button>@endforeach</div>
            <div class="job-list-heading" id="job-list"><div><span class="section-kicker">DIKURASI UNTUK KAMU</span><h3>Posisi yang layak dilihat <span id="job-count">({{ $jobs->count() }})</span></h3></div><button class="sort-button" id="refresh-jobs">Terbaru <span>⌄</span></button></div>
            <div class="job-grid" id="job-grid">
                @forelse($jobs as $job)
                    <article class="job-card" data-job-card data-job="{{ json_encode($job, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">
                    <div class="job-card-top"><div class="company-mark">@if($job['companyLogo'])<img src="{{ $job['companyLogo'] }}" alt="" loading="lazy">@else{{ mb_substr($job['companyName'], 0, 1) }}@endif</div><span class="job-age">{{ $job['createdAt'] ? \Illuminate\Support\Carbon::parse($job['createdAt'])->diffForHumans() : 'Baru saja' }}</span></div>
                        <div class="job-category">{{ $job['category'] }}</div><h4>{{ $job['title'] }}</h4><p class="job-company">{{ $job['companyName'] }}</p><p class="job-description">{{ $job['description'] }}</p>
                        <div class="job-tags"><span>⌖ {{ $job['location'] }}</span><span>▣ {{ $job['type'] }}</span></div><div class="job-card-bottom"><strong>{{ $job['salary'] ?: 'Gaji kompetitif' }}</strong><button data-apply="{{ $job['id'] }}" aria-label="Lamar {{ $job['title'] }}">↗</button></div>
                    </article>
                @empty
                    <div class="empty-jobs"><span>⌕</span><strong>Lowongan baru segera hadir.</strong><span>Daftar untuk mendapat kabar saat peluang baru tersedia.</span></div>
                @endforelse
            </div>
        </section>

        <section class="feature-section" id="cara-kerja">
            <div class="feature-visual"><div class="feature-visual-top"><span>JOBAGENT MATCH</span><span class="match-percent">98%</span></div><div class="match-ring"><div class="match-inner"><span>98</span><small>SKOR KECOCOKAN</small></div></div><div class="match-lines"><span></span><span></span><span></span></div><div class="match-profile"><div class="profile-avatar">A</div><div><strong>Andra Pratama</strong><span>Product Designer · 4 tahun</span></div><span class="verified">✓</span></div><div class="sparkle sparkle-a">✳</div><div class="sparkle sparkle-b">✳</div></div>
            <div class="feature-copy"><span class="section-kicker">LEBIH DARI SEKADAR MELAMAR</span><h2>Karier yang cocok,<br><span>bukan asal klik.</span></h2><p>JobAgent mempertemukan apa yang kamu bisa dengan apa yang benar-benar dibutuhkan tim. Lebih relevan untukmu, lebih tepat untuk mereka.</p><div class="feature-points"><div><span class="point-icon">✳</span><div><strong>Peluang yang lebih relevan</strong><span>Temukan posisi sesuai pengalaman dan arah kariermu.</span></div></div><div><span class="point-icon point-blue">✓</span><div><strong>Perusahaan yang lebih jelas</strong><span>Informasi lowongan disampaikan oleh tim perekrut.</span></div></div></div><button class="text-link" data-auth="register" data-role="candidate">Mulai perjalananmu <span>→</span></button></div>
        </section>

        <section class="company-cta" id="perusahaan"><div class="cta-decoration cta-d1"></div><div class="cta-decoration cta-d2"></div><div class="cta-content"><span class="section-kicker">UNTUK TIM YANG SEDANG BERTUMBUH</span><h2>Temukan orang yang<br>membuat timmu <span>lebih hebat.</span></h2><p>Jangkau talenta yang tepat dan mulai percakapan yang berarti.</p><button class="button button-light" data-auth="register" data-role="employer">Daftarkan perusahaan <span>→</span></button></div><div class="cta-stats"><div><span>♧</span><strong>Talenta terbaik</strong><small>lebih dekat dari yang kamu kira</small></div><div><span>▣</span><strong>Lowonganmu, ceritamu</strong><small>tampilkan peluang dengan cara yang tepat</small></div></div><div class="cta-3d-card"><div class="cta-card-icon">♧</div><span>YOUR NEXT<br>GREAT HIRE</span><b>↗</b></div></section>
        <section class="bottom-note"><div class="note-mark">✓</div><span>Mulai dengan langkah kecil. Temukan kemungkinan besar.</span><button data-auth="register" data-role="candidate">Buat akun gratis <span>→</span></button></section>
    </main>

    <footer class="site-footer"><a class="brand" href="/"><img src="{{ asset('jobagent-mark.svg') }}" alt=""><span>jobagent<span class="brand-dot">.</span></span></a><span>© {{ date('Y') }} JobAgent. Temukan peluang, tumbuh bersama.</span><div><a href="#cara-kerja">Tentang kami</a><a href="mailto:halo@jobagent.id">Hubungi kami</a><button class="footer-status" data-check-status>Status layanan</button></div></footer>

    <div class="modal-backdrop" id="auth-modal" hidden>
        <section class="auth-modal" role="dialog" aria-modal="true" aria-labelledby="auth-title">
            <button class="modal-close" aria-label="Tutup" data-close>×</button><a class="brand" href="/"><img src="{{ asset('jobagent-mark.svg') }}" alt=""><span>jobagent<span class="brand-dot">.</span></span></a>
            <div class="auth-heading"><span class="section-kicker" id="auth-kicker">MULAI PERJALANAN BARU</span><h2 id="auth-title">Buat akun JobAgent.</h2><p id="auth-description">Daftar dengan email dan kata sandi, atau lanjutkan dengan Google.</p><div class="process-indicator" id="auth-processing" hidden><span class="spinner"></span><span id="auth-progress-label">Mengamankan akun...</span></div></div>
            <form class="auth-form" id="auth-form">
                <div class="role-switch" hidden><button type="button" class="role-selected" data-role-choice="candidate">⌕ Cari pekerjaan</button><button type="button" data-role-choice="employer">▣ Rekrut talenta</button></div>
                <div class="role-fields" data-profile="candidate"><label>Nama lengkap<input name="fullName" minlength="2" maxlength="100" placeholder="Nama sesuai identitas"></label><div class="form-row"><label>Nomor telepon<input name="phone" maxlength="30" placeholder="+62 812..."></label><label>Lokasi<input name="location" maxlength="100" placeholder="Kota domisili"></label></div><label>Bidang yang diminati <span class="optional-label">opsional</span><input name="headline" maxlength="120" placeholder="Contoh: Product Designer"></label></div>
                <div class="upload-field" data-profile="candidate"><label>Foto profil <span class="optional-label">opsional · JPG, PNG, WebP maks. 3 MB</span><span class="upload-control"><img class="image-preview" alt="Pratinjau foto profil" hidden><span class="upload-symbol">↑</span><span><strong>Unggah foto</strong><small>atau pilih dari perangkat</small></span><input type="file" name="profilePhoto" accept="image/png,image/jpeg,image/webp"></span></label></div>
                <div class="role-fields" data-profile="employer" hidden><label>Nama perusahaan<input name="companyName" minlength="2" maxlength="120" placeholder="Nama resmi perusahaan"></label><label>Nama kontak / perekrut<input name="contactName" minlength="2" maxlength="100" placeholder="Nama penanggung jawab"></label><div class="form-row"><label>Industri<input name="industry" maxlength="100" placeholder="Contoh: Teknologi"></label><label>Ukuran tim<select name="companySize"><option value="">Pilih ukuran</option><option>1–10 orang</option><option>11–50 orang</option><option>51–200 orang</option><option>201+ orang</option></select></label></div><label>Situs perusahaan <span class="optional-label">opsional</span><input name="website" type="url" maxlength="200" placeholder="https://..."></label>
                    <label>Logo perusahaan <span class="optional-label">opsional · JPG, PNG, WebP maks. 3 MB</span><span class="upload-control"><img class="image-preview" alt="Pratinjau logo perusahaan" hidden><span class="upload-symbol">↑</span><span><strong>Unggah logo</strong><small>atau pilih dari perangkat</small></span><input type="file" name="companyLogo" accept="image/png,image/jpeg,image/webp"></span></label>
                </div>
                <label id="email-label">Email<input name="email" type="email" required maxlength="254" autocomplete="email" placeholder="nama@email.com"></label>
                <label id="password-label">Kata sandi<input name="password" type="password" required autocomplete="current-password" placeholder="Minimal 6 karakter"></label>
                <label id="password-confirmation-label" hidden>Konfirmasi kata sandi<input name="password_confirmation" type="password" autocomplete="new-password" placeholder="Ulangi kata sandi"></label>
                <button class="back-email" id="forgot-password" type="button">Lupa kata sandi?</button>
                <p class="form-error" id="auth-error" hidden></p>
                <button class="button auth-submit" id="auth-submit">Buat akun <span>→</span></button>
                <div class="auth-divider"><span>atau</span></div>
                <button class="google-auth-button" id="google-auth" type="button"><svg aria-hidden="true" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.73 7.18l7.27 5.64c4.24-3.91 6.5-9.68 6.5-17.29z"/><path fill="#FBBC05" d="M10.53 28.59A14.4 14.4 0 0 1 9.75 24c0-1.59.27-3.13.76-4.59l-7.98-6.19A23.9 23.9 0 0 0 0 24c0 3.87.93 7.52 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.91-5.79l-7.27-5.64c-2.02 1.35-4.6 2.15-8.64 2.15-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>Lanjutkan dengan Google</button>
                <p class="auth-switch" id="auth-switch">Sudah punya akun? <button type="button" data-mode-switch>Masuk</button></p>
            </form><div class="modal-security">✓ Kata sandi terenkripsi dan autentikasi melindungi akunmu.</div>
        </section>
    </div>

    <div class="modal-backdrop" id="job-modal" hidden><section class="auth-modal job-modal" role="dialog" aria-modal="true" aria-labelledby="job-modal-title"><button class="modal-close" aria-label="Tutup" data-close>×</button><div class="auth-heading"><span class="section-kicker">UNTUK PERUSAHAAN</span><h2 id="job-modal-title">Pasang lowongan.</h2><p>Ceritakan posisi yang sedang ingin kamu isi.</p></div><form class="auth-form" id="job-form"><label>Nama posisi<input name="title" required minlength="3" maxlength="120" placeholder="Contoh: Senior Product Designer"></label><div class="form-row"><label>Lokasi<input name="location" required maxlength="120" placeholder="Kota atau Remote"></label><label>Tipe pekerjaan<select name="type">@foreach(['Full-time','Part-time','Contract','Internship','Remote'] as $type)<option>{{ $type }}</option>@endforeach</select></label></div><div class="form-row"><label>Bidang<select name="category">@foreach(['Teknologi','Desain','Pemasaran','Bisnis','Operasional'] as $category)<option>{{ $category }}</option>@endforeach</select></label><label>Rentang gaji<input name="salary" maxlength="80" placeholder="Contoh: Rp 10–15 juta"></label></div><label>Deskripsi posisi<textarea name="description" required minlength="30" maxlength="5000" rows="4" placeholder="Ceritakan tanggung jawab dan kualifikasi utama..."></textarea></label><p class="form-error" id="job-error" hidden></p><button class="button auth-submit">Publikasikan lowongan <span>→</span></button></form></section></div>
    <div class="modal-backdrop" id="status-modal" hidden><section class="auth-modal status-modal" role="dialog" aria-modal="true" aria-labelledby="status-title"><button class="modal-close" aria-label="Tutup" data-close>×</button><div class="auth-heading"><span class="section-kicker">JOBAGENT CHECKUP</span><h2 id="status-title">Status layanan.</h2><p>Pemeriksaan koneksi utama aplikasi secara langsung.</p></div><div class="diagnostic-list"><div><span class="diagnostic-dot"></span><strong>Status lowongan</strong><span data-check="database">Memeriksa...</span></div><div><span class="diagnostic-dot"></span><strong>Authentication</strong><span data-check="firebase">Memeriksa...</span></div><div><span class="diagnostic-dot"></span><strong>Email & Google sign-in</strong><span data-check="email">Memeriksa...</span></div></div><button class="button auth-submit" data-run-check>Periksa ulang <span>↻</span></button><p class="diagnostic-checked" id="diagnostic-checked"></p></section></div>
    <div class="success-overlay" id="success-overlay" hidden><div class="success-card"><div class="success-burst"><span>✓</span></div><span class="section-kicker">AKUN BERHASIL DIBUAT</span><h2>Selamat datang di JobAgent.</h2><p>Akun kamu siap. Masuk untuk mulai menjelajahi lowongan dan mengirim lamaran sesuai peranmu.</p><div class="success-progress"><span></span></div></div></div>
    <div class="toast" id="toast" hidden><span class="toast-check">✓</span><span id="toast-message"></span></div>
    <script>window.JobAgent = {config: @json($firebaseConfig), user: @json($currentUser)};</script>
    <script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
