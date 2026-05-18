import { NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";

export async function GET() {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const tenantId = session.tenantId;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const tomorrow = new Date(today);
  tomorrow.setDate(tomorrow.getDate() + 1);

  const weekStart = new Date(today);
  weekStart.setDate(weekStart.getDate() - weekStart.getDay());
  const weekEnd = new Date(weekStart);
  weekEnd.setDate(weekEnd.getDate() + 7);

  const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
  const monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);

  const [
    appointmentsToday,
    appointmentsWeek,
    revenueMonth,
    newClientsMonth,
    pendingQueue,
    recentAppointments,
  ] = await Promise.all([
    prisma.appointment.count({
      where: {
        tenantId,
        date: { gte: today, lt: tomorrow },
        deletedAt: null,
        status: { notIn: ["cancelled_by_client", "cancelled_by_business"] },
      },
    }),
    prisma.appointment.count({
      where: {
        tenantId,
        date: { gte: weekStart, lt: weekEnd },
        deletedAt: null,
        status: { notIn: ["cancelled_by_client", "cancelled_by_business"] },
      },
    }),
    prisma.financialTransaction.aggregate({
      where: {
        tenantId,
        type: "income",
        status: "paid",
        createdAt: { gte: monthStart, lte: monthEnd },
      },
      _sum: { amount: true },
    }),
    prisma.client.count({
      where: { tenantId, deletedAt: null, createdAt: { gte: monthStart, lte: monthEnd } },
    }),
    prisma.serviceQueue.count({
      where: { tenantId, status: { in: ["waiting", "called"] } },
    }),
    prisma.appointment.findMany({
      where: {
        tenantId,
        date: { gte: today, lt: tomorrow },
        deletedAt: null,
        status: { notIn: ["cancelled_by_client", "cancelled_by_business"] },
      },
      include: {
        client: { select: { name: true } },
        professional: { select: { name: true, color: true } },
        service: { select: { name: true } },
      },
      orderBy: { startTime: "asc" },
      take: 10,
    }),
  ]);

  return NextResponse.json({
    metrics: {
      appointmentsToday,
      appointmentsWeek,
      revenueMonth: Number(revenueMonth._sum.amount ?? 0),
      newClientsMonth,
      pendingQueue,
    },
    recentAppointments,
  });
}
