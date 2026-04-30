const CaptchaWidget = (() => {
  const IMAGE_ENDPOINT = '/backend/api/captcha-image.php';
  const AUDIO_ENDPOINT = '/backend/api/captcha-audio.php';

  function init(containerEl) {
    if (!containerEl) return null;

    const img       = containerEl.querySelector('[data-captcha-image]');
    const input     = containerEl.querySelector('[data-captcha-input]');
    const reloadBtn = containerEl.querySelector('[data-captcha-reload]');
    const audioLink = containerEl.querySelector('[data-captcha-audio]');

    function loadImage() {
      const t = Date.now();
      if (img)       img.src        = IMAGE_ENDPOINT + '?t=' + t;
      if (audioLink) audioLink.href  = AUDIO_ENDPOINT + '?t=' + t;
      if (input)     input.value    = '';
    }

    if (reloadBtn) {
      reloadBtn.addEventListener('click', (e) => {
        e.preventDefault();
        loadImage();
      });
    }

    loadImage();

    return {
      getAnswer: () => (input ? input.value.trim() : ''),
      reset: loadImage,
    };
  }

  return { init };
})();
