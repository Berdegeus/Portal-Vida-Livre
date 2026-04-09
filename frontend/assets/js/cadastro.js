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

  // ── Botões ver/ocultar senha ──────────────────────────────────────────────

  document.querySelectorAll("[data-toggle-password]").forEach((btn) => {
    btn.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();

      const fieldName = btn.dataset.togglePassword;
      const input = form.querySelector(`[name="${fieldName}"]`);
      const eyeOpen = btn.querySelector("[data-eye-open]");
      const eyeClosed = btn.querySelector("[data-eye-closed]");

      if (!input) return;

      const visivel = input.type === "text";
      input.type = visivel ? "password" : "text";

      eyeOpen?.classList.toggle("hidden", !visivel);
      eyeClosed?.classList.toggle("hidden", visivel);
    });
  });
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
      };

      const response = await PortalVidaLivreApi.post("register.php", payload, { csrf: true });
      PortalVidaLivreAuth.showMessage(response.message, "success");
      form.reset();
      window.setTimeout(() => {
        window.location.assign("/frontend/login.html?status=verification-pending");
      }, 500);
    } catch (error) {
      PortalVidaLivreAuth.applyErrors(form, error.errors || {});
      PortalVidaLivreAuth.showMessage(
        error.message || "Nao foi possivel concluir o cadastro.",
        "error",
      );
    }
  });
});