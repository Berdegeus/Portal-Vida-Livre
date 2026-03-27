/* ========================================
   VidaLivre — Scripts
   ======================================== */

document.addEventListener('DOMContentLoaded', () => {

  // ---- Data ----
  const specialties = [
    { emoji: '🚬', label: 'Tabagismo' },
    { emoji: '🍷', label: 'Alcoolismo' },
    { emoji: '📱', label: 'Dependência Digital' },
    { emoji: '🎰', label: 'Jogos de Azar' },
    { emoji: '💊', label: 'Dependência Química' },
    { emoji: '🍽️', label: 'Transtornos Alimentares' },
    { emoji: '🤝', label: 'Grupos de Apoio' },
    { emoji: '🧠', label: 'Psicólogo' },
    { emoji: '💉', label: 'Psiquiatra' },
    { emoji: '🏥', label: 'Clínica de Reabilitação' },
    { emoji: '☕', label: 'Cafeína' },
    { emoji: '🎮', label: 'Videogames' },
    { emoji: '🛒', label: 'Compras Compulsivas' },
  ];

  const cities = [
    'São Paulo, SP', 'Rio de Janeiro, RJ', 'Curitiba, PR',
    'Belo Horizonte, MG', 'Porto Alegre, RS', 'Salvador, BA',
    'Brasília, DF', 'Recife, PE', 'Fortaleza, CE',
    'Florianópolis, SC', 'Goiânia, GO', 'Manaus, AM',
    'Campinas, SP', 'Londrina, PR', 'Joinville, SC',
  ];


  // ---- Header scroll effect ----
  const header = document.getElementById('header');
  let lastScroll = 0;

  window.addEventListener('scroll', () => {
    const scrollY = window.scrollY;
    if (scrollY > 50) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
    lastScroll = scrollY;
  }, { passive: true });


  // ---- Mobile menu ----
  const hamburger = document.getElementById('hamburger');
  const nav = document.getElementById('nav');

  hamburger.addEventListener('click', () => {
    hamburger.classList.toggle('active');
    nav.classList.toggle('open');
    document.body.style.overflow = nav.classList.contains('open') ? 'hidden' : '';
  });

  // Close menu on link click
  nav.querySelectorAll('.nav__link').forEach(link => {
    link.addEventListener('click', () => {
      hamburger.classList.remove('active');
      nav.classList.remove('open');
      document.body.style.overflow = '';
    });
  });


  // ---- Search suggestions ----
  function setupSuggestions(inputId, suggestionsId, data, isCity) {
    const input = document.getElementById(inputId);
    const suggestionsEl = document.getElementById(suggestionsId);

    input.addEventListener('input', () => {
      const val = input.value.toLowerCase().trim();
      suggestionsEl.innerHTML = '';

      if (val.length < 1) {
        suggestionsEl.classList.remove('active');
        return;
      }

      const filtered = data.filter(item => {
        const label = isCity ? item : item.label;
        return label.toLowerCase().includes(val);
      }).slice(0, 6);

      if (filtered.length === 0) {
        suggestionsEl.classList.remove('active');
        return;
      }

      filtered.forEach(item => {
        const div = document.createElement('div');
        div.classList.add('search-suggestions__item');
        if (isCity) {
          div.innerHTML = `<span>📍</span> ${item}`;
          div.addEventListener('click', () => {
            input.value = item;
            suggestionsEl.classList.remove('active');
          });
        } else {
          div.innerHTML = `<span>${item.emoji}</span> ${item.label}`;
          div.addEventListener('click', () => {
            input.value = item.label;
            suggestionsEl.classList.remove('active');
          });
        }
        suggestionsEl.appendChild(div);
      });

      suggestionsEl.classList.add('active');
    });

    // Close suggestions on outside click
    document.addEventListener('click', (e) => {
      if (!e.target.closest(`#${inputId}`) && !e.target.closest(`#${suggestionsId}`)) {
        suggestionsEl.classList.remove('active');
      }
    });
  }

  setupSuggestions('searchSpecialty', 'specialtySuggestions', specialties, false);
  setupSuggestions('searchLocation', 'locationSuggestions', cities, true);


  // ---- Quick tags ----
  const tags = document.querySelectorAll('.tag');
  const specialtyInput = document.getElementById('searchSpecialty');

  tags.forEach(tag => {
    tag.addEventListener('click', () => {
      tags.forEach(t => t.classList.remove('active'));
      tag.classList.add('active');
      specialtyInput.value = tag.dataset.value;
      specialtyInput.focus();
    });
  });


  // ---- Search button → redireciona pro BuscaController.php ----
  const searchBtn = document.getElementById('searchBtn');
  searchBtn.addEventListener('click', () => {
    const specialty = specialtyInput.value.trim();
    const location  = document.getElementById('searchLocation').value.trim();

    if (!specialty && !location) {
      specialtyInput.focus();
      specialtyInput.style.outline = '2px solid var(--orange-400)';
      setTimeout(() => { specialtyInput.style.outline = ''; }, 1500);
      return;
    }

    // Monta a URL com os parâmetros de busca
    const params = new URLSearchParams();
    if (specialty) params.set('especialidade', specialty);
    if (location)  params.set('cidade', location);

    // Redireciona para a página de resultados (backend PHP)
    window.location.href = `/controllers/BuscaController.php?${params.toString()}`;
  });

  // Também busca ao pressionar Enter nos campos
  document.querySelectorAll('.search-bar__input').forEach(input => {
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        searchBtn.click();
      }
    });
  });


  // ---- Counter animation ----
  function animateCounters() {
    const counters = document.querySelectorAll('.stat__number');
    counters.forEach(counter => {
      const target = parseInt(counter.dataset.target);
      const duration = 2000;
      const startTime = performance.now();

      function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        // Ease out cubic
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = Math.round(eased * target);

        counter.textContent = current.toLocaleString('pt-BR');

        if (progress < 1) {
          requestAnimationFrame(update);
        }
      }

      requestAnimationFrame(update);
    });
  }

  // Trigger counters when hero stats section is visible
  const statsSection = document.querySelector('.hero__stats');
  const statsObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        animateCounters();
        statsObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.3 });

  statsObserver.observe(statsSection);


  // ---- Carousel ----
  const track = document.getElementById('carouselTrack');
  const cards = track.querySelectorAll('.testimonial-card');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const dotsContainer = document.getElementById('carouselDots');

  let currentIndex = 0;
  let cardsPerView = getCardsPerView();

  function getCardsPerView() {
    if (window.innerWidth <= 768) return 1;
    if (window.innerWidth <= 1024) return 2;
    return 3;
  }

  const totalSlides = Math.max(cards.length - cardsPerView + 1, 1);

  // Create dots
  function createDots() {
    dotsContainer.innerHTML = '';
    const numDots = Math.max(cards.length - cardsPerView + 1, 1);
    for (let i = 0; i < numDots; i++) {
      const dot = document.createElement('button');
      dot.classList.add('carousel-dot');
      if (i === 0) dot.classList.add('active');
      dot.addEventListener('click', () => goToSlide(i));
      dotsContainer.appendChild(dot);
    }
  }

  function goToSlide(index) {
    const maxIndex = cards.length - cardsPerView;
    currentIndex = Math.max(0, Math.min(index, maxIndex));

    const cardWidth = cards[0].offsetWidth;
    const gap = 24; // 1.5rem
    const offset = currentIndex * (cardWidth + gap);
    track.style.transform = `translateX(-${offset}px)`;

    // Update dots
    dotsContainer.querySelectorAll('.carousel-dot').forEach((dot, i) => {
      dot.classList.toggle('active', i === currentIndex);
    });
  }

  prevBtn.addEventListener('click', () => goToSlide(currentIndex - 1));
  nextBtn.addEventListener('click', () => goToSlide(currentIndex + 1));

  // Auto-advance
  let autoplay = setInterval(() => {
    const maxIndex = cards.length - cardsPerView;
    goToSlide(currentIndex >= maxIndex ? 0 : currentIndex + 1);
  }, 5000);

  // Pause on hover
  track.addEventListener('mouseenter', () => clearInterval(autoplay));
  track.addEventListener('mouseleave', () => {
    autoplay = setInterval(() => {
      const maxIndex = cards.length - cardsPerView;
      goToSlide(currentIndex >= maxIndex ? 0 : currentIndex + 1);
    }, 5000);
  });

  // Touch/swipe support
  let startX = 0;
  let isDragging = false;

  track.addEventListener('touchstart', (e) => {
    startX = e.touches[0].clientX;
    isDragging = true;
  }, { passive: true });

  track.addEventListener('touchend', (e) => {
    if (!isDragging) return;
    const diff = startX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 50) {
      if (diff > 0) goToSlide(currentIndex + 1);
      else goToSlide(currentIndex - 1);
    }
    isDragging = false;
  }, { passive: true });

  createDots();

  // Recalc on resize
  window.addEventListener('resize', () => {
    cardsPerView = getCardsPerView();
    createDots();
    goToSlide(Math.min(currentIndex, cards.length - cardsPerView));
  });


  // ---- Scroll reveal ----
  const revealElements = document.querySelectorAll('.step, .section-header, .cta__card, .footer__grid');

  revealElements.forEach(el => el.classList.add('reveal'));

  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        revealObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

  revealElements.forEach(el => revealObserver.observe(el));

  // Stagger steps
  document.querySelectorAll('.step').forEach((step, i) => {
    step.classList.add(`reveal-delay-${i + 1}`);
  });

});
