(function () {
  "use strict";

  var CHAVE_STORAGE = "cookie_consent";
  var banner = document.getElementById("cookieBanner");

  // Se o banner não existe na página, encerra aqui
  if (!banner) return;

  // ── Verifica se o usuário já respondeu antes ──────────────────────────
  // Se já existe uma resposta salva no localStorage, não mostra o banner
  function jaRespondeu() {
    try {
      return localStorage.getItem(CHAVE_STORAGE) !== null;
    } catch (e) {
      // localStorage pode estar bloqueado em alguns navegadores (modo restrito)
      return true;
    }
  }

  if (jaRespondeu()) return;

  // ── Salva a preferência e fecha o banner ──────────────────────────────
  function salvarPreferencia(necessary, analytics) {
    try {
      localStorage.setItem(
        CHAVE_STORAGE,
        JSON.stringify({
          necessary: necessary,
          analytics: analytics,
          timestamp: Date.now(),
        }),
      );
    } catch (e) {
      // falha silenciosa se localStorage estiver indisponível
    }
    fecharBanner();
  }

  function fecharBanner() {
    banner.classList.add("cookie-banner--saindo");

    // Aguarda a animação de saída terminar antes de remover do DOM
    banner.addEventListener(
      "animationend",
      function () {
        banner.remove();
      },
      { once: true },
    );
  }

  // ── Tela de confirmação do "Rejeitar" ─────────────────────────────────
  // Substitui os botões por uma mensagem explicativa antes de confirmar
  function mostrarConfirmacaoRejeitar() {
    var areaAcoes = banner.querySelector(".cookie-banner__actions");

    areaAcoes.innerHTML =
      '<p class="cookie-banner__aviso">' +
      "Os cookies de sessão são necessários para que o login funcione. " +
      "Se continuar sem aceitar, você pode navegar no site, " +
      "mas não conseguirá entrar na sua conta." +
      "</p>" +
      '<div class="cookie-banner__confirmacao">' +
      '<button class="cookie-banner__btn cookie-banner__btn--reject" id="cookieConfirmarRejeitar">' +
      "Continuar sem aceitar" +
      "</button>" +
      '<button class="cookie-banner__btn cookie-banner__btn--necessary" id="cookieVoltarAcoes">' +
      "Voltar" +
      "</button>" +
      "</div>";

    document
      .getElementById("cookieConfirmarRejeitar")
      .addEventListener("click", function () {
        fecharBanner();
      });

    document
      .getElementById("cookieVoltarAcoes")
      .addEventListener("click", function () {
        restaurarBotoes();
      });
  }

  // ── Restaura os botões originais se o usuário voltar ─────────────────
  function restaurarBotoes() {
    var areaAcoes = banner.querySelector(".cookie-banner__actions");

    areaAcoes.innerHTML =
      '<button class="cookie-banner__btn cookie-banner__btn--reject" id="cookieReject">' +
      "Rejeitar" +
      "</button>" +
      '<button class="cookie-banner__btn cookie-banner__btn--necessary" id="cookieNecessary">' +
      "Aceitar somente necessários" +
      "</button>" +
      '<button class="cookie-banner__btn cookie-banner__btn--all" id="cookieAll">' +
      "Aceitar todos" +
      "</button>";

    registrarEventos();
  }

  // ── Registra os eventos nos botões ────────────────────────────────────
  function registrarEventos() {
    var btnRejeitar = document.getElementById("cookieReject");
    var btnNecessarios = document.getElementById("cookieNecessary");
    var btnTodos = document.getElementById("cookieAll");

    if (btnRejeitar)
      btnRejeitar.addEventListener("click", mostrarConfirmacaoRejeitar);
    if (btnNecessarios)
      btnNecessarios.addEventListener("click", function () {
        salvarPreferencia(true, false);
      });
    if (btnTodos)
      btnTodos.addEventListener("click", function () {
        salvarPreferencia(true, true);
      });
  }

  // ── Mostra o banner ───────────────────────────────────────────────────
  banner.classList.remove("cookie-banner--oculto");
  registrarEventos();
})();
