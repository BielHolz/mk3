"use strict";
document.querySelectorAll("input[data-preview]").forEach((input) => {
  let urls = [];
  input.addEventListener("change", () => {
    urls.forEach((url) => URL.revokeObjectURL(url));
    urls = [];
    const target = document.getElementById(input.dataset.preview);
    target.replaceChildren();
    const files = [...input.files];
    let error = "";
    if (files.length > 12) error = "Escolha no máximo 12 fotografias.";
    files.forEach((file) => {
      if (
        !["image/jpeg", "image/png", "image/webp"].includes(file.type) ||
        file.size > 5 * 1024 * 1024
      )
        error = "Escolha imagens JPG, PNG ou WebP de até 5 MB.";
      else {
        const img = document.createElement("img");
        const url = URL.createObjectURL(file);
        urls.push(url);
        img.src = url;
        img.alt = file.name;
        target.appendChild(img);
      }
    });
    input.setCustomValidity(error);
    if (error) {
      const message = document.createElement("p");
      message.textContent = error;
      target.appendChild(message);
      input.reportValidity();
    }
  });
});
