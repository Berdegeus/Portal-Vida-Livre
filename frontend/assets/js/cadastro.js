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

  if (btnVerPolitica) {
    btnVerPolitica.addEventListener("click", (e) => {
      e.preventDefault();
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

  try {
    await PortalVidaLivreApi.getCsrfToken();
  } catch (error) {
    PortalVidaLivreAuth.showMessage(
      "Nao foi possivel iniciar a sessao do formulario.",
      "error",
    );
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    PortalVidaLivreAuth.clearMessage();
    PortalVidaLivreAuth.clearFieldErrors(form);

    const data = PortalVidaLivreAuth.formDataToObject(form);
    const errors = {};
    data.name = (data.name || "").trim();
    data.email = (data.email || "").trim().toLowerCase();

    if (!data.name || data.name.length < 3) {
      errors.name = ["Informe seu nome com pelo menos 3 caracteres."];
    } else if (data.name.length > 120) {
      errors.name = ["O nome deve ter no maximo 120 caracteres."];
    } else if (!PortalVidaLivreAuth.isValidName(data.name)) {
      errors.name = ["O nome deve conter apenas letras, espacos e hifens."];
    }

    if (!PortalVidaLivreAuth.isValidEmail(data.email || "")) {
      errors.email = ["Informe um e-mail valido."];
    }

    const passwordErrors = PortalVidaLivreAuth.passwordStrengthErrors(
      data.password || "",
    );
    if (passwordErrors.length > 0) {
      errors.password = passwordErrors;
    }

    if (!data.password_confirmation) {
      errors.password_confirmation = ["Confirme sua senha."];
    } else if (data.password !== data.password_confirmation) {
      errors.password_confirmation = ["A confirmacao deve ser igual a senha."];
    }

    if (!checkConsent || !checkConsent.checked) {
      errors.lgpd_consent = [
        "Voce precisa aceitar a Politica de Privacidade para continuar.",
      ];
    }

    if (Object.keys(errors).length > 0) {
      PortalVidaLivreAuth.applyErrors(form, errors);
      PortalVidaLivreAuth.showMessage(
        "Verifique os campos informados.",
        "error",
      );
      return;
    }

    try {
      const payload = {
        name: data.name,
        email: data.email,
        password: data.password,
        password_confirmation: data.password_confirmation,
        lgpd_consent: true,
      };
      const response = await PortalVidaLivreApi.post("register.php", payload, {
        csrf: true,
      });
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
