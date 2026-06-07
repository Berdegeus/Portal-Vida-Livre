document.addEventListener("DOMContentLoaded", async () => {
  const redirectTarget = "/frontend/dashboard.html";

  // ── Seções ─────────────────────────────────────────────────────────────────
  const sectionLogin   = document.getElementById("section-login");
  const sectionOTP     = document.getElementById("section-telegram-code");
  const sectionVinc    = document.getElementById("section-telegram-vinculacao");

  // ── Fase 1 ──────────────────────────────────────────────────────────────────
  const form   = document.querySelector("#login-form");
  const msgBox = document.querySelector("[data-message]");

  // ── Fase 2a: OTP ────────────────────────────────────────────────────────────
  const tgCodeForm   = document.getElementById("tg-code-form");
  const inputCodigo  = document.getElementById("input-user-tg-codigo");
  const btnVerificar = document.getElementById("btn-user-tg-verificar");
  const tgCodeMsg    = document.querySelector("[data-tg-code-message]");
  const tgCodeErr    = document.getElementById("tg-code-field-error");
  const btnReenviar  = document.getElementById("btn-user-tg-reenviar");

  // ── Fase 2b: vinculação ──────────────────────────────────────────────────────
  const btnVinc   = document.getElementById("btn-user-tg-vinc");
  const tgVincMsg = document.querySelector("[data-tg-vinc-message]");

  let pollingInterval = null;

  if (!form) return;

  // ── Helpers ────────────────────────────────────────────────────────────────
  const showMsg = (box, text, type = "error") => {
    if (!box) return;
    box.textContent = text;
    box.className = `message message-${type}`;
  };

  const clearMsg = (box) => {
    if (!box) return;
    box.textContent = "";
    box.className = "message hidden";
  };

  const mostrarOTP = () => {
    if (sectionLogin) sectionLogin.style.display = "none";
    if (sectionOTP)   sectionOTP.style.display   = "block";
    if (inputCodigo)  inputCodigo.focus();
  };

  const mostrarVinculacao = (telegramUrl, botUsername, vincCode) => {
    if (sectionLogin) sectionLogin.style.display = "none";
    if (sectionVinc)  sectionVinc.style.display  = "block";
    if (btnVinc && telegramUrl) btnVinc.href = telegramUrl;
    const codeEl = document.getElementById("login-vinc-code-display");
    if (codeEl) codeEl.textContent = vincCode || "---";
    const botEl = document.getElementById("login-vinc-bot-username");
    if (botEl) botEl.textContent = botUsername || "VidaLivreBot";
    iniciarPollingVinculacao();
  };

  // ── CSRF ───────────────────────────────────────────────────────────────────
  try {
    await PortalVidaLivreApi.getCsrfToken();
  } catch {
    PortalVidaLivreAuth.showMessage("Nao foi possivel iniciar a sessao do formulario.", "error");
  }

  PortalVidaLivreAuth.bindTogglePassword(form);

  const captchaWidget = CaptchaWidget.init(document.getElementById("captcha-widget"));

  // ── Fase 1: submit ─────────────────────────────────────────────────────────
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    PortalVidaLivreAuth.clearMessage();
    PortalVidaLivreAuth.clearFieldErrors(form);

    const data = PortalVidaLivreAuth.formDataToObject(form);
    const errors = {};
    data.email = (data.email || "").trim().toLowerCase();

    if (!PortalVidaLivreAuth.isValidEmail(data.email || "")) {
      errors.email = ["Informe um e-mail valido."];
    }

    if (!data.password) {
      errors.password = ["Informe sua senha."];
    }

    const humanCheck = document.getElementById("captcha_human");
    if (!humanCheck || !humanCheck.checked) {
      PortalVidaLivreAuth.showMessage("Confirme que voce nao e um robo marcando a caixa.", "error");
      return;
    }

    if (Object.keys(errors).length > 0) {
      PortalVidaLivreAuth.applyErrors(form, errors);
      PortalVidaLivreAuth.showMessage("Verifique os campos informados.", "error");
      return;
    }

    try {
      const response = await PortalVidaLivreApi.post("login.php", {
        ...data,
        captcha_answer: captchaWidget ? captchaWidget.getAnswer() : "",
      }, { csrf: true });

      const d = response.data || {};

      if (d.step === "telegram_code") {
        mostrarOTP();
        return;
      }

      window.location.assign(redirectTarget);
    } catch (error) {
      const d = error.errors || {};

      if (d.step === "vinculacao" && d.telegram_url) {
        mostrarVinculacao(d.telegram_url, d.bot_username, d.vinc_code);
        return;
      }

      // Email ainda não verificado — mostrar botão de deep link de verificação
      if (d.telegram_url) {
        let btnTg = document.getElementById("btn-telegram-verify-login");
        if (!btnTg) {
          btnTg = document.createElement("a");
          btnTg.id = "btn-telegram-verify-login";
          btnTg.target = "_blank";
          btnTg.rel = "noopener noreferrer";
          btnTg.className = "btn btn--primary";
          btnTg.style.cssText = "display:inline-block;margin-top:12px;width:100%;text-align:center;box-sizing:border-box;";
          form.parentNode.appendChild(btnTg);
        }
        btnTg.href = d.telegram_url;
        btnTg.textContent = "Verificar conta via Telegram";
      }

      PortalVidaLivreAuth.applyErrors(form, d);
      PortalVidaLivreAuth.showMessage(error.message || "Nao foi possivel realizar o login.", "error");
      if (d.captcha && captchaWidget) captchaWidget.reset();
    }
  });

  // ── Fase 2a: input OTP ─────────────────────────────────────────────────────
  if (inputCodigo) {
    inputCodigo.addEventListener("input", () => {
      inputCodigo.value = inputCodigo.value.replace(/\D/g, "").slice(0, 6);
      if (tgCodeErr) tgCodeErr.textContent = "";
      clearMsg(tgCodeMsg);
    });
  }

  tgCodeForm?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const codigo = (inputCodigo?.value || "").trim();

    if (codigo.length !== 6) {
      if (tgCodeErr) tgCodeErr.textContent = "Informe os 6 dígitos do código.";
      return;
    }

    if (btnVerificar) {
      btnVerificar.disabled = true;
      btnVerificar.textContent = "Verificando...";
    }
    clearMsg(tgCodeMsg);

    try {
      await PortalVidaLivreApi.post("user-telegram-verify.php", { codigo }, { csrf: true });
      window.location.assign(redirectTarget);
    } catch (error) {
      showMsg(tgCodeMsg, error.message || "Código inválido ou expirado.");
      if (btnVerificar) {
        btnVerificar.disabled = false;
        btnVerificar.textContent = "Verificar código";
      }
    }
  });

  // ── Fase 2a: reenviar OTP ──────────────────────────────────────────────────
  const iniciarCooldownReenviar = (segundos) => {
    if (!btnReenviar) return;
    btnReenviar.disabled = true;
    let restante = segundos;
    btnReenviar.textContent = `Reenviar código (${restante}s)`;
    const t = setInterval(() => {
      restante -= 1;
      if (restante <= 0) {
        clearInterval(t);
        btnReenviar.disabled = false;
        btnReenviar.textContent = "Reenviar código";
      } else {
        btnReenviar.textContent = `Reenviar código (${restante}s)`;
      }
    }, 1000);
  };

  btnReenviar?.addEventListener("click", async () => {
    clearMsg(tgCodeMsg);
    if (btnReenviar) {
      btnReenviar.disabled = true;
      btnReenviar.textContent = "Enviando...";
    }

    try {
      await PortalVidaLivreApi.post("user-telegram-reenviar.php", {}, { csrf: true });
      showMsg(tgCodeMsg, "Novo código enviado ao seu Telegram.", "success");
      if (inputCodigo) inputCodigo.value = "";
      iniciarCooldownReenviar(30);
    } catch (error) {
      showMsg(tgCodeMsg, error.message || "Não foi possível reenviar o código.");
      if (btnReenviar) {
        btnReenviar.disabled = false;
        btnReenviar.textContent = "Reenviar código";
      }
    }
  });

  // ── Fase 2b: polling de vinculação ─────────────────────────────────────────
  const iniciarPollingVinculacao = () => {
    if (pollingInterval) return;

    pollingInterval = setInterval(async () => {
      try {
        const res = await PortalVidaLivreApi.post("user-poll-vinculacao.php", {}, { csrf: true });
        const d = res.data || {};

        if (d.vinculado) {
          clearInterval(pollingInterval);
          pollingInterval = null;
          window.location.assign(redirectTarget);
          return;
        }

        if (d.expirado) {
          clearInterval(pollingInterval);
          pollingInterval = null;
          showMsg(tgVincMsg, "Link expirado. Faça login novamente para gerar um novo link.");
        }
      } catch {
        clearInterval(pollingInterval);
        pollingInterval = null;
        showMsg(tgVincMsg, "Sessão expirada. Faça login novamente.");
      }
    }, 3000);
  };
});
