document.addEventListener("DOMContentLoaded", async () => {
  // ── Elementos ──────────────────────────────────────────────────────────────
  const sectionEmail  = document.getElementById("section-email");
  const sectionCodigo = document.getElementById("section-codigo");
  const footerVoltar  = document.getElementById("footer-voltar");

  // Fase 1
  const loginForm  = document.getElementById("admin-login-form");
  const submitBtn  = document.getElementById("submit-btn");
  const msgBox     = document.querySelector("[data-message]");

  // Fase 2
  const codigoForm      = document.getElementById("codigo-form");
  const inputCodigo     = document.getElementById("input-codigo");
  const submitCodigoBtn = document.getElementById("submit-codigo-btn");
  const codigoMsgBox    = document.querySelector("[data-codigo-message]");
  const codigoFieldErr  = document.getElementById("codigo-field-error");
  const btnReenviar     = document.getElementById("btn-reenviar");

  let emailSalvo = "";
  let reenviarTimeout = null;

  // ── Helpers ────────────────────────────────────────────────────────────────
  const showMsg = (box, text, type = "error") => {
    if (!box) return;
    box.textContent = text;
    box.className = `admin-message admin-message-${type}`;
  };

  const clearMsg = (box) => {
    if (!box) return;
    box.textContent = "";
    box.className = "admin-message hidden";
  };

  const setFieldError = (input, errEl, msg) => {
    if (input) input.style.borderColor = msg ? "#f87171" : "";
    if (errEl) errEl.textContent = msg;
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
      window.location.replace("/frontend/admin-dashboard.html");
    }
  };

  const mostrarFase2Telegram = () => {
    const title = document.getElementById("section-codigo-title");
    const hint  = document.getElementById("section-codigo-hint");
    if (title) title.textContent = "Código Telegram";
    if (hint)  hint.innerHTML = "Enviamos um <strong>código de 6 dígitos</strong> ao seu Telegram. Insira-o abaixo para acessar o painel.";
    sectionEmail.style.display  = "none";
    sectionCodigo.style.display = "block";
    footerVoltar.style.display  = "none";
    inputCodigo.focus();
  };

  // ── Inicialização ──────────────────────────────────────────────────────────
  try {
    await PortalVidaLivreApi.getCsrfToken();
  } catch {
    showMsg(msgBox, "Não foi possível iniciar o formulário. Recarregue a página.");
  }

  // ── Fase 1: envio do e-mail ────────────────────────────────────────────────
  loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearMsg(msgBox);
    loginForm.querySelectorAll(".field-error").forEach((el) => (el.textContent = ""));
    loginForm.querySelectorAll("input").forEach((el) => el.classList.remove("invalid"));

    const email = (loginForm.querySelector("[name='email']")?.value || "").trim().toLowerCase();

    if (!/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/.test(email)) {
      const errEl = loginForm.querySelector("[data-error-for='email']");
      loginForm.querySelector("[name='email']")?.classList.add("invalid");
      if (errEl) errEl.textContent = "Informe um e-mail válido.";
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = "Enviando...";

    try {
      const response = await PortalVidaLivreApi.post("admin-request-login.php", { email }, { csrf: true });

      const d = response.data || {};

      if (d.fallback === "security_questions") {
        window.location.replace("/frontend/admin-security-questions.html");
        return;
      }

      if (d.step === "vinculacao") {
        sessionStorage.setItem("admin_2fa_codigo", d.codigo || "");
        sessionStorage.setItem("admin_2fa_bot", d.bot_username || "");
        window.location.replace("/frontend/admin-vinculacao.html");
        return;
      }

      // step === 'telegram_code': admin vinculado, código enviado ao Telegram
      emailSalvo = email;
      mostrarFase2Telegram();
    } catch (error) {
      if (error.errors?.email) {
        const errEl = loginForm.querySelector("[data-error-for='email']");
        loginForm.querySelector("[name='email']")?.classList.add("invalid");
        if (errEl) errEl.textContent = error.errors.email[0];
      } else {
        showMsg(msgBox, error.message || "Não foi possível processar a solicitação.");
      }
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = "Enviar link de acesso";
    }
  });

  // ── Fase 2: validação do código ────────────────────────────────────────────
  inputCodigo.addEventListener("input", () => {
    inputCodigo.value = inputCodigo.value.replace(/\D/g, "").slice(0, 6);
    setFieldError(inputCodigo, codigoFieldErr, "");
    clearMsg(codigoMsgBox);
  });

  codigoForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const codigo = inputCodigo.value.trim();

    if (codigo.length !== 6) {
      setFieldError(inputCodigo, codigoFieldErr, "Informe os 6 dígitos do código.");
      return;
    }

    submitCodigoBtn.disabled = true;
    submitCodigoBtn.textContent = "Verificando...";
    clearMsg(codigoMsgBox);
    setFieldError(inputCodigo, codigoFieldErr, "");

    try {
      const result = await PortalVidaLivreApi.post("admin-2fa-verify-login.php", { codigo }, { csrf: true });
      handleNextStep(result.data);
    } catch (error) {
      showMsg(codigoMsgBox, error.message || "Código inválido ou expirado.");
      submitCodigoBtn.disabled = false;
      submitCodigoBtn.textContent = "Verificar código";
    }
  });

  // ── Reenvio ────────────────────────────────────────────────────────────────
  const iniciarCooldownReenviar = (segundos) => {
    btnReenviar.disabled = true;
    let restante = segundos;
    btnReenviar.textContent = `Reenviar código (${restante}s)`;
    reenviarTimeout = setInterval(() => {
      restante -= 1;
      if (restante <= 0) {
        clearInterval(reenviarTimeout);
        btnReenviar.disabled = false;
        btnReenviar.textContent = "Reenviar código";
      } else {
        btnReenviar.textContent = `Reenviar código (${restante}s)`;
      }
    }, 1000);
  };

  btnReenviar.addEventListener("click", async () => {
    if (!emailSalvo) return;
    clearMsg(codigoMsgBox);
    btnReenviar.disabled = true;
    btnReenviar.textContent = "Enviando...";

    try {
      await PortalVidaLivreApi.post("admin-2fa-reenviar.php", {}, { csrf: true });
      showMsg(codigoMsgBox, "Código reenviado ao seu Telegram.", "success");
      inputCodigo.value = "";
      iniciarCooldownReenviar(60);
    } catch (error) {
      showMsg(codigoMsgBox, error.message || "Não foi possível reenviar o código.");
      btnReenviar.disabled = false;
      btnReenviar.textContent = "Reenviar código";
    }
  });
});
