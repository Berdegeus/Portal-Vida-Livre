document.addEventListener("DOMContentLoaded", async () => {
  const statusTarget = document.querySelector("[data-two-factor-status]");
  const setupSection = document.querySelector("[data-setup-section]");
  const setupResult = document.querySelector("[data-setup-result]");
  const enabledSection = document.querySelector("[data-enabled-section]");
  const backupCodesSection = document.querySelector("[data-backup-codes-section]");
  const backupCodesList = document.querySelector("[data-backup-codes-list]");
  const qrImage = document.querySelector("[data-qr-image]");
  const manualSecret = document.querySelector("[data-manual-secret]");
  const setupForm = document.querySelector("#two-factor-setup-form");
  const confirmForm = document.querySelector("#two-factor-confirm-form");
  const disableForm = document.querySelector("#two-factor-disable-form");
  const backupForm = document.querySelector("#two-factor-backup-form");

  if (!statusTarget) {
    return;
  }

  const renderBackupCodes = (codes) => {
    if (!backupCodesSection || !backupCodesList) {
      return;
    }

    backupCodesList.innerHTML = "";

    codes.forEach((code) => {
      const item = document.createElement("li");
      item.textContent = code;
      backupCodesList.appendChild(item);
    });

    backupCodesSection.classList.remove("hidden");
  };

  const hideBackupCodes = () => {
    if (!backupCodesSection || !backupCodesList) {
      return;
    }

    backupCodesSection.classList.add("hidden");
    backupCodesList.innerHTML = "";
  };

  const renderStatus = (twoFactor) => {
    if (!twoFactor) {
      statusTarget.textContent = "Nao foi possivel carregar o status do 2FA.";
      return;
    }

    const enabled = Boolean(twoFactor.enabled);
    const remaining = Number(twoFactor.backup_codes_remaining || 0);
    statusTarget.textContent = enabled
      ? `2FA ativo. Backup codes restantes: ${remaining}.`
      : "2FA inativo.";

    if (setupSection) {
      setupSection.classList.toggle("hidden", enabled);
    }

    if (enabledSection) {
      enabledSection.classList.toggle("hidden", !enabled);
    }

    if (!enabled && !twoFactor.setup_pending && setupResult) {
      setupResult.classList.add("hidden");
    }
  };

  const loadStatus = async () => {
    const response = await PortalVidaLivreApi.get("two-factor-status.php");

    if (!response.data.authenticated) {
      window.location.replace("/frontend/login.html");
      return null;
    }

    renderStatus(response.data.two_factor);
    return response.data.two_factor;
  };

  try {
    await PortalVidaLivreApi.getCsrfToken();
    await loadStatus();
  } catch (error) {
    PortalVidaLivreAuth.showMessage(error.message || "Nao foi possivel carregar o 2FA.", "error");
  }

  if (setupForm) {
    setupForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      PortalVidaLivreAuth.clearMessage();
      PortalVidaLivreAuth.clearFieldErrors(setupForm);
      hideBackupCodes();

      const data = PortalVidaLivreAuth.formDataToObject(setupForm);

      if (!data.current_password) {
        PortalVidaLivreAuth.applyErrors(setupForm, {
          current_password: ["Informe sua senha atual."],
        });
        PortalVidaLivreAuth.showMessage("Verifique os campos informados.", "error");
        return;
      }

      try {
        const response = await PortalVidaLivreApi.post("two-factor-setup.php", data, { csrf: true });

        if (qrImage) {
          qrImage.src = response.data.qr_code_url;
        }

        if (manualSecret) {
          manualSecret.textContent = response.data.secret || "";
        }

        if (setupResult) {
          setupResult.classList.remove("hidden");
        }

        PortalVidaLivreAuth.showMessage(response.message, "success");
      } catch (error) {
        PortalVidaLivreAuth.applyErrors(setupForm, error.errors || {});
        PortalVidaLivreAuth.showMessage(error.message || "Nao foi possivel iniciar o 2FA.", "error");
      }
    });
  }

  if (confirmForm) {
    confirmForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      PortalVidaLivreAuth.clearMessage();
      PortalVidaLivreAuth.clearFieldErrors(confirmForm);

      const data = PortalVidaLivreAuth.formDataToObject(confirmForm);
      data.code = (data.code || "").trim();

      const errors = {};

      if (!data.current_password) {
        errors.current_password = ["Informe sua senha atual."];
      }

      if (!/^\d{6}$/.test(data.code || "")) {
        errors.code = ["Informe um codigo valido de 6 digitos."];
      }

      if (Object.keys(errors).length > 0) {
        PortalVidaLivreAuth.applyErrors(confirmForm, errors);
        PortalVidaLivreAuth.showMessage("Verifique os campos informados.", "error");
        return;
      }

      try {
        const response = await PortalVidaLivreApi.post("two-factor-confirm.php", data, { csrf: true });
        PortalVidaLivreAuth.showMessage(response.message, "success");
        renderBackupCodes(response.data.backup_codes || []);

        if (setupResult) {
          setupResult.classList.add("hidden");
        }

        setupForm?.reset();
        confirmForm.reset();
        await loadStatus();
      } catch (error) {
        PortalVidaLivreAuth.applyErrors(confirmForm, error.errors || {});
        PortalVidaLivreAuth.showMessage(error.message || "Nao foi possivel confirmar o 2FA.", "error");
      }
    });
  }

  if (disableForm) {
    disableForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      PortalVidaLivreAuth.clearMessage();
      PortalVidaLivreAuth.clearFieldErrors(disableForm);
      hideBackupCodes();

      const data = PortalVidaLivreAuth.formDataToObject(disableForm);

      if (!data.current_password) {
        PortalVidaLivreAuth.applyErrors(disableForm, {
          current_password: ["Informe sua senha atual."],
        });
        PortalVidaLivreAuth.showMessage("Verifique os campos informados.", "error");
        return;
      }

      try {
        const response = await PortalVidaLivreApi.post("two-factor-disable.php", data, { csrf: true });
        PortalVidaLivreAuth.showMessage(response.message, "success");
        disableForm.reset();
        backupForm?.reset();
        await loadStatus();
      } catch (error) {
        PortalVidaLivreAuth.applyErrors(disableForm, error.errors || {});
        PortalVidaLivreAuth.showMessage(error.message || "Nao foi possivel desativar o 2FA.", "error");
      }
    });
  }

  if (backupForm) {
    backupForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      PortalVidaLivreAuth.clearMessage();
      PortalVidaLivreAuth.clearFieldErrors(backupForm);

      const data = PortalVidaLivreAuth.formDataToObject(backupForm);

      if (!data.current_password) {
        PortalVidaLivreAuth.applyErrors(backupForm, {
          current_password: ["Informe sua senha atual."],
        });
        PortalVidaLivreAuth.showMessage("Verifique os campos informados.", "error");
        return;
      }

      try {
        const response = await PortalVidaLivreApi.post("two-factor-backup-codes.php", data, { csrf: true });
        PortalVidaLivreAuth.showMessage(response.message, "success");
        renderBackupCodes(response.data.backup_codes || []);
        backupForm.reset();
        await loadStatus();
      } catch (error) {
        PortalVidaLivreAuth.applyErrors(backupForm, error.errors || {});
        PortalVidaLivreAuth.showMessage(error.message || "Nao foi possivel regenerar os backup codes.", "error");
      }
    });
  }
});

