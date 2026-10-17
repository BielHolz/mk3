"use strict";
document.querySelectorAll("input[data-preview]").forEach((input) => {
  const target = document.getElementById(input.dataset.preview);
  if (!target || typeof DataTransfer === "undefined") return;
  const maxFiles = Number(input.dataset.maxFiles ?? (input.multiple ? 12 : 1));
  let queue = [];
  let focusAfterRender;
  function syncFiles() {
    const transfer = new DataTransfer();
    queue.forEach(({ file }) => transfer.items.add(file));
    input.files = transfer.files;
  }
  function button(label, text, disabled, action) {
    const el = document.createElement("button");
    el.type = "button";
    el.className = "preview-action";
    el.textContent = text;
    el.setAttribute("aria-label", label);
    el.disabled = disabled;
    el.addEventListener("click", action);
    return el;
  }
  function render() {
    target.replaceChildren();
    let error = "";
    if (queue.length > maxFiles)
      error = `Há espaço para ${maxFiles} fotografia(s). Remova os arquivos excedentes.`;
    const summary = document.createElement("p");
    summary.className = "preview-summary";
    summary.textContent = queue.length
      ? `${queue.length} arquivo(s) selecionado(s). A ordem abaixo será mantida no envio.`
      : "Nenhuma imagem selecionada.";
    target.appendChild(summary);
    queue.forEach((item, index) => {
      const { file, url } = item;
      const tile = document.createElement("article");
      tile.className = "preview-tile";
      if (url) {
        const img = document.createElement("img");
        img.src = url;
        img.alt = `Prévia: ${file.name}`;
        tile.appendChild(img);
      }
      if (!url || file.size === 0 || file.size > 5 * 1024 * 1024) {
        error =
          "Remova arquivos inválidos. Use JPG, PNG ou WebP de até 5 MB cada.";
        tile.classList.add("is-invalid");
      }
      const name = document.createElement("p");
      name.className = "preview-name";
      name.textContent = `${index + 1}. ${file.name}`;
      const size = document.createElement("span");
      size.className = "preview-size";
      size.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
      tile.append(name, size);
      const actions = document.createElement("div");
      actions.className = "preview-actions";
      function move(delta) {
        [queue[index], queue[index + delta]] = [
          queue[index + delta],
          queue[index],
        ];
        focusAfterRender = index + delta;
        syncFiles();
        render();
      }
      actions.append(
        button(`Mover ${file.name} para antes`, "←", index === 0, () =>
          move(-1),
        ),
        button(
          `Mover ${file.name} para depois`,
          "→",
          index === queue.length - 1,
          () => move(1),
        ),
        button(`Remover ${file.name} da seleção`, "Remover", false, () => {
          if (item.url) URL.revokeObjectURL(item.url);
          queue.splice(index, 1);
          focusAfterRender = Math.min(index, queue.length - 1);
          syncFiles();
          render();
        }),
      );
      tile.appendChild(actions);
      target.appendChild(tile);
    });
    input.setCustomValidity(error);
    if (error) {
      const message = document.createElement("p");
      message.className = "preview-error";
      message.setAttribute("role", "alert");
      message.textContent = error;
      target.appendChild(message);
    }
    if (focusAfterRender !== undefined) {
      const tiles = target.querySelectorAll(".preview-tile");
      const actions = tiles[focusAfterRender]?.querySelectorAll(
        "button:not(:disabled)",
      );
      if (actions?.length) actions[0].focus();
      else input.focus();
      focusAfterRender = undefined;
    }
  }
  input.addEventListener("change", () => {
    const added = [...input.files];
    if (!input.multiple) {
      queue.forEach((item) => {
        if (item.url) URL.revokeObjectURL(item.url);
      });
      queue = [];
    }
    added.forEach((file) => {
      if (
        !queue.some(
          (item) =>
            item.file.name === file.name &&
            item.file.size === file.size &&
            item.file.lastModified === file.lastModified,
        )
      ) {
        const validType = ["image/jpeg", "image/png", "image/webp"].includes(
          file.type,
        );
        queue.push({ file, url: validType ? URL.createObjectURL(file) : null });
      }
    });
    syncFiles();
    render();
  });
  input.form?.addEventListener("reset", () => {
    queue.forEach((item) => {
      if (item.url) URL.revokeObjectURL(item.url);
    });
    queue = [];
    input.setCustomValidity("");
    target.replaceChildren();
  });
});
