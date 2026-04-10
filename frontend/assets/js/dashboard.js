document.addEventListener("DOMContentLoaded", async () => {
  const container = document.querySelector("[data-dashboard-subscriptions]");

  // ── Elementos de dados da conta ──────────────────────────────────────────
  const campoEmailVerificado = document.querySelector(
    "[data-conta-email-verificado]",
  );
  const campoLgpdConsentimento = document.querySelector(
    "[data-conta-lgpd-consentimento]",
  );
  const campoCriadaEm = document.querySelector("[data-conta-criada-em]");

  // ── Elementos do formulário de exclusão ───────────────────────────────────
  const formExclusao = document.querySelector("#form-exclusao");
  const mensagemExclusao = document.querySelector("[data-exclusao-message]");
  const botaoExclusao = document.querySelector("[data-btn-exclusao]");

  // ── Utilitários ───────────────────────────────────────────────────────────

  const escapeHtml = (value) =>
    String(value || "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  const formatarData = (valor) => {
    if (!valor) return "Não informado";
    const data = new Date(valor);
    if (isNaN(data.getTime())) return "Não informado";
    return data.toLocaleString("pt-BR", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  const exibirMensagem = (elemento, texto, tipo = "error") => {
    if (!elemento) return;
    elemento.textContent = texto;
    elemento.className = `message message-${tipo}`;
    elemento.classList.remove("hidden");
  };

  const limparMensagem = (elemento) => {
    if (!elemento) return;
    elemento.textContent = "";
    elemento.className = "message hidden";
  };

  const limparErros = (form) => {
    form.querySelectorAll(".field-error").forEach((el) => {
      el.textContent = "";
    });
    form.querySelectorAll("input").forEach((el) => {
      el.classList.remove("invalid");
    });
  };

  const exibirErroCampo = (form, campo, mensagem) => {
    const input = form.querySelector(`[name="${campo}"]`);
    const erro = form.querySelector(`[data-error-for="${campo}"]`);
    if (input) input.classList.add("invalid");
    if (erro) erro.textContent = mensagem;
  };

  const aplicarErros = (form, errors = {}) => {
    Object.entries(errors).forEach(([campo, mensagens]) => {
      const mensagem = Array.isArray(mensagens)
        ? mensagens[0]
        : String(mensagens || "");
      if (mensagem) exibirErroCampo(form, campo, mensagem);
    });
  };

  // ── Inscrições ────────────────────────────────────────────────────────────

  const renderSubscriptions = (subscriptions) => {
    if (!container) return;

    if (!Array.isArray(subscriptions) || subscriptions.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <p>Você ainda não se inscreveu em nenhum especialista, clínica ou grupo.</p>
        </div>
      `;
      return;
    }

    container.innerHTML = subscriptions
      .map(
        (item) => `
    <article class="result-card">
      <div class="result-card__top">
        <span class="result-card__badge">${escapeHtml(item.entry_type_label)}</span>
        <span class="result-card__mode">${escapeHtml(item.service_mode_label)}</span>
      </div>
      <div>
        <h2 class="result-card__title">${escapeHtml(item.name)}</h2>
        <div class="result-card__meta">
          <span>🧭 ${escapeHtml(item.specialty)}</span>
          <span>📍 ${escapeHtml(item.location)}</span>
        </div>
      </div>
      <p class="result-card__text">${escapeHtml(item.short_bio)}</p>
      <div class="result-card__actions">
        <button
          type="button"
          class="result-card__button result-card__button--secondary"
          data-cancelar-inscricao="${item.id}"
        >
          Cancelar inscrição
        </button>
      </div>
    </article>
  `,
      )
      .join("");
  };

  // ── Dados completos da conta (inclui lgpd_consent_at) ────────────────────

  const carregarDadosConta = async () => {
    try {
      const resposta = await PortalVidaLivreApi.get("conta-usuario.php");
      const usuario = resposta.data.user;

      if (campoEmailVerificado) {
        campoEmailVerificado.textContent = usuario.email_verified
          ? `Sim (${formatarData(usuario.email_verified_at)})`
          : "Não verificado";
      }

      if (campoLgpdConsentimento) {
        campoLgpdConsentimento.textContent = usuario.lgpd_consent_at
          ? formatarData(usuario.lgpd_consent_at)
          : "Não registrado";
      }

      if (campoCriadaEm) {
        campoCriadaEm.textContent = formatarData(usuario.created_at);
      }
    } catch (erro) {
      // Falha silenciosa nos dados extras — os dados básicos já vêm do requireAuth
    }
  };

  // -── open exclusão da conta ───────────────────────────────────────────────────
  const btnMostrarExclusao = document.querySelector(
    "[data-btn-mostrar-exclusao]",
  );
  const exclusaoForm = document.querySelector("[data-exclusao-form]");

  if (btnMostrarExclusao && exclusaoForm) {
    btnMostrarExclusao.addEventListener("click", () => {
      exclusaoForm.classList.remove("hidden");
      btnMostrarExclusao.classList.add("hidden");
    });
  }
  // ── Excluir conta ─────────────────────────────────────────────────────────

  if (formExclusao) {
    formExclusao.addEventListener("submit", async (evento) => {
      evento.preventDefault();
      limparErros(formExclusao);
      limparMensagem(mensagemExclusao);

      const senha =
        formExclusao.querySelector("[name='password']")?.value || "";

      if (senha === "") {
        exibirErroCampo(
          formExclusao,
          "password",
          "Informe sua senha para confirmar a exclusão.",
        );
        return;
      }

      const confirmacao = window.confirm(
        "Tem certeza que deseja excluir sua conta? Todos os seus dados serão removidos permanentemente. Essa ação não pode ser desfeita.",
      );

      if (!confirmacao) return;

      if (botaoExclusao) {
        botaoExclusao.disabled = true;
        botaoExclusao.textContent = "Processando...";
      }

      try {
        await PortalVidaLivreApi.post(
          "solicitar-exclusao.php",
          { password: senha },
          { csrf: true },
        );
        window.location.assign("/frontend/login.html?status=conta-excluida");
      } catch (erro) {
        if (erro.errors && Object.keys(erro.errors).length > 0) {
          aplicarErros(formExclusao, erro.errors);
        } else {
          exibirMensagem(
            mensagemExclusao,
            erro.message || "Não foi possível processar a solicitação.",
            "error",
          );
        }

        if (botaoExclusao) {
          botaoExclusao.disabled = false;
          botaoExclusao.textContent = "Solicitar exclusão da minha conta";
        }
      }
    });
  }

// ── Cancelar inscrição ────────────────────────────────────────────────────

  const cancelarInscricao = async (entryId, button) => {
    button.disabled = true;
    button.textContent = "Cancelando...";

    try {
      await PortalVidaLivreApi.post(
        "directory-subscriptions.php",
        { entry_id: entryId, action: "unsubscribe" },
        { csrf: true }
      );

      const card = button.closest("article");
      if (card) card.remove();

      const restantes = container.querySelectorAll("article");
      if (restantes.length === 0) {
        container.innerHTML = `
          <div class="empty-state">
            <p>Você ainda não se inscreveu em nenhum especialista, clínica ou grupo.</p>
          </div>
        `;
      }
    } catch (erro) {
      PortalVidaLivreAuth.showMessage(
        erro.message || "Não foi possível cancelar a inscrição.",
        "error"
      );
      button.disabled = false;
      button.textContent = "Cancelar inscrição";
    }
  };

  container.addEventListener("click", async (evento) => {
    const button = evento.target.closest("[data-cancelar-inscricao]");
    if (!button) return;

    const entryId = Number(button.dataset.cancelarInscricao);
    if (entryId <= 0) return;

    await cancelarInscricao(entryId, button);
  });

  // ── Inicialização ─────────────────────────────────────────────────────────

  try {
    const session = await PortalVidaLivreAuth.loadSession();
    if (!session.authenticated) return;

    const [subscriptionsResponse] = await Promise.all([
      PortalVidaLivreApi.get("directory-subscriptions.php"),
      carregarDadosConta(),
    ]);

    renderSubscriptions(subscriptionsResponse.data?.subscriptions || []);
    PortalVidaLivreAuth.bindTogglePassword(
      document.querySelector("#form-exclusao"),
    );
  } catch (erro) {
    PortalVidaLivreAuth.showMessage(
      erro.message || "Não foi possível carregar o painel.",
      "error",
    );
    if (container) container.innerHTML = "";
  }
});