(function () {
  const guestPages = ["login", "cadastro", "esqueceu-senha", "redefinir-senha"];
  const protectedPages = ["dashboard", "security", "minha-conta", "two-factor"];

  const getMessageBox = () => document.querySelector("[data-message]");

  const showMessage = (message, type = "error") => {
    const box = getMessageBox();

    if (!box) {
      return;
    }

    box.textContent = message;
    box.className = `message message-${type}`;
    box.classList.remove("hidden");
  };

  const clearMessage = () => {
    const box = getMessageBox();

    if (!box) {
      return;
    }

    box.textContent = "";
    box.className = "message hidden";
  };

  const clearFieldErrors = (form) => {
    form.querySelectorAll(".field-error").forEach((element) => {
      element.textContent = "";
    });

    form.querySelectorAll("input").forEach((field) => {
      field.classList.remove("invalid");
    });
  };

  const setFieldError = (form, fieldName, message) => {
    const field = form.querySelector(`[name="${fieldName}"]`);
    const error = form.querySelector(`[data-error-for="${fieldName}"]`);

    if (field) {
      field.classList.add("invalid");
    }

    if (error) {
      error.textContent = message;
    }
  };

  const applyErrors = (form, errors = {}) => {
    clearFieldErrors(form);

    Object.entries(errors).forEach(([field, messages]) => {
      const message = Array.isArray(messages) ? messages[0] : String(messages || "");

      if (!message) {
        return;
      }

      if (field === "_general") {
        showMessage(message, "error");
        return;
      }

      setFieldError(form, field, message);
    });
  };

const isValidEmail = (email) => /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/.test(email);

const isValidName = (name) => /^[\p{L}\s\-]+$/u.test(name);

const isValidTotpCode = (code) => /^\d{6}$/.test(code);

  const passwordStrengthErrors = (password) => {
    const errors = [];

    if (password.length < 12) {
      errors.push("A senha deve ter pelo menos 12 caracteres.");
    }

    if (!/[A-Z]/.test(password)) {
      errors.push("A senha deve ter ao menos uma letra maiuscula.");
    }

    if (!/[a-z]/.test(password)) {
      errors.push("A senha deve ter ao menos uma letra minuscula.");
    }

    if (!/\d/.test(password)) {
      errors.push("A senha deve ter ao menos um numero.");
    }

    if (!/[^A-Za-z0-9]/.test(password)) {
      errors.push("A senha deve ter ao menos um caractere especial.");
    }

    return errors;
  };

  const formDataToObject = (form) => {
    const data = {};

    new FormData(form).forEach((value, key) => {
      data[key] = value;
    });

    return data;
  };

  const loadSession = async () => {
    const response = await PortalVidaLivreApi.get("me.php");
    return response.data;
  };

  const loadTwoFactorStatus = async () => {
    const response = await PortalVidaLivreApi.get("two-factor-status.php");
    return response.data;
  };

  const redirectIfAuthenticated = async () => {
    const session = await loadSession();

    if (session.login_two_factor_pending) {
      window.location.replace("/frontend/two-factor.html");
      return true;
    }

    if (session.authenticated) {
      window.location.replace("/frontend/dashboard.html");
      return true;
    }

    return false;
  };

  const requireAuth = async () => {
    const session = await loadSession();

    if (!session.authenticated) {
      window.location.replace("/frontend/login.html");
      return null;
    }

    const nameTarget = document.querySelector("[data-current-user-name]");
    const emailTarget = document.querySelector("[data-current-user-email]");

    if (nameTarget) {
      nameTarget.textContent = session.user?.name || "";
    }

    if (emailTarget) {
      emailTarget.textContent = session.user?.email || "";
    }

    const twoFactorTarget = document.querySelector("[data-current-user-2fa]");

    if (twoFactorTarget) {
      twoFactorTarget.textContent = session.user?.two_factor_enabled ? "Ativo" : "Inativo";
    }

    return session.user;
  };

  const bindLogout = () => {
    const button = document.querySelector("[data-logout]");

    if (!button) {
      return;
    }

    button.addEventListener("click", async () => {
      clearMessage();

      try {
        await PortalVidaLivreApi.post("logout.php", {}, { csrf: true });
        window.location.assign("/frontend/login.html?status=logged-out");
      } catch (error) {
        showMessage(error.message || "Nao foi possivel encerrar a sessao.", "error");
      }
    });
  };

  const showQueryStatus = () => {
    const status = new URLSearchParams(window.location.search).get("status");
    const messages = {
      registered: "Cadastro realizado com sucesso. Faca login para continuar.",
      "verification-pending":
        "Cadastro realizado. Verifique seu e-mail para confirmar a conta antes de entrar.",
      "email-verified": "E-mail confirmado com sucesso. Agora voce pode entrar.",
      "password-reset": "Senha redefinida com sucesso. Faca login com a nova senha.",
      "logged-out": "Sessao encerrada com sucesso.",
      "conta-excluida": "Sua conta foi excluida. Esperamos que tenha encontrado o apoio que precisava. Se precisar de ajuda no futuro, estaremos aqui para ajudar.",
    };

    if (status && messages[status]) {
      showMessage(messages[status], "success");
    }
  };

  document.addEventListener("DOMContentLoaded", async () => {
    const page = document.body.dataset.page || "";

    if (page === "index") {
      try {
        const session = await loadSession();

        if (session.login_two_factor_pending) {
          window.location.replace("/frontend/two-factor.html");
          return;
        }

        window.location.replace(
          session.authenticated ? "/frontend/dashboard.html" : "/frontend/login.html"
        );
      } catch (error) {
        window.location.replace("/frontend/login.html");
      }

      return;
    }

    if (guestPages.includes(page)) {
      showQueryStatus();

      try {
        await redirectIfAuthenticated();
      } catch (error) {
        showMessage("Nao foi possivel verificar a sessao atual.", "error");
      }

      return;
    }

    if (page === "two-factor") {
      try {
        const status = await loadTwoFactorStatus();

        if (status.authenticated) {
          window.location.replace("/frontend/dashboard.html");
          return;
        }

        if (!status.login_pending) {
          window.location.replace("/frontend/login.html");
        }
      } catch (error) {
        showMessage("Nao foi possivel validar o desafio 2FA.", "error");
      }

      return;
    }

    if (protectedPages.includes(page)) {
      try {
        await requireAuth();
        bindLogout();
      } catch (error) {
        showMessage("Nao foi possivel carregar a sessao.", "error");
      }
    }
  });

  window.PortalVidaLivreAuth = {
    showMessage,
    clearMessage,
    clearFieldErrors,
    setFieldError,
    applyErrors,
    isValidEmail,
    isValidName,
    isValidTotpCode,
    passwordStrengthErrors,
    formDataToObject,
    loadSession,
    loadTwoFactorStatus,
    redirectIfAuthenticated,
    requireAuth,
    bindLogout,
  };
})();
