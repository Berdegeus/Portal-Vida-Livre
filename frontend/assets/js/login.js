document.addEventListener("DOMContentLoaded", async () => {
  const form = document.querySelector("#login-form");
  const redirectTarget = "/frontend/dashboard.html";

  if (!form) {
    return;
  }

  try {
    await PortalVidaLivreApi.getCsrfToken();
  } catch (error) {
    PortalVidaLivreAuth.showMessage("Nao foi possivel iniciar a sessao do formulario.", "error");
  }

  // toggle password --------------------
  PortalVidaLivreAuth.bindTogglePassword(form);

  const captchaWidget = CaptchaWidget.init(document.getElementById("captcha-widget"));

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

      if (response.data?.requires_2fa) {
        const twoFactorUrl = new URL("/frontend/two-factor.html", window.location.origin);
        if (redirectTarget && redirectTarget !== "/frontend/dashboard.html") {
          twoFactorUrl.searchParams.set("redirect", redirectTarget);
        }
        window.location.assign(twoFactorUrl.toString());
        return;
      }

      window.location.assign(redirectTarget);
    } catch (error) {
      PortalVidaLivreAuth.applyErrors(form, error.errors || {});
      const telegramUrl = error.errors?.telegram_url || (typeof error.data === "object" ? error.data?.telegram_url : null);
      if (telegramUrl) {
        // Email não verificado: mostrar link Telegram
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
        btnTg.href = telegramUrl;
        btnTg.textContent = "Verificar conta via Telegram";
      }
      PortalVidaLivreAuth.showMessage(error.message || "Nao foi possivel realizar o login.", "error");
      if (error.errors?.captcha && captchaWidget) captchaWidget.reset();
    }
  });
});
