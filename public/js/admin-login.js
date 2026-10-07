const csrf = document.querySelector('meta[name="csrf-token"]').content;
const form = document.getElementById("admin-login-form");
let otpStep = false;

async function postJson(url, payload) {
  const response = await fetch(url, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": csrf,
      "X-Requested-With": "XMLHttpRequest",
    },
    body: JSON.stringify(payload),
  });
  const result = await response.json();
  if (!response.ok) throw new Error(result.error || result.message || "Permintaan tidak berhasil.");
  return result;
}

async function firebaseSignIn(token) {
  const [{ initializeApp }, { getAuth, signInWithCustomToken }] = await Promise.all([
    import("https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js"),
    import("https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js"),
  ]);
  await signInWithCustomToken(getAuth(initializeApp(window.JobAgent.config, "jobagent-admin")), token);
}

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  const data = new FormData(form);
  const button = document.getElementById("admin-login-submit");
  const error = document.getElementById("admin-login-error");
  const progress = document.getElementById("admin-processing");
  error.hidden = true;
  progress.hidden = false;
  button.disabled = true;
  button.classList.add("is-loading");
  try {
    if (!otpStep) {
      document.getElementById("admin-progress-label").textContent = "Memeriksa akun admin dan menyiapkan OTP...";
      await postJson("/api/auth/request-otp", { email: data.get("email"), mode: "login", role: "admin" });
      otpStep = true;
      document.getElementById("admin-code-label").hidden = false;
      document.getElementById("admin-code-label").querySelector("input").required = true;
      document.getElementById("admin-login-form").querySelector('[name="email"]').required = false;
      document.getElementById("admin-resend").hidden = false;
      button.innerHTML = "Verifikasi identitas admin <span>→</span>";
      document.getElementById("admin-progress-label").textContent = "Kode OTP dikirim. Periksa email admin.";
      return;
    }
    document.getElementById("admin-progress-label").textContent = "Mencocokkan kode dan mengamankan sesi administrator...";
    const result = await postJson("/api/auth/verify-otp", {
      email: data.get("email"),
      code: data.get("code"),
      role: "admin",
    });
    try {
      await firebaseSignIn(result.firebaseCustomToken);
    } catch {
      document.getElementById("admin-progress-label").textContent = "Sesi server aktif. Firebase Web belum tersambung.";
    }
    document.getElementById("admin-progress-label").textContent = "Akses diberikan. Menyiapkan dashboard...";
    window.location.assign("/admin");
  } catch (exception) {
    error.textContent = exception.message;
    error.hidden = false;
  } finally {
    progress.hidden = true;
    button.classList.remove("is-loading");
    button.disabled = false;
  }
});

document.getElementById("admin-resend").addEventListener("click", () => {
  otpStep = false;
  document.getElementById("admin-code-label").hidden = true;
  document.getElementById("admin-code-label").querySelector("input").required = false;
  const email = form.querySelector('[name="email"]');
  email.required = true;
  document.getElementById("admin-resend").hidden = true;
  document.getElementById("admin-login-submit").innerHTML = "Kirim kode verifikasi <span>→</span>";
  email.focus();
});
