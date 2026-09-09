import { createRequire } from "node:module";
import fs from "node:fs/promises";
import path from "node:path";

const require = createRequire(import.meta.url);
const { chromium } = require("C:/Developer/atoi/node_modules/playwright");

const baseUrl = "http://localhost:3001";
const outputDir = path.resolve("portfolio-captures/atoi-verification");
const viewports = [
  { name: "desktop", width: 1440, height: 900 },
  { name: "tablet", width: 820, height: 1180 },
  { name: "mobile", width: 390, height: 844 },
];

await fs.mkdir(outputDir, { recursive: true });

const browser = await chromium.launch();

for (const viewport of viewports) {
  const context = await browser.newContext({
    viewport: { width: viewport.width, height: viewport.height },
  });
  const page = await context.newPage();

  await page.goto(baseUrl, { waitUntil: "domcontentloaded" });
  await page.waitForLoadState("networkidle").catch(() => {});
  await page.locator("#work").scrollIntoViewIfNeeded();
  await page.waitForTimeout(700);

  const rentivoCard = page.locator("article.work-card", { hasText: "Rentivo" }).first();
  await rentivoCard.waitFor({ state: "visible", timeout: 30000 });
  await rentivoCard.screenshot({
    path: path.join(outputDir, `rentivo-card-${viewport.name}.png`),
  });

  const galleryImage = rentivoCard.locator("img").first();
  const cardImageSource = await galleryImage.getAttribute("src");
  if (!cardImageSource || cardImageSource.includes("404")) {
    throw new Error(`Rentivo card image did not load at ${viewport.name}`);
  }

  await rentivoCard.getByRole("button", { name: /case study: rentivo/i }).click();
  const dialog = page.getByRole("dialog").filter({ hasText: "Rentivo" }).last();
  await dialog.waitFor({ state: "visible", timeout: 30000 });

  await dialog.screenshot({
    path: path.join(outputDir, `rentivo-modal-${viewport.name}.png`),
  });

  const status = dialog.locator(".project-gallery-status").first();
  await status.waitFor({ state: "visible", timeout: 10000 });
  const initialStatus = (await status.textContent())?.replace(/\s+/g, " ").trim();

  const next = dialog.getByRole("button", { name: /next image/i }).first();
  await next.click({ timeout: 10000 });
  await page.waitForTimeout(300);
  const nextStatus = (await status.textContent())?.replace(/\s+/g, " ").trim();

  const dotTen = dialog.getByRole("button", { name: /show image 10/i }).first();
  await dotTen.click({ timeout: 10000 });
  await page.waitForTimeout(300);
  const finalStatus = (await status.textContent())?.replace(/\s+/g, " ").trim();

  if (!initialStatus?.includes("1 / 10")) {
    throw new Error(`Expected initial gallery status 1 / 10 at ${viewport.name}, got ${initialStatus}`);
  }
  if (!nextStatus?.includes("2 / 10")) {
    throw new Error(`Expected next gallery status 2 / 10 at ${viewport.name}, got ${nextStatus}`);
  }
  if (!finalStatus?.includes("10 / 10")) {
    throw new Error(`Expected final gallery status 10 / 10 at ${viewport.name}, got ${finalStatus}`);
  }

  await context.close();
  console.log(`verified ${viewport.name}`);
}

await browser.close();
