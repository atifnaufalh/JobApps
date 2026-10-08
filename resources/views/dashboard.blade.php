<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0e2e29">
    <link rel="icon" type="image/svg+xml" href="{{ asset('jobagent-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/effects.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <title>Dashboard {{ $user->role === 'employer' ? 'Perusahaan' : 'Kandidat' }} — JobAgent</title>
</head>
<body class="dash-page" data-role="{{ $user->role }}">
    <div class="dash-backdrop" data-dash-close hidden></div>

    <aside class="dash-sidebar" id="dash-sidebar">
        <a class="brand dash-brand" href="/"><img src="{{ asset('jobagent-mark.svg') }}" alt=""><span>jobagent<span class="brand-dot">.</span></span></a>
        <div class="dash-nav-label">MENU</div>
        <nav class="dash-nav">
            <button class="dash-link dash-link-active" data-goto="overview"><i>▦</i><span>Ringkasan</span></button>
            @if($user->role === 'employer')
                <button class="dash-link" data-goto="jobs"><i>▣</i><span>Lowongan saya</span><em class="dash-link-badge" id="badge-jobs" hidden>0</em></button>
            @endif
            <button class="dash-link" data-goto="applications"><i>{{ $user->role === 'employer' ? '♧' : '↗' }}</i><span>{{ $user->role === 'employer' ? 'Lamaran masuk' : 'Lamaran saya' }}</span><em class="dash-link-badge" id="badge-applications" hidden>0</em></button>
            @if($user->role === 'candidate')
                <button class="dash-link" data-goto="cv"><i>✎</i><span>CV online</span><em class="dash-completion-badge" id="badge-cv">{{ $cvCompletion }}%</em></button>
            @endif
            <button class="dash-link" data-goto="profile"><i>◉</i><span>{{ $user->role === 'employer' ? 'Profil perusahaan' : 'Profil' }}</span></button>
        </nav>
        <div class="dash-nav-label dash-nav-label-foot">AKUN</div>
        <nav class="dash-nav">
            <a class="dash-link" href="/"><i>↗</i><span>Lihat situs</span></a>
            <button class="dash-link" data-dash-logout><i>⏻</i><span>Keluar</span></button>
        </nav>
        <div class="dash-sidebar-card">
            <div class="dash-sidebar-avatar">
                @if($user->role === 'employer' && $companyLogoUrl)<img src="{{ $companyLogoUrl }}" alt="" loading="lazy">@elseif($avatarUrl)<img src="{{ $avatarUrl }}" alt="" loading="lazy">@else{{ mb_substr($user->name, 0, 1) }}@endif
            </div>
            <div class="dash-sidebar-user"><strong>{{ $user->name }}</strong><span>{{ $user->role === 'employer' ? ($user->company_name ?: 'Perusahaan') : ($user->headline ?: 'Pencari kerja') }}</span></div>
        </div>
    </aside>

    <main class="dash-main">
        <header class="dash-topbar">
            <button class="dash-menu-toggle" data-dash-toggle aria-label="Buka menu"><span></span><span></span><span></span></button>
            <div class="dash-topbar-title"><span class="section-kicker" id="dash-kicker">OVERVIEW</span><h1 id="dash-title">Ringkasan</h1></div>
            <div class="dash-topbar-right">
                <span class="dash-date">{{ now()->translatedFormat('l, d F Y') }}</span>
                <button class="dash-top-avatar" data-goto="profile" title="Buka profil">
                    @if($user->role === 'employer' && $companyLogoUrl)<img src="{{ $companyLogoUrl }}" alt="">@elseif($avatarUrl)<img src="{{ $avatarUrl }}" alt="">@else{{ mb_substr($user->name, 0, 1) }}@endif
                </button>
            </div>
        </header>

        <section class="dash-hero">
            <div class="dash-hero-orb orb-a"></div><div class="dash-hero-orb orb-b"></div>
            <div class="dash-hero-copy">
                <span class="dash-hero-kicker">{{ $user->role === 'employer' ? 'WORKSPACE PERUSAHAAN' : 'WORKSPACE KANDIDAT' }}</span>
                <h2>Selamat datang, {{ explode(' ', trim($user->name))[0] }}.</h2>
                <p>{{ $user->role === 'employer' ? 'Kelola lowongan, tinjau kandidat, dan temukan orang terbaik untuk timmu.' : 'Pantau lamaranmu, lengkapi CV online, dan temukan langkah karier berikutnya.' }}</p>
                @if($user->role === 'candidate')
                    <div class="dash-hero-progress">
                        <div class="dash-progress-track"><span id="hero-progress-bar" style="width: {{ $cvCompletion }}%"></span></div>
                        <div class="dash-progress-meta"><strong id="hero-progress-label">CV {{ $cvCompletion }}% lengkap</strong><button data-goto="cv">{{ $cvCompletion >= 100 ? 'Tinjau CV' : 'Lengkapi sekarang' }} →</button></div>
                    </div>
                @else
                    <div class="dash-hero-actions"><button class="button button-light" data-open-job>＋ Pasang lowongan baru</button></div>
                @endif
            </div>
            <div class="dash-hero-art" aria-hidden="true">
                <div class="dash-hero-ring r1"></div><div class="dash-hero-ring r2"></div>
                <div class="dash-hero-sphere"><span>{{ $user->role === 'employer' ? '▣' : 'J.' }}</span></div>
                <div class="dash-hero-chip chip-a">{{ $user->role === 'employer' ? 'Talenta terbaik' : 'Peluang baru' }} ✳</div>
                <div class="dash-hero-chip chip-b"><span class="live-dot"></span> {{ $user->role === 'employer' ? 'Seleksi berjalan' : 'Progres karier' }}</div>
            </div>
        </section>

        <section class="dash-section" data-section="overview">
            <div class="dash-stat-grid" id="dash-stats">
                @for($i = 0; $i < 4; $i++)
                    <article class="stat-card dash-stat-skeleton"><span>&nbsp;</span><strong>&nbsp;</strong><small>&nbsp;</small></article>
                @endfor
            </div>
            <div class="dash-two-col">
                <div class="dash-panel">
                    <div class="dash-panel-head"><div><span class="section-kicker">AKTIVITAS TERBARU</span><h2>{{ $user->role === 'employer' ? 'Lamaran terbaru masuk' : 'Lamaran terbaru' }}</h2></div><button class="dash-panel-link" data-goto="applications">Lihat semua →</button></div>
                    <div class="dash-list" id="dash-recent-applications"><div class="dash-empty">Memuat lamaran...</div></div>
                </div>
                <div class="dash-panel">
                    <div class="dash-panel-head"><div><span class="section-kicker">{{ $user->role === 'employer' ? 'LOWONGANMU' : 'REKOMENDASI UNTUKMU' }}</span><h2>{{ $user->role === 'employer' ? 'Lowongan terbaru' : 'Lowongan baru' }}</h2></div>
                        @if($user->role === 'employer')<button class="dash-panel-link" data-goto="jobs">Kelola →</button>@endif
                    </div>
                    <div class="dash-list" id="dash-recent-jobs"><div class="dash-empty">Memuat lowongan...</div></div>
                </div>
            </div>
        </section>

        <section class="dash-section" data-section="applications" hidden>
            <div class="dash-panel">
                <div class="dash-panel-head"><div><span class="section-kicker">{{ $user->role === 'employer' ? 'SELEKSI KANDIDAT' : 'PROGRES LAMARAN' }}</span><h2>{{ $user->role === 'employer' ? 'Lamaran masuk' : 'Lamaran saya' }}</h2><p>{{ $user->role === 'employer' ? 'Perbarui status seleksi untuk setiap kandidat.' : 'Pantau perkembangan setiap lamaran yang kamu kirim.' }}</p></div></div>
                <div class="dash-app-list" id="dash-applications"><div class="dash-empty">Memuat lamaran...</div></div>
            </div>
        </section>

        @if($user->role === 'employer')
            <section class="dash-section" data-section="jobs" hidden>
                <div class="dash-panel">
                    <div class="dash-panel-head"><div><span class="section-kicker">MANAJEMEN LOWONGAN</span><h2>Lowongan saya</h2><p>Buat, perbarui, tutup, atau hapus lowongan yang kamu publikasikan.</p></div><button class="button button-small" data-open-job>＋ Pasang lowongan</button></div>
                    <div class="dash-jobs-grid" id="dash-jobs-list"><div class="dash-empty">Memuat lowongan...</div></div>
                </div>
            </section>
        @endif

        @if($user->role === 'candidate')
            <section class="dash-section" data-section="cv" hidden>
                <div class="dash-cv-layout">
                    <form class="dash-panel" id="cv-form">
                        <div class="dash-panel-head"><div><span class="section-kicker">PROFIL PUBLIK</span><h2>CV online</h2><p>Lengkapi data kariermu — ditampilkan kepada perusahaan yang melihat profilmu.</p></div><span class="dash-completion-pill" id="cv-completion-pill">{{ $cvCompletion }}% lengkap</span></div>

                        <label class="dash-field">Ringkasan diri <span class="optional-label">opsional</span><textarea name="summary" rows="4" maxlength="2000" placeholder="Ceritakan pengalaman, keahlian utama, dan apa yang kamu cari dalam karier...">{{ $cv->summary }}</textarea></label>

                        <div class="dash-field"><span class="dash-field-label">Keahlian <span class="optional-label">minimal 3 rekomendasi</span></span>
                            <div class="dash-chips" id="skill-chips">
                                @foreach($cv->skills ?? [] as $skill)<span class="dash-chip">{{ $skill }}<button type="button" data-remove-skill="{{ $skill }}" aria-label="Hapus {{ $skill }}">×</button></span>@endforeach
                            </div>
                            <div class="dash-chip-input"><input id="skill-input" maxlength="60" placeholder="Tambahkan keahlian, tekan Enter" list="skill-suggestions"><datalist id="skill-suggestions">@foreach(['JavaScript','TypeScript','React','Vue.js','Laravel','PHP','Python','Java','Kotlin','Flutter','UI/UX Design','Figma','Data Analysis','SQL','Digital Marketing','SEO','Project Management','Public Speaking','Copywriting','Customer Service'] as $s)<option value="{{ $s }}">@endforeach</datalist><button type="button" id="skill-add" class="button button-small">Tambah</button></div>
                        </div>

                        <div class="dash-field"><span class="dash-field-label">Pengalaman kerja</span>
                            <div class="dash-repeat" id="cv-experiences">
                                @foreach($cv->experiences ?? [] as $index => $experience)
                                    <div class="dash-repeat-item" data-repeat="experience">
                                        <div class="dash-repeat-head"><strong>Pengalaman {{ $index + 1 }}</strong><button type="button" data-remove-repeat class="dash-repeat-remove" aria-label="Hapus pengalaman">×</button></div>
                                        <label>Jabatan<input name="title" maxlength="120" value="{{ $experience['title'] ?? '' }}" placeholder="Contoh: Product Designer" required></label>
                                        <div class="form-row"><label>Perusahaan<input name="company" maxlength="120" value="{{ $experience['company'] ?? '' }}" placeholder="Nama perusahaan" required></label><label>Lokasi<input name="location" maxlength="100" value="{{ $experience['location'] ?? '' }}" placeholder="Kota / Remote" data-autocomplete="location" autocomplete="off"></label></div>
                                        <div class="form-row"><label>Mulai<input name="start" maxlength="20" value="{{ $experience['start'] ?? '' }}" placeholder="Contoh: Jan 2022"></label><label>Selesai <span class="optional-label">kosongkan bila masih berjalan</span><input name="end" maxlength="20" value="{{ $experience['end'] ?? '' }}" placeholder="Contoh: Des 2024"></label></div>
                                        <label>Deskripsi<textarea name="description" rows="2" maxlength="1000" placeholder="Tanggung jawab dan pencapaian...">{{ $experience['description'] ?? '' }}</textarea></label>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="dash-add-button" data-add-repeat="experience">＋ Tambah pengalaman</button>
                        </div>

                        <div class="dash-field"><span class="dash-field-label">Pendidikan</span>
                            <div class="dash-repeat" id="cv-educations">
                                @foreach($cv->educations ?? [] as $index => $education)
                                    <div class="dash-repeat-item" data-repeat="education">
                                        <div class="dash-repeat-head"><strong>Pendidikan {{ $index + 1 }}</strong><button type="button" data-remove-repeat class="dash-repeat-remove" aria-label="Hapus pendidikan">×</button></div>
                                        <label>Institusi<input name="school" maxlength="140" value="{{ $education['school'] ?? '' }}" placeholder="Contoh: Universitas Indonesia" required></label>
                                        <label>Jurusan / gelar<input name="major" maxlength="120" value="{{ $education['major'] ?? '' }}" placeholder="Contoh: Teknik Informatika"></label>
                                        <div class="form-row"><label>Mulai<input name="start" maxlength="20" value="{{ $education['start'] ?? '' }}" placeholder="2018"></label><label>Selesai<input name="end" maxlength="20" value="{{ $education['end'] ?? '' }}" placeholder="2022 / Sekarang"></label></div>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="dash-add-button" data-add-repeat="education">＋ Tambah pendidikan</button>
                        </div>

                        <div class="dash-field"><span class="dash-field-label">Tautan</span>
                            <div class="dash-repeat" id="cv-links">
                                @foreach($cv->links ?? [] as $link)
                                    <div class="dash-repeat-item dash-link-item" data-repeat="link">
                                        <div class="dash-repeat-head"><strong>Tautan</strong><button type="button" data-remove-repeat class="dash-repeat-remove" aria-label="Hapus tautan">×</button></div>
                                        <div class="form-row"><label>Jenis<input name="label" maxlength="60" value="{{ $link['label'] ?? '' }}" placeholder="Portfolio / LinkedIn / GitHub" required></label><label>URL<input name="url" type="url" maxlength="300" value="{{ $link['url'] ?? '' }}" placeholder="https://..." required></label></div>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="dash-add-button" data-add-repeat="link">＋ Tambah tautan</button>
                        </div>

                        <div class="dash-field"><span class="dash-field-label">Dokumen CV</span>
                            <div class="dash-cv-doc" id="cv-doc">
                                @if($cvUrl)
                                    <div class="dash-cv-doc-ready"><span class="dash-cv-doc-icon">PDF</span><div><strong>CV terunggah</strong><a href="{{ $cvUrl }}" target="_blank" rel="noopener">Lihat dokumen →</a></div><button type="button" class="dash-cv-doc-delete" data-cv-delete>Hapus</button></div>
                                    <input type="file" id="cv-doc-input" accept="application/pdf" hidden>
                                @else
                                    <label class="dash-cv-doc-empty" for="cv-doc-input"><span class="dash-cv-doc-icon">↑</span><div><strong>Unggah CV (PDF)</strong><small>Maks. 5 MB · tampil di profil publikmu</small></div><input type="file" id="cv-doc-input" accept="application/pdf"></label>
                                @endif
                            </div>
                        </div>

                        <p class="form-error" id="cv-error" hidden></p>
                        <div class="dash-form-actions"><button class="button" id="cv-submit">Simpan CV online <span>→</span></button></div>
                    </form>

                    <aside class="dash-cv-preview" id="cv-preview">
                        <div class="dash-cv-preview-head"><span class="section-kicker">PRATINJAU</span><span class="dash-live-pill">● live</span></div>
                        <div class="dash-cv-doc-card">
                            <div class="dash-cv-person">
                                <div class="dash-cv-avatar">@if($avatarUrl)<img src="{{ $avatarUrl }}" alt="">@else{{ mb_substr($user->name, 0, 1) }}@endif</div>
                                <div><strong id="preview-name">{{ $user->name }}</strong><span id="preview-headline">{{ $user->headline ?: 'Bidang karier belum diisi' }}</span><small id="preview-meta">{{ collect([$user->location, $user->phone])->filter()->implode(' · ') ?: 'Lokasi & kontak belum diisi' }}</small></div>
                            </div>
                            <div class="dash-cv-block" id="preview-summary-block"><h4>Tentang saya</h4><p id="preview-summary">Belum ada ringkasan diri.</p></div>
                            <div class="dash-cv-block" id="preview-skills-block"><h4>Keahlian</h4><div class="dash-cv-skills" id="preview-skills"></div></div>
                            <div class="dash-cv-block" id="preview-experiences-block"><h4>Pengalaman</h4><div id="preview-experiences"></div></div>
                            <div class="dash-cv-block" id="preview-educations-block"><h4>Pendidikan</h4><div id="preview-educations"></div></div>
                            <div class="dash-cv-block" id="preview-links-block"><h4>Tautan</h4><div class="dash-cv-links" id="preview-links"></div></div>
                        </div>
                    </aside>
                </div>
            </section>
        @endif

        <section class="dash-section" data-section="profile" hidden>
            <div class="dash-two-col">
                <form class="dash-panel" id="profile-form">
                    <div class="dash-panel-head"><div><span class="section-kicker">DATA AKUN</span><h2>{{ $user->role === 'employer' ? 'Profil perusahaan' : 'Profil saya' }}</h2><p>Perbarui data yang ditampilkan kepada {{ $user->role === 'employer' ? 'kandidat' : 'perusahaan' }}.</p></div></div>
                    <div class="dash-profile-photo">
                        <div class="dash-profile-photo-preview">
                            @if($user->role === 'employer' && $companyLogoUrl)<img src="{{ $companyLogoUrl }}" alt="Logo perusahaan" id="profile-photo-img">@elseif($avatarUrl)<img src="{{ $avatarUrl }}" alt="Foto profil" id="profile-photo-img">@else<span id="profile-photo-initial">{{ mb_substr($user->name, 0, 1) }}</span>@endif
                        </div>
                        <label class="dash-profile-photo-upload"><span>Ubah {{ $user->role === 'employer' ? 'logo' : 'foto' }}</span><input type="file" id="profile-photo-input" name="{{ $user->role === 'employer' ? 'companyLogo' : 'avatarPhoto' }}" accept="image/png,image/jpeg,image/webp" hidden></label>
                    </div>
                    <label class="dash-field">{{ $user->role === 'employer' ? 'Nama penanggung jawab' : 'Nama lengkap' }}<input name="name" required minlength="2" maxlength="100" value="{{ $user->name }}"></label>
                    @if($user->role === 'employer')
                        <label class="dash-field">Nama perusahaan<input name="company_name" required minlength="2" maxlength="120" value="{{ $user->company_name }}"></label>
                        <div class="form-row"><label class="dash-field">Industri<input name="industry" maxlength="100" value="{{ $user->industry }}" placeholder="Contoh: Teknologi"></label><label class="dash-field">Ukuran tim<select name="company_size"><option value="">Pilih ukuran</option>@foreach(['1–10 orang','11–50 orang','51–200 orang','201+ orang'] as $size)<option value="{{ $size }}" @selected($user->company_size === $size)>{{ $size }}</option>@endforeach</select></label></div>
                        <label class="dash-field">Situs perusahaan <span class="optional-label">opsional</span><input name="website" type="url" maxlength="200" value="{{ $user->website }}" placeholder="https://..."></label>
                    @else
                        <div class="form-row"><label class="dash-field">Nomor telepon<input name="phone" maxlength="30" value="{{ $user->phone }}" placeholder="+62 812..."></label><label class="dash-field">Lokasi<input name="location" maxlength="100" value="{{ $user->location }}" placeholder="Kota domisili" data-autocomplete="location" autocomplete="off"></label></div>
                        <label class="dash-field">Bidang yang diminati<input name="headline" maxlength="120" value="{{ $user->headline }}" placeholder="Contoh: Product Designer" data-autocomplete="headline" autocomplete="off"></label>
                    @endif
                    <p class="form-error" id="profile-error" hidden></p>
                    <div class="dash-form-actions"><button class="button" id="profile-submit">Simpan perubahan <span>→</span></button></div>
                </form>
                <aside class="dash-panel dash-panel-soft">
                    <div class="dash-panel-head"><div><span class="section-kicker">INFORMASI AKUN</span><h2>Detail login</h2></div></div>
                    <div class="dash-info-rows">
                        <div><span>Email</span><strong>{{ $user->email }}</strong></div>
                        <div><span>Jenis akun</span><strong>{{ ['candidate' => 'Pencari kerja', 'employer' => 'Perusahaan'][$user->role] ?? $user->role }}</strong></div>
                        <div><span>Status email</span><strong class="dash-info-ok">{{ $user->email_verified_at ? '✓ Terverifikasi' : 'Menunggu verifikasi' }}</strong></div>
                        <div><span>Terdaftar</span><strong>{{ $user->created_at?->format('d M Y') }}</strong></div>
                    </div>
                    <p class="dash-info-note">⌑ Email dan kata sandi dikelola oleh sistem autentikasi JobAgent. Perubahan data lainnya dapat dilakukan melalui formulir di samping.</p>
                </aside>
            </div>
        </section>

        <footer class="dash-footer"><span>JobAgent {{ $user->role === 'employer' ? 'Employer Workspace' : 'Career Workspace' }} · © {{ date('Y') }} JobAgent</span><a href="/">Kembali ke beranda →</a></footer>
    </main>

    <nav class="dash-bottomnav">
        <button class="dash-bottom-link bottom-active" data-goto="overview"><i>▦</i><span>Ringkasan</span></button>
        @if($user->role === 'employer')<button class="dash-bottom-link" data-goto="jobs"><i>▣</i><span>Lowongan</span></button>@endif
        <button class="dash-bottom-link" data-goto="applications"><i>{{ $user->role === 'employer' ? '♧' : '↗' }}</i><span>{{ $user->role === 'employer' ? 'Masuk' : 'Lamaran' }}</span></button>
        @if($user->role === 'candidate')<button class="dash-bottom-link" data-goto="cv"><i>✎</i><span>CV</span></button>@endif
        <button class="dash-bottom-link" data-goto="profile"><i>◉</i><span>Profil</span></button>
    </nav>

    @if($user->role === 'employer')
        <div class="modal-backdrop" id="job-modal" hidden><section class="auth-modal job-modal" role="dialog" aria-modal="true" aria-labelledby="dash-job-modal-title"><button class="modal-close" aria-label="Tutup" data-close>×</button><div class="auth-heading"><span class="section-kicker" id="dash-job-modal-kicker">UNTUK PERUSAHAAN</span><h2 id="dash-job-modal-title">Pasang lowongan.</h2><p id="dash-job-modal-desc">Ceritakan posisi yang sedang ingin kamu isi.</p></div><form class="auth-form" id="dash-job-form"><input type="hidden" name="id"><label>Nama posisi<input name="title" required minlength="3" maxlength="120" placeholder="Contoh: Senior Product Designer"></label><div class="form-row"><label>Lokasi<input name="location" required maxlength="120" placeholder="Kota atau Remote" data-autocomplete="location" autocomplete="off"></label><label>Tipe pekerjaan<select name="type">@foreach(['Full-time','Part-time','Contract','Internship','Remote'] as $type)<option>{{ $type }}</option>@endforeach</select></label></div><div class="form-row"><label>Bidang<select name="category">@foreach(['Teknologi','Desain','Pemasaran','Bisnis','Operasional'] as $category)<option>{{ $category }}</option>@endforeach</select></label><label>Rentang gaji<input name="salary" maxlength="80" placeholder="Contoh: Rp 10–15 juta"></label></div><label>Deskripsi posisi<textarea name="description" required minlength="30" maxlength="5000" rows="4" placeholder="Ceritakan tanggung jawab dan kualifikasi utama..."></textarea></label><label>Status lowongan<select name="status"><option value="active">Aktif — menerima lamaran</option><option value="closed">Ditutup — tidak menerima lamaran</option></select></label><p class="form-error" id="dash-job-error" hidden></p><button class="button auth-submit" id="dash-job-submit">Publikasikan lowongan <span>→</span></button></form></section></div>
    @endif

    <div class="modal-backdrop" id="confirm-modal" hidden><section class="auth-modal confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title"><div class="auth-heading"><span class="section-kicker">KONFIRMASI</span><h2 id="confirm-title">Hapus lowongan?</h2><p id="confirm-copy">Lowongan dan seluruh lamarannya akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.</p></div><div class="confirm-actions"><button class="ghost-button" data-confirm-cancel>Batal</button><button class="button confirm-danger" data-confirm-ok>Ya, hapus</button></div></section></div>

    <div class="toast" id="dash-toast" hidden><span class="toast-check">✓</span><span id="dash-toast-message"></span></div>

    <script>window.JobAgent = {config: @json($firebaseConfig), user: @json(['id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'role'=>$user->role,'headline'=>$user->headline,'location'=>$user->location,'phone'=>$user->phone]), cv: @json(['summary'=>$cv->summary ?? null,'skills'=>$cv->skills ?? [],'experiences'=>$cv->experiences ?? [],'educations'=>$cv->educations ?? [],'links'=>$cv->links ?? []]), avatarUrl: @json($avatarUrl)};</script>
    <script src="{{ asset('js/smart-fields.js') }}" defer></script>
    <script src="{{ asset('js/dashboard.js') }}" defer></script>
</body>
</html>
