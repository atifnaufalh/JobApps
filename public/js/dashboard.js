(() => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
  const role = document.body.dataset.role;
  const isEmployer = role === "employer";

  const state = {
    stats: [],
    applications: [],
    jobs: [],
    suggestedJobs: [],
    publicJobs: [],
    appliedJobIds: new Set(),
    selectedJobId: null,
    jdFilter: "all",
    jdQuery: "",
    appFilter: "all",
    cvCompletion: 0,
    loaded: false,
    loading: false,
  };

  const STATUS = {
    submitted: "Baru masuk",
    reviewing: "Sedang ditinjau",
    interview: "Wawancara",
    rejected: "Tidak dilanjutkan",
    hired: "Diterima",
  };

  const TITLES = {
    overview: { kicker: "OVERVIEW", title: "Ringkasan" },
    browse: { kicker: "CARI LOWONGAN", title: "Cari lowongan" },
    applications: { kicker: isEmployer ? "SELEKSI KANDIDAT" : "PROGRES LAMARAN", title: isEmployer ? "Lamaran masuk" : "Lamaran saya" },
    jobs: { kicker: "MANAJEMEN LOWONGAN", title: "Lowongan saya" },
    cv: { kicker: "PROFIL PUBLIK", title: "CV online" },
    profile: { kicker: "DATA AKUN", title: isEmployer ? "Profil perusahaan" : "Profil saya" },
  };

  const APP_CLOSED = ["rejected", "hired"];

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (character) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;",
    })[character]);
  }

  function formatDate(value) {
    return value ? new Date(value).toLocaleDateString("id-ID", { day: "numeric", month: "long", year: "numeric" }) : "—";
  }

  function toast(message, isError = false) {
    const el = $("#dash-toast");
    $("#dash-toast-message").textContent = message;
    el.classList.toggle("dash-toast-error", isError);
    el.hidden = false;
    window.clearTimeout(toast._timer);
    toast._timer = window.setTimeout(() => { el.hidden = true; }, 3000);
  }

  async function api(url, { method = "GET", body = null, formData = false } = {}) {
    const headers = { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": csrfToken };
    if (body !== null && !formData) headers["Content-Type"] = "application/json";
    const response = await fetch(url, {
      method,
      headers,
      body: formData ? body : body !== null ? JSON.stringify(body) : null,
    });
    const result = await response.json().catch(() => ({}));
    if (response.status === 401) {
      window.location.assign("/?login=account");
      throw new Error("Sesi berakhir.");
    }
    if (!response.ok) throw new Error(result.error || result.message || "Permintaan gagal.");
    return result;
  }

  let renderCvPreview = null;

  /* ── Navigation ─────────────────────────── */
  function showSection(name, { updateHash = true } = {}) {
    if (!TITLES[name] || (name === "jobs" && !isEmployer) || (name === "cv" && isEmployer) || (name === "browse" && isEmployer)) name = "overview";
    $$(".dash-section").forEach((section) => { section.hidden = section.dataset.section !== name; });
    $$(".dash-link[data-goto]").forEach((link) => link.classList.toggle("dash-link-active", link.dataset.goto === name));
    $$(".dash-bottom-link").forEach((link) => link.classList.toggle("bottom-active", link.dataset.goto === name));
    $("#dash-kicker").textContent = TITLES[name].kicker;
    $("#dash-title").textContent = TITLES[name].title;
    if (updateHash) history.replaceState(null, "", `#${name}`);
    closeDrawer();
    window.scrollTo({ top: 0, behavior: "smooth" });
    if (name === "overview" || name === "browse") loadDashboard();
    if (name === "browse") requestAnimationFrame(renderJobBoard);
    if (name === "cv" && renderCvPreview) renderCvPreview();
  }

  function openDrawer() {
    document.body.classList.add("dash-nav-open");
    const backdrop = $(".dash-backdrop");
    backdrop.hidden = false;
    requestAnimationFrame(() => backdrop.classList.add("dash-show"));
  }

  function closeDrawer() {
    document.body.classList.remove("dash-nav-open");
    const backdrop = $(".dash-backdrop");
    backdrop.classList.remove("dash-show");
    window.setTimeout(() => { if (!document.body.classList.contains("dash-nav-open")) backdrop.hidden = true; }, 250);
  }

  document.addEventListener("click", (event) => {
    const goto = event.target.closest("[data-goto]");
    if (goto) { showSection(goto.dataset.goto); return; }
    if (event.target.closest("[data-dash-toggle]")) {
      document.body.classList.contains("dash-nav-open") ? closeDrawer() : openDrawer();
      return;
    }
    if (event.target.closest("[data-dash-close]")) closeDrawer();
  });

  /* ── Logout ─────────────────────────────── */
  $$("[data-dash-logout]").forEach((button) => button.addEventListener("click", async () => {
    button.disabled = true;
    try {
      const [{ getApps, initializeApp }, { getAuth, signOut }] = await Promise.all([
        import("https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js"),
        import("https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js"),
      ]);
      const firebaseApp = getApps().find((app) => app.name === "jobagent-browser")
        || initializeApp(window.JobAgent.config, "jobagent-browser");
      await signOut(getAuth(firebaseApp));
    } catch (error) {
      console.warn("Firebase sign-out failed; ending the session anyway.", error);
    }
    try {
      await api("/api/auth/logout", { method: "POST" });
      window.location.assign("/");
    } catch (error) {
      button.disabled = false;
      toast(error.message, true);
    }
  }));

  /* ── Dashboard data ─────────────────────── */
  function renderStats() {
    const grid = $("#dash-stats");
    if (!grid) return;
    grid.innerHTML = state.stats.map((stat) => `
      <article class="stat-card">
        <span>${escapeHtml(stat.label)}</span>
        <strong data-count-to="${Number(stat.value) || 0}">0</strong>
        <small>${escapeHtml(stat.note)}</small>
        <i>${escapeHtml(stat.icon)}</i>
      </article>`).join("");
    animateCounters(grid);
  }

  /* Counter halus untuk angka statistik */
  function animateCounters(root) {
    const prefersReduced = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
    $$("[data-count-to]", root).forEach((node) => {
      const target = Number(node.dataset.countTo) || 0;
      if (prefersReduced || target === 0) {
        node.textContent = target.toLocaleString("id-ID");
        return;
      }
      const duration = 620;
      const start = performance.now();
      const step = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        node.textContent = Math.round(target * eased).toLocaleString("id-ID");
        if (progress < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    });
  }

  function updateProfileCounts() {
    $$("[data-dash-count]").forEach((node) => {
      const key = node.dataset.dashCount;
      const value = key === "applications" ? state.applications.length : state.jobs.length;
      node.textContent = value.toLocaleString("id-ID");
    });
  }

  const APPLICATION_STEPS = [
    { key: "submitted", label: "Terkirim" },
    { key: "reviewing", label: "Ditinjau" },
    { key: "interview", label: "Wawancara" },
    { key: "hired", label: "Diterima" },
  ];

  function applicationSteps(application) {
    const order = ["submitted", "reviewing", "interview", "hired"];
    const rejected = application.status === "rejected";
    const current = order.indexOf(application.status);
    return `<div class="dash-steps ${rejected ? "is-rejected" : ""}">${APPLICATION_STEPS.map((step, index) => {
      const done = !rejected && current >= index;
      const active = !rejected && current === index;
      return `<span class="dash-step ${done ? "is-done" : ""} ${active ? "is-active" : ""}">
        <i>${done ? "✓" : index + 1}</i><em>${escapeHtml(step.label)}</em>
      </span>`;
    }).join("")}<span class="dash-step-note">${rejected ? "Lamaran tidak dilanjutkan" : escapeHtml(STATUS[application.status] || "Dalam proses")}</span></div>`;
  }

  function applicationCard(application, compact = false) {
    const status = `<span class="dash-status status-${escapeHtml(application.status)}">${escapeHtml(STATUS[application.status] || "Dalam proses")}</span>`;
    if (compact) {
      const detail = isEmployer
        ? `${escapeHtml(application.candidate?.name || "Kandidat")} · ${escapeHtml(application.jobTitle)}`
        : `${escapeHtml(application.companyName)} · ${escapeHtml(application.location || "")}`;
      return `<div class="dash-list-item">
        <span class="dash-list-icon">${isEmployer ? "♧" : "↗"}</span>
        <div class="dash-list-copy"><strong>${escapeHtml(application.jobTitle)}</strong><span>${detail} · ${formatDate(application.appliedAt)}</span></div>
        <div class="dash-list-side">${status}</div>
      </div>`;
    }

    const person = isEmployer
      ? `<div class="dash-app-person">
            <strong>${escapeHtml(application.candidate?.name || "Tanpa nama")}</strong>
            <a href="mailto:${encodeURIComponent(application.candidate?.email || "")}">${escapeHtml(application.candidate?.email || "")}</a>
            <span>${escapeHtml(application.candidate?.headline || "Profil belum dilengkapi")}</span>
            <span>${escapeHtml([application.candidate?.location, application.candidate?.phone].filter(Boolean).join(" · ") || "Detail kontak belum tersedia")}</span>
         </div>`
      : `<div class="dash-app-person">
            <strong>${escapeHtml(application.companyName)}</strong>
            <span>${escapeHtml(application.location || "")} · ${escapeHtml(application.type || "")}</span>
         </div>`;

    const control = isEmployer
      ? `<div class="dash-list-side">
            <label class="dash-status-control">Status<select data-status-for="${Number(application.id)}">${Object.entries(STATUS).map(([value, label]) => `<option value="${value}"${application.status === value ? " selected" : ""}>${label}</option>`).join("")}</select></label>
            <button class="dash-save-status" data-save-status="${Number(application.id)}">Simpan</button>
         </div>`
      : `<div class="dash-list-side">${status}</div>`;

    return `<article class="dash-app-card" data-application="${Number(application.id)}">
        <div class="dash-app-head">
            <div><span class="section-kicker">${isEmployer ? "KANDIDAT MELAMAR" : "POSISI YANG DILAMAR"}</span><h3>${escapeHtml(application.jobTitle)}</h3></div>
            ${control}
        </div>
        ${person}
        ${isEmployer ? "" : applicationSteps(application)}
        <div class="dash-app-foot"><span>Dikirim ${formatDate(application.appliedAt)}</span><span>Diperbarui ${formatDate(application.updatedAt)}</span></div>
    </article>`;
  }

  function renderApplications() {
    const list = $("#dash-applications");
    if (list) {
      const filtered = state.applications.filter((application) => {
        if (state.appFilter === "active") return !APP_CLOSED.includes(application.status);
        if (state.appFilter === "closed") return APP_CLOSED.includes(application.status);
        return true;
      });
      list.innerHTML = filtered.length
        ? filtered.map((application) => applicationCard(application)).join("")
        : `<div class="dash-empty">${isEmployer ? "Belum ada lamaran masuk untuk lowonganmu." : state.appFilter === "active" ? "Tidak ada lamaran yang sedang diproses." : state.appFilter === "closed" ? "Belum ada lamaran yang selesai." : "Kamu belum mengirim lamaran. Temukan peluang di beranda."}</div>`;
    }
    const recent = $("#dash-recent-applications");
    if (recent) {
      recent.innerHTML = state.applications.length
        ? state.applications.slice(0, 4).map((application) => applicationCard(application, true)).join("")
        : `<div class="dash-empty">${isEmployer ? "Belum ada lamaran masuk." : "Belum ada lamaran. Mulai dari lowongan terbaru."}</div>`;
    }

    const counts = {
      all: state.applications.length,
      active: state.applications.filter((application) => !APP_CLOSED.includes(application.status)).length,
      closed: state.applications.filter((application) => APP_CLOSED.includes(application.status)).length,
    };
    $$("[data-tab-count]").forEach((node) => { node.textContent = counts[node.dataset.tabCount] ?? 0; });

    const badge = $("#badge-applications");
    if (badge) {
      const pending = state.applications.filter((application) => ["submitted", "reviewing"].includes(application.status)).length;
      badge.textContent = isEmployer ? pending : state.applications.length;
      badge.hidden = state.applications.length === 0;
    }
  }

  function jobCardFull(job) {
    return `<article class="dash-job-card ${job.status === "closed" ? "job-closed" : ""}">
        <div class="dash-job-top">
            <div><span class="section-kicker">${escapeHtml(job.category || "Karier pilihan")}</span><h3>${escapeHtml(job.title)}</h3></div>
            <span class="dash-status status-${escapeHtml(job.status)}">${job.status === "active" ? "Aktif" : "Ditutup"}</span>
        </div>
        <div class="dash-job-meta"><span>⌖ ${escapeHtml(job.location)}</span><span>▣ ${escapeHtml(job.type)}</span>${job.salary ? `<span>◎ ${escapeHtml(job.salary)}</span>` : ""}</div>
        <p class="dash-job-desc">${escapeHtml(job.description)}</p>
        <div class="dash-job-stats"><span><strong>${Number(job.applicationsCount).toLocaleString("id-ID")}</strong> lamaran</span><span>Dipublikasikan <strong>${formatDate(job.createdAt)}</strong></span></div>
        <div class="dash-job-actions">
            <button data-edit-job="${Number(job.id)}">✎ Edit</button>
            <button data-toggle-job="${Number(job.id)}">${job.status === "active" ? "⏸ Tutup" : "▶ Buka"}</button>
            <button class="dash-action-danger" data-delete-job="${Number(job.id)}">× Hapus</button>
        </div>
    </article>`;
  }

  function jobListItem(job, actionHtml = "") {
    return `<div class="dash-list-item">
        <span class="dash-list-icon">${job.status === "closed" ? "▤" : "▣"}</span>
        <div class="dash-list-copy"><strong>${escapeHtml(job.title)}</strong><span>${escapeHtml(job.location)} · ${escapeHtml(job.type)}${job.salary ? ` · ${escapeHtml(job.salary)}` : ""}</span></div>
        <div class="dash-list-side">${actionHtml}</div>
    </div>`;
  }

  function renderJobs() {
    const list = $("#dash-jobs-list");
    if (list) {
      list.innerHTML = state.jobs.length
        ? state.jobs.map(jobCardFull).join("")
        : `<div class="dash-empty">Belum ada lowongan. Mulai dengan mempublikasikan lowongan pertamamu.</div>`;
    }

    const badge = $("#badge-jobs");
    if (badge) {
      const active = state.jobs.filter((job) => job.status === "active").length;
      badge.textContent = active;
      badge.hidden = state.jobs.length === 0;
    }

    const recent = $("#dash-recent-jobs");
    if (isEmployer) {
      recent.innerHTML = state.jobs.length
        ? state.jobs.slice(0, 4).map((job) => jobListItem(job, `<span class="dash-status status-${escapeHtml(job.status)}">${job.applicationsCount} lamaran</span>`)).join("")
        : `<div class="dash-empty">Belum ada lowongan. Klik "Pasang lowongan" untuk memulai.</div>`;
    } else {
      recent.innerHTML = state.suggestedJobs.length
        ? state.suggestedJobs.slice(0, 4).map((job) => jobListItem(job, `<button class="dash-save-status" data-apply="${Number(job.id)}">Lamar ↗</button>`)).join("")
        : `<div class="dash-empty">Belum ada lowongan aktif saat ini.</div>`;
    }
  }

  function applyCompletion(completion) {
    state.cvCompletion = completion;
    const bar = $("#hero-progress-bar");
    const label = $("#hero-progress-label");
    const pill = $("#cv-completion-pill");
    const badge = $("#badge-cv");
    const ring = $("#profile-ring");
    const ringValue = $("#profile-ring-value");
    if (bar) bar.style.width = `${completion}%`;
    if (label) label.textContent = `CV ${completion}% lengkap`;
    if (pill) pill.textContent = `${completion}% lengkap`;
    if (badge) badge.textContent = `${completion}%`;
    if (ring) ring.style.setProperty("--pct", completion);
    if (ringValue) ringValue.innerHTML = `${completion}<small>%</small>`;
  }

  /* ======================= Job board ala LinkedIn (kandidat) ======================= */
  function relativeAge(value) {
    if (!value) return "Baru saja";
    const days = Math.floor((Date.now() - new Date(value).getTime()) / 86400000);
    if (days < 1) return "Hari ini";
    return days === 1 ? "Kemarin" : `${days} hari lalu`;
  }

  function boardJobs() {
    const query = state.jdQuery.trim().toLowerCase();
    return state.publicJobs.filter((job) => {
      const applied = state.appliedJobIds.has(Number(job.id));
      if (state.jdFilter === "applied" && !applied) return false;
      if (state.jdFilter === "new" && applied) return false;
      if (!query) return true;
      return [job.title, job.companyName, job.category, job.location, job.description]
        .some((value) => String(value || "").toLowerCase().includes(query));
    });
  }

  function jobRow(job, index) {
    const applied = state.appliedJobIds.has(Number(job.id));
    const letter = escapeHtml((job.companyName || "J").slice(0, 1));
    const logo = job.companyLogo ? `<img src="${escapeHtml(job.companyLogo)}" alt="" loading="lazy">` : letter;
    return `<article class="jd-row ${applied ? "is-applied" : ""} ${state.selectedJobId === Number(job.id) ? "is-active" : ""}" data-jd-select="${Number(job.id)}" style="--i:${index}" tabindex="0" role="button" aria-label="Lihat detail ${escapeHtml(job.title)}">
        <span class="company-mark jd-row-mark">${logo}</span>
        <div class="jd-row-copy">
            <strong>${escapeHtml(job.title)}</strong>
            <span class="jd-row-company">${escapeHtml(job.companyName)}</span>
            <span class="jd-row-meta">⌖ ${escapeHtml(job.location)} · ▣ ${escapeHtml(job.type)}</span>
            <span class="jd-row-foot">${escapeHtml(relativeAge(job.createdAt))}${job.salary ? ` · ${escapeHtml(job.salary)}` : ""}</span>
        </div>
        <div class="jd-row-side">${applied
          ? '<span class="jd-row-tag jd-tag-applied">Sudah dilamar</span>'
          : '<span class="jd-row-tag jd-row-cta">Lamar</span>'}</div>
    </article>`;
  }

  function renderJobBoard() {
    const list = $("#jd-job-list");
    if (!list) return;
    const jobs = boardJobs();
    list.innerHTML = jobs.length
      ? jobs.map(jobRow).join("")
      : '<div class="dash-empty">Tidak ada lowongan yang cocok. Ubah kata kunci atau filter.</div>';

    const counts = {
      all: state.publicJobs.length,
      new: state.publicJobs.filter((job) => !state.appliedJobIds.has(Number(job.id))).length,
      applied: state.appliedJobIds.size,
    };
    $$("[data-jd-count]").forEach((node) => { node.textContent = counts[node.dataset.jdCount] ?? 0; });
    const counter = $("#jd-result-count");
    if (counter) counter.textContent = `${jobs.length} lowongan`;

    const browseBadge = $("#badge-browse");
    if (browseBadge) {
      browseBadge.textContent = counts.new;
      browseBadge.hidden = counts.new === 0;
    }

    if (state.selectedJobId && !jobs.some((job) => Number(job.id) === state.selectedJobId)) {
      state.selectedJobId = null;
    }
    if (!state.selectedJobId && jobs.length) state.selectedJobId = Number(jobs[0].id);
    renderJobDetail();
    $$("[data-jd-select]").forEach((row) => row.classList.toggle("is-active", Number(row.dataset.jdSelect) === state.selectedJobId));
  }

  function jobDetailMarkup(job) {
    const applied = state.appliedJobIds.has(Number(job.id));
    const letter = escapeHtml((job.companyName || "J").slice(0, 1));
    const logo = job.companyLogo ? `<img src="${escapeHtml(job.companyLogo)}" alt="" loading="lazy">` : letter;
    const facts = [
      ["Lokasi", job.location],
      ["Tipe kerja", job.type],
      ["Bidang", job.category || "Karier pilihan"],
      ["Gaji", job.salary || "Gaji kompetitif"],
      ["Dipublikasikan", relativeAge(job.createdAt)],
      ["Pelamar", `${Number(job.applicationsCount) || 0} pelamar`],
    ];
    return `
      <button class="jd-detail-back" data-jd-close type="button">← Kembali ke daftar</button>
      <header class="jd-detail-head">
        <div class="jd-detail-company">
          <span class="company-mark jd-detail-mark">${logo}</span>
          <div>
            <span class="section-kicker">${escapeHtml((job.category || "Karier pilihan").toUpperCase())}</span>
            <h2 id="jd-detail-title">${escapeHtml(job.title)}</h2>
            <p class="jd-detail-sub">${escapeHtml(job.companyName)} · ${escapeHtml(job.location)}</p>
          </div>
        </div>
        <div class="jd-detail-chips">
          <span class="jd-chip">${escapeHtml(job.type)}</span>
          <span class="jd-chip">${escapeHtml(job.salary || "Gaji kompetitif")}</span>
          <span class="jd-chip">${escapeHtml(relativeAge(job.createdAt))}</span>
        </div>
        <div class="jd-detail-actions">
          ${applied
            ? '<span class="jd-detail-done">✓ Lamaran kamu sudah terkirim</span><a class="ghost-button" href="/applications">Lacak lamaran</a>'
            : '<button class="button" data-apply="' + Number(job.id) + '">Lamar sekarang →</button>'}
        </div>
      </header>
      <div class="jd-detail-body">
        <div class="jd-detail-main">
          <h3>Tentang posisi</h3>
          <p class="jd-detail-desc">${escapeHtml(job.description || "Deskripsi posisi belum tersedia.")}</p>
        </div>
        <aside class="jd-detail-side">
          <div class="jd-detail-card">
            <h4>Info lowongan</h4>
            <ul>${facts.map(([label, value]) => `<li><span>${escapeHtml(label)}</span><strong>${escapeHtml(String(value))}</strong></li>`).join("")}</ul>
          </div>
          <div class="jd-detail-card">
            <h4>Tentang perusahaan</h4>
            <div class="jd-detail-company-mini">
              <span class="company-mark">${logo}</span>
              <div><strong>${escapeHtml(job.companyName)}</strong><span>Perusahaan perekrut di JobAgent</span></div>
            </div>
          </div>
        </aside>
      </div>`;
  }

  function renderJobDetail() {
    const panel = $("#jd-detail");
    if (!panel) return;
    const job = state.publicJobs.find((item) => Number(item.id) === state.selectedJobId);
    if (!job) {
      panel.innerHTML = '<div class="jd-detail-empty"><span>▤</span><strong>Pilih lowongan di daftar</strong><p>Detail posisi, kualifikasi, dan tombol melamar akan muncul di sini.</p></div>';
      panel.classList.remove("is-active");
      const mobileActions = $("#jd-mobile-actions");
      if (mobileActions) { mobileActions.hidden = true; mobileActions.innerHTML = ""; }
      return;
    }
    panel.innerHTML = jobDetailMarkup(job);
    panel.classList.add("is-active");
    panel.scrollTop = 0;
    const board = $("#jd-board");
    if (board) board.classList.add("is-detail-open");

    const mobileActions = $("#jd-mobile-actions");
    if (mobileActions) {
      const applied = state.appliedJobIds.has(Number(job.id));
      mobileActions.hidden = false;
      mobileActions.innerHTML = applied
        ? '<span class="jd-m-done">✓ Sudah dilamar</span><a class="ghost-button" href="/applications">Lacak lamaran</a>'
        : `<button class="button" data-apply="${Number(job.id)}">Lamar sekarang →</button>`;
    }
  }

  function selectJob(id) {
    const jobId = Number(id);
    if (!state.publicJobs.some((job) => Number(job.id) === jobId)) return;
    state.selectedJobId = jobId;
    $$("[data-jd-select]").forEach((row) => row.classList.toggle("is-active", Number(row.dataset.jdSelect) === jobId));
    renderJobDetail();
  }

  function closeJobDetail() {
    state.selectedJobId = null;
    $$("[data-jd-select]").forEach((row) => row.classList.remove("is-active"));
    const board = $("#jd-board");
    if (board) board.classList.remove("is-detail-open");
    renderJobDetail();
  }

  /* Interaksi job board & tab lamaran */
  document.addEventListener("click", (event) => {
    const filter = event.target.closest("[data-jd-filter]");
    if (filter) {
      state.jdFilter = filter.dataset.jdFilter;
      $$("[data-jd-filter]").forEach((button) => button.classList.toggle("jd-filter-active", button === filter));
      renderJobBoard();
      return;
    }
    const row = event.target.closest("[data-jd-select]");
    if (row) {
      selectJob(row.dataset.jdSelect);
      return;
    }
    if (event.target.closest("[data-jd-close]")) {
      closeJobDetail();
      return;
    }
    const tab = event.target.closest("[data-app-filter]");
    if (tab) {
      state.appFilter = tab.dataset.appFilter;
      $$("[data-app-filter]").forEach((button) => button.classList.toggle("dash-tab-active", button === tab));
      renderApplications();
    }
  });

  document.addEventListener("keydown", (event) => {
    const row = event.target.closest?.("[data-jd-select]");
    if (row && (event.key === "Enter" || event.key === " ")) {
      event.preventDefault();
      selectJob(row.dataset.jdSelect);
    }
  });

  document.addEventListener("input", (event) => {
    if (event.target.id !== "jd-search") return;
    state.jdQuery = event.target.value;
    renderJobBoard();
  });

  async function loadDashboard() {
    if (state.loading || state.loaded) return;
    state.loading = true;
    try {
      const [dashboard, publicJobs] = await Promise.all([
        api("/api/dashboard"),
        isEmployer ? Promise.resolve({ jobs: [] }) : api("/api/jobs"),
      ]);
      state.stats = dashboard.stats;
      state.applications = dashboard.applications;
      state.jobs = dashboard.jobs;
      state.publicJobs = publicJobs.jobs || [];
      state.suggestedJobs = state.publicJobs.slice(0, 4);
      dashboard.applications.forEach((application) => {
        if (application.jobId) state.appliedJobIds.add(Number(application.jobId));
      });
      if (dashboard.cvCompletion !== null) applyCompletion(dashboard.cvCompletion);
      state.loaded = true;
      renderStats();
      renderApplications();
      renderJobs();
      updateProfileCounts();
      renderJobBoard();
      if (state.selectedJobId) selectJob(state.selectedJobId);
    } catch (error) {
      toast(error.message, true);
      const empty = `<div class="dash-empty">${escapeHtml(error.message)}</div>`;
      $("#dash-stats").innerHTML = empty;
      $("#dash-recent-applications").innerHTML = empty;
      $("#dash-recent-jobs").innerHTML = empty;
    } finally {
      state.loading = false;
    }
  }

  function reloadDashboard() {
    state.loaded = false;
    return loadDashboard();
  }

  /* ── Application status (employer) ──────── */
  document.addEventListener("click", async (event) => {
    const button = event.target.closest("[data-save-status]");
    if (!button) return;
    const id = button.dataset.saveStatus;
    const select = document.querySelector(`[data-status-for="${id}"]`);
    button.disabled = true;
    try {
      await api(`/api/applications/${id}`, { method: "PATCH", body: { status: select.value } });
      toast("Status lamaran diperbarui.");
      await reloadDashboard();
    } catch (error) {
      toast(error.message, true);
    } finally {
      button.disabled = false;
    }
  });

  /* ── Quick apply (candidate) ────────────── */
  document.addEventListener("click", async (event) => {
    const button = event.target.closest("[data-apply]");
    if (!button) return;
    const jobId = Number(button.dataset.apply);
    button.disabled = true;
    try {
      const result = await api(`/api/jobs/${jobId}/apply`, { method: "POST" });
      toast(result.message || "Lamaran terkirim.");
      state.appliedJobIds.add(jobId);
      state.selectedJobId = jobId;
      await reloadDashboard();
    } catch (error) {
      toast(error.message, true);
      if (error.status === 409) {
        state.appliedJobIds.add(jobId);
        state.selectedJobId = jobId;
        await reloadDashboard();
      } else {
        button.disabled = false;
      }
    }
  });

  /* ── Job modal (employer) ───────────────── */
  const jobModal = $("#job-modal");
  const jobForm = $("#dash-job-form");

  function openJobModal(job = null) {
    jobForm.reset();
    jobForm.elements.id.value = job ? job.id : "";
    $("#dash-job-error").hidden = true;
    if (job) {
      jobForm.elements.title.value = job.title;
      jobForm.elements.location.value = job.location;
      jobForm.elements.type.value = job.type;
      jobForm.elements.category.value = job.category || "";
      jobForm.elements.salary.value = job.salary || "";
      jobForm.elements.description.value = job.description;
      jobForm.elements.status.value = job.status;
      $("#dash-job-modal-title").textContent = "Edit lowongan.";
      $("#dash-job-modal-desc").textContent = "Perbarui detail posisi yang sudah dipublikasikan.";
      $("#dash-job-submit").innerHTML = "Simpan perubahan <span>→</span>";
    } else {
      jobForm.elements.status.value = "active";
      $("#dash-job-modal-title").textContent = "Pasang lowongan.";
      $("#dash-job-modal-desc").textContent = "Ceritakan posisi yang sedang ingin kamu isi.";
      $("#dash-job-submit").innerHTML = "Publikasikan lowongan <span>→</span>";
    }
    jobModal.hidden = false;
    jobForm.elements.title.focus();
  }

  document.addEventListener("click", (event) => {
    if (event.target.closest("[data-open-job]")) openJobModal();
    const edit = event.target.closest("[data-edit-job]");
    if (edit) {
      const job = state.jobs.find((item) => item.id === Number(edit.dataset.editJob));
      if (job) openJobModal(job);
    }
    const close = event.target.closest("#job-modal [data-close]");
    if (close || event.target === jobModal) jobModal.hidden = true;
  });

  jobForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const id = jobForm.elements.id.value;
    const payload = {
      title: jobForm.elements.title.value.trim(),
      location: jobForm.elements.location.value.trim(),
      type: jobForm.elements.type.value,
      category: jobForm.elements.category.value,
      salary: jobForm.elements.salary.value.trim() || null,
      description: jobForm.elements.description.value.trim(),
      status: jobForm.elements.status.value,
    };
    const errorBox = $("#dash-job-error");
    errorBox.hidden = true;
    $("#dash-job-submit").disabled = true;
    try {
      const result = id
        ? await api(`/api/jobs/${id}`, { method: "PATCH", body: payload })
        : await api("/api/jobs", { method: "POST", body: payload });
      jobModal.hidden = true;
      toast(result.message || "Lowongan tersimpan.");
      await reloadDashboard();
    } catch (error) {
      errorBox.textContent = error.message;
      errorBox.hidden = false;
    } finally {
      $("#dash-job-submit").disabled = false;
    }
  });

  /* ── Job toggle & delete ────────────────── */
  document.addEventListener("click", async (event) => {
    const toggle = event.target.closest("[data-toggle-job]");
    if (toggle) {
      const job = state.jobs.find((item) => item.id === Number(toggle.dataset.toggleJob));
      if (!job) return;
      toggle.disabled = true;
      try {
        const payload = {
          title: job.title, location: job.location, type: job.type, category: job.category,
          salary: job.salary || null, description: job.description,
          status: job.status === "active" ? "closed" : "active",
        };
        await api(`/api/jobs/${job.id}`, { method: "PATCH", body: payload });
        toast(job.status === "active" ? "Lowongan ditutup." : "Lowongan dibuka kembali.");
        await reloadDashboard();
      } catch (error) {
        toast(error.message, true);
        toggle.disabled = false;
      }
      return;
    }

    const remove = event.target.closest("[data-delete-job]");
    if (remove) {
      const job = state.jobs.find((item) => item.id === Number(remove.dataset.deleteJob));
      if (!job) return;
      openConfirm({
        title: "Hapus lowongan?",
        copy: `“${job.title}” dan seluruh lamarannya akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.`,
        onOk: async () => {
          try {
            await api(`/api/jobs/${job.id}`, { method: "DELETE" });
            toast("Lowongan dihapus.");
            await reloadDashboard();
          } catch (error) {
            toast(error.message, true);
          }
        },
      });
    }
  });

  /* ── Confirm modal ──────────────────────── */
  const confirmModal = $("#confirm-modal");
  let confirmAction = null;

  function openConfirm({ title, copy, onOk }) {
    $("#confirm-title").textContent = title;
    $("#confirm-copy").textContent = copy;
    confirmAction = onOk;
    confirmModal.hidden = false;
  }

  $("[data-confirm-cancel]").addEventListener("click", () => { confirmModal.hidden = true; confirmAction = null; });
  $("[data-confirm-ok]").addEventListener("click", async () => {
    const action = confirmAction;
    confirmModal.hidden = true;
    confirmAction = null;
    if (action) await action();
  });
  confirmModal.addEventListener("click", (event) => {
    if (event.target === confirmModal) { confirmModal.hidden = true; confirmAction = null; }
  });

  /* ── CV online (candidate) ──────────────── */
  const cvForm = $("#cv-form");

  if (cvForm) {
    const skills = new Set(((window.JobAgent?.cv || {}).skills || []).map((skill) => String(skill)));
    const skillChips = $("#skill-chips");
    const skillInput = $("#skill-input");

    function renderSkillChips() {
      skillChips.innerHTML = Array.from(skills).map((skill) =>
        `<span class="dash-chip">${escapeHtml(skill)}<button type="button" data-remove-skill="${escapeHtml(skill)}" aria-label="Hapus ${escapeHtml(skill)}">×</button></span>`).join("");
    }

    function addSkill(value) {
      const skill = String(value || "").trim();
      if (!skill) return;
      skills.add(skill);
      skillInput.value = "";
      renderSkillChips();
      renderCvPreview();
    }

    $("#skill-add").addEventListener("click", () => addSkill(skillInput.value));
    skillInput.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === ",") { event.preventDefault(); addSkill(skillInput.value); }
    });
    skillChips.addEventListener("click", (event) => {
      const button = event.target.closest("[data-remove-skill]");
      if (!button) return;
      skills.delete(button.dataset.removeSkill);
      renderSkillChips();
      renderCvPreview();
    });

    const TEMPLATES = {
      experience: `<div class="dash-repeat-head"><strong>Pengalaman</strong><button type="button" data-remove-repeat class="dash-repeat-remove" aria-label="Hapus pengalaman">×</button></div>
        <label>Jabatan<input name="title" maxlength="120" placeholder="Contoh: Product Designer" required></label>
        <div class="form-row"><label>Perusahaan<input name="company" maxlength="120" placeholder="Nama perusahaan" required></label><label>Lokasi<input name="location" maxlength="100" placeholder="Kota / Remote"></label></div>
        <div class="form-row"><label>Mulai<input name="start" maxlength="20" placeholder="Contoh: Jan 2022"></label><label>Selesai <span class="optional-label">kosongkan bila masih berjalan</span><input name="end" maxlength="20" placeholder="Contoh: Des 2024"></label></div>
        <label>Deskripsi<textarea name="description" rows="2" maxlength="1000" placeholder="Tanggung jawab dan pencapaian..."></textarea></label>`,
      education: `<div class="dash-repeat-head"><strong>Pendidikan</strong><button type="button" data-remove-repeat class="dash-repeat-remove" aria-label="Hapus pendidikan">×</button></div>
        <label>Institusi<input name="school" maxlength="140" placeholder="Contoh: Universitas Indonesia" required></label>
        <label>Jurusan / gelar<input name="major" maxlength="120" placeholder="Contoh: Teknik Informatika"></label>
        <div class="form-row"><label>Mulai<input name="start" maxlength="20" placeholder="2018"></label><label>Selesai<input name="end" maxlength="20" placeholder="2022 / Sekarang"></label></div>`,
      link: `<div class="dash-repeat-head"><strong>Tautan</strong><button type="button" data-remove-repeat class="dash-repeat-remove" aria-label="Hapus tautan">×</button></div>
        <div class="form-row"><label>Jenis<input name="label" maxlength="60" placeholder="Portfolio / LinkedIn / GitHub" required></label><label>URL<input name="url" type="url" maxlength="300" placeholder="https://..." required></label></div>`,
    };

    const CONTAINERS = { experience: "#cv-experiences", education: "#cv-educations", link: "#cv-links" };

    $$("[data-add-repeat]").forEach((button) => button.addEventListener("click", () => {
      const kind = button.dataset.addRepeat;
      const item = document.createElement("div");
      item.className = "dash-repeat-item";
      item.dataset.repeat = kind;
      item.innerHTML = TEMPLATES[kind];
      $(CONTAINERS[kind]).appendChild(item);
      item.querySelector("input")?.focus();
    }));

    cvForm.addEventListener("click", (event) => {
      const remove = event.target.closest("[data-remove-repeat]");
      if (remove) {
        remove.closest(".dash-repeat-item").remove();
        renderCvPreview();
      }
    });

    function collectRepeat(containerSelector, fields) {
      return $$(containerSelector).map((item) => {
        const entry = {};
        fields.forEach((field) => { entry[field] = item.querySelector(`[name="${field}"]`)?.value.trim() || null; });
        return entry;
      }).filter((entry) => Object.values(entry).some(Boolean));
    }

    function collectCv() {
      return {
        summary: cvForm.elements.summary.value.trim() || null,
        skills: Array.from(skills),
        experiences: collectRepeat("#cv-experiences .dash-repeat-item", ["title", "company", "location", "start", "end", "description"]),
        educations: collectRepeat("#cv-educations .dash-repeat-item", ["school", "major", "start", "end"]),
        links: collectRepeat("#cv-links .dash-link-item, #cv-links .dash-repeat-item", ["label", "url"]),
      };
    }

    cvForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const errorBox = $("#cv-error");
      errorBox.hidden = true;
      $("#cv-submit").disabled = true;
      try {
        const result = await api("/api/profile/cv", { method: "PUT", body: collectCv() });
        toast(result.message || "CV online disimpan.");
        await reloadDashboard();
      } catch (error) {
        errorBox.textContent = error.message;
        errorBox.hidden = false;
      } finally {
        $("#cv-submit").disabled = false;
      }
    });

    /* Live preview */
    renderCvPreview = function () {
      const data = collectCv();
      $("#preview-summary").textContent = data.summary || "Belum ada ringkasan diri.";
      $("#preview-summary-block").hidden = !data.summary;
      $("#preview-skills-block").hidden = !data.skills.length;
      $("#preview-skills").innerHTML = data.skills.map((skill) => `<span>${escapeHtml(skill)}</span>`).join("");
      $("#preview-experiences-block").hidden = !data.experiences.length;
      $("#preview-experiences").innerHTML = data.experiences.map((entry) => `
        <div class="dash-cv-entry"><strong>${escapeHtml(entry.title || "")}</strong><span>${escapeHtml([entry.company, entry.location].filter(Boolean).join(" · "))}${entry.start || entry.end ? ` · ${escapeHtml([entry.start, entry.end || "Sekarang"].filter(Boolean).join(" – "))}` : ""}</span>${entry.description ? `<p>${escapeHtml(entry.description)}</p>` : ""}</div>`).join("");
      $("#preview-educations-block").hidden = !data.educations.length;
      $("#preview-educations").innerHTML = data.educations.map((entry) => `
        <div class="dash-cv-entry"><strong>${escapeHtml(entry.school || "")}</strong><span>${escapeHtml([entry.major, [entry.start, entry.end].filter(Boolean).join(" – ")].filter(Boolean).join(" · "))}</span></div>`).join("");
      $("#preview-links-block").hidden = !data.links.length;
      $("#preview-links").innerHTML = data.links.map((entry) => `<a href="${escapeHtml(entry.url)}" target="_blank" rel="noopener">${escapeHtml(entry.label || entry.url)}</a>`).join("");
    }

    cvForm.addEventListener("input", (event) => {
      if (event.target.name === "headline" || event.target.name === "location") return;
      renderCvPreview();
    });

    renderCvPreview();

    /* CV document upload / delete */
    const cvDoc = $("#cv-doc");
    if (cvDoc) {
      cvDoc.addEventListener("change", async (event) => {
        const file = event.target.files?.[0];
        if (!file) return;
        const formData = new FormData();
        formData.append("document", file);
        try {
          const result = await api("/api/profile/cv/document", { method: "POST", body: formData, formData: true });
          toast(result.message || "Dokumen CV terunggah.");
          window.location.reload();
        } catch (error) {
          toast(error.message, true);
        }
      });

      cvDoc.addEventListener("click", (event) => {
        if (!event.target.closest("[data-cv-delete]")) return;
        openConfirm({
          title: "Hapus dokumen CV?",
          copy: "Dokumen PDF yang terunggah akan dihapus dari profilmu.",
          onOk: async () => {
            try {
              const result = await api("/api/profile/cv/document", { method: "DELETE" });
              toast(result.message || "Dokumen dihapus.");
              window.location.reload();
            } catch (error) {
              toast(error.message, true);
            }
          },
        });
      });
    }
  }

  /* ── Profile form ───────────────────────── */
  const profileForm = $("#profile-form");
  if (profileForm) {
    const photoInput = $("#profile-photo-input");
    photoInput.addEventListener("change", () => {
      const file = photoInput.files?.[0];
      if (!file) return;
      const preview = $(".dash-profile-photo-preview");
      preview.innerHTML = `<img alt="Pratinjau foto" src="${URL.createObjectURL(file)}">`;
    });

    profileForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const errorBox = $("#profile-error");
      errorBox.hidden = true;
      $("#profile-submit").disabled = true;
      try {
        const formData = new FormData(profileForm);
        for (const [key, value] of Array.from(formData.entries())) {
          if (typeof value === "string" && value.trim() === "") formData.delete(key);
        }
        const result = await api("/api/profile", { method: "POST", body: formData, formData: true });
        toast(result.message || "Profil diperbarui.");
        if (result.user?.photoUrl) {
          $$(".dash-sidebar-avatar, .dash-top-avatar").forEach((element) => {
            element.innerHTML = `<img alt="" src="${result.user.photoUrl}">`;
          });
        }
      } catch (error) {
        errorBox.textContent = error.message;
        errorBox.hidden = false;
      } finally {
        $("#profile-submit").disabled = false;
      }
    });
  }

  /* ── Boot ───────────────────────────────── */
  const initial = (window.location.hash || "#overview").slice(1);
  showSection(initial, { updateHash: false });
  loadDashboard();
})();
