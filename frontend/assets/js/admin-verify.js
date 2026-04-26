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
    const result = await PortalVidaLivreApi.post("admin-verify-token.php", { token }, { csrf: true });
    const data = result.data;

    if (data.requires_2fa) {
      if (data.step === "vinculacao") {
        sessionStorage.setItem("admin_2fa_codigo", data.codigo);
        sessionStorage.setItem("admin_2fa_bot", data.bot_username);
        window.location.replace("/frontend/admin-vinculacao.html");
      } else {
        window.location.replace("/frontend/admin-2fa.html");
      }
      return;
    }

    show("state-success");
    setTimeout(() => window.location.replace("/frontend/admin-dashboard.html"), 1200);
  } catch (error) {
    const msg = error.message || "O link expirou ou ja foi utilizado.";
    document.getElementById("error-message").textContent = msg;
    show("state-error");
  }
});
