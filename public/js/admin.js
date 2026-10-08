const token = document.querySelector('meta[name="csrf-token"]').content;
const userModal = document.getElementById("user-modal");
const userForm = document.getElementById("user-form");

window.JobAgentFirebaseSignOut = async () => {
  const [{ getApps, initializeApp }, { getAuth, signOut }] = await Promise.all([
    import("https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js"),
    import("https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js"),
  ]);
  const app = getApps().find((item) => item.name === "jobagent-browser")
    || initializeApp(window.JobAgent.config, "jobagent-browser");
  await signOut(getAuth(app));
};

async function adminRequest(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": token,
      "X-Requested-With": "XMLHttpRequest",
      ...options.headers,
    },
  });
  const result = await response.json();
  if (!response.ok) {
    const message = result.error || result.message || Object.values(result.errors || {}).flat()[0];
    const error = new Error(message || "Permintaan admin gagal.");
    error.payload = result;
    throw error;
  }
  return result;
}

function notify(message) {
  const toast = document.getElementById("toast");
  document.getElementById("toast-message").textContent = message;
  toast.hidden = false;
  window.clearTimeout(notify.timeout);
  notify.timeout = window.setTimeout(() => { toast.hidden = true; }, 3200);
}

function hideModal(modal) {
  modal.hidden = true;
  if (!document.querySelector(".modal-backdrop:not([hidden])")) document.body.classList.remove("modal-open");
}

function openUser(user = null) {
  userForm.reset();
  document.getElementById("user-form-error").hidden = true;
  document.getElementById("user-modal-title").textContent = user ? "Perbarui akun." : "Tambah akun.";
  document.getElementById("user-modal-description").textContent = user
    ? "Perubahan alamat email mengharuskan pemilik akun memverifikasi ulang email lewat tautan yang dikirim."
    : "Akun baru dapat masuk setelah alamat emailnya diverifikasi lewat tautan yang dikirim ke email tersebut.";
  if (user) Object.entries(user).forEach(([key, value]) => {
    if (userForm.elements.namedItem(key)) userForm.elements.namedItem(key).value = value ?? "";
  });
  userForm.elements.namedItem("company_name").required = userForm.elements.namedItem("role").value === "employer";
  userModal.hidden = false;
  document.body.classList.add("modal-open");
}

document.addEventListener("click", async (event) => {
  if (event.target.closest("[data-add-user]")) openUser();
  const editButton = event.target.closest("[data-edit-user]");
  if (editButton) openUser(JSON.parse(editButton.dataset.user));
  if (event.target.closest("[data-close]") || event.target === userModal || event.target === document.getElementById("admin-health-modal")) {
    hideModal(event.target.closest(".modal-backdrop") || event.target);
  }

  const deleteButton = event.target.closest("[data-delete-user]");
  if (deleteButton && window.confirm(`Hapus akun ${deleteButton.dataset.userName}? Lowongan dan lamaran yang terkait juga akan dihapus.`)) {
    try {
      const result = await adminRequest(`/api/admin/users/${deleteButton.dataset.deleteUser}`, { method: "DELETE" });
      notify(result.message);
      window.setTimeout(() => window.location.reload(), 700);
    } catch (error) {
      notify(error.message);
    }
  }

  if (event.target.closest("[data-admin-logout]")) {
    try {
      try {
        await window.JobAgentFirebaseSignOut?.();
      } catch (error) {
        console.warn("Firebase sign-out failed; ending the administrator session anyway.", error);
      }
      await adminRequest("/api/auth/logout", { method: "POST", body: "{}" });
      window.location.assign("/admin/login");
    } catch (error) {
      notify(error.message);
    }
  }

  if (event.target.closest("[data-admin-health]")) {
    const modal = document.getElementById("admin-health-modal");
    modal.hidden = false;
    document.body.classList.add("modal-open");
    checkHealth();
  }
});

userForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(userForm).entries());
  const id = data.id;
  delete data.id;
  const error = document.getElementById("user-form-error");
  const button = userForm.querySelector('[type="submit"]');
  error.hidden = true;
  button.disabled = true;
  button.classList.add("is-loading");
  try {
    const result = await adminRequest(id ? `/api/admin/users/${id}` : "/api/admin/users", {
      method: id ? "PATCH" : "POST",
      body: JSON.stringify(data),
    });

    hideModal(userModal);
    notify(id ? `Akun ${result.user.name} berhasil diperbarui.` : `Akun ${result.user.name} berhasil ditambahkan.`);
    window.setTimeout(() => window.location.reload(), 700);
  } catch (exception) {
    error.textContent = exception.message;
    error.hidden = false;
  } finally {
    button.disabled = false;
    button.classList.remove("is-loading");
  }
});

async function checkHealth() {
  document.querySelectorAll("[data-admin-check]").forEach((item) => {
    item.textContent = "Memeriksa...";
    item.dataset.status = "pending";
  });
  try {
    const result = await adminRequest("/api/health");
    updateChecks(result.checks);
    document.getElementById("admin-health-time").textContent = `Pemeriksaan ${new Date(result.checkedAt).toLocaleString("id-ID")}`;
  } catch (error) {
    if (error.payload?.checks) updateChecks(error.payload.checks);
    else document.querySelectorAll("[data-admin-check]").forEach((item) => {
      item.textContent = "Tidak dapat dijangkau";
      item.dataset.status = "error";
    });
  }
}

function updateChecks(checks) {
  Object.entries(checks).forEach(([key, status]) => {
    const item = document.querySelector(`[data-admin-check="${key}"]`);
    if (item) {
      item.textContent = status === "ok" ? "Beroperasi" : "Perlu diperiksa";
      item.dataset.status = status;
    }
  });
}

userForm.elements.namedItem("role").addEventListener("change", (event) => {
  userForm.elements.namedItem("company_name").required = event.target.value === "employer";
});

window.addEventListener("error", () => notify("Ada kendala pada dashboard. Coba muat ulang halaman."));
window.addEventListener("unhandledrejection", () => notify("Permintaan gagal. Periksa koneksi lalu coba kembali."));
