document.addEventListener("DOMContentLoaded", async () => {
  const STATES = ["state-loading", "state-success", "state-error", "state-manual"];

  const show = (id) => {
    STATES.forEach((s) => {
      document.getElementById(s)?.classList.toggle("hidden", s !== id);
    });
  };

  const handleNextStep = (data) => {
    if (data.requires_2fa) {
      if (data.step === "vinculacao") {
        sessionStorage.setItem("admin_2fa_codigo", data.codigo);
        sessionStorage.setItem("admin_2fa_bot", data.bot_username);
        window.location.replace("/frontend/admin-vinculacao.html");
      } else if (data.step === "security_questions") {
        window.location.replace("/frontend/admin-security-questions.html");
      } else {
        window.location.replace("/frontend/admin-2fa.html");
      }
    } else {
      show("state-success");
      setTimeout(() => window.location.replace("/frontend/admin-dashboard.html"), 1200);
    }
  };

  const token = new URLSearchParams(window.location.search).get("token") || "";

  if (!token) {
    show("state-manual");
    initManualForm();
    return;
  }

  // Fluxo via link (token na URL)
  try {
    await PortalVidaLivreApi.getCsrfToken();
    const result = await PortalVidaLivreApi.post("admin-verify-token.php", { token }, { csrf: true });
    handleNextStep(result.data);
  } catch (error) {
    const msg = error.message || "O link expirou ou ja foi utilizado.";
    document.getElementById("error-message").textContent = msg;
    show("state-error");
  }

  function initManualForm() {
    const form    = document.getElementById("form-codigo");
    const input   = document.getElementById("input-codigo");
    const btn     = document.getElementById("btn-verify");
    const msgErr  = document.getElementById("msg-error");

    input.addEventListener("input", () => {
      input.value = input.value.replace(/\D/g, "").slice(0, 6);
    });

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const codigo = input.value.trim();

      if (codigo.length !== 6) {
        showError("Informe os 6 dígitos do código.");
        return;
      }

      btn.disabled = true;
      msgErr.classList.add("hidden");

      try {
        await PortalVidaLivreApi.getCsrfToken();
        const result = await PortalVidaLivreApi.post(
          "admin-verify-codigo.php",
          { codigo },
          { csrf: true }
        );
        handleNextStep(result.data);
      } catch (error) {
        showError(error.message || "Código inválido ou expirado.");
        btn.disabled = false;
      }
    });

    function showError(msg) {
      msgErr.textContent = msg;
      msgErr.classList.remove("hidden");
    }
  }
});
