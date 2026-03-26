document.addEventListener("DOMContentLoaded", async () => {
  const form = document.querySelector("#login-form");

  if (!form) {
    return;
  }

  try {
    await PortalVidaLivreApi.getCsrfToken();
  } catch (error) {
    PortalVidaLivreAuth.showMessage("Nao foi possivel iniciar a sessao do formulario.", "error");
  }

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

    if (Object.keys(errors).length > 0) {
      PortalVidaLivreAuth.applyErrors(form, errors);
      PortalVidaLivreAuth.showMessage("Verifique os campos informados.", "error");
      return;
    }

    try {
      const response = await PortalVidaLivreApi.post("login.php", data, { csrf: true });

      if (response.data?.requires_2fa) {
        window.location.assign("/frontend/two-factor.html");
        return;
      }

      window.location.assign("/frontend/dashboard.html");
    } catch (error) {
      PortalVidaLivreAuth.applyErrors(form, error.errors || {});
      PortalVidaLivreAuth.showMessage(error.message || "Nao foi possivel realizar o login.", "error");
    }
  });
});
