"use strict";
(() => {
  const main = document.getElementById("main-photo");
  if (!main) return;
  const thumbs = [...document.querySelectorAll("[data-photo]")];
  const images = thumbs.map((el) => el.dataset.photo);
  let index = 0;
  const dialog = document.getElementById("photo-dialog");
  function show(next) {
    index = (next + images.length) % images.length;
    main.src = images[index];
    main.alt = main.alt.replace(/foto \d+$/, `foto ${index + 1}`);
    document.getElementById("zoom-photo").src = images[index];
    document.getElementById("photo-count").textContent =
      `${index + 1} / ${images.length}`;
    thumbs.forEach((el, i) =>
      el.setAttribute("aria-pressed", String(i === index)),
    );
    if (images.length > 1) {
      const preload = new Image();
      preload.src = images[(index + 1) % images.length];
    }
  }
  thumbs.forEach((el, i) => el.addEventListener("click", () => show(i)));
  document
    .querySelectorAll("[data-gallery-step]")
    .forEach((el) =>
      el.addEventListener("click", () =>
        show(index + Number(el.dataset.galleryStep)),
      ),
    );
  document
    .getElementById("zoom-open")
    .addEventListener("click", () => dialog.showModal());
  document
    .getElementById("zoom-close")
    .addEventListener("click", () => dialog.close());
  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) dialog.close();
  });
  document.addEventListener("keydown", (event) => {
    if (
      !dialog.open &&
      !document
        .querySelector(".vehicle-gallery")
        .contains(document.activeElement)
    )
      return;
    if (event.key === "ArrowRight") {
      event.preventDefault();
      show(index + 1);
    }
    if (event.key === "ArrowLeft") {
      event.preventDefault();
      show(index - 1);
    }
  });
  let x;
  [main, document.getElementById("zoom-photo")].forEach((el) => {
    el.addEventListener(
      "touchstart",
      (e) => {
        x = e.touches[0].clientX;
      },
      { passive: true },
    );
    el.addEventListener(
      "touchend",
      (e) => {
        if (x !== undefined) {
          const dx = e.changedTouches[0].clientX - x;
          if (Math.abs(dx) > 50) {
            e.preventDefault();
            show(index + (dx < 0 ? 1 : -1));
          }
          x = undefined;
        }
      },
      { passive: false },
    );
  });
})();
