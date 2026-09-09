import { createRequire } from "node:module";
import fs from "node:fs/promises";
import path from "node:path";

const require = createRequire(import.meta.url);
const { chromium } = require("C:/Developer/atoi/node_modules/playwright");

const baseUrl = "http://localhost:8000";
const outputDir = path.resolve("portfolio-captures/rentivo/raw");

const shots = [
  { file: "rentivo-home.png", url: "/" },
  { file: "rentivo-browse-cars.png", url: "/cars" },
  { file: "rentivo-car-details.png", url: "/cars/porsche-911-carrera-2023" },
  { file: "rentivo-agency.png", url: "/agency/manama-motors" },
  { file: "rentivo-dashboard.png", url: "/manage/manama-motors" },
  { file: "rentivo-fleet.png", url: "/manage/manama-motors/cars" },
  { file: "rentivo-bookings.png", url: "/manage/manama-motors/bookings" },
  { file: "rentivo-booking-detail.png", url: "/manage/manama-motors/bookings/BK-2026-000003" },
  { file: "rentivo-customers.png", url: "/manage/manama-motors/customers" },
  { file: "rentivo-employees.png", url: "/manage/manama-motors/employees" },
  { file: "rentivo-permissions.png", url: "/manage/manama-motors/employees/3/permissions" },
  { file: "rentivo-reports.png", url: "/manage/manama-motors/reports" },
];

async function waitForPage(page) {
  await page.waitForLoadState("domcontentloaded");
  await page.waitForLoadState("networkidle").catch(() => {});
  await page.evaluate(async () => {
    if (document.fonts?.ready) {
      await document.fonts.ready;
    }
    const images = Array.from(document.images);
    await Promise.all(
      images.map((image) => {
        if (image.complete) {
          return Promise.resolve();
        }
        return new Promise((resolve) => {
          image.addEventListener("load", resolve, { once: true });
          image.addEventListener("error", resolve, { once: true });
        });
      }),
    );
  });
  await page.waitForTimeout(350);
}

await fs.mkdir(outputDir, { recursive: true });

const browser = await chromium.launch();
const context = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  deviceScaleFactor: 1,
});

await context.addCookies([
  {
    name: "rentivo_session",
    value: "rentivoportfolioadmin",
    domain: "localhost",
    path: "/",
    httpOnly: true,
    sameSite: "Lax",
  },
]);

const page = await context.newPage();

for (const shot of shots) {
  await page.goto(baseUrl + shot.url, { waitUntil: "domcontentloaded" });
  await waitForPage(page);
  await page.screenshot({
    path: path.join(outputDir, shot.file),
    fullPage: false,
    animations: "disabled",
    caret: "hide",
  });
  console.log(`saved ${shot.file}`);
}

await browser.close();
