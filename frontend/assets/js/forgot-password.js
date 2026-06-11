document.addEventListener("DOMContentLoaded", async () => {
  const form = document.querySelector("#forgot-password-form");

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

    if (Object.keys(errors).length > 0) {
      PortalVidaLivreAuth.applyErrors(form, errors);
      PortalVidaLivreAuth.showMessage("Verifique os campos informados.", "error");
      return;
    }

    try {
      const response = await PortalVidaLivreApi.post("forgot-password.php", data, { csrf: true });
      const d = response.data || {};

      if (d.channel === "telegram_sent") {
        PortalVidaLivreAuth.showMessage("Link de redefinição enviado ao seu Telegram.", "success");
      } else if (d.channel === "telegram" && d.telegram_url) {
        // Usuário sem Telegram vinculado: mostrar botão de deep link
        let existing = document.getElementById("btn-telegram-reset");
        if (!existing) {
          existing = document.createElement("a");
          existing.id = "btn-telegram-reset";
          existing.target = "_blank";
          existing.rel = "noopener noreferrer";
          existing.className = "btn btn--primary";
          existing.style.cssText = "display:inline-block;margin-top:12px;width:100%;text-align:center;box-sizing:border-box;";
          form.parentNode.appendChild(existing);
        }
        existing.href = d.telegram_url;
        existing.textContent = "Receber link via Telegram";
        PortalVidaLivreAuth.showMessage("Clique no botão abaixo para receber o link de redefinição via Telegram.", "success");
      } else {
        PortalVidaLivreAuth.showMessage(response.message, "success");
      }

      form.reset();
    } catch (error) {
      PortalVidaLivreAuth.applyErrors(form, error.errors || {});
      PortalVidaLivreAuth.showMessage(error.message || "Nao foi possivel enviar o link.", "error");
    }
  });
});
