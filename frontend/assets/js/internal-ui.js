document.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector(".header");
  const nav = document.querySelector("#nav");
  const hamburger = document.querySelector("#hamburger");
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  if (header) {
    const syncHeader = () => {
      header.classList.toggle("scrolled", window.scrollY > 24);
    };

    syncHeader();
    window.addEventListener("scroll", syncHeader, { passive: true });
  }

  if (hamburger && nav) {
    const closeMenu = () => {
      hamburger.classList.remove("active");
      nav.classList.remove("open");
      document.body.style.overflow = "";
    };

    hamburger.addEventListener("click", () => {
      hamburger.classList.toggle("active");
      nav.classList.toggle("open");
      document.body.style.overflow = nav.classList.contains("open") ? "hidden" : "";
    });

    nav.addEventListener("click", (event) => {
      const target = event.target.closest("a, button");

      if (!target) {
        return;
      }

      closeMenu();
    });
  }

  const revealTargets = Array.from(
    document.querySelectorAll(".panel, .section-block, .info-list p, .modal-box"),
  ).filter((element) => !element.closest(".hidden"));

  if (prefersReducedMotion) {
    revealTargets.forEach((element) => {
      element.classList.add("visible");
    });
    return;
  }

  revealTargets.forEach((element, index) => {
    element.classList.add("reveal");

    if (index === 1) {
      element.classList.add("reveal-delay-1");
    } else if (index === 2) {
      element.classList.add("reveal-delay-2");
    } else if (index >= 3) {
      element.classList.add("reveal-delay-3");
    }
  });

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) {
          return;
        }

        entry.target.classList.add("visible");
        observer.unobserve(entry.target);
      });
    },
    { threshold: 0.15, rootMargin: "0px 0px -40px 0px" },
  );

  revealTargets.forEach((element) => observer.observe(element));
});
