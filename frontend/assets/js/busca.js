document.addEventListener("DOMContentLoaded", async () => {
  const form = document.querySelector("#directory-search-form");
  const summary = document.querySelector("[data-results-summary]");
  const resultsContainer = document.querySelector("[data-results-container]");
  const messageBox = document.querySelector("[data-message]");
  const specialtyInput = document.querySelector("#especialidade");
  const cityInput = document.querySelector("#cidade");
  const typeInput = document.querySelector("#tipo");
  const specialtiesList = document.querySelector("#specialties-list");
  const locationsList = document.querySelector("#locations-list");
  const headerActions = document.querySelector("#searchHeaderActions");
  const loginRedirectBase = "/frontend/login.html";
  let session = { authenticated: false };
  let subscribedIds = new Set();
  let currentPayload = null;

  if (!form || !summary || !resultsContainer) {
    return;
  }

  const typeLabels = {
    professional: "Especialistas",
    clinic: "Clínicas",
    support_group: "Grupos de apoio",
  };

  const showMessage = (message, type = "error") => {
    if (!messageBox) {
      return;
    }

    messageBox.textContent = message;
    messageBox.className = `message message-${type}`;
    messageBox.classList.remove("hidden");
  };

  const clearMessage = () => {
    if (!messageBox) {
      return;
    }

    messageBox.textContent = "";
    messageBox.className = "message hidden";
  };

  const escapeHtml = (value) =>
    String(value || "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  const getLoginRedirectUrl = () => {
    const redirect = `${window.location.pathname}${window.location.search}`;
    return `${loginRedirectBase}?redirect=${encodeURIComponent(redirect)}`;
  };

  const renderHeaderActions = async () => {
    if (!headerActions) {
      return;
    }

    try {
      const sessionResponse = await PortalVidaLivreApi.get("me.php");
      session = sessionResponse.data;

      if (!session.authenticated) {
        return;
      }

      headerActions.innerHTML = `
        <a href="/frontend/dashboard.html" class="btn btn--ghost">Perfil</a>
        <button type="button" class="btn btn--primary" data-logout>Sair</button>
      `;

      const logoutButton = headerActions.querySelector("[data-logout]");
      if (!logoutButton) {
        return;
      }

      logoutButton.addEventListener("click", async () => {
        try {
          await PortalVidaLivreApi.post("logout.php", {}, { csrf: true });
        } catch (error) {
          window.location.assign("/frontend/login.html");
          return;
        }

        window.location.assign("/frontend/login.html?status=logged-out");
      });
    } catch (error) {
      /* noop */
    }
  };

  const loadSubscriptions = async () => {
    if (!session.authenticated) {
      subscribedIds = new Set();
      return;
    }

    try {
      const response = await PortalVidaLivreApi.get("directory-subscriptions.php");
      subscribedIds = new Set(response.data?.subscription_ids || []);
    } catch (error) {
      subscribedIds = new Set();
    }
  };

  const syncFormWithQuery = () => {
    const params = new URLSearchParams(window.location.search);

    specialtyInput.value = params.get("especialidade") || "";
    cityInput.value = params.get("cidade") || "";
    typeInput.value = params.get("tipo") || "";
  };

  const updateQueryString = () => {
    const params = new URLSearchParams();
    const specialty = specialtyInput.value.trim();
    const city = cityInput.value.trim();
    const type = typeInput.value.trim();

    if (specialty) {
      params.set("especialidade", specialty);
    }

    if (city) {
      params.set("cidade", city);
    }

    if (type) {
      params.set("tipo", type);
    }

    const query = params.toString();
    const nextUrl = query ? `/frontend/busca.html?${query}` : "/frontend/busca.html";
    window.history.replaceState({}, "", nextUrl);
  };

  const renderMetadata = async () => {
    try {
      const response = await PortalVidaLivreApi.get("home-data.php");
      const metadata = response.data?.metadata || {};

      if (specialtiesList) {
        specialtiesList.innerHTML = (metadata.specialties || [])
          .map((item) => `<option value="${escapeHtml(item)}"></option>`)
          .join("");
      }

      if (locationsList) {
        locationsList.innerHTML = (metadata.locations || [])
          .map((item) => `<option value="${escapeHtml(item)}"></option>`)
          .join("");
      }
    } catch (error) {
      /* noop */
    }
  };

  const renderResults = (payload) => {
    currentPayload = payload;
    const results = payload.results || [];
    const total = Number(payload.total || 0);
    const activeType = payload.filters?.tipo;
    const typeLabel = activeType ? typeLabels[activeType] || "Resultados" : "Resultados";

    summary.textContent =
      total > 0
        ? `${total} ${typeLabel.toLowerCase()} encontrado(s) com os filtros atuais.`
        : "Nenhum resultado encontrado com os filtros atuais.";

    if (results.length === 0) {
      resultsContainer.innerHTML = `
        <div class="empty-state">
          <p>Não encontramos atendimentos com esse filtro.</p>
          <button type="button" id="clear-search-results">Limpar filtros</button>
        </div>
      `;

      const clearButton = document.querySelector("#clear-search-results");
      if (clearButton) {
        clearButton.addEventListener("click", () => {
          specialtyInput.value = "";
          cityInput.value = "";
          typeInput.value = "";
          updateQueryString();
          runSearch();
        });
      }

      return;
    }

    resultsContainer.innerHTML = results
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
                class="result-card__button ${subscribedIds.has(item.id) ? "result-card__button--secondary" : ""}"
                data-entry-action="${subscribedIds.has(item.id) ? "unsubscribe" : "subscribe"}"
                data-entry-id="${item.id}"
              >
                ${subscribedIds.has(item.id) ? "Cancelar inscricao" : "Agendar"}
              </button>
            </div>
          </article>
        `,
      )
      .join("");
  };

  const handleEntryAction = async (button) => {
    const entryId = Number(button.dataset.entryId || 0);
    const action = button.dataset.entryAction || "subscribe";

    if (entryId <= 0) {
      return;
    }

    if (!session.authenticated) {
      window.location.assign(getLoginRedirectUrl());
      return;
    }

    button.disabled = true;

    try {
      const response = await PortalVidaLivreApi.post(
        "directory-subscriptions.php",
        { entry_id: entryId, action },
        { csrf: true },
      );

      if (response.data?.subscribed) {
        subscribedIds.add(entryId);
      } else {
        subscribedIds.delete(entryId);
      }

      showMessage(response.message, "success");

      if (currentPayload) {
        renderResults(currentPayload);
      }
    } catch (error) {
      showMessage(error.message || "Nao foi possivel atualizar a inscricao.", "error");
    } finally {
      button.disabled = false;
    }
  };

  async function runSearch() {
    clearMessage();
    summary.textContent = "Carregando resultados...";
    resultsContainer.innerHTML = "";

    const params = new URLSearchParams();
    const specialty = specialtyInput.value.trim();
    const city = cityInput.value.trim();
    const type = typeInput.value.trim();

    if (specialty) {
      params.set("especialidade", specialty);
    }

    if (city) {
      params.set("cidade", city);
    }

    if (type) {
      params.set("tipo", type);
    }

    try {
      const response = await PortalVidaLivreApi.get(`directory-search.php?${params.toString()}`);
      renderResults(response.data);
    } catch (error) {
      showMessage(error.message || "Nao foi possivel carregar os resultados.", "error");
      summary.textContent = "Nao foi possivel carregar os resultados.";
      resultsContainer.innerHTML = "";
    }
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    updateQueryString();
    await runSearch();
  });

  resultsContainer.addEventListener("click", async (event) => {
    const button = event.target.closest("[data-entry-id]");

    if (!button) {
      return;
    }

    await handleEntryAction(button);
  });

  syncFormWithQuery();
  await Promise.all([renderHeaderActions(), renderMetadata()]);
  await loadSubscriptions();
  await runSearch();
});
