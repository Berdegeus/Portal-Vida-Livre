document.addEventListener("DOMContentLoaded", async () => {
  const codigo = sessionStorage.getItem("admin_2fa_codigo");
  const botUsername = sessionStorage.getItem("admin_2fa_bot");

  if (!codigo) {
    window.location.replace("/frontend/admin-login.html");
    return;
  }

  document.getElementById("codigo-display").textContent = codigo;

  const botLink = document.getElementById("bot-link");
  botLink.href = `https://t.me/${botUsername}`;
  botLink.textContent = `@${botUsername}`;

  const show = (id) => {
    ["state-waiting", "state-success", "state-expired", "state-error"].forEach((s) => {
      document.getElementById(s)?.classList.toggle("hidden", s !== id);
    });
  };

  await PortalVidaLivreApi.getCsrfToken();

  let pollTimer = null;

  const stopPolling = () => {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  };

  const poll = async () => {
    try {
      const result = await PortalVidaLivreApi.post(
        "admin-2fa-poll-vinculacao.php",
        {},
        { csrf: true }
      );
      const data = result.data;

      if (data.vinculado) {
        stopPolling();
        sessionStorage.removeItem("admin_2fa_codigo");
        sessionStorage.removeItem("admin_2fa_bot");
        show("state-success");
        setTimeout(() => window.location.replace("/frontend/admin-dashboard.html"), 1500);
        return;
      }

      if (data.expirado) {
        stopPolling();
        show("state-expired");
        return;
      }
    } catch (err) {
      stopPolling();
      document.getElementById("error-message").textContent =
        err.message || "Sessão expirada. Solicite um novo link de acesso.";
      show("state-error");
    }
  };

  pollTimer = setInterval(poll, 3000);
  poll();

  document.getElementById("btn-reenviar")?.addEventListener("click", async () => {
    const btn = document.getElementById("btn-reenviar");
    const errEl = document.getElementById("reenviar-error");
    errEl.classList.add("hidden");
    btn.disabled = true;
    btn.textContent = "Aguarde...";

    try {
      const result = await PortalVidaLivreApi.post(
        "admin-2fa-reenviar.php",
        {},
        { csrf: true }
      );
      const novoCodigo = result.data.codigo;
      sessionStorage.setItem("admin_2fa_codigo", novoCodigo);
      document.getElementById("codigo-display").textContent = novoCodigo;

      show("state-waiting");
      pollTimer = setInterval(poll, 3000);
      poll();

      let segundos = 30;
      btn.textContent = `Aguarde ${segundos}s...`;
      const countdown = setInterval(() => {
        segundos--;
        if (segundos <= 0) {
          clearInterval(countdown);
          btn.disabled = false;
          btn.textContent = "Gerar novo código";
        } else {
          btn.textContent = `Aguarde ${segundos}s...`;
        }
      }, 1000);
    } catch (err) {
      errEl.textContent = err.message || "Não foi possível gerar novo código.";
      errEl.classList.remove("hidden");
      btn.disabled = false;
      btn.textContent = "Gerar novo código";
    }
  });
});
