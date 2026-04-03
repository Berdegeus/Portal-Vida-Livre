document.addEventListener("DOMContentLoaded", async () => {
  const container = document.querySelector("[data-dashboard-subscriptions]");
  const messageBox = document.querySelector("[data-message]");

  if (!container) {
    return;
  }

  const escapeHtml = (value) =>
    String(value || "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  const showMessage = (message, type = "error") => {
    if (!messageBox) {
      return;
    }

    messageBox.textContent = message;
    messageBox.className = `message message-${type}`;
    messageBox.classList.remove("hidden");
  };

  const renderSubscriptions = (subscriptions) => {
    if (!Array.isArray(subscriptions) || subscriptions.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <p>Voce ainda nao se inscreveu em nenhum especialista, clinica ou grupo.</p>
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
          </article>
        `,
      )
      .join("");
  };

  try {
    const session = await PortalVidaLivreAuth.loadSession();

    if (!session.authenticated) {
      return;
    }

    const response = await PortalVidaLivreApi.get("directory-subscriptions.php");
    renderSubscriptions(response.data?.subscriptions || []);
  } catch (error) {
    showMessage(error.message || "Nao foi possivel carregar suas inscricoes.", "error");
    container.innerHTML = "";
  }
});
