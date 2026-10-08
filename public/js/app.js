const state = {
  mode: "login",
  role: "candidate",
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
    error.status = response.status;
    throw error;
  }
  return result;
}

function openAuth(mode = "login", role = "candidate") {
  state.mode = mode;
  state.role = role;
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
  state.mode = mode;
  const isRegister = mode === "register";
  document.getElementById("auth-kicker").textContent = isRegister ? "MULAI PERJALANAN BARU" : "SELAMAT DATANG KEMBALI";
  document.getElementById("auth-title").textContent = isRegister ? "Buat akun JobAgent." : "Masuk dengan aman.";
  document.getElementById("auth-description").textContent = isRegister
    ? "Daftar dengan email dan kata sandi, atau lanjutkan dengan Google."
    : "Masuk dengan email dan kata sandi, atau gunakan akun Google.";
  document.getElementById("email-label").hidden = false;
  document.getElementById("auth-switch").hidden = false;
  document.getElementById("password-confirmation-label").hidden = !isRegister;
  document.getElementById("forgot-password").hidden = isRegister;
  const password = authForm.elements.namedItem("password");
  password.autocomplete = isRegister ? "new-password" : "current-password";
  password.minLength = isRegister ? 6 : 0;
  authForm.elements.namedItem("password_confirmation").required = isRegister;
  document.getElementById("auth-switch").innerHTML = isRegister
    ? 'Sudah punya akun? <button type="button" data-mode-switch>Masuk</button>'
    : 'Belum punya akun? <button type="button" data-mode-switch>Daftar</button>';
  document.querySelector(".auth-form .role-switch").hidden = !isRegister;
  document.querySelectorAll("[data-role-choice]").forEach((button) => button.classList.toggle("role-selected", button.dataset.roleChoice === state.role));
  document.querySelectorAll("[data-profile]").forEach((section) => { section.hidden = !isRegister || section.dataset.profile !== state.role; });
  document.querySelectorAll("[data-profile] input, [data-profile] select").forEach((field) => {
    const optional = ["phone", "location", "headline", "industry", "companySize", "website", "profilePhoto", "companyLogo"].includes(field.name);
    field.required = isRegister && !optional && field.closest("[data-profile]")?.dataset.profile === state.role;
  });
  document.getElementById("auth-submit").innerHTML = isRegister ? "Buat akun <span>→</span>" : "Masuk <span>→</span>";
}

function setAuthRole(role) {
  state.role = role;
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

function firebaseErrorCode(error) {
  const code = typeof error?.code === "string" ? error.code : "";
  return code.startsWith("auth/api-key-not-valid") ? "auth/api-key-not-valid" : code;
}

async function firebaseAuth() {
  const [{ getApps, initializeApp }, { getAuth, setPersistence, browserLocalPersistence }] = await Promise.all([
    import("https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js"),
    import("https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js"),
  ]);
  const firebaseApp = getApps().find((item) => item.name === "jobagent-browser")
    || initializeApp(window.JobAgent.config, "jobagent-browser");
  const auth = getAuth(firebaseApp);
  await setPersistence(auth, browserLocalPersistence);
  auth.languageCode = "id";
  return auth;
}

async function signOutFirebase() {
  const [{ signOut }, auth] = await Promise.all([
    import("https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js"),
    firebaseAuth(),
  ]);
  await signOut(auth);
}

async function syncFirebaseSession(user, mode) {
  const idToken = await user.getIdToken(true);
  const payload = new FormData();
  payload.append("mode", mode);
  if (mode === "register") {
    payload.append("role", state.role);
    const profileNames = state.role === "candidate"
      ? ["fullName", "phone", "location", "headline"]
      : ["contactName", "companyName", "industry", "companySize", "website"];
    const profile = {};
    profileNames.forEach((name) => { profile[name] = authForm.elements.namedItem(name).value || ""; });
    payload.append("profile", JSON.stringify(profile));
    const imageField = state.role === "candidate" ? "profilePhoto" : "companyLogo";
    const image = authForm.elements.namedItem(imageField).files?.[0];
    if (image) payload.append(imageField, image);
  }

  const result = await request("/api/auth/firebase-session", {
    method: "POST",
    body: payload,
    headers: { Authorization: `Bearer ${idToken}` },
  });
  if (result.verificationRequired) {
    return false;
  }

  window.JobAgent.user = result.user;
  if (result.user.role === "admin") {
    window.location.assign("/admin");
  } else {
    window.location.reload();
  }
  return true;
}

async function sendVerificationLink(user) {
  const { sendEmailVerification } = await import("https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js");
  await sendEmailVerification(user, {
    url: `${window.location.origin}/?login=account`,
    handleCodeInApp: false,
  });
}

async function showEmailVerificationMessage() {
  document.getElementById("success-overlay").hidden = false;
  document.querySelector(".success-card h2").textContent = "Satu langkah lagi.";
  document.querySelector(".success-card p").textContent = "Kami sudah mengirim tautan verifikasi ke emailmu. Setelah tautannya dibuka, masuk kembali ke JobAgent dan mulai gunakan aplikasi sesuai peranmu.";
  document.querySelector(".success-card .section-kicker").textContent = "VERIFIKASI EMAIL";
  window.setTimeout(() => {
    document.getElementById("success-overlay").hidden = true;
    closeModal(authModal);
  }, 3200);
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
  if (authButton) {
    openAuth(authButton.dataset.auth, authButton.dataset.role || "candidate");
    document.getElementById("main-nav").classList.remove("nav-open");
    document.querySelector("[data-menu]").setAttribute("aria-expanded", "false");
  }

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
if (loginTarget) openAuth("login");
if (loginTarget === "account") {
  (async () => {
    const [{ onAuthStateChanged }, auth] = await Promise.all([
      import("https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js"),
      firebaseAuth(),
    ]);
    const unsubscribe = onAuthStateChanged(auth, async (user) => {
      unsubscribe();
      if (!user) return;
      try {
        await user.reload();
        if (user.emailVerified) {
          await syncFirebaseSession(user, "login");
        }
      } catch (error) {
        showToast(error.message);
      }
    });
  })().catch((error) => showToast(error.message));
}

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
  try {
    const auth = await firebaseAuth();
    const { createUserWithEmailAndPassword, signInWithEmailAndPassword } = await import("https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js");
    const email = authForm.elements.namedItem("email").value.trim().toLowerCase();
    const password = authForm.elements.namedItem("password").value;
    let user;

    if (state.mode === "register") {
      if (password !== authForm.elements.namedItem("password_confirmation").value) {
        throw new Error("Konfirmasi kata sandi tidak sama.");
      }
      document.getElementById("auth-progress-label").textContent = "Membuat akun JobAgent...";
      user = (await createUserWithEmailAndPassword(auth, email, password)).user;
      const synced = await syncFirebaseSession(user, "register");
      if (!synced) {
        await sendVerificationLink(user);
        await showEmailVerificationMessage();
      }
    } else {
      document.getElementById("auth-progress-label").textContent = "Memverifikasi akun...";
      user = (await signInWithEmailAndPassword(auth, email, password)).user;
      await user.reload();
      if (!user.emailVerified) {
        await sendVerificationLink(user);
        throw new Error("Email belum terverifikasi. Tautan verifikasi baru sudah dikirim.");
      }
      await syncFirebaseSession(user, "login");
    }
  } catch (error) {
    const messages = {
      "auth/email-already-in-use": "Email sudah memiliki akun. Silakan masuk.",
      "auth/invalid-credential": "Email atau kata sandi tidak cocok. Jika mendaftar dengan Google, gunakan tombol Google.",
      "auth/invalid-email": "Format email tidak valid.",
      "auth/weak-password": "Kata sandi harus terdiri dari minimal 6 karakter.",
      "auth/operation-not-allowed": "Pendaftaran melalui email belum diaktifkan. Hubungi admin JobAgent.",
      "auth/too-many-requests": "Terlalu banyak percobaan. Coba lagi nanti.",
      "auth/unauthorized-domain": "Alamat domain ini belum diizinkan untuk masuk. Hubungi admin JobAgent.",
      "auth/user-disabled": "Akun ini dinonaktifkan. Hubungi administrator JobAgent.",
      "auth/api-key-not-valid": "Konfigurasi masuk belum siap. Coba lagi beberapa saat atau hubungi admin JobAgent.",
    };
    const message = error.status === 404
      ? "Profil JobAgent belum ada. Pilih Daftar untuk membuat profil dengan akun ini."
      : messages[firebaseErrorCode(error)] || error.message;
    errorFor(new Error(message), errorBox);
  } finally {
    processing.hidden = true;
    submit.classList.remove("is-loading");
    submit.disabled = false;
  }
});

document.getElementById("google-auth").addEventListener("click", async (event) => {
  const errorBox = document.getElementById("auth-error");
  errorBox.hidden = true;
  if (state.mode === "register") {
    const requiredFields = document.querySelectorAll("[data-profile]:not([hidden]) input[required]");
    for (const field of requiredFields) {
      if (!field.reportValidity()) return;
    }
  }
  const button = event.currentTarget;
  button.disabled = true;
  document.getElementById("auth-processing").hidden = false;
  try {
    const [{ GoogleAuthProvider, signInWithPopup }, auth] = await Promise.all([
      import("https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js"),
      firebaseAuth(),
    ]);
    const provider = new GoogleAuthProvider();
    provider.setCustomParameters({ prompt: "select_account" });
    const user = (await signInWithPopup(auth, provider)).user;
    await syncFirebaseSession(user, state.mode);
  } catch (error) {
    const messages = {
      "auth/popup-closed-by-user": "Proses masuk Google dibatalkan.",
      "auth/popup-blocked": "Izinkan pop-up untuk masuk dengan Google.",
      "auth/operation-not-allowed": "Login Google belum diaktifkan. Hubungi admin JobAgent.",
      "auth/unauthorized-domain": "Alamat domain ini belum diizinkan untuk masuk. Hubungi admin JobAgent.",
      "auth/account-exists-with-different-credential": "Email ini sudah terdaftar menggunakan metode lain. Masuk dengan metode awal untuk akun tersebut.",
      "auth/api-key-not-valid": "Konfigurasi masuk belum siap. Coba lagi beberapa saat atau hubungi admin JobAgent.",
    };
    const message = error.status === 404 && state.mode === "login"
      ? "Profil JobAgent belum ada. Pilih Daftar untuk membuat profil dengan akun Google ini."
      : messages[firebaseErrorCode(error)] || error.message;
    errorFor(new Error(message), errorBox);
  } finally {
    document.getElementById("auth-processing").hidden = true;
    button.disabled = false;
  }
});

document.getElementById("forgot-password").addEventListener("click", async () => {
  const errorBox = document.getElementById("auth-error");
  errorBox.hidden = true;
  const email = authForm.elements.namedItem("email").value.trim().toLowerCase();
  if (!email) {
    authForm.elements.namedItem("email").focus();
    errorFor(new Error("Masukkan email terlebih dahulu untuk menerima tautan reset."), errorBox);
    return;
  }
  try {
    const [{ sendPasswordResetEmail }, auth] = await Promise.all([
      import("https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js"),
      firebaseAuth(),
    ]);
    const returnUrl = `${window.location.origin}/password/reset?status=complete`;
    await sendPasswordResetEmail(auth, email, { url: returnUrl, handleCodeInApp: false });
    showToast("Jika akun tersedia, tautan reset kata sandi akan dikirim.");
  } catch (error) {
    if (error.code === "auth/invalid-email") {
      errorFor(new Error("Format email tidak valid."), errorBox);
    } else {
      showToast("Jika akun tersedia, tautan reset kata sandi akan dikirim ke email tersebut.");
    }
  }
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
