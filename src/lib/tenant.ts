import { prisma } from "./db";
import { cache } from "react";

export const getTenantBySlug = cache(async (slug: string) => {
  return prisma.tenant.findUnique({
    where: { slug },
    include: {
      subscriptions: {
        where: { status: { in: ["active", "trial"] } },
        include: { plan: true },
        take: 1,
        orderBy: { createdAt: "desc" },
      },
    },
  });
});

export function isTenantActive(status: string, trialEndsAt: Date | null): boolean {
  if (status === "active") return true;
  if (status === "trial" && trialEndsAt) return trialEndsAt > new Date();
  return false;
}

export async function getPlanLimits(tenantId: number) {
  const sub = await prisma.subscription.findFirst({
    where: { tenantId, status: { in: ["active", "trial"] } },
    include: { plan: true },
    orderBy: { createdAt: "desc" },
  });
  return sub?.plan ?? null;
}

export async function checkPlanLimit(
  tenantId: number,
  resource: "professionals" | "units" | "clients" | "appointments_month"
): Promise<{ allowed: boolean; current: number; max: number }> {
  const plan = await getPlanLimits(tenantId);
  if (!plan) return { allowed: false, current: 0, max: 0 };

  let current = 0;
  let max = 0;

  switch (resource) {
    case "professionals":
      current = await prisma.professional.count({
        where: { tenantId, deletedAt: null, isActive: true },
      });
      max = plan.maxProfessionals;
      break;
    case "units":
      current = await prisma.unit.count({
        where: { tenantId, deletedAt: null, isActive: true },
      });
      max = plan.maxUnits;
      break;
    case "clients":
      current = await prisma.client.count({
        where: { tenantId, deletedAt: null },
      });
      max = plan.maxClients;
      break;
    case "appointments_month": {
      const now = new Date();
      const start = new Date(now.getFullYear(), now.getMonth(), 1);
      const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
      current = await prisma.appointment.count({
        where: {
          tenantId,
          deletedAt: null,
          date: { gte: start, lte: end },
          status: { notIn: ["cancelled_by_client", "cancelled_by_business"] },
        },
      });
      max = plan.maxAppointmentsMonth;
      break;
    }
  }

  return { allowed: max === -1 || current < max, current, max };
}
