document.addEventListener("DOMContentLoaded", async () => {
  const form = document.querySelector("#admin-login-form");
  const submitBtn = document.querySelector("#submit-btn");

  const showMessage = (text, type = "error") => {
    const box = document.querySelector("[data-message]");
    if (!box) return;
    box.textContent = text;
    box.className = `admin-message admin-message-${type}`;
  };

  const clearMessage = () => {
    const box = document.querySelector("[data-message]");
    if (!box) return;
    box.textContent = "";
    box.className = "admin-message hidden";
  };

  const setFieldError = (name, message) => {
    const input = form.querySelector(`[name="${name}"]`);
    const error = form.querySelector(`[data-error-for="${name}"]`);
    if (input) input.classList.add("invalid");
    if (error) error.textContent = message;
  };

  const clearFieldErrors = () => {
    form.querySelectorAll("input").forEach((el) => el.classList.remove("invalid"));
    form.querySelectorAll(".field-error").forEach((el) => (el.textContent = ""));
  };

  try {
    await PortalVidaLivreApi.getCsrfToken();
  } catch {
    showMessage("Nao foi possivel iniciar o formulario. Recarregue a pagina.");
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearMessage();
    clearFieldErrors();

    const email = (form.querySelector("[name='email']")?.value || "").trim().toLowerCase();

    if (!/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/.test(email)) {
      setFieldError("email", "Informe um e-mail valido.");
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = "Enviando...";

    try {
      const response = await PortalVidaLivreApi.post("admin-request-login.php", { email }, { csrf: true });
      showMessage(response.message || "Link enviado. Verifique sua caixa de entrada.", "success");
      form.reset();
    } catch (error) {
      if (error.errors?.email) {
        setFieldError("email", error.errors.email[0]);
      } else {
        showMessage(error.message || "Nao foi possivel processar a solicitacao.", "error");
      }
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = "Enviar link de acesso";
    }
  });
});
