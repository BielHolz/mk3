/* Browser tests against the isolated HTTP test server; requires Playwright.
   MK3_TEST_BASE_URL=http://127.0.0.1:8081 node tests/browser.cjs
   Starts with an empty disposable database. Credentials are generated only in memory.
   After the run, reset that disposable DB before rerunning or running integration.php.
*/
"use strict";
const { chromium } = require("playwright");
const assert = require("node:assert/strict");
const path = require("node:path");
const fs = require("node:fs");
const crypto = require("node:crypto");
const base = process.env.MK3_TEST_BASE_URL;
if (!base || !["localhost", "127.0.0.1"].includes(new URL(base).hostname))
  throw new Error(
    "Informe o servidor de teste local isolado em MK3_TEST_BASE_URL.",
  );
(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.CHROMIUM_PATH || "/usr/bin/chromium",
    headless: true,
    args: ["--no-sandbox"],
  });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
  });
  const page = await context.newPage();
  const errors = [];
  const ids = [];
  page.on("pageerror", (e) => errors.push(e.message));
  page.on("console", (msg) => {
    if (msg.type() === "error" && !msg.text().includes("favicon"))
      errors.push(msg.text());
  });
  const shot = process.env.MK3_SCREENSHOTS_DIR;
  if (shot) fs.mkdirSync(shot, { recursive: true });
  async function capture(name) {
    for (const el of await page.locator(".reveal").all()) {
      await el.scrollIntoViewIfNeeded();
      await page.waitForTimeout(120);
    }
    await page.evaluate(() => scrollTo({ top: 0, behavior: "instant" }));
    await page.waitForTimeout(700);
    await page.screenshot({ path: path.join(shot, name), fullPage: true });
  }
  async function checkNoOverflow(label) {
    assert(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth + 1,
      ),
      label,
    );
  }
  async function createVehicle(title, order) {
    await page.goto(base + "/admin/edit.php");
    for (const [key, value] of Object.entries({
      title,
      brand: "Marca de teste",
      model: "Modelo de teste",
      version: "Versão de teste",
      manufacture_year: "2021",
      model_year: "2022",
      mileage: "12000",
      price: String(50000 + order * 1000),
      featured_order: String(order),
    }))
      await page.locator(`[name="${key}"]`).fill(value);
    await page.locator('[name="featured"]').check();
    await page
      .getByRole("button", { name: "Salvar veículo", exact: true })
      .click();
    await page.waitForURL(/id=\d+&saved=1/);
    const id = new URL(page.url()).searchParams.get("id");
    ids.push(id);
    await page
      .locator('[name="photos[]"]')
      .setInputFiles([
        path.join(__dirname, "../assets/carro-01.jpg"),
        path.join(__dirname, "../assets/carro-02.jpg"),
      ]);
    assert.equal(
      await page.locator("#upload-preview img").count(),
      2,
      "prévia de upload múltiplo",
    );
    await page
      .getByRole("button", { name: "Enviar fotografias", exact: true })
      .click();
    await page.waitForURL(/photos=1/);
    assert.equal(await page.locator(".photo-tile").count(), 2);
    return id;
  }
  try {
    // Empty state must render without a fake listing and without carousel errors.
    await page.goto(base + "/");
    assert.equal(await page.locator(".car-card").count(), 0);
    assert(await page.locator("#hero-forward").isDisabled());
    await page.goto(base + "/instalar.php");
    assert.equal(
      await page.locator('[name="pass"]').count(),
      1,
      "banco de teste precisa estar vazio",
    );
    const email = `browser-${crypto.randomBytes(5).toString("hex")}@example.test`;
    const password = crypto.randomBytes(20).toString("hex");
    await page.locator('[name="name"]').fill("Administrador de teste");
    await page.locator('[name="email"]').fill(email);
    await page.locator('[name="pass"]').fill(password);
    await page.getByRole("button", { name: "Criar administrador" }).click();
    await page.goto(base + "/admin/login.php");
    await page.locator('[name="email"]').fill(email);
    await page.locator('[name="password"]').fill(password);
    await page.getByRole("button", { name: "Entrar no painel" }).click();
    await page.waitForURL(base + "/admin/index.php");
    const first = await createVehicle("[TESTE] Primeiro destaque", 1);
    await page.goto(base + "/");
    assert(
      await page.locator("#hero-forward").isDisabled(),
      "um único destaque não troca",
    );
    const second = await createVehicle("[TESTE] Segundo destaque", 2);
    await page.goto(base + "/");
    assert.equal(
      await page.locator("#hero-name").textContent(),
      "[TESTE] Primeiro destaque",
    );
    await page.mouse.move(0, 0);
    await page.waitForFunction(
      () => document.querySelector("#hero-index").textContent === "02",
      null,
      { timeout: 7500 },
    );
    assert.equal(
      await page.locator("#hero-name").textContent(),
      "[TESTE] Segundo destaque",
      "troca automática",
    );
    await page
      .getByRole("button", { name: "Destaque anterior", exact: true })
      .click();
    await page.waitForFunction(
      () => document.querySelector("#hero-index").textContent === "01",
    );
    await page.mouse.move(0, 0);
    await page.locator("#hero-prev").blur();
    await page.waitForTimeout(2200);
    assert.equal(
      await page.locator("#hero-index").innerText(),
      "01",
      "troca manual reinicia intervalo",
    );
    await page.locator("#hero-stage").hover();
    await page.waitForTimeout(5500);
    assert.equal(
      await page.locator("#hero-index").innerText(),
      "01",
      "hover pausa o destaque",
    );
    await page.locator("#hero-pause").click();
    await page.locator("#hero-pause").blur();
    await page.mouse.move(0, 0);
    await page.waitForTimeout(5500);
    assert.equal(
      await page.locator("#hero-index").innerText(),
      "01",
      "pausa explícita",
    );
    await checkNoOverflow("desktop sem rolagem horizontal");
    if (shot) await capture("home-desktop.png");
    await page.goto(base + "/veiculo.php?id=" + first);
    assert.equal(await page.locator(".vehicle-thumbs button").count(), 2);
    const initial = await page.locator("#main-photo").getAttribute("src");
    await page
      .getByRole("button", { name: "Próxima foto", exact: true })
      .first()
      .click();
    assert.notEqual(
      await page.locator("#main-photo").getAttribute("src"),
      initial,
    );
    await page.locator("#zoom-open").click();
    assert(await page.locator("#photo-dialog").evaluate((el) => el.open));
    await page.keyboard.press("ArrowLeft");
    await page.keyboard.press("Escape");
    assert(!(await page.locator("#photo-dialog").evaluate((el) => el.open)));
    if (shot)
      await page.screenshot({
        path: path.join(shot, "vehicle-desktop.png"),
        fullPage: true,
      });
    await page.goto(base + "/admin/index.php");
    await checkNoOverflow("painel desktop sem overflow");
    if (shot)
      await page.screenshot({
        path: path.join(shot, "admin-desktop.png"),
        fullPage: true,
      });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(base + "/");
    await checkNoOverflow("home mobile sem overflow");
    assert(!(await page.locator(".main-nav").isVisible()));
    await page.locator(".menu-toggle").click();
    assert(await page.locator(".main-nav").isVisible());
    await page.locator('.main-nav a[href="index.php#estoque"]').click();
    assert(!(await page.locator(".main-nav").isVisible()));
    if (shot) {
      await page.evaluate(() => scrollTo(0, 0));
      await capture("home-mobile.png");
    }
    await page.goto(base + "/veiculo.php?id=" + second);
    await checkNoOverflow("detalhe mobile sem overflow");
    await page.locator("#zoom-open").click();
    await page.getByRole("button", { name: "Fechar ×" }).click();
    await page.goto(base + "/admin/edit.php?id=" + first);
    await checkNoOverflow("edição mobile sem overflow");
    await page.goto(base + "/admin/settings.php");
    await checkNoOverflow("configurações mobile sem overflow");
    const reduced = await browser.newContext({
      reducedMotion: "reduce",
      viewport: { width: 768, height: 1024 },
    });
    const reducedPage = await reduced.newPage();
    await reducedPage.goto(base + "/");
    await reducedPage.waitForTimeout(5500);
    assert.equal(
      await reducedPage.locator("#hero-index").innerText(),
      "01",
      "movimento reduzido desativa reprodução automática",
    );
    await reduced.close();
    assert.deepEqual(errors, [], "nenhum erro JavaScript, CSP ou recurso");
    console.log(
      "OK: navegador — zero/um/dois destaques, reprodução automática, setas, reinício, pausas, galeria, zoom, menu, uploads, responsividade e movimento reduzido.",
    );
  } finally {
    // Remove only the vehicle fixtures created by this test, using authenticated routes.
    await page.goto(base + "/admin/index.php");
    const csrf = await page
      .locator('input[name="csrf"]')
      .first()
      .inputValue()
      .catch(() => null);
    if (csrf)
      for (const id of ids)
        await context.request.post(base + "/admin/delete.php", {
          form: { csrf, id },
        });
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
