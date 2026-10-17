"use strict";
const menu = document.querySelector(".menu-toggle");
const nav = document.querySelector(".main-nav");
menu?.addEventListener("click", () => {
  const open = menu.getAttribute("aria-expanded") !== "true";
  menu.setAttribute("aria-expanded", String(open));
  nav.classList.toggle("is-open", open);
});
nav?.addEventListener("click", (event) => {
  if (event.target.closest("a")) {
    nav.classList.remove("is-open");
    menu?.setAttribute("aria-expanded", "false");
  }
});
document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && nav?.classList.contains("is-open")) {
    nav.classList.remove("is-open");
    menu.setAttribute("aria-expanded", "false");
    menu.focus();
  }
});
document.querySelectorAll("form[data-confirm]").forEach((form) =>
  form.addEventListener("submit", (event) => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  }),
);
document.querySelectorAll("img[data-fallback]").forEach((img) => {
  const fallback = () => {
    if (img.dataset.fallback) {
      const src = img.dataset.fallback;
      delete img.dataset.fallback;
      img.src = src;
    }
  };
  img.addEventListener("error", fallback, { once: true });
  if (img.complete && img.naturalWidth === 0) fallback();
});
const reducedMotion = matchMedia("(prefers-reduced-motion: reduce)");
if ("IntersectionObserver" in window && !reducedMotion.matches) {
  const observer = new IntersectionObserver(
    (entries) =>
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");
          observer.unobserve(entry.target);
        }
      }),
    { threshold: 0.08 },
  );
  document.querySelectorAll(".reveal").forEach((el) => {
    el.classList.add("reveal-ready");
    observer.observe(el);
  });
}
