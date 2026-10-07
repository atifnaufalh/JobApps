const state = {
  mode: "login",
  role: "candidate",
  otpStep: false,
  jobs: [],
  category: "Semua",
};

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const authModal = document.getElementById("auth-modal");
const jobModal = document.getElementById("job-modal");
const authForm = document.getElementById("auth-form");

function showToast(message) {
  const toast = document.getElementById("toast");
  document.getElementById("toast-message").textContent = message;
  toast.hidden = false;
  window.clearTimeout(showToast.timeout);
  showToast.timeout = window.setTimeout(() => { toast.hidden = true; }, 3500);
}

async function request(url, options = {}) {
  const multipart = options.body instanceof FormData;
  const headers = {
    Accept: "application/json",
    "X-CSRF-TOKEN": csrfToken,
    "X-Requested-With": "XMLHttpRequest",
    ...options.headers,
  };
  if (!multipart) headers["Content-Type"] = "application/json";
  const response = await fetch(url, {
    ...options,
    headers,
  });
  const result = await response.json();
  if (!response.ok) {
    const message = result.error || result.message || Object.values(result.errors || {}).flat()[0];
    const error = new Error(message || "Permintaan tidak berhasil.");
    error.payload = result;
    throw error;
  }
  return result;
}

function openAuth(mode = "login", role = "candidate") {
  state.mode = mode;
  state.role = role;
  state.otpStep = false;
  authForm.reset();
  document.getElementById("auth-error").hidden = true;
  document.querySelectorAll(".image-preview").forEach((image) => { image.src = ""; image.hidden = true; });
  setAuthMode(mode);
  setAuthRole(role);
  authModal.hidden = false;
  document.body.classList.add("modal-open");
  authForm.querySelector('input[name="email"]').focus();
}

function closeModal(modal) {
  modal.hidden = true;
  if (!document.querySelector(".modal-backdrop:not([hidden])")) document.body.classList.remove("modal-open");
}

function setAuthMode(mode) {
  if (mode === "register" && state.role === "admin") {
    state.role = "candidate";
  }
  state.mode = mode;
  state.otpStep = false;
  const isRegister = mode === "register";
  document.getElementById("auth-kicker").textContent = isRegister ? "MULAI PERJALANAN BARU" : "SELAMAT DATANG KEMBALI";
  document.getElementById("auth-title").textContent = isRegister ? "Buat akun JobAgent." : "Masuk dengan aman.";
  document.getElementById("auth-description").textContent = "Tanpa kata sandi. Kami akan mengirim kode sekali pakai ke emailmu.";
  document.getElementById("email-label").hidden = false;
  document.getElementById("otp-label").hidden = true;
  document.getElementById("auth-switch").hidden = state.role === "admin";
  document.getElementById("resend-code").hidden = true;
  const roleSwitch = document.querySelector(".auth-form .role-switch");
  roleSwitch.hidden = false;
  roleSwitch.classList.toggle("admin-choice-visible", !isRegister);
  document.querySelector('[data-role-choice="admin"]').hidden = isRegister;
  document.querySelectorAll("[data-role-choice]").forEach((button) => button.classList.toggle("role-selected", button.dataset.roleChoice === state.role));
  document.querySelectorAll("[data-profile]").forEach((section) => { section.hidden = !isRegister || section.dataset.profile !== state.role; });
  document.querySelectorAll("[data-profile] input, [data-profile] select").forEach((field) => {
    const optional = ["phone", "location", "headline", "industry", "companySize", "website", "profilePhoto", "companyLogo"].includes(field.name);
    field.required = isRegister && !optional && field.closest("[data-profile]")?.dataset.profile === state.role;
  });
  document.getElementById("auth-submit").innerHTML = "Kirim kode ke email <span>→</span>";
}

function setAuthRole(role) {
  if (role === "admin" && state.mode === "register") {
    setAuthMode("login");
  }
  state.role = role;
  if (role === "admin") {
    state.mode = "login";
    document.getElementById("auth-switch").hidden = true;
    document.getElementById("auth-kicker").textContent = "AKSES ADMINISTRATOR";
    document.getElementById("auth-title").textContent = "Masuk sebagai admin.";
  } else if (state.mode === "login") {
    document.getElementById("auth-switch").hidden = false;
    document.getElementById("auth-kicker").textContent = "SELAMAT DATANG KEMBALI";
    document.getElementById("auth-title").textContent = "Masuk dengan aman.";
  }
  document.querySelectorAll("[data-role-choice]").forEach((button) => button.classList.toggle("role-selected", button.dataset.roleChoice === role));
  document.querySelectorAll("[data-profile]").forEach((section) => { section.hidden = state.mode !== "register" || section.dataset.profile !== role; });
  document.querySelectorAll("[data-profile] input, [data-profile] select").forEach((field) => {
    const optional = ["phone", "location", "headline", "industry", "companySize", "website", "profilePhoto", "companyLogo"].includes(field.name);
    field.required = state.mode === "register" && !optional && field.closest("[data-profile]")?.dataset.profile === role;
  });
}

function errorFor(error, element) {
  element.textContent = error.message;
  element.hidden = false;
}

async function signInFirebase(customToken, appName = "jobagent-browser") {
  const [{ initializeApp }, { getAuth, signInWithCustomToken }] = await Promise.all([
    import("https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js"),
    import("https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js"),
  ]);
  const firebaseApp = initializeApp(window.JobAgent.config, appName);
  await signInWithCustomToken(getAuth(firebaseApp), customToken);
}

async function signOutFirebase() {
  const [{ getApps, initializeApp }, { getAuth, signOut }] = await Promise.all([
    import("https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js"),
    import("https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js"),
  ]);
  const app = getApps().find((item) => item.name === "jobagent-browser")
    || initializeApp(window.JobAgent.config, "jobagent-browser");
  await signOut(getAuth(app));
}

async function renderJobs() {
  const grid = document.getElementById("job-grid");
  const query = document.getElementById("search-query").value.trim().toLowerCase();
  const location = document.getElementById("search-location").value.trim().toLowerCase();
  const filtered = state.jobs.filter((job) => {
    const matchesQuery = !query || [job.title, job.companyName, job.category, job.description].some((value) => value?.toLowerCase().includes(query));
    const matchesLocation = !location || job.location?.toLowerCase().includes(location);
    return matchesQuery && matchesLocation && (state.category === "Semua" || job.category === state.category);
  });

  document.getElementById("job-count").textContent = `(${filtered.length})`;
  if (!filtered.length) {
    grid.innerHTML = '<div class="empty-jobs"><span>⌕</span><strong>Belum ada lowongan yang cocok.</strong><span>Coba kata kunci atau bidang yang berbeda.</span></div>';
    return;
  }
  grid.innerHTML = filtered.map((job, index) => {
    const letter = escapeHtml(job.companyName?.slice(0, 1) || "J");
    const logo = job.companyLogo ? `<img src="${escapeHtml(job.companyLogo)}" alt="" loading="lazy">` : letter;
    return `<article class="job-card"><div class="job-card-top"><div class="company-mark company-${index % 4}">${logo}</div><span class="job-age">${escapeHtml(relativeDate(job.createdAt))}</span></div><div class="job-category">${escapeHtml(job.category || "Karier pilihan")}</div><h4>${escapeHtml(job.title)}</h4><p class="job-company">${escapeHtml(job.companyName)}</p><p class="job-description">${escapeHtml(job.description)}</p><div class="job-tags"><span>⌖ ${escapeHtml(job.location)}</span><span>▣ ${escapeHtml(job.type)}</span></div><div class="job-card-bottom"><strong>${escapeHtml(job.salary || "Gaji kompetitif")}</strong><button data-apply="${Number(job.id)}" aria-label="Lamar ${escapeHtml(job.title)}">↗</button></div></article>`;
  }).join("");
}

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, (character) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;",
  })[character]);
}

function relativeDate(value) {
  if (!value) return "Baru saja";
  const days = Math.floor((Date.now() - new Date(value).getTime()) / 86400000);
  if (days < 1) return "Hari ini";
  return days === 1 ? "1 hari lalu" : `${days} hari lalu`;
}

async function loadJobs() {
  try {
    const result = await request("/api/jobs");
    state.jobs = result.jobs;
    await renderJobs();
  } catch {
    showToast("Lowongan belum bisa dimuat. Silakan coba lagi.");
  }
}

document.addEventListener("click", async (event) => {
  const authButton = event.target.closest("[data-auth]");
  if (authButton) openAuth(authButton.dataset.auth, authButton.dataset.role || "candidate");

  if (event.target.closest("[data-menu]")) {
    const nav = document.getElementById("main-nav");
    const open = nav.classList.toggle("nav-open");
    event.target.closest("[data-menu]").setAttribute("aria-expanded", String(open));
  }
  if (event.target.closest("[data-close]")) closeModal(event.target.closest(".modal-backdrop"));
  if (event.target === authModal || event.target === jobModal) closeModal(event.target);

  const roleButton = event.target.closest("[data-role-choice]");
  if (roleButton) setAuthRole(roleButton.dataset.roleChoice);

  if (event.target.closest("[data-mode-switch]")) {
    setAuthMode(state.mode === "register" ? "login" : "register");
    document.getElementById("auth-error").hidden = true;
  }

  const categoryButton = event.target.closest("[data-category]");
  if (categoryButton) {
    state.category = categoryButton.dataset.category;
    document.querySelectorAll("[data-category]").forEach((button) => button.classList.toggle("category-active", button === categoryButton));
    renderJobs();
  }

  const applyButton = event.target.closest("[data-apply]");
  if (applyButton) {
    if (!window.JobAgent.user) return openAuth("login", "candidate");
    try {
      const result = await request(`/api/jobs/${applyButton.dataset.apply}/apply`, { method: "POST", body: "{}" });
      showToast(result.message);
    } catch (error) {
      showToast(error.message);
    }
  }

  if (event.target.closest("[data-open-job]")) {
    jobModal.hidden = false;
    document.body.classList.add("modal-open");
  }
  if (event.target.closest("[data-logout]")) {
    try {
      await request("/api/auth/logout", { method: "POST", body: "{}" });
      try {
        await signOutFirebase();
      } catch (error) {
        console.warn("Firebase sign-out failed; ending the application session anyway.", error);
      }
      window.location.reload();
    } catch (error) {
      showToast(error.message);
    }
  }
  if (event.target.closest("#refresh-jobs")) loadJobs();
});

const loginTarget = new URLSearchParams(window.location.search).get("login");
if (loginTarget) openAuth("login", loginTarget === "admin" ? "admin" : "candidate");

document.getElementById("job-search").addEventListener("submit", (event) => {
  event.preventDefault();
  document.getElementById("job-list").scrollIntoView({ behavior: "smooth" });
  renderJobs();
});
document.getElementById("search-query").addEventListener("input", renderJobs);
document.getElementById("search-location").addEventListener("input", renderJobs);

authForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const errorBox = document.getElementById("auth-error");
  errorBox.hidden = true;
  const submit = document.getElementById("auth-submit");
  submit.disabled = true;
  submit.classList.add("is-loading");
  const processing = document.getElementById("auth-processing");
  processing.hidden = false;
  const form = new FormData(authForm);
  try {
    if (!state.otpStep) {
      const email = form.get("email");
      const payload = new FormData();
      payload.append("email", email);
      payload.append("mode", state.mode);
      payload.append("role", state.role);
      if (state.mode === "register") {
        const profileNames = state.role === "candidate"
          ? ["fullName", "phone", "location", "headline"]
          : ["contactName", "companyName", "industry", "companySize", "website"];
        const profile = { role: state.role };
        profileNames.forEach((name) => { profile[name] = form.get(name) || ""; });
        payload.append("profile", JSON.stringify(profile));
        const imageField = state.role === "candidate" ? "profilePhoto" : "companyLogo";
        if (form.get(imageField)?.size) payload.append(imageField, form.get(imageField));
      }
      document.getElementById("auth-progress-label").textContent = "Memeriksa email dan menyiapkan kode OTP...";
      await request("/api/auth/request-otp", { method: "POST", body: payload });
      state.otpStep = true;
      document.getElementById("auth-description").textContent = `Kami mengirimkan kode 6 digit ke ${email}.`;
      document.getElementById("auth-kicker").textContent = "VERIFIKASI EMAIL";
      document.getElementById("auth-title").textContent = "Cek kotak masukmu.";
      document.getElementById("email-label").hidden = true;
      document.getElementById("otp-label").hidden = false;
      document.getElementById("auth-switch").hidden = true;
      document.querySelector(".auth-form .role-switch").hidden = true;
      document.querySelectorAll("[data-profile]").forEach((section) => { section.hidden = true; });
      document.querySelectorAll("[data-profile] input, [data-profile] select").forEach((field) => { field.required = false; });
      document.getElementById("resend-code").hidden = false;
      document.querySelector('input[name="code"]').required = true;
      document.querySelector('input[name="email"]').required = false;
      submit.innerHTML = "Verifikasi & masuk <span>→</span>";
      document.querySelector('input[name="code"]').focus();
      return;
    }

    document.getElementById("auth-progress-label").textContent = "Mencocokkan kode dan mengamankan sesi akun...";
    const result = await request("/api/auth/verify-otp", {
      method: "POST",
      body: JSON.stringify({ email: form.get("email"), code: form.get("code"), role: state.role }),
    });
    document.getElementById("auth-processing").hidden = true;
    document.getElementById("success-overlay").hidden = false;
    try {
      await signInFirebase(result.firebaseCustomToken, result.user.role === "admin" ? "jobagent-admin" : "jobagent-browser");
    } catch {
      document.querySelector(".success-card p").textContent = "Sesi JobAgent berhasil dibuat. Sinkronisasi Firebase Web perlu diperiksa oleh admin.";
    }
    window.JobAgent.user = result.user;
    if (result.user.role === "admin") {
      window.location.assign("/admin");
      return;
    }
    closeModal(authModal);
    window.setTimeout(() => window.location.reload(), 1800);
  } catch (error) {
    errorFor(error, errorBox);
  } finally {
    processing.hidden = true;
    submit.classList.remove("is-loading");
    submit.disabled = false;
  }
});

document.getElementById("resend-code").addEventListener("click", () => {
  const email = authForm.querySelector('[name="email"]');
  email.required = true;
  document.getElementById("otp-label").hidden = true;
  document.getElementById("email-label").hidden = false;
  document.getElementById("resend-code").hidden = true;
  document.getElementById("auth-switch").hidden = false;
  document.querySelector('[name="code"]').required = false;
  setAuthMode(state.mode);
  email.focus();
});

document.querySelectorAll('.upload-control input[type="file"]').forEach((input) => {
  input.addEventListener("change", () => {
    const file = input.files?.[0];
    const preview = input.closest(".upload-control").querySelector(".image-preview");
    if (!file) {
      preview.hidden = true;
      return;
    }
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type) || file.size > 3 * 1024 * 1024) {
      input.value = "";
      preview.hidden = true;
      errorFor(new Error("Pilih gambar JPG, PNG, atau WebP berukuran maksimal 3 MB."), document.getElementById("auth-error"));
      return;
    }
    preview.src = URL.createObjectURL(file);
    preview.hidden = false;
    document.getElementById("auth-error").hidden = true;
  });
});

document.getElementById("job-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  const form = new FormData(event.currentTarget);
  const errorBox = document.getElementById("job-error");
  errorBox.hidden = true;
  const submit = event.currentTarget.querySelector('[type="submit"]');
  submit.disabled = true;
  try {
    const result = await request("/api/jobs", {
      method: "POST",
      body: JSON.stringify(Object.fromEntries(form.entries())),
    });
    state.jobs.unshift(result.job);
    await renderJobs();
    closeModal(jobModal);
    event.currentTarget.reset();
    showToast("Lowongan berhasil dipublikasikan.");
  } catch (error) {
    errorFor(error, errorBox);
  } finally {
    submit.disabled = false;
  }
});

if ("serviceWorker" in navigator && window.location.protocol === "https:") {
  window.addEventListener("load", () => navigator.serviceWorker.register("/sw.js"));
}
loadJobs();

async function runHealthCheck() {
  document.querySelectorAll("[data-check]").forEach((item) => { item.textContent = "Memeriksa..."; item.dataset.status = "pending"; });
  try {
    const result = await request("/api/health");
    updateHealthRows(result.checks, "[data-check]");
    document.getElementById("diagnostic-checked").textContent = `Diperiksa ${new Date(result.checkedAt).toLocaleTimeString("id-ID")}`;
  } catch (error) {
    if (error.payload?.checks) updateHealthRows(error.payload.checks, "[data-check]");
    else document.querySelectorAll("[data-check]").forEach((item) => { item.textContent = "Tidak dapat dijangkau"; item.dataset.status = "error"; });
  }
}

function updateHealthRows(checks, selector) {
  Object.entries(checks).forEach(([key, status]) => {
    const item = document.querySelector(`${selector}[data-check="${key}"]`);
    if (item) {
      item.textContent = status === "ok" ? "Beroperasi" : "Perlu diperiksa";
      item.dataset.status = status;
    }
  });
}

document.querySelectorAll("[data-check-status], [data-run-check]").forEach((button) => {
  button.addEventListener("click", () => {
    document.getElementById("status-modal").hidden = false;
    document.body.classList.add("modal-open");
    runHealthCheck();
  });
});

window.addEventListener("error", () => showToast("Ada kendala pada halaman. Muat ulang atau hubungi dukungan jika berlanjut."));
window.addEventListener("unhandledrejection", () => showToast("Permintaan gagal diproses. Periksa koneksi lalu coba kembali."));
