document.addEventListener("DOMContentLoaded", async () => {
  const codeForm = document.querySelector("#two-factor-code-form");
  const backupForm = document.querySelector("#two-factor-backup-login-form");

  if (!codeForm || !backupForm) {
    return;
  }

  const ensurePendingChallenge = async () => {
    const status = await PortalVidaLivreAuth.loadTwoFactorStatus();

    if (status.authenticated) {
      window.location.replace("/frontend/dashboard.html");
      return false;
    }

    if (!status.login_pending) {
      window.location.replace("/frontend/login.html");
      return false;
    }

    return true;
  };

  try {
    await PortalVidaLivreApi.getCsrfToken();
    const challengeReady = await ensurePendingChallenge();

    if (!challengeReady) {
      return;
    }
  } catch (error) {
    PortalVidaLivreAuth.showMessage("Nao foi possivel iniciar a verificacao 2FA.", "error");
    return;
  }

  codeForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    PortalVidaLivreAuth.clearMessage();
    PortalVidaLivreAuth.clearFieldErrors(codeForm);

    const data = PortalVidaLivreAuth.formDataToObject(codeForm);
    data.code = (data.code || "").trim();

    if (!/^\d{6}$/.test(data.code || "")) {
      PortalVidaLivreAuth.applyErrors(codeForm, {
        code: ["Informe um codigo valido de 6 digitos."],
      });
      PortalVidaLivreAuth.showMessage("Verifique os campos informados.", "error");
      return;
    }

    try {
      await PortalVidaLivreApi.post("two-factor-verify.php", { code: data.code }, { csrf: true });
      window.location.assign("/frontend/dashboard.html");
    } catch (error) {
      PortalVidaLivreAuth.applyErrors(codeForm, error.errors || {});
      PortalVidaLivreAuth.showMessage(error.message || "Nao foi possivel validar o codigo.", "error");
    }
  });

  backupForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    PortalVidaLivreAuth.clearMessage();
    PortalVidaLivreAuth.clearFieldErrors(backupForm);

    const data = PortalVidaLivreAuth.formDataToObject(backupForm);
    data.backup_code = (data.backup_code || "").trim();

    if (!data.backup_code) {
      PortalVidaLivreAuth.applyErrors(backupForm, {
        backup_code: ["Informe um backup code."],
      });
      PortalVidaLivreAuth.showMessage("Verifique os campos informados.", "error");
      return;
    }

    try {
      await PortalVidaLivreApi.post(
        "two-factor-verify.php",
        { backup_code: data.backup_code },
        { csrf: true }
      );
      window.location.assign("/frontend/dashboard.html");
    } catch (error) {
      PortalVidaLivreAuth.applyErrors(backupForm, error.errors || {});
      PortalVidaLivreAuth.showMessage(error.message || "Nao foi possivel validar o backup code.", "error");
    }
  });
});
