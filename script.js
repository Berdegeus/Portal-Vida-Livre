/* ========================================
   VidaLivre — Scripts
   ======================================== */

document.addEventListener("DOMContentLoaded", () => {
  const API_BASE = "/backend/api";
  const ROUTES = {
    login: "/frontend/login.html",
    register: "/frontend/cadastro.html",
    profile: "/frontend/dashboard.html",
    search: "/frontend/busca.html",
  };

  const fallbackSpecialties = [
    { emoji: "🚬", label: "Tabagismo" },
    { emoji: "🍷", label: "Alcoolismo" },
    { emoji: "📱", label: "Dependência Digital" },
    { emoji: "🎰", label: "Jogos de Azar" },
    { emoji: "💊", label: "Dependência Química" },
    { emoji: "🍽️", label: "Transtornos Alimentares" },
    { emoji: "🤝", label: "Grupos de Apoio" },
    { emoji: "🧠", label: "Psicólogo" },
    { emoji: "💉", label: "Psiquiatra" },
    { emoji: "🏥", label: "Clínica de Reabilitação" },
    { emoji: "☕", label: "Cafeína" },
    { emoji: "🎮", label: "Videogames" },
    { emoji: "🛒", label: "Compras Compulsivas" },
  ];

  const fallbackCities = [
    "São Paulo, SP",
    "Rio de Janeiro, RJ",
    "Curitiba, PR",
    "Belo Horizonte, MG",
    "Porto Alegre, RS",
    "Salvador, BA",
    "Brasília, DF",
    "Recife, PE",
    "Fortaleza, CE",
    "Florianópolis, SC",
    "Goiânia, GO",
    "Manaus, AM",
    "Campinas, SP",
    "Londrina, PR",
    "Joinville, SC",
  ];

  const header = document.getElementById("header");
  const nav = document.getElementById("nav");
  const hamburger = document.getElementById("hamburger");
  const headerActions = document.getElementById("headerActions");
  const specialtyInput = document.getElementById("searchSpecialty");
  const locationInput = document.getElementById("searchLocation");
  const searchBtn = document.getElementById("searchBtn");
  const heroBadgeText = document.getElementById("heroBadgeText");
  const statsSection = document.querySelector(".hero__stats");
  const statsCounters = Array.from(document.querySelectorAll(".stat__number"));
  const track = document.getElementById("carouselTrack");
  const prevBtn = document.getElementById("prevBtn");
  const nextBtn = document.getElementById("nextBtn");
  const dotsContainer = document.getElementById("carouselDots");

  let csrfToken = "";
  let specialties = [...fallbackSpecialties];
  let cities = [...fallbackCities];
  let statsVisible = false;
  let statsLoaded = false;
  let statsAnimated = false;

  const normalizeText = (value) =>
    String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .trim();

  const uniq = (items) => Array.from(new Set(items.filter(Boolean)));

  const escapeHtml = (value) =>
    String(value || "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  const closeMobileMenu = () => {
    if (!hamburger || !nav) {
      return;
    }

    hamburger.classList.remove("active");
    nav.classList.remove("open");
    document.body.style.overflow = "";
  };

  const buildSearchUrl = ({ specialty = "", city = "", type = "" } = {}) => {
    const params = new URLSearchParams();

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

    return query ? `${ROUTES.search}?${query}` : ROUTES.search;
  };

  const applyPayloadCsrf = (payload) => {
    const token = payload?.data?.csrf_token;

    if (token) {
      csrfToken = token;
    }
  };

  const requestJson = async (path, options = {}) => {
    const settings = {
      method: options.method || "GET",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        ...(options.headers || {}),
      },
    };

    if (options.csrf && csrfToken) {
      settings.headers["X-CSRF-Token"] = csrfToken;
    }

    if (options.body !== undefined) {
      settings.headers["Content-Type"] = "application/json";
      settings.body = JSON.stringify(options.body);
    }

    const response = await fetch(`${API_BASE}/${path}`, settings);
    const payload = await response.json();
    applyPayloadCsrf(payload);

    if (!response.ok || payload.success === false) {
      throw new Error(
        payload.message || "Nao foi possivel processar a solicitacao.",
      );
    }

    return payload.data || {};
  };

  const loadSession = async () => requestJson("me.php");
  const loadHomeData = async () => requestJson("home-data.php");

  const handleLogout = async () => {
    try {
      if (!csrfToken) {
        await loadSession();
      }

      await requestJson("logout.php", {
        method: "POST",
        body: {},
        csrf: true,
      });
    } catch (error) {
      window.location.assign(ROUTES.login);
      return;
    }

    window.location.assign(`${ROUTES.login}?status=logged-out`);
  };

  const renderHeaderActions = (session) => {
    if (!headerActions) {
      return;
    }

    if (session?.login_two_factor_pending) {
      headerActions.innerHTML = `
        <a href="/frontend/two-factor.html" class="btn btn--ghost">Continuar acesso</a>
        <a href="${ROUTES.register}" class="btn btn--primary">Cadastre-se</a>
      `;

      return;
    }

    if (session?.authenticated) {
      const nome = session.user?.name?.split(" ")[0] || "você";
      headerActions.innerHTML = `
        <span class="btn btn--ghost">Olá, ${escapeHtml(nome)}!</span>
        <a href="${ROUTES.profile}" class="btn btn--ghost">Painel</a>
        <button type="button" class="btn btn--primary" id="logoutBtn">Sair</button>
      `;

      const logoutBtn = document.getElementById("logoutBtn");
      if (logoutBtn) {
        logoutBtn.addEventListener("click", async () => {
          closeMobileMenu();
          await handleLogout();
        });
      }

      return;
    }

    headerActions.innerHTML = `
      <a href="${ROUTES.login}" class="btn btn--ghost">Entrar</a>
      <a href="${ROUTES.register}" class="btn btn--primary">Cadastre-se</a>
    `;
  };

  const mergeHomeMetadata = (metadata) => {
    const emojiMap = new Map(
      fallbackSpecialties.map((item) => [
        normalizeText(item.label),
        item.emoji,
      ]),
    );

    const mergedSpecialties = uniq([
      ...fallbackSpecialties.map((item) => item.label),
      ...(metadata?.specialties || []),
    ]).map((label) => ({
      label,
      emoji: emojiMap.get(normalizeText(label)) || "✨",
    }));

    specialties = mergedSpecialties;
    cities = uniq([...(metadata?.locations || []), ...fallbackCities]);
  };

  const updateHomeStatsTargets = (stats) => {
    statsCounters.forEach((counter) => {
      const key = counter.dataset.statKey;
      const value = Number(stats?.[key] || 0);
      counter.dataset.target = String(value);
    });

    const specialistsTotal = Number(stats?.specialists_total || 0);
    if (heroBadgeText && specialistsTotal > 0) {
      heroBadgeText.textContent = `${specialistsTotal.toLocaleString("pt-BR")} especialistas prontos para ajudar`;
    }

    statsLoaded = true;
    maybeAnimateCounters();
  };

  function animateCounters() {
    statsCounters.forEach((counter) => {
      const target = parseInt(counter.dataset.target || "0", 10);
      const duration = 2000;
      const startTime = performance.now();

      function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = Math.round(eased * target);

        counter.textContent = current.toLocaleString("pt-BR");

        if (progress < 1) {
          requestAnimationFrame(update);
        }
      }

      requestAnimationFrame(update);
    });
  }

  const maybeAnimateCounters = () => {
    if (!statsVisible || !statsLoaded || statsAnimated) {
      return;
    }

    statsAnimated = true;
    animateCounters();
  };

  const setupSuggestions = (inputId, suggestionsId, getData, isCity) => {
    const input = document.getElementById(inputId);
    const suggestionsEl = document.getElementById(suggestionsId);

    if (!input || !suggestionsEl) {
      return;
    }

    input.addEventListener("input", () => {
      const value = normalizeText(input.value);
      const source = typeof getData === "function" ? getData() : getData;

      suggestionsEl.innerHTML = "";

      if (value.length < 1) {
        suggestionsEl.classList.remove("active");
        return;
      }

      const filtered = source
        .filter((item) => {
          const label = isCity ? item : item.label;
          return normalizeText(label).includes(value);
        })
        .slice(0, 6);

      if (filtered.length === 0) {
        suggestionsEl.classList.remove("active");
        return;
      }

      filtered.forEach((item) => {
        const div = document.createElement("div");
        div.classList.add("search-suggestions__item");

        if (isCity) {
          div.innerHTML = `<span>📍</span> ${escapeHtml(item)}`;
          div.addEventListener("click", () => {
            input.value = item;
            suggestionsEl.classList.remove("active");
          });
        } else {
          div.innerHTML = `<span>${escapeHtml(item.emoji)}</span> ${escapeHtml(item.label)}`;
          div.addEventListener("click", () => {
            input.value = item.label;
            suggestionsEl.classList.remove("active");
          });
        }

        suggestionsEl.appendChild(div);
      });

      suggestionsEl.classList.add("active");
    });

    document.addEventListener("click", (event) => {
      if (
        !event.target.closest(`#${inputId}`) &&
        !event.target.closest(`#${suggestionsId}`)
      ) {
        suggestionsEl.classList.remove("active");
      }
    });
  };

  const submitSearch = () => {
    if (!specialtyInput || !locationInput) {
      return;
    }

    const specialty = specialtyInput.value.trim();
    const city = locationInput.value.trim();

    if (!specialty && !city) {
      specialtyInput.focus();
      specialtyInput.style.outline = "2px solid var(--orange-400)";
      setTimeout(() => {
        specialtyInput.style.outline = "";
      }, 1500);
      return;
    }

    window.location.href = buildSearchUrl({ specialty, city });
  };

  const bindUnavailableLinks = () => {
    document.querySelectorAll("[data-disabled-link]").forEach((link) => {
      link.addEventListener("click", (event) => {
        event.preventDefault();
        window.alert(
          link.dataset.disabledLink || "Conteúdo disponível em breve.",
        );
      });
    });
  };

  const syncHeaderOnScroll = () => {
    if (!header) {
      return;
    }

    header.classList.toggle("scrolled", window.scrollY > 50);
  };

  syncHeaderOnScroll();
  window.addEventListener("scroll", syncHeaderOnScroll, { passive: true });

  if (hamburger && nav) {
    hamburger.addEventListener("click", () => {
      hamburger.classList.toggle("active");
      nav.classList.toggle("open");
      document.body.style.overflow = nav.classList.contains("open")
        ? "hidden"
        : "";
    });

    nav.addEventListener("click", (event) => {
      const target = event.target.closest("a, button");

      if (!target || target.matches("[data-disabled-link]")) {
        return;
      }

      closeMobileMenu();
    });
  }

  setupSuggestions(
    "searchSpecialty",
    "specialtySuggestions",
    () => specialties,
    false,
  );
  setupSuggestions("searchLocation", "locationSuggestions", () => cities, true);

  document.querySelectorAll(".tag").forEach((tag) => {
    tag.addEventListener("click", () => {
      document
        .querySelectorAll(".tag")
        .forEach((item) => item.classList.remove("active"));
      tag.classList.add("active");

      if (specialtyInput) {
        specialtyInput.value = tag.dataset.value || "";
        specialtyInput.focus();
      }
    });
  });

  if (searchBtn) {
    searchBtn.addEventListener("click", submitSearch);
  }

  document.querySelectorAll(".search-bar__input").forEach((input) => {
    input.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        submitSearch();
      }
    });
  });

  if (statsSection) {
    const statsObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) {
            return;
          }

          statsVisible = true;
          maybeAnimateCounters();
          statsObserver.unobserve(entry.target);
        });
      },
      { threshold: 0.3 },
    );

    statsObserver.observe(statsSection);
  }

  if (track && prevBtn && nextBtn && dotsContainer) {
    const cards = track.querySelectorAll(".testimonial-card");
    let currentIndex = 0;
    let cardsPerView = getCardsPerView();

    function getCardsPerView() {
      if (window.innerWidth <= 768) return 1;
      if (window.innerWidth <= 1024) return 2;
      return 3;
    }

    function createDots() {
      dotsContainer.innerHTML = "";
      const numDots = Math.max(cards.length - cardsPerView + 1, 1);

      for (let index = 0; index < numDots; index += 1) {
        const dot = document.createElement("button");
        dot.classList.add("carousel-dot");

        if (index === 0) {
          dot.classList.add("active");
        }

        dot.addEventListener("click", () => goToSlide(index));
        dotsContainer.appendChild(dot);
      }
    }

    function goToSlide(index) {
      const maxIndex = cards.length - cardsPerView;
      currentIndex = Math.max(0, Math.min(index, maxIndex));

      const cardWidth = cards[0].offsetWidth;
      const gap = 24;
      const offset = currentIndex * (cardWidth + gap);
      track.style.transform = `translateX(-${offset}px)`;

      dotsContainer
        .querySelectorAll(".carousel-dot")
        .forEach((dot, dotIndex) => {
          dot.classList.toggle("active", dotIndex === currentIndex);
        });
    }

    prevBtn.addEventListener("click", () => goToSlide(currentIndex - 1));
    nextBtn.addEventListener("click", () => goToSlide(currentIndex + 1));

    let autoplay = setInterval(() => {
      const maxIndex = cards.length - cardsPerView;
      goToSlide(currentIndex >= maxIndex ? 0 : currentIndex + 1);
    }, 5000);

    track.addEventListener("mouseenter", () => clearInterval(autoplay));
    track.addEventListener("mouseleave", () => {
      autoplay = setInterval(() => {
        const maxIndex = cards.length - cardsPerView;
        goToSlide(currentIndex >= maxIndex ? 0 : currentIndex + 1);
      }, 5000);
    });

    let startX = 0;
    let isDragging = false;

    track.addEventListener(
      "touchstart",
      (event) => {
        startX = event.touches[0].clientX;
        isDragging = true;
      },
      { passive: true },
    );

    track.addEventListener(
      "touchend",
      (event) => {
        if (!isDragging) {
          return;
        }

        const diff = startX - event.changedTouches[0].clientX;

        if (Math.abs(diff) > 50) {
          if (diff > 0) goToSlide(currentIndex + 1);
          else goToSlide(currentIndex - 1);
        }

        isDragging = false;
      },
      { passive: true },
    );

    createDots();

    window.addEventListener("resize", () => {
      cardsPerView = getCardsPerView();
      createDots();
      goToSlide(Math.min(currentIndex, cards.length - cardsPerView));
    });
  }

  const revealElements = document.querySelectorAll(
    ".step, .section-header, .cta__card, .footer__grid",
  );

  revealElements.forEach((element) => element.classList.add("reveal"));

  const revealObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) {
          return;
        }

        entry.target.classList.add("visible");
        revealObserver.unobserve(entry.target);
      });
    },
    { threshold: 0.15, rootMargin: "0px 0px -40px 0px" },
  );

  revealElements.forEach((element) => revealObserver.observe(element));

  document.querySelectorAll(".step").forEach((step, index) => {
    step.classList.add(`reveal-delay-${index + 1}`);
  });

  bindUnavailableLinks();

  Promise.allSettled([loadSession(), loadHomeData()]).then((results) => {
    const [sessionResult, homeDataResult] = results;

    if (sessionResult.status === "fulfilled") {
      renderHeaderActions(sessionResult.value);
    } else {
      renderHeaderActions({ authenticated: false });
    }

    if (homeDataResult.status === "fulfilled") {
      mergeHomeMetadata(homeDataResult.value.metadata);
      updateHomeStatsTargets(homeDataResult.value.stats);
    } else {
      statsLoaded = true;
      maybeAnimateCounters();
    }
  });
});
