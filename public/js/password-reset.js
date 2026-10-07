const resetForm = document.getElementById("reset-form");
const resetMessage = document.getElementById("reset-message");
const resetError = document.getElementById("reset-error");
const query = new URLSearchParams(window.location.search);
const actionCode = query.get("oobCode");

function showResetError(message) {
  resetError.textContent = message;
  resetError.hidden = false;
}

function firebaseErrorMessage(error) {
  const messages = {
    "auth/expired-action-code": "Tautan reset sudah kedaluwarsa. Minta tautan reset baru dari halaman masuk.",
    "auth/invalid-action-code": "Tautan reset tidak valid atau sudah pernah digunakan.",
    "auth/user-disabled": "Akun ini telah dinonaktifkan.",
    "auth/user-not-found": "Akun Firebase tidak ditemukan.",
    "auth/weak-password": "Gunakan kata sandi dengan minimal 6 karakter.",
    "auth/unauthorized-domain": "Domain halaman reset belum diizinkan di Firebase Authentication.",
    "auth/operation-not-allowed": "Aktifkan metode Email/Password di Firebase Authentication.",
  };
  return messages[error.code] || "Tautan reset tidak dapat diproses. Minta tautan baru dan coba kembali.";
}

async function loadResetForm() {
  if (query.get("mode") !== "resetPassword" || !actionCode) {
    resetMessage.textContent = "Tautan reset tidak lengkap. Minta tautan reset baru dari halaman masuk.";
    return null;
  }

  try {
    const [{ getApps, initializeApp }, { getAuth, verifyPasswordResetCode }] = await Promise.all([
      import("https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js"),
      import("https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js"),
    ]);
    const app = getApps().find((item) => item.name === "jobagent-browser")
      || initializeApp(window.JobAgent.config, "jobagent-browser");
    const auth = getAuth(app);
    const email = await verifyPasswordResetCode(auth, actionCode);
    document.getElementById("reset-email").value = email;
    resetMessage.textContent = "Buat kata sandi baru untuk mengamankan akun JobAgent Anda.";
    resetForm.hidden = false;
    return { auth, email };
  } catch (error) {
    resetMessage.textContent = firebaseErrorMessage(error);
    return null;
  }
}

const resetContextPromise = loadResetForm();

resetForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  resetError.hidden = true;
  const password = document.getElementById("new-password").value;
  const confirmation = document.getElementById("confirm-password").value;
  if (password !== confirmation) {
    showResetError("Konfirmasi kata sandi tidak sama.");
    return;
  }

  const submit = document.getElementById("reset-submit");
  submit.disabled = true;
  document.getElementById("reset-message").textContent = "Memperbarui kata sandi dan menyiapkan sesi akun...";
  try {
    const context = await resetContextPromise;
    if (!context) throw new Error("Tautan reset tidak lagi valid. Minta tautan reset baru.");

    const { confirmPasswordReset, signInWithEmailAndPassword } = await import("https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js");
    await confirmPasswordReset(context.auth, actionCode, password);
    const user = (await signInWithEmailAndPassword(context.auth, context.email, password)).user;
    await user.reload();
    if (!user.emailVerified) {
      throw new Error("Kata sandi diperbarui. Verifikasi email akun sebelum masuk ke JobAgent.");
    }

    const idToken = await user.getIdToken(true);
    const response = await fetch("/api/auth/firebase-session", {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
        "X-Requested-With": "XMLHttpRequest",
        Authorization: `Bearer ${idToken}`,
      },
      body: JSON.stringify({ mode: "login" }),
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || "Tidak dapat membuat sesi JobAgent.");

    window.location.assign(result.user.role === "admin" ? "/admin" : "/");
  } catch (error) {
    showResetError(error.code ? firebaseErrorMessage(error) : error.message);
    document.getElementById("reset-message").textContent = "Periksa kembali kata sandi baru Anda.";
  } finally {
    submit.disabled = false;
  }
});
