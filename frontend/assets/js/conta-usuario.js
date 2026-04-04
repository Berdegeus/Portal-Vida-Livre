document.addEventListener("DOMContentLoaded", async () => {
  const elementoCarregando = document.querySelector("[data-conta-carregando]");
  const elementoDados = document.querySelector("[data-conta-dados]");
  const campoNome = document.querySelector("[data-conta-nome]");
  const campoEmail = document.querySelector("[data-conta-email]");
  const campoEmailVerificado = document.querySelector(
    "[data-conta-email-verificado]",
  );
  const campoCriadaEm = document.querySelector("[data-conta-criada-em]");
  const campoLgpdConsentimento = document.querySelector(
    "[data-conta-lgpd-consentimento]",
  );
  const campo2fa = document.querySelector("[data-conta-2fa]");
  const formExclusao = document.querySelector("#form-exclusao");
  const mensagemExclusao = document.querySelector("[data-exclusao-message]");
  const botaoExclusao = document.querySelector("[data-btn-exclusao]");

  const formatarData = (valor) => {
    if (!valor) {
      return "Não informado";
    }

    const data = new Date(valor);

    if (isNaN(data.getTime())) {
      return "Não informado";
    }

    return data.toLocaleString("pt-BR", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  const exibirMensagemExclusao = (texto, tipo = "error") => {
    if (!mensagemExclusao) {
      return;
    }

    mensagemExclusao.textContent = texto;
    mensagemExclusao.className = `message message-${tipo}`;
    mensagemExclusao.classList.remove("hidden");
  };

  const limparMensagemExclusao = () => {
    if (!mensagemExclusao) {
      return;
    }

    mensagemExclusao.textContent = "";
    mensagemExclusao.className = "message hidden";
  };

  const exibirErroCampo = (campo, mensagem) => {
    if (!formExclusao) {
      return;
    }

    const input = formExclusao.querySelector(`[name="${campo}"]`);
    const erro = formExclusao.querySelector(`[data-error-for="${campo}"]`);

    if (input) {
      input.classList.add("invalid");
    }

    if (erro) {
      erro.textContent = mensagem;
    }
  };

  const limparErrosCampos = () => {
    if (!formExclusao) {
      return;
    }

    formExclusao.querySelectorAll(".field-error").forEach((el) => {
      el.textContent = "";
    });

    formExclusao.querySelectorAll("input").forEach((el) => {
      el.classList.remove("invalid");
    });
  };

  // Carrega e exibe os dados da conta
  const carregarDadosConta = async () => {
    try {
      const resposta = await PortalVidaLivreApi.get("conta-usuario.php");
      const usuario = resposta.data.user;

      if (campoNome) campoNome.textContent = usuario.name || "-";
      if (campoEmail) campoEmail.textContent = usuario.email || "-";

      if (campoEmailVerificado) {
        campoEmailVerificado.textContent = usuario.email_verified
          ? `Sim (${formatarData(usuario.email_verified_at)})`
          : "Não verificado";
      }

      if (campoCriadaEm) {
        campoCriadaEm.textContent = formatarData(usuario.created_at);
      }

      if (campoLgpdConsentimento) {
        campoLgpdConsentimento.textContent = usuario.lgpd_consent_at
          ? formatarData(usuario.lgpd_consent_at)
          : "Não registrado";
      }

      if (campo2fa) {
        campo2fa.textContent = usuario.two_factor_enabled ? "Ativo" : "Inativo";
      }

      if (elementoCarregando) elementoCarregando.classList.add("hidden");
      if (elementoDados) elementoDados.classList.remove("hidden");
    } catch (erro) {
      PortalVidaLivreAuth.showMessage(
        erro.message || "Não foi possível carregar os dados da conta.",
        "error",
      );

      if (elementoCarregando) elementoCarregando.classList.add("hidden");
    }
  };

  // Gerencia o envio do formulário de exclusão
  if (formExclusao) {
    formExclusao.addEventListener("submit", async (evento) => {
      evento.preventDefault();
      limparErrosCampos();
      limparMensagemExclusao();

      const senha =
        formExclusao.querySelector("[name='password']")?.value || "";

      if (senha === "") {
        exibirErroCampo(
          "password",
          "Informe sua senha para confirmar a exclusão.",
        );
        return;
      }

      const confirmacao = window.confirm(
        "Tem certeza que deseja excluir sua conta? Todos os seus dados serão removidos permanentemente. Essa ação não pode ser desfeita.",
      );

      if (!confirmacao) {
        return;
      }

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
          Object.entries(erro.errors).forEach(([campo, mensagens]) => {
            const mensagem = Array.isArray(mensagens)
              ? mensagens[0]
              : String(mensagens || "");

            if (mensagem) {
              exibirErroCampo(campo, mensagem);
            }
          });
        } else {
          exibirMensagemExclusao(
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

  await carregarDadosConta();
});
