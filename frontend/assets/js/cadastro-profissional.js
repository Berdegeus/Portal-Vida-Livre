/**
 * VidaLivre — Cadastro de Profissional/Grupo no Diretório
 * frontend/assets/js/cadastro-profissional.js
 */

(function () {
  'use strict';

  /* ─── State ─────────────────────────────────────────── */
  let currentStep = 1;
  const TOTAL_STEPS = 4;

  const TYPE_LABELS = {
    professional: 'Profissional',
    clinic: 'Clínica',
    support_group: 'Grupo de Apoio',
  };

  const MODE_LABELS = {
    presencial: 'Presencial',
    online: 'Online',
    hibrido: 'Híbrido',
  };

  /* ─── DOM refs ───────────────────────────────────────── */
  const form       = document.getElementById('directoryForm');
  const btnNext    = document.getElementById('btnNext');
  const btnBack    = document.getElementById('btnBack');
  const formNav    = document.getElementById('formNav');
  const successScr = document.getElementById('successScreen');
  const globalAlert    = document.getElementById('globalAlert');
  const globalAlertMsg = document.getElementById('globalAlertMsg');
  const bioCounter = document.getElementById('bioCounter');
  const slugInput  = document.getElementById('slug');
  const slugValue  = document.getElementById('slugValue');
  const nameInput  = document.getElementById('name');

  /* ─── Init ───────────────────────────────────────────── */
  function init() {
    renderStep(1);

    btnNext.addEventListener('click', handleNext);
    btnBack.addEventListener('click', handleBack);

    // Slug auto-generation from name
    nameInput.addEventListener('input', () => {
      const currentSlug = slugInput.value;
      const generated   = toSlug(nameInput.value);
      // Only auto-fill if user hasn't typed a custom slug
      if (!slugInput.dataset.dirty) {
        slugInput.value = generated;
        updateSlugPreview(generated);
      }
    });

    // Manual slug edit
    slugInput.addEventListener('input', () => {
      slugInput.dataset.dirty = 'true';
      const cleaned = slugInput.value.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-');
      slugInput.value = cleaned;
      updateSlugPreview(cleaned);
    });

    // Bio char counter
    const bioArea = document.getElementById('short_bio');
    bioArea.addEventListener('input', () => updateBioCounter(bioArea.value.length));

    // Keyboard submit on last step
    form.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && currentStep === TOTAL_STEPS) {
        e.preventDefault();
        handleNext();
      }
    });
  }

  /* ─── Step navigation ────────────────────────────────── */
  function handleNext() {
    hideAlert();

    if (!validateStep(currentStep)) return;

    if (currentStep < TOTAL_STEPS) {
      markTabDone(currentStep);
      currentStep++;
      renderStep(currentStep);
    } else {
      submitForm();
    }
  }

  function handleBack() {
    hideAlert();
    if (currentStep > 1) {
      currentStep--;
      renderStep(currentStep);
    }
  }

  function renderStep(step) {
    // Panels
    document.querySelectorAll('.form-step').forEach((el) => el.classList.remove('active'));
    document.getElementById(`step-${step}`).classList.add('active');

    // Tabs
    document.querySelectorAll('.form-step-tab').forEach((tab, i) => {
      tab.classList.remove('active');
      if (i + 1 < step)  tab.classList.add('done');
      if (i + 1 === step) { tab.classList.add('active'); tab.classList.remove('done'); }
    });

    // Back button
    btnBack.disabled = step === 1;

    // Next button label
    if (step === TOTAL_STEPS) {
      btnNext.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        Enviar cadastro`;
    } else {
      btnNext.innerHTML = `Continuar
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>`;
    }

    // Extra per-step work
    if (step === 2) updateStep2Desc();
    if (step === 4) fillReviewSummary();

    // Scroll to card top
    document.getElementById('formCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function markTabDone(step) {
    const tab = document.getElementById(`tab-${step}`);
    if (tab) {
      tab.classList.remove('active');
      tab.classList.add('done');
      tab.querySelector('.step-num').innerHTML =
        `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`;
    }
  }

  /* ─── Validation ─────────────────────────────────────── */
  const validators = {
    1() { return true; /* radio always has a value */ },

    2() {
      let ok = true;
      ok = requireField('name',      'Informe o nome completo ou da organização.') && ok;
      ok = requireField('specialty', 'Informe a especialidade ou área de atuação.') && ok;
      ok = requireSlug()   && ok;
      return ok;
    },

    3() {
      let ok = true;
      ok = requireField('city',  'Informe a cidade.') && ok;
      ok = requireField('state', 'Selecione o estado.') && ok;
      return ok;
    },

    4() {
      return requireBio();
    },
  };

  function validateStep(step) {
    return validators[step] ? validators[step]() : true;
  }

  function requireField(id, msg) {
    const el  = document.getElementById(id);
    const err = document.getElementById(`${id}-error`);
    const val = el.value.trim();
    if (!val) {
      showFieldError(el, err, msg);
      return false;
    }
    clearFieldError(el, err);
    return true;
  }

  function requireSlug() {
    const el  = slugInput;
    const err = document.getElementById('slug-error');
    const val = el.value.trim();

    if (!val) {
      showFieldError(el, err, 'Defina um endereço para o seu perfil.');
      return false;
    }
    if (!/^[a-z0-9][a-z0-9-]{1,}[a-z0-9]$/.test(val)) {
      showFieldError(el, err, 'Use apenas letras minúsculas, números e hífens (mín. 3 caracteres).');
      return false;
    }
    clearFieldError(el, err);
    return true;
  }

  function requireBio() {
    const el  = document.getElementById('short_bio');
    const err = document.getElementById('short_bio-error');
    const len = el.value.trim().length;

    if (len < 80) {
      showFieldError(el, err, `Descrição muito curta. Mínimo 80 caracteres (atual: ${len}).`);
      return false;
    }
    clearFieldError(el, err);
    return true;
  }

  function showFieldError(el, errEl, msg) {
    el.classList.add('error');
    if (errEl) { errEl.textContent = msg; errEl.classList.add('show'); }
    el.focus();
  }

  function clearFieldError(el, errEl) {
    el.classList.remove('error');
    if (errEl) { errEl.textContent = ''; errEl.classList.remove('show'); }
  }

  /* ─── Helpers ────────────────────────────────────────── */
  function toSlug(str) {
    return str
      .toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9\s-]/g, '')
      .trim()
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .substring(0, 160);
  }

  function updateSlugPreview(val) {
    slugValue.textContent = val || '—';
  }

  function updateBioCounter(len) {
    bioCounter.textContent = `${len} / 1200`;
    bioCounter.classList.toggle('warn', len > 900 && len <= 1200);
    bioCounter.classList.toggle('over',  len > 1200);
  }

  function getRadioValue(name) {
    const checked = document.querySelector(`input[name="${name}"]:checked`);
    return checked ? checked.value : '';
  }

  function updateStep2Desc() {
    const type = getRadioValue('entry_type');
    const map = {
      professional: 'Informações que identificam você no diretório.',
      clinic:       'Informações que identificam sua clínica no diretório.',
      support_group:'Informações que identificam seu grupo de apoio no diretório.',
    };
    document.getElementById('step2Desc').textContent = map[type] || map.professional;
  }

  function fillReviewSummary() {
    const type = getRadioValue('entry_type');
    const mode = getRadioValue('service_mode');
    document.getElementById('rev-type').textContent      = TYPE_LABELS[type] || type;
    document.getElementById('rev-name').textContent      = document.getElementById('name').value.trim() || '—';
    document.getElementById('rev-specialty').textContent = document.getElementById('specialty').value.trim() || '—';
    document.getElementById('rev-location').textContent  =
      `${document.getElementById('city').value.trim()}, ${document.getElementById('state').value}`;
    document.getElementById('rev-mode').textContent      = MODE_LABELS[mode] || mode;
    document.getElementById('rev-slug').textContent      = slugInput.value.trim() || '—';
  }

  /* ─── Alert ──────────────────────────────────────────── */
  function showAlert(msg, type = 'error') {
    globalAlertMsg.textContent = msg;
    globalAlert.className = `form-alert form-alert--${type} show`;
    globalAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function hideAlert() {
    globalAlert.className = 'form-alert form-alert--error';
  }

  /* ─── Submission ─────────────────────────────────────── */
  async function submitForm() {
    btnNext.disabled = true;
    btnNext.innerHTML = `
      <svg class="spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
           style="animation:spin 0.8s linear infinite;">
        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
      </svg>
      Enviando…`;

    const payload = {
      entry_type:   getRadioValue('entry_type'),
      name:         document.getElementById('name').value.trim(),
      specialty:    document.getElementById('specialty').value.trim(),
      slug:         slugInput.value.trim(),
      city:         document.getElementById('city').value.trim(),
      state:        document.getElementById('state').value,
      service_mode: getRadioValue('service_mode'),
      short_bio:    document.getElementById('short_bio').value.trim(),
    };

    try {
      const res = await fetch('../backend/api/store.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
      });

      const data = await res.json();

      if (!res.ok || !data.success) {
        throw new Error(data.message || 'Erro ao enviar. Tente novamente.');
      }

      // Show success
      form.style.display    = 'none';
      formNav.style.display = 'none';
      document.querySelector('.form-progress').style.display = 'none';
      const alertEl = document.getElementById('globalAlert');
      if (alertEl) alertEl.style.display = 'none';
      successScr.classList.add('show');

    } catch (err) {
      showAlert(err.message || 'Erro inesperado. Por favor tente novamente.');
      btnNext.disabled = false;
      btnNext.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        Enviar cadastro`;
    }
  }

  /* ─── Spin keyframe (injected) ───────────────────────── */
  const style = document.createElement('style');
  style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
  document.head.appendChild(style);

  /* ─── Boot ───────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', init);
  // If DOM already ready (script deferred / end of body)
  if (document.readyState !== 'loading') init();
})();
