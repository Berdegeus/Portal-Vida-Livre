document.addEventListener("DOMContentLoaded", async () => {
  const stateLoading = document.getElementById("state-loading");
  const stateLocked = document.getElementById("state-locked");
  const formQuestions = document.getElementById("form-questions");
  const questionsContainer = document.getElementById("questions-container");
  const submitBtn = document.getElementById("submit-btn");
  const attemptsInfo = document.getElementById("attempts-info");
  const msgError = document.getElementById("msg-error");
  const msgSuccess = document.getElementById("msg-success");
  const msgLocked = document.getElementById("msg-locked");

  const showError = (msg) => {
    msgError.textContent = msg;
    msgError.classList.remove("hidden");
    msgSuccess.classList.add("hidden");
  };

  const showSuccess = (msg) => {
    msgSuccess.textContent = msg;
    msgSuccess.classList.remove("hidden");
    msgError.classList.add("hidden");
  };

  const clearMessages = () => {
    msgError.classList.add("hidden");
    msgSuccess.classList.add("hidden");
  };

  const showLocked = (hours) => {
    stateLoading.classList.add("hidden");
    formQuestions.classList.add("hidden");
    stateLocked.classList.remove("hidden");
    msgLocked.textContent = `Acesso bloqueado por tentativas incorretas. Tente novamente em ${hours} hora(s) ou solicite um novo link.`;
  };

  // Load CSRF token and fetch questions
  try {
    await PortalVidaLivreApi.getCsrfToken();

    const result = await PortalVidaLivreApi.post("admin-security-questions-get.php", {}, { csrf: true });
    const questions = result.data.questions || [];

    if (questions.length < 3) {
      stateLoading.classList.add("hidden");
      showError("Perguntas de segurança não configuradas. Contate o suporte.");
      return;
    }

    // Build form fields dynamically
    questions.forEach((q, index) => {
      const group = document.createElement("div");
      group.className = "question-group";

      const label = document.createElement("p");
      label.className = "question-text";
      label.textContent = `${q.order}. ${q.text}`;

      const input = document.createElement("input");
      input.type = "text";
      input.name = `answer_${index}`;
      input.id = `answer-${index}`;
      input.autocomplete = "off";
      input.setAttribute("autocorrect", "off");
      input.setAttribute("autocapitalize", "off");
      input.setAttribute("spellcheck", "false");

      const error = document.createElement("small");
      error.className = "field-error";
      error.id = `answer-${index}-error`;

      group.appendChild(label);
      group.appendChild(input);
      group.appendChild(error);
      questionsContainer.appendChild(group);
    });

    stateLoading.classList.add("hidden");
    formQuestions.classList.remove("hidden");

    // Focus first input
    const firstInput = questionsContainer.querySelector("input");
    if (firstInput) firstInput.focus();
  } catch (err) {
    stateLoading.classList.add("hidden");

    if (err.errors?.locked) {
      showLocked(err.errors.hours || 24);
      return;
    }

    if (err.status === 401) {
      showError("Sessão expirada. Solicite um novo link de acesso.");
      return;
    }

    showError(err.message || "Não foi possível carregar as perguntas de segurança.");
  }

  formQuestions.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearMessages();

    // Collect and validate answers
    const inputs = questionsContainer.querySelectorAll("input");
    const answers = [];
    let hasEmpty = false;

    inputs.forEach((input, i) => {
      const val = input.value.trim();
      const errorEl = document.getElementById(`answer-${i}-error`);
      input.classList.remove("invalid");
      if (errorEl) errorEl.textContent = "";

      if (val === "") {
        input.classList.add("invalid");
        if (errorEl) errorEl.textContent = "Este campo é obrigatório.";
        hasEmpty = true;
      }

      answers.push(val);
    });

    if (hasEmpty) {
      inputs[answers.findIndex((a) => a === "")]?.focus();
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = "Verificando...";

    try {
      await PortalVidaLivreApi.post("admin-security-questions-verify.php", { answers }, { csrf: true });
      showSuccess("Acesso autorizado. Redirecionando...");
      submitBtn.textContent = "Verificando...";
      setTimeout(() => window.location.replace("/frontend/admin-dashboard.html"), 1200);
    } catch (err) {
      if (err.errors?.locked) {
        formQuestions.classList.add("hidden");
        showLocked(err.errors.hours || 24);
        return;
      }

      if (err.errors?.remaining_attempts !== undefined) {
        const remaining = err.errors.remaining_attempts;
        showError(err.message || "Respostas incorretas.");
        attemptsInfo.textContent = `Tentativa(s) restante(s): ${remaining}`;
      } else {
        showError(err.message || "Não foi possível verificar as respostas.");
      }

      submitBtn.disabled = false;
      submitBtn.textContent = "Verificar Respostas";
    }
  });
});
