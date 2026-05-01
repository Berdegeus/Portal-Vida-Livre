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
  const tabUsers = document.getElementById("tab-users");
  const tabDirectory = document.getElementById("tab-directory");

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      tabs.forEach((t) => t.classList.remove("active"));
      tab.classList.add("active");
      const isUsers = tab.dataset.tab === "users";
      tabUsers.classList.toggle("hidden", !isUsers);
      tabDirectory.classList.toggle("hidden", isUsers);
    });
  });

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

  Promise.all([loadUsers(), loadDirectory()]);
});
