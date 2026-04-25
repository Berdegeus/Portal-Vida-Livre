document.addEventListener("DOMContentLoaded", async () => {
  // Verify admin session via a dedicated endpoint; redirect if not authenticated.
  try {
    const response = await PortalVidaLivreApi.get("admin-me.php");
    const admin = response.data?.admin;

    const nameEl = document.querySelector("[data-admin-name]");
    const emailEl = document.querySelector("[data-admin-email]");
    if (nameEl) nameEl.textContent = admin?.name || "";
    if (emailEl) emailEl.textContent = admin?.email || "";
  } catch {
    window.location.replace("/frontend/admin-login.html");
    return;
  }

  document.querySelector("[data-admin-logout]")?.addEventListener("click", async () => {
    try {
      await PortalVidaLivreApi.post("admin-logout.php", {}, { csrf: true });
    } finally {
      window.location.replace("/frontend/admin-login.html");
    }
  });
});
