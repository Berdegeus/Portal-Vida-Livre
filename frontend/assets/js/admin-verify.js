document.addEventListener("DOMContentLoaded", async () => {
  const show = (id) => {
    ["state-loading", "state-success", "state-error"].forEach((s) => {
      document.getElementById(s)?.classList.toggle("hidden", s !== id);
    });
  };

  const token = new URLSearchParams(window.location.search).get("token") || "";

  if (!token) {
    document.getElementById("error-message").textContent =
      "Nenhum token encontrado. Verifique o link no e-mail.";
    show("state-error");
    return;
  }

  try {
    await PortalVidaLivreApi.getCsrfToken();
    await PortalVidaLivreApi.post("admin-verify-token.php", { token }, { csrf: true });
    show("state-success");
    setTimeout(() => window.location.replace("/frontend/admin-dashboard.html"), 1200);
  } catch (error) {
    const msg = error.message || "O link expirou ou ja foi utilizado.";
    document.getElementById("error-message").textContent = msg;
    show("state-error");
  }
});
