const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const role = document.body.dataset.role;
const list = document.getElementById("application-list");
const statuses = {
  submitted: "Baru masuk",
  reviewing: "Sedang ditinjau",
  interview: "Wawancara",
  rejected: "Tidak dilanjutkan",
  hired: "Diterima",
};

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, (character) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;",
  })[character]);
}

function formatDate(value) {
  return value ? new Date(value).toLocaleDateString("id-ID", { day: "numeric", month: "long", year: "numeric" }) : "—";
}

function showMessage(message) {
  list.innerHTML = `<div class="application-message">${escapeHtml(message)}</div>`;
}

function renderApplication(application) {
  const employer = role === "employer";
  const detail = employer
    ? `<div class="application-person"><strong>${escapeHtml(application.candidate.name)}</strong><a href="mailto:${encodeURIComponent(application.candidate.email)}">${escapeHtml(application.candidate.email)}</a><span>${escapeHtml(application.candidate.headline || "Profil belum dilengkapi")}</span><span>${escapeHtml([application.candidate.location, application.candidate.phone].filter(Boolean).join(" · ") || "Detail kontak belum tersedia")}</span></div>`
    : `<div class="application-person"><strong>${escapeHtml(application.companyName)}</strong><span>${escapeHtml(application.location)}</span></div>`;
  const status = employer
    ? `<label class="application-status-control">Status seleksi<select data-status-for="${Number(application.id)}">${Object.entries(statuses).map(([value, label]) => `<option value="${value}"${application.status === value ? " selected" : ""}>${label}</option>`).join("")}</select></label><button class="button button-small" data-save-status="${Number(application.id)}">Simpan status</button>`
    : `<span class="application-status status-${escapeHtml(application.status)}">${escapeHtml(statuses[application.status] || "Dalam proses")}</span>`;

  return `<article class="application-card"><div class="application-card-heading"><div><span class="section-kicker">${employer ? "KANDIDAT MELAMAR" : "POSISI YANG DILAMAR"}</span><h2>${escapeHtml(application.jobTitle)}</h2></div>${status}</div>${detail}<div class="application-card-footer"><span>Dikirim ${escapeHtml(formatDate(application.appliedAt))}</span>${employer ? "" : `<span>Diperbarui ${escapeHtml(formatDate(application.updatedAt))}</span>`}</div></article>`;
}

async function loadApplications() {
  try {
    const response = await fetch("/api/applications", { headers: { Accept: "application/json" } });
    const result = await response.json();
    if (response.status === 401) {
      window.location.assign("/?login=account");
      return;
    }
    if (!response.ok) throw new Error(result.error || "Lamaran gagal dimuat.");
    if (!result.applications.length) {
      showMessage(role === "employer" ? "Belum ada lamaran masuk untuk lowonganmu." : "Kamu belum mengirim lamaran. Temukan peluang di halaman lowongan.");
      return;
    }
    list.innerHTML = result.applications.map(renderApplication).join("");
  } catch (error) {
    showMessage(error.message || "Lamaran gagal dimuat. Coba muat ulang halaman.");
  }
}

list.addEventListener("click", async (event) => {
  const button = event.target.closest("[data-save-status]");
  if (!button) return;
  button.disabled = true;
  try {
    const response = await fetch(`/api/applications/${button.dataset.saveStatus}`, {
      method: "PATCH",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken,
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({ status: list.querySelector(`[data-status-for="${button.dataset.saveStatus}"]`).value }),
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || result.message || "Status gagal diperbarui.");
    button.textContent = result.message;
    window.setTimeout(() => { button.textContent = "Simpan status"; }, 1800);
  } catch (error) {
    button.textContent = error.message || "Gagal menyimpan";
    window.setTimeout(() => { button.textContent = "Simpan status"; }, 2500);
  } finally {
    button.disabled = false;
  }
});

document.getElementById("applications-logout").addEventListener("click", async () => {
  const button = document.getElementById("applications-logout");
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
    console.warn("Firebase sign-out failed; ending the application session anyway.", error);
  }

  try {
    const response = await fetch("/api/auth/logout", {
      method: "POST",
      headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken, "X-Requested-With": "XMLHttpRequest" },
    });
    if (!response.ok) throw new Error("Sesi gagal diakhiri. Coba lagi.");
    window.location.assign("/");
  } catch (error) {
    button.disabled = false;
    document.getElementById("applications-toast-message").textContent = error.message;
    document.getElementById("applications-toast").hidden = false;
  }
});

loadApplications();
