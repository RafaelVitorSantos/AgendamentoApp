import "dotenv/config";
import { PrismaClient } from "@prisma/client";
import { PrismaMariaDb } from "@prisma/adapter-mariadb";
import bcrypt from "bcryptjs";

const url = new URL(process.env.DATABASE_URL!);
const adapter = new PrismaMariaDb({
  host: url.hostname,
  port: parseInt(url.port || "3306"),
  user: url.username,
  password: url.password || undefined,
  database: url.pathname.slice(1),
});
const prisma = new PrismaClient({ adapter });

async function main() {
  console.log("Seeding database...");

  // Roles
  const roles = await Promise.all([
    prisma.role.upsert({ where: { name: "super_admin" }, update: {}, create: { name: "super_admin", label: "Super Admin" } }),
    prisma.role.upsert({ where: { name: "tenant_admin" }, update: {}, create: { name: "tenant_admin", label: "Administrador" } }),
    prisma.role.upsert({ where: { name: "manager" }, update: {}, create: { name: "manager", label: "Gerente" } }),
    prisma.role.upsert({ where: { name: "professional" }, update: {}, create: { name: "professional", label: "Profissional" } }),
    prisma.role.upsert({ where: { name: "receptionist" }, update: {}, create: { name: "receptionist", label: "Recepcionista" } }),
  ]);

  console.log("Roles criadas:", roles.map((r) => r.name));

  // Permissions
  const modules = [
    "appointments", "clients", "services", "professionals", "units",
    "financial", "reports", "queue", "loyalty", "reviews", "whatsapp", "settings",
  ];
  const actions = ["view", "create", "edit", "delete"];

  for (const mod of modules) {
    for (const action of actions) {
      await prisma.permission.upsert({
        where: { name: `${mod}.${action}` },
        update: {},
        create: { name: `${mod}.${action}`, module: mod, label: `${mod} - ${action}` },
      });
    }
  }

  console.log("Permissões criadas");

  // Planos
  const plans = [
    {
      name: "Grátis", slug: "free", price: 0,
      maxProfessionals: 1, maxAppointmentsMonth: 30, maxUnits: 1, maxClients: 50,
    },
    {
      name: "Básico", slug: "basic", price: 79,
      maxProfessionals: 3, maxAppointmentsMonth: 200, maxUnits: 1, maxClients: 500,
      hasReports: true, hasFinancial: true,
    },
    {
      name: "Profissional", slug: "professional", price: 149,
      maxProfessionals: 10, maxAppointmentsMonth: 1000, maxUnits: 3, maxClients: 5000,
      hasReports: true, hasFinancial: true, hasWhatsapp: true, hasLoyalty: true,
      hasCommissions: true, hasReviews: true,
    },
    {
      name: "Enterprise", slug: "enterprise", price: 349,
      maxProfessionals: -1, maxAppointmentsMonth: -1, maxUnits: -1, maxClients: -1,
      hasReports: true, hasFinancial: true, hasWhatsapp: true, hasLoyalty: true,
      hasCommissions: true, hasReviews: true, hasCustomBrand: true,
    },
  ];

  for (const plan of plans) {
    await prisma.plan.upsert({
      where: { slug: plan.slug },
      update: plan,
      create: plan,
    });
  }

  console.log("Planos criados");

  // Demo tenant
  const adminRole = await prisma.role.findUnique({ where: { name: "tenant_admin" } });
  const freePlan = await prisma.plan.findUnique({ where: { slug: "free" } });

  if (adminRole && freePlan) {
    const existing = await prisma.tenant.findUnique({ where: { slug: "demo" } });
    if (!existing) {
      const tenant = await prisma.tenant.create({
        data: {
          name: "Demo Salão",
          slug: "demo",
          email: "demo@agendapro.com.br",
          status: "active",
        },
      });

      const passwordHash = await bcrypt.hash("demo123456", 12);
      await prisma.user.create({
        data: {
          tenantId: tenant.id,
          roleId: adminRole.id,
          name: "Admin Demo",
          email: "admin@demo.com",
          passwordHash,
        },
      });

      await prisma.unit.create({
        data: { tenantId: tenant.id, name: "Unidade Principal" },
      });

      const now = new Date();
      const periodEnd = new Date(now);
      periodEnd.setFullYear(periodEnd.getFullYear() + 1);

      await prisma.subscription.create({
        data: {
          tenantId: tenant.id,
          planId: freePlan.id,
          status: "active",
          currentPeriodStart: now,
          currentPeriodEnd: periodEnd,
        },
      });

      console.log("Tenant demo criado — login: admin@demo.com / demo123456 / slug: demo");
    }
  }

  console.log("Seed concluído!");
}

main().catch(console.error).finally(() => prisma.$disconnect());
