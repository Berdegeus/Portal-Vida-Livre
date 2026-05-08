document.addEventListener("DOMContentLoaded", async () => {
  try {
    const response = await PortalVidaLivreApi.get("admin-me.php");
    const admin = response.data?.admin;
    document.querySelector("[data-admin-name]").textContent = admin?.name || "";
    document.querySelector("[data-admin-email]").textContent = admin?.email || "";
  } catch {
    window.location.replace("/frontend/admin-login.html");
    return;
  }

  document.querySelector("[data-admin-logout]").addEventListener("click", async () => {
    try {
      await PortalVidaLivreApi.post("admin-logout.php", {}, { csrf: true });
    } finally {
      window.location.replace("/frontend/admin-login.html");
    }
  });

  // --- Tab switching ---
  const tabs = document.querySelectorAll(".admin-tab");
  const tabPanels = {
    users: document.getElementById("tab-users"),
    directory: document.getElementById("tab-directory"),
    logs: document.getElementById("tab-logs"),
  };

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      tabs.forEach((t) => t.classList.remove("active"));
      tab.classList.add("active");
      Object.entries(tabPanels).forEach(([key, el]) => {
        el.classList.toggle("hidden", key !== tab.dataset.tab);
      });
      if (tab.dataset.tab === "logs" && !logsLoaded) {
        loadLogs();
      }
    });
  });

  let logsLoaded = false;

  // --- Toast ---
  const toastEl = document.getElementById("toast");
  let toastTimer;
  function showToast(message, type = "success") {
    toastEl.textContent = message;
    toastEl.className = `toast toast-${type} show`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove("show"), 3500);
  }

  // --- Confirmation modal ---
  const modal = document.getElementById("delete-modal");
  const modalText = document.getElementById("delete-modal-text");
  const modalConfirm = document.getElementById("modal-confirm");
  let pendingDelete = null;

  document.getElementById("modal-cancel").addEventListener("click", () => {
    modal.classList.add("hidden");
    pendingDelete = null;
  });

  modalConfirm.addEventListener("click", async () => {
    if (!pendingDelete) return;
    const action = pendingDelete;
    pendingDelete = null;
    modalConfirm.disabled = true;
    await action();
    modalConfirm.disabled = false;
    modal.classList.add("hidden");
  });

  function openDeleteModal(text, action) {
    modalText.textContent = text;
    pendingDelete = action;
    modal.classList.remove("hidden");
  }

  // --- Helpers ---
  function escapeHtml(str) {
    return String(str ?? "").replace(/[&<>"']/g, (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])
    );
  }

  function formatDate(dateStr) {
    if (!dateStr) return "—";
    return new Date(dateStr).toLocaleDateString("pt-BR", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    });
  }

  const ENTRY_TYPE_LABEL = {
    professional: "Profissional",
    clinic: "Clínica",
    support_group: "Grupo de Apoio",
  };

  // --- Users ---
  async function loadUsers() {
    try {
      const res = await PortalVidaLivreApi.get("admin-users-list.php");
      const users = res.data.users || [];

      document.getElementById("stat-users").textContent = users.length;
      document.getElementById("users-loading").classList.add("hidden");

      if (users.length === 0) {
        document.getElementById("users-empty").classList.remove("hidden");
        return;
      }

      const tbody = document.getElementById("users-tbody");
      tbody.innerHTML = users
        .map(
          (u) => `
        <tr data-row-user="${u.id}">
          <td>${escapeHtml(u.name)}</td>
          <td style="color: var(--gray-400);">${escapeHtml(u.email)}</td>
          <td>${u.email_verified_at ? '<span class="badge badge-green">Verificado</span>' : '<span class="badge badge-gray">Pendente</span>'}</td>
          <td>${u.two_factor_enabled ? '<span class="badge badge-amber">Ativo</span>' : '<span class="badge badge-gray">Inativo</span>'}</td>
          <td style="color: var(--gray-400);">${formatDate(u.created_at)}</td>
          <td><button class="btn-delete" data-delete-user="${u.id}" data-name="${escapeHtml(u.name)}">Excluir</button></td>
        </tr>`
        )
        .join("");

      document.getElementById("users-table").classList.remove("hidden");

      tbody.querySelectorAll("[data-delete-user]").forEach((btn) => {
        btn.addEventListener("click", () => {
          const id = btn.dataset.deleteUser;
          const name = btn.dataset.name;
          openDeleteModal(
            `Tem certeza que deseja excluir o usuário "${name}"? Todos os dados relacionados serão removidos permanentemente.`,
            async () => {
              try {
                await PortalVidaLivreApi.post(
                  "admin-users-delete.php",
                  { id: parseInt(id, 10) },
                  { csrf: true }
                );
                document.querySelector(`[data-row-user="${id}"]`).remove();
                const stat = document.getElementById("stat-users");
                stat.textContent = parseInt(stat.textContent, 10) - 1;
                showToast("Usuário excluído com sucesso.");
              } catch (err) {
                showToast(err.message || "Erro ao excluir usuário.", "error");
              }
            }
          );
        });
      });
    } catch {
      document.getElementById("users-loading").textContent =
        "Erro ao carregar usuários.";
    }
  }

  // --- Directory ---
  async function loadDirectory() {
    try {
      const res = await PortalVidaLivreApi.get("admin-directory-list.php");
      const entries = res.data.entries || [];

      document.getElementById("stat-entries").textContent = entries.length;
      document.getElementById("directory-loading").classList.add("hidden");

      if (entries.length === 0) {
        document.getElementById("directory-empty").classList.remove("hidden");
        return;
      }

      const tbody = document.getElementById("directory-tbody");
      tbody.innerHTML = entries
        .map(
          (e) => `
        <tr data-row-entry="${e.id}">
          <td>${escapeHtml(e.name)}</td>
          <td><span class="badge badge-blue">${escapeHtml(ENTRY_TYPE_LABEL[e.entry_type] ?? e.entry_type)}</span></td>
          <td style="color: var(--gray-400);">${escapeHtml(e.city)} / ${escapeHtml(e.state)}</td>
          <td style="color: var(--gray-400);">${escapeHtml(e.specialty)}</td>
          <td>${e.is_active ? '<span class="badge badge-green">Ativo</span>' : '<span class="badge badge-gray">Inativo</span>'}</td>
          <td style="color: var(--gray-400);">${formatDate(e.created_at)}</td>
          <td><button class="btn-delete" data-delete-entry="${e.id}" data-name="${escapeHtml(e.name)}">Excluir</button></td>
        </tr>`
        )
        .join("");

      document.getElementById("directory-table").classList.remove("hidden");

      tbody.querySelectorAll("[data-delete-entry]").forEach((btn) => {
        btn.addEventListener("click", () => {
          const id = btn.dataset.deleteEntry;
          const name = btn.dataset.name;
          openDeleteModal(
            `Tem certeza que deseja excluir "${name}" do diretório? Esta ação não pode ser desfeita.`,
            async () => {
              try {
                await PortalVidaLivreApi.post(
                  "admin-directory-delete.php",
                  { id: parseInt(id, 10) },
                  { csrf: true }
                );
                document.querySelector(`[data-row-entry="${id}"]`).remove();
                const stat = document.getElementById("stat-entries");
                stat.textContent = parseInt(stat.textContent, 10) - 1;
                showToast("Entrada excluída com sucesso.");
              } catch (err) {
                showToast(err.message || "Erro ao excluir entrada.", "error");
              }
            }
          );
        });
      });
    } catch {
      document.getElementById("directory-loading").textContent =
        "Erro ao carregar diretório.";
    }
  }

  // --- Audit log ---
  const ACTION_LABELS = {
    "user.register": "Cadastro de usuário",
    "user.login": "Login",
    "user.login_failed": "Tentativa de login falhou",
    "user.login_2fa_required": "Login (2FA necessário)",
    "user.login_2fa_completed": "Login (2FA concluído)",
    "user.login_2fa_failed": "Código 2FA inválido",
    "user.logout": "Logout",
    "user.email_verified": "E-mail verificado",
    "user.password_reset_requested": "Redefinição de senha solicitada",
    "user.password_reset": "Senha redefinida",
    "user.password_changed": "Senha alterada",
    "user.2fa_enabled": "2FA ativado",
    "user.2fa_disabled": "2FA desativado",
    "user.account_deleted": "Conta excluída",
    "admin.login": "Login administrativo",
    "admin.logout": "Logout administrativo",
    "admin.users_viewed": "Usuários visualizados",
    "admin.directory_viewed": "Diretório visualizado",
    "admin.user_deleted": "Usuário excluído pelo admin",
    "admin.directory_deleted": "Entrada excluída pelo admin",
  };

  const ACTOR_BADGE = {
    user: "badge-blue",
    admin: "badge-amber",
    system: "badge-gray",
  };

  const ALERT_ACTIONS = new Set([
    "user.login_failed",
    "user.login_2fa_failed",
    "user.account_deleted",
    "admin.user_deleted",
    "admin.directory_deleted",
  ]);

  let logPage = 1;
  const LOG_PER_PAGE = 50;
  let logTotal = 0;

  function buildLogParams() {
    const action = document.getElementById("log-filter-action").value;
    const actor = document.getElementById("log-filter-actor").value;
    const params = new URLSearchParams({ page: logPage, per_page: LOG_PER_PAGE });
    if (action) params.set("action", action);
    if (actor) params.set("actor_type", actor);
    return params.toString();
  }

  async function loadLogs(resetPage = false) {
    if (resetPage) logPage = 1;
    logsLoaded = true;

    const loading = document.getElementById("logs-loading");
    const table = document.getElementById("logs-table");
    const empty = document.getElementById("logs-empty");
    const pagination = document.getElementById("log-pagination");

    loading.classList.remove("hidden");
    table.classList.add("hidden");
    empty.classList.add("hidden");
    pagination.classList.add("hidden");

    try {
      const res = await PortalVidaLivreApi.get(`admin-audit-log.php?${buildLogParams()}`);
      const logs = res.data.logs || [];
      logTotal = res.data.total || 0;

      loading.classList.add("hidden");

      document.getElementById("log-total").textContent =
        logTotal > 0 ? `${logTotal} registro${logTotal !== 1 ? "s" : ""}` : "";

      if (logs.length === 0) {
        empty.classList.remove("hidden");
        return;
      }

      const tbody = document.getElementById("logs-tbody");
      tbody.innerHTML = logs
        .map((log) => {
          const actionLabel = ACTION_LABELS[log.action] ?? log.action;
          const actorBadge = ACTOR_BADGE[log.actor_type] ?? "badge-gray";
          const isAlert = ALERT_ACTIONS.has(log.action);
          const actionClass = isAlert ? "badge badge-red" : "badge badge-gray";

          const actorDisplay = log.actor_email
            ? escapeHtml(log.actor_email)
            : log.actor_id
            ? `#${log.actor_id}`
            : "—";

          const targetDisplay = log.target_label
            ? escapeHtml(log.target_label)
            : "—";

          const dateDisplay = log.created_at
            ? new Date(log.created_at).toLocaleString("pt-BR", {
                day: "2-digit", month: "2-digit", year: "numeric",
                hour: "2-digit", minute: "2-digit", second: "2-digit",
              })
            : "—";

          return `
            <tr>
              <td style="color: var(--gray-400); white-space: nowrap; font-size: 13px;">${dateDisplay}</td>
              <td>
                <span class="badge ${actorBadge}" style="margin-right: 6px;">${escapeHtml(log.actor_type)}</span>
                <span style="font-size: 13px; color: var(--gray-300);">${actorDisplay}</span>
              </td>
              <td><span class="${actionClass}">${escapeHtml(actionLabel)}</span></td>
              <td style="color: var(--gray-400); font-size: 13px;">${targetDisplay}</td>
              <td style="color: var(--gray-500); font-size: 13px; font-family: monospace;">${escapeHtml(log.ip ?? "—")}</td>
            </tr>`;
        })
        .join("");

      table.classList.remove("hidden");

      const totalPages = Math.ceil(logTotal / LOG_PER_PAGE);
      if (totalPages > 1) {
        document.getElementById("log-page-info").textContent =
          `Página ${logPage} de ${totalPages}`;
        document.getElementById("log-prev").disabled = logPage <= 1;
        document.getElementById("log-next").disabled = logPage >= totalPages;
        pagination.classList.remove("hidden");
      }
    } catch {
      loading.textContent = "Erro ao carregar logs.";
    }
  }

  document.getElementById("log-filter-btn").addEventListener("click", () => loadLogs(true));

  document.getElementById("log-prev").addEventListener("click", () => {
    if (logPage > 1) { logPage--; loadLogs(); }
  });

  document.getElementById("log-next").addEventListener("click", () => {
    if (logPage < Math.ceil(logTotal / LOG_PER_PAGE)) { logPage++; loadLogs(); }
  });

  Promise.all([loadUsers(), loadDirectory()]);
});
