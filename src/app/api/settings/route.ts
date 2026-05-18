import { NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";

export async function GET() {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const today = new Date();
  const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
  const monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);

  const [user, tenant, subscription, profCount, clientCount, unitCount, apptCount] = await Promise.all([
    prisma.user.findUnique({
      where: { id: session.userId },
      select: {
        id: true,
        name: true,
        email: true,
        phone: true,
        role: { select: { name: true, label: true } },
      },
    }),
    prisma.tenant.findUnique({
      where: { id: session.tenantId },
      select: {
        id: true, name: true, slug: true, email: true,
        phone: true, document: true, timezone: true,
        status: true, trialEndsAt: true,
      },
    }),
    prisma.subscription.findFirst({
      where: { tenantId: session.tenantId, status: { in: ["active", "trial"] } },
      include: { plan: true },
      orderBy: { createdAt: "desc" },
    }),
    prisma.professional.count({ where: { tenantId: session.tenantId, deletedAt: null } }),
    prisma.client.count({ where: { tenantId: session.tenantId, deletedAt: null } }),
    prisma.unit.count({ where: { tenantId: session.tenantId, deletedAt: null } }),
    prisma.appointment.count({
      where: { tenantId: session.tenantId, deletedAt: null, date: { gte: monthStart, lte: monthEnd } },
    }),
  ]);

  return NextResponse.json({
    user,
    tenant,
    subscription,
    usage: { professionals: profCount, clients: clientCount, units: unitCount, appointmentsMonth: apptCount },
  });
}
