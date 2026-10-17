"use strict";
(() => {
  const data = document.getElementById("slide-data");
  const slides = JSON.parse(data?.textContent || "[]");
  const stage = document.getElementById("hero-stage");
  if (!stage || !slides.length) return;
  const front = document.getElementById("hero-img");
  const back = document.getElementById("hero-next");
  const link = document.getElementById("hero-link");
  const dots = document.getElementById("hero-dots");
  const pause = document.getElementById("hero-pause");
  const reduced = matchMedia("(prefers-reduced-motion: reduce)");
  let current = 0,
    timer,
    busy = false,
    manualPause = false,
    hovered = false,
    focused = false,
    sequence = 0;
  const cache = new Map();
  function load(src) {
    if (!cache.has(src))
      cache.set(
        src,
        new Promise((resolve, reject) => {
          const img = new Image();
          img.onload = () => resolve(img);
          img.onerror = () => {
            cache.delete(src);
            reject(new Error("Imagem indisponível"));
          };
          img.src = src;
        }),
      );
    return cache.get(src);
  }
  const preload = () => {
    if (slides.length > 1)
      load(slides[(current + 1) % slides.length].src).catch(() => {});
  };
  function schedule() {
    clearTimeout(timer);
    timer = undefined;
    if (
      slides.length > 1 &&
      !manualPause &&
      !hovered &&
      !focused &&
      !document.hidden &&
      !reduced.matches
    )
      timer = setTimeout(() => {
        timer = undefined;
        change(current + 1, false);
      }, 5000);
  }
  async function change(index, manual = true) {
    clearTimeout(timer);
    timer = undefined;
    if (busy || slides.length < 2) {
      schedule();
      return;
    }
    const target = (index + slides.length) % slides.length;
    if (target === current) {
      schedule();
      return;
    }
    busy = true;
    const token = ++sequence;
    try {
      await load(slides[target].src);
      if (token !== sequence) return;
      const slide = slides[target];
      back.src = slide.src;
      schedule();
      link.classList.toggle("is-changing", !reduced.matches);
      if (!reduced.matches)
        await new Promise((resolve) => setTimeout(resolve, 850));
      front.src = slide.src;
      front.alt = slide.title;
      link.href = slide.link;
      document.getElementById("hero-name").textContent = slide.title;
      document.getElementById("hero-index").textContent = String(
        target + 1,
      ).padStart(2, "0");
      current = target;
      link.classList.remove("is-changing");
      [...dots.children].forEach((dot, i) =>
        dot.setAttribute("aria-current", String(i === current)),
      );
      if (manual)
        document.getElementById("hero-status").textContent = slide.title;
      preload();
    } catch {
      document.getElementById("hero-status").textContent =
        "Não foi possível carregar esta fotografia.";
    } finally {
      busy = false;
      if (timer === undefined) schedule();
    }
  }
  slides.forEach((slide, i) => {
    const dot = document.createElement("button");
    dot.type = "button";
    dot.setAttribute("aria-label", `Mostrar ${slide.title}`);
    dot.setAttribute("aria-current", String(i === 0));
    dot.addEventListener("click", () => change(i));
    dots.appendChild(dot);
  });
  document
    .getElementById("hero-prev")
    .addEventListener("click", () => change(current - 1));
  document
    .getElementById("hero-forward")
    .addEventListener("click", () => change(current + 1));
  pause.addEventListener("click", () => {
    manualPause = !manualPause;
    pause.setAttribute("aria-pressed", String(manualPause));
    pause.setAttribute(
      "aria-label",
      manualPause ? "Retomar troca automática" : "Pausar troca automática",
    );
    pause.textContent = manualPause ? "▶" : "Ⅱ";
    schedule();
  });
  stage.addEventListener("pointerenter", () => {
    hovered = true;
    schedule();
  });
  stage.addEventListener("pointerleave", () => {
    hovered = false;
    schedule();
  });
  stage.addEventListener("focusin", () => {
    focused = true;
    schedule();
  });
  stage.addEventListener("focusout", (event) => {
    if (!stage.contains(event.relatedTarget)) {
      focused = false;
      schedule();
    }
  });
  document.addEventListener("visibilitychange", schedule);
  reduced.addEventListener("change", schedule);
  let startX;
  link.addEventListener(
    "touchstart",
    (e) => {
      startX = e.touches[0].clientX;
    },
    { passive: true },
  );
  link.addEventListener(
    "touchend",
    (e) => {
      if (startX !== undefined) {
        const dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 55) {
          e.preventDefault();
          change(current + (dx < 0 ? 1 : -1));
        }
        startX = undefined;
      }
    },
    { passive: false },
  );
  preload();
  schedule();
})();
