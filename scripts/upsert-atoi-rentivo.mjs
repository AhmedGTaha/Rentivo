import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";

const atoiRoot = "C:/Developer/atoi";
process.chdir(atoiRoot);
process.loadEnvFile(".env");

const require = createRequire(import.meta.url);
const { PrismaClient } = require("C:/Developer/atoi/node_modules/@prisma/client");
const prisma = new PrismaClient();

const imageFiles = [
  ["rentivo-home.webp", "Rentivo homepage marketplace hero"],
  ["rentivo-browse-cars.webp", "Rentivo public car marketplace with filters and fleet cards"],
  ["rentivo-car-details.webp", "Rentivo car details page with gallery, price and booking call to action"],
  ["rentivo-agency.webp", "Rentivo agency storefront with locations and available fleet"],
  ["rentivo-dashboard.webp", "Rentivo organization dashboard with fleet and booking metrics"],
  ["rentivo-fleet.webp", "Rentivo fleet management table with vehicle statuses"],
  ["rentivo-bookings.webp", "Rentivo booking management table with lifecycle and payment statuses"],
  ["rentivo-booking-detail.webp", "Rentivo active booking detail with rental timeline and return workflow"],
  ["rentivo-permissions.webp", "Rentivo employee permission matrix for organization staff"],
  ["rentivo-reports.webp", "Rentivo reports dashboard with revenue, bookings and fleet status"],
];

const technologies = [
  "PHP 8.3+",
  "MySQL 8",
  "PDO",
  "Server-rendered PHP",
  "Vanilla JavaScript",
  "Composer",
  "Google OAuth",
  "PHPMailer",
  "PHPUnit",
];

const projectInput = {
  titleEn: "Rentivo",
  titleAr: "Rentivo",
  descriptionEn:
    "A multi-agency car rental SaaS platform that combines a public vehicle marketplace with complete agency fleet, booking, customer, employee and rental management.",
  descriptionAr:
    "منصة SaaS متعددة الوكالات لتأجير السيارات تجمع بين سوق عام للمركبات وإدارة كاملة للأسطول والحجوزات والعملاء والموظفين ودورة التأجير لكل وكالة.",
  category: "Car Rental SaaS / Marketplace",
  categoryAr: "منصة SaaS وسوق لتأجير السيارات",
  clientName: "Rentivo",
  problemEn:
    "Rental agencies need tenant-isolated operations while customers need one public marketplace to compare cars, agencies, availability and rates.",
  problemAr:
    "تحتاج وكالات التأجير إلى تشغيل معزول لكل منظمة، بينما يحتاج العملاء إلى سوق واحد لمقارنة السيارات والوكالات والتوافر والأسعار.",
  builtEn:
    "Rentivo implements public browsing, agency storefronts, Google-only authentication, organization-scoped dashboards, fleet and booking management, customer records, employee permissions, pickup and return workflows, documents, reporting and activity logs.",
  builtAr:
    "ينفذ Rentivo التصفح العام، صفحات الوكالات، تسجيل الدخول عبر Google، لوحات تحكم معزولة حسب المنظمة، إدارة الأسطول والحجوزات والعملاء وصلاحيات الموظفين ومراحل الاستلام والإرجاع والمستندات والتقارير وسجلات النشاط.",
  resultEn:
    "The product reads as a complete operating system for Bahrain rental agencies, with marketplace discovery and back-office workflows in one application.",
  resultAr:
    "تظهر المنصة كنظام تشغيل متكامل لوكالات التأجير في البحرين، يجمع اكتشاف السوق مع سير عمل الإدارة الداخلية في تطبيق واحد.",
  technologies,
  liveUrl: "https://github.com/AhmedGTaha/Rentivo",
  featured: true,
  published: true,
};

try {
  const existing = await prisma.portfolioProject.findFirst({
    where: { titleEn: "Rentivo" },
    include: { images: true },
  });

  const displayOrder =
    existing?.displayOrder ??
    (await prisma.portfolioProject.count());

  const project = existing
    ? await prisma.portfolioProject.update({
        where: { id: existing.id },
        data: { ...projectInput, displayOrder },
      })
    : await prisma.portfolioProject.create({
        data: { ...projectInput, displayOrder },
      });

  await prisma.portfolioImage.deleteMany({
    where: { portfolioProjectId: project.id },
  });

  for (const [index, [fileName, altEn]] of imageFiles.entries()) {
    const publicUrl = `/projects/rentivo/${fileName}`;
    const absolutePath = path.join(atoiRoot, "public", "projects", "rentivo", fileName);
    const fileSize = fs.statSync(absolutePath).size;

    await prisma.portfolioImage.create({
      data: {
        portfolioProjectId: project.id,
        storageKey: publicUrl,
        publicUrl,
        altEn,
        altAr: altEn,
        fileName,
        fileSize,
        isMain: fileName === "rentivo-browse-cars.webp",
        displayOrder: index,
      },
    });
  }

  const summary = await prisma.portfolioProject.findUnique({
    where: { id: project.id },
    include: { images: { orderBy: { displayOrder: "asc" } } },
  });

  console.log(
    JSON.stringify(
      {
        id: summary?.id,
        titleEn: summary?.titleEn,
        published: summary?.published,
        featured: summary?.featured,
        cover: summary?.images.find((image) => image.isMain)?.fileName,
        images: summary?.images.map((image) => image.fileName),
      },
      null,
      2,
    ),
  );
} finally {
  await prisma.$disconnect();
}
