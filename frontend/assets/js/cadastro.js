document.addEventListener("DOMContentLoaded", async () => {
  const form = document.querySelector("#register-form");
  const modal = document.querySelector("#modal-politica");
  const btnVerPolitica = document.querySelector("#btn-ver-politica");
  const btnFechar = document.querySelector("#btn-fechar-modal");
  const btnAceitar = document.querySelector("#btn-aceitar-politica");
  const btnRecusar = document.querySelector("#btn-recusar-politica");
  const checkConsent = document.querySelector("#lgpd_consent");

  if (!form) {
    return;
  }

  // ── Modal de política ─────────────────────────────────────────────────────

  if (btnVerPolitica) {
    btnVerPolitica.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      modal.classList.remove("hidden");
      document.body.style.overflow = "hidden";
    });
  }

  const fecharModal = () => {
    modal.classList.add("hidden");
    document.body.style.overflow = "";
  };

  if (btnFechar) btnFechar.addEventListener("click", fecharModal);
  if (btnRecusar) btnRecusar.addEventListener("click", fecharModal);

  if (modal) {
    modal.addEventListener("click", (e) => {
      if (e.target === modal) fecharModal();
    });
  }

  if (btnAceitar) {
    btnAceitar.addEventListener("click", () => {
      if (checkConsent) checkConsent.checked = true;
      fecharModal();
      PortalVidaLivreAuth.clearFieldErrors(form);
    });
  }

  // ── Toggle para mostrar/ocultar senha ───────────────────────────────────
  PortalVidaLivreAuth.bindTogglePassword(form);

  const captchaWidget = CaptchaWidget.init(document.getElementById("captcha-widget"));

// ── Validação em tempo real da senha ──────────────────────────────────────

  const inputSenha = form.querySelector('[name="password"]');
  const inputConfirmacao = form.querySelector('[name="password_confirmation"]');

  if (inputSenha) {
    inputSenha.addEventListener("input", () => {
      const erros = PortalVidaLivreAuth.passwordStrengthErrors(inputSenha.value);
      if (erros.length > 0) {
        PortalVidaLivreAuth.setFieldError(form, "password", erros[0]);
      } else {
        PortalVidaLivreAuth.setFieldError(form, "password", "");
      }
      if (inputConfirmacao && inputConfirmacao.value) {
        if (inputSenha.value !== inputConfirmacao.value) {
          PortalVidaLivreAuth.setFieldError(form, "password_confirmation", "A confirmação deve ser igual à senha.");
        } else {
          PortalVidaLivreAuth.setFieldError(form, "password_confirmation", "");
        }
      }
    });
  }

  if (inputConfirmacao) {
    inputConfirmacao.addEventListener("input", () => {
      if (!inputSenha) return;
      if (inputConfirmacao.value && inputSenha.value !== inputConfirmacao.value) {
        PortalVidaLivreAuth.setFieldError(form, "password_confirmation", "A confirmação de senha deve ser igual à senha.");
      } else {
        PortalVidaLivreAuth.setFieldError(form, "password_confirmation", "");
      }
    });
  }
  // ── CSRF ──────────────────────────────────────────────────────────────────

  try {
    await PortalVidaLivreApi.getCsrfToken();
  } catch (error) {
    PortalVidaLivreAuth.showMessage(
      "Nao foi possivel iniciar a sessao do formulario.",
      "error",
    );
  }

  // ── Submit ────────────────────────────────────────────────────────────────

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    PortalVidaLivreAuth.clearMessage();
    PortalVidaLivreAuth.clearFieldErrors(form);

    const data = PortalVidaLivreAuth.formDataToObject(form);
    const errors = {};

    const firstName = (data.first_name || "").trim();
    const lastName  = (data.last_name || "").trim();
    const fullName  = `${firstName} ${lastName}`.trim();

    data.email = (data.email || "").trim().toLowerCase();

    if (!firstName || firstName.length < 2) {
      errors.first_name = ["Informe seu nome com pelo menos 2 caracteres."];
    } else if (!PortalVidaLivreAuth.isValidName(firstName)) {
      errors.first_name = ["O nome deve conter apenas letras, espacos e hifens."];
    }

    if (!lastName || lastName.length < 2) {
      errors.last_name = ["Informe seu sobrenome com pelo menos 2 caracteres."];
    } else if (!PortalVidaLivreAuth.isValidName(lastName)) {
      errors.last_name = ["O sobrenome deve conter apenas letras, espacos e hifens."];
    }

    if (!PortalVidaLivreAuth.isValidEmail(data.email || "")) {
      errors.email = ["Informe um e-mail valido."];
    }

    const passwordErrors = PortalVidaLivreAuth.passwordStrengthErrors(data.password || "");
    if (passwordErrors.length > 0) {
      errors.password = passwordErrors;
    }

    if (!data.password_confirmation) {
      errors.password_confirmation = ["Confirme sua senha."];
    } else if (data.password !== data.password_confirmation) {
      errors.password_confirmation = ["A confirmacao deve ser igual a senha."];
    }

    if (!checkConsent || !checkConsent.checked) {
      errors.lgpd_consent = ["Voce precisa aceitar a Politica de Privacidade para continuar."];
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
      const payload = {
        name: fullName,
        email: data.email,
        password: data.password,
        password_confirmation: data.password_confirmation,
        lgpd_consent: true,
        captcha_answer: captchaWidget ? captchaWidget.getAnswer() : "",
      };

      const response = await PortalVidaLivreApi.post("register.php", payload, { csrf: true });
      form.reset();
      emailCadastrado = data.email;
      mostrarFase2(response.data || {});
    } catch (error) {
      PortalVidaLivreAuth.applyErrors(form, error.errors || {});
      PortalVidaLivreAuth.showMessage(
        error.message || "Nao foi possivel concluir o cadastro.",
        "error",
      );
      if (error.errors?.captcha && captchaWidget) captchaWidget.reset();
    }
  });

  // ── Fase 2: verificação por código ────────────────────────────────────────

  const sectionCadastro  = document.getElementById("section-cadastro");
  const sectionVerificar = document.getElementById("section-verificar");
  const verificarForm    = document.getElementById("verificar-form");
  const inputCodigo      = document.getElementById("codigo-verificacao");
  const btnVerificar     = document.getElementById("btn-verificar");
  const codigoFieldErr   = document.getElementById("codigo-field-error");
  const verificarMsgBox  = document.querySelector("[data-verificar-message]");
  const btnReenviar      = document.getElementById("btn-reenviar");

  let emailCadastrado = "";
  let reenviarTimeout = null;

  let telegramUrlAtual = "";

  const mostrarFase2 = (responseData = {}) => {
    telegramUrlAtual = responseData.telegram_url || "";
    const btnTelegram = document.getElementById("btn-telegram-verify");
    if (btnTelegram && telegramUrlAtual) {
      btnTelegram.href = telegramUrlAtual;
      btnTelegram.style.display = "inline-block";
    } else if (btnTelegram) {
      btnTelegram.style.display = "none";
    }
    sectionCadastro.style.display  = "none";
    sectionVerificar.style.display = "block";
  };

  const showVerificarMsg = (text, type = "error") => {
    if (!verificarMsgBox) return;
    verificarMsgBox.textContent = text;
    verificarMsgBox.className = `message message-${type}`;
  };

  const clearVerificarMsg = () => {
    if (!verificarMsgBox) return;
    verificarMsgBox.textContent = "";
    verificarMsgBox.className = "message hidden";
  };

  inputCodigo?.addEventListener("input", () => {
    inputCodigo.value = inputCodigo.value.replace(/\D/g, "").slice(0, 6);
    if (codigoFieldErr) codigoFieldErr.textContent = "";
    clearVerificarMsg();
  });

  verificarForm?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const codigo = inputCodigo.value.trim();

    if (codigo.length !== 6) {
      if (codigoFieldErr) codigoFieldErr.textContent = "Informe os 6 dígitos do código.";
      return;
    }

    btnVerificar.disabled = true;
    btnVerificar.textContent = "Verificando...";
    clearVerificarMsg();

    try {
      await PortalVidaLivreApi.post("verify-email-codigo.php", { codigo }, { csrf: true });
      showVerificarMsg("Cadastro confirmado! Redirecionando para o login...", "success");
      setTimeout(() => {
        window.location.assign("/frontend/login.html?status=email-verified");
      }, 1200);
    } catch (error) {
      showVerificarMsg(error.message || "Código inválido ou expirado.");
      btnVerificar.disabled = false;
      btnVerificar.textContent = "Confirmar cadastro";
    }
  });

  const iniciarCooldownReenviar = (segundos) => {
    if (!btnReenviar) return;
    btnReenviar.disabled = true;
    let restante = segundos;
    btnReenviar.textContent = `Gerar novo link (${restante}s)`;
    reenviarTimeout = setInterval(() => {
      restante -= 1;
      if (restante <= 0) {
        clearInterval(reenviarTimeout);
        btnReenviar.disabled = false;
        btnReenviar.textContent = "Gerar novo link Telegram";
      } else {
        btnReenviar.textContent = `Gerar novo link (${restante}s)`;
      }
    }, 1000);
  };

  btnReenviar?.addEventListener("click", async () => {
    if (!emailCadastrado) return;
    clearVerificarMsg();
    btnReenviar.disabled = true;
    btnReenviar.textContent = "Gerando...";

    try {
      const res = await PortalVidaLivreApi.post("resend-verification.php", { email: emailCadastrado }, { csrf: true });
      const novoUrl = res.data?.telegram_url;
      const btnTelegram = document.getElementById("btn-telegram-verify");
      if (novoUrl && btnTelegram) {
        telegramUrlAtual = novoUrl;
        btnTelegram.href = novoUrl;
      }
      showVerificarMsg("Novo link gerado. Clique em 'Verificar via Telegram' para continuar.", "success");
      if (inputCodigo) inputCodigo.value = "";
      iniciarCooldownReenviar(60);
    } catch (error) {
      showVerificarMsg(error.message || "Não foi possível gerar novo link.");
      btnReenviar.disabled = false;
      btnReenviar.textContent = "Gerar novo link Telegram";
    }
  });
});