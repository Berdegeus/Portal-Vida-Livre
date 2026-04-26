document.addEventListener("DOMContentLoaded", async () => {
  const form = document.getElementById("form-2fa");
  const codigoInput = document.getElementById("codigo");
  const submitBtn = document.getElementById("submit-btn");
  const btnReenviar = document.getElementById("btn-reenviar");
  const msgError = document.getElementById("msg-error");
  const msgSuccess = document.getElementById("msg-success");
  const codigoError = document.getElementById("codigo-error");

  await PortalVidaLivreApi.getCsrfToken();

  const showError = (msg) => {
    msgError.textContent = msg;
    msgError.classList.remove("hidden");
    msgSuccess.classList.add("hidden");
  };

  const showSuccess = (msg) => {
    msgSuccess.textContent = msg;
    msgSuccess.classList.remove("hidden");
    msgError.classList.add("hidden");
  };

  const clearMessages = () => {
    msgError.classList.add("hidden");
    msgSuccess.classList.add("hidden");
    codigoError.textContent = "";
    codigoInput.classList.remove("invalid");
  };

  codigoInput.addEventListener("input", () => {
    codigoInput.value = codigoInput.value.replace(/\D/g, "").slice(0, 6);
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearMessages();

    const codigo = codigoInput.value.trim();

    if (!/^\d{6}$/.test(codigo)) {
      codigoError.textContent = "Informe o código de 6 dígitos.";
      codigoInput.classList.add("invalid");
      codigoInput.focus();
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = "Verificando...";

    try {
      await PortalVidaLivreApi.post("admin-2fa-verify-login.php", { codigo }, { csrf: true });
      showSuccess("Acesso autorizado. Redirecionando...");
      submitBtn.textContent = "Verificando...";
      setTimeout(() => window.location.replace("/frontend/admin-dashboard.html"), 1200);
    } catch (err) {
      if (err.errors?.codigo?.[0]) {
        codigoError.textContent = err.errors.codigo[0];
        codigoInput.classList.add("invalid");
      } else {
        showError(err.message || "Código inválido ou expirado.");
      }
      submitBtn.disabled = false;
      submitBtn.textContent = "Verificar";
      codigoInput.focus();
    }
  });

  btnReenviar.addEventListener("click", async () => {
    clearMessages();
    btnReenviar.disabled = true;

    try {
      await PortalVidaLivreApi.post("admin-2fa-reenviar.php", {}, { csrf: true });
      showSuccess("Novo código enviado pelo Telegram.");

      let segundos = 30;
      btnReenviar.textContent = `Aguarde ${segundos}s`;
      const countdown = setInterval(() => {
        segundos--;
        if (segundos <= 0) {
          clearInterval(countdown);
          btnReenviar.disabled = false;
          btnReenviar.textContent = "Reenviar código";
        } else {
          btnReenviar.textContent = `Aguarde ${segundos}s`;
        }
      }, 1000);
    } catch (err) {
      showError(err.message || "Não foi possível reenviar o código.");
      btnReenviar.disabled = false;
      btnReenviar.textContent = "Reenviar código";
    }
  });
});
