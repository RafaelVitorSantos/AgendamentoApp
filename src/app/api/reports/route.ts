import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";

export async function GET(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { searchParams } = new URL(req.url);
  const today = new Date();
  const defaultFrom = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split("T")[0];
  const defaultTo = today.toISOString().split("T")[0];

  const from = searchParams.get("from") ?? defaultFrom;
  const to = searchParams.get("to") ?? defaultTo;

  const tenantId = session.tenantId;
  const fromDate = new Date(from + "T00:00:00");
  const toDate = new Date(to + "T23:59:59");

  const aptWhere = {
    tenantId,
    date: { gte: fromDate, lte: toDate },
    deletedAt: null,
  } as const;

  const finWhere = {
    tenantId,
    createdAt: { gte: fromDate, lte: toDate },
  } as const;

  const [
    apptByStatus,
    topServicesRaw,
    topClientsRaw,
    byProfessionalRaw,
    revenueSummary,
    revenueByMethod,
  ] = await Promise.all([
    prisma.appointment.groupBy({
      by: ["status"],
      where: aptWhere,
      _count: { id: true },
    }),
    prisma.appointment.groupBy({
      by: ["serviceId"],
      where: aptWhere,
      _count: { id: true },
      _sum: { price: true },
      orderBy: { _count: { id: "desc" } },
      take: 10,
    }),
    prisma.appointment.groupBy({
      by: ["clientId"],
      where: {
        ...aptWhere,
        status: { notIn: ["cancelled_by_client", "cancelled_by_business"] },
      },
      _count: { id: true },
      _sum: { price: true },
      orderBy: { _count: { id: "desc" } },
      take: 10,
    }),
    prisma.appointment.groupBy({
      by: ["professionalId"],
      where: aptWhere,
      _count: { id: true },
      _sum: { price: true },
      orderBy: { _count: { id: "desc" } },
    }),
    prisma.financialTransaction.groupBy({
      by: ["type"],
      where: { ...finWhere, status: "paid" },
      _sum: { amount: true },
    }),
    prisma.financialTransaction.groupBy({
      by: ["paymentMethod"],
      where: { ...finWhere, status: "paid", type: "income" },
      _sum: { amount: true },
      orderBy: { _sum: { amount: "desc" } },
      take: 6,
    }),
  ]);

  const serviceIds = topServicesRaw.map((s) => s.serviceId);
  const clientIds = topClientsRaw.map((c) => c.clientId);
  const professionalIds = byProfessionalRaw.map((p) => p.professionalId);

  const [services, clients, professionals] = await Promise.all([
    serviceIds.length
      ? prisma.service.findMany({ where: { id: { in: serviceIds } }, select: { id: true, name: true, color: true } })
      : [],
    clientIds.length
      ? prisma.client.findMany({ where: { id: { in: clientIds } }, select: { id: true, name: true } })
      : [],
    professionalIds.length
      ? prisma.professional.findMany({ where: { id: { in: professionalIds } }, select: { id: true, name: true, color: true } })
      : [],
  ]);

  const serviceMap = Object.fromEntries(services.map((s) => [s.id, s]));
  const clientMap = Object.fromEntries(clients.map((c) => [c.id, c]));
  const profMap = Object.fromEntries(professionals.map((p) => [p.id, p]));

  const totalAppts = apptByStatus.reduce((sum, s) => sum + s._count.id, 0);
  const completedAppts = apptByStatus.find((s) => s.status === "completed")?._count.id ?? 0;
  const cancelledAppts =
    (apptByStatus.find((s) => s.status === "cancelled_by_client")?._count.id ?? 0) +
    (apptByStatus.find((s) => s.status === "cancelled_by_business")?._count.id ?? 0);

  const income = Number(revenueSummary.find((s) => s.type === "income")?._sum.amount ?? 0);
  const expense = Number(revenueSummary.find((s) => s.type === "expense")?._sum.amount ?? 0);

  return NextResponse.json({
    period: { from, to },
    overview: {
      totalAppointments: totalAppts,
      completedAppointments: completedAppts,
      cancelledAppointments: cancelledAppts,
      completionRate: totalAppts > 0 ? completedAppts / totalAppts : 0,
      income,
      expense,
      balance: income - expense,
    },
    byStatus: apptByStatus.map((s) => ({ status: s.status, count: s._count.id })),
    topServices: topServicesRaw.map((s) => ({
      id: s.serviceId,
      name: serviceMap[s.serviceId]?.name ?? "—",
      color: serviceMap[s.serviceId]?.color ?? null,
      count: s._count.id,
      revenue: Number(s._sum.price ?? 0),
    })),
    topClients: topClientsRaw.map((c) => ({
      id: c.clientId,
      name: clientMap[c.clientId]?.name ?? "—",
      visits: c._count.id,
      spent: Number(c._sum.price ?? 0),
    })),
    byProfessional: byProfessionalRaw.map((p) => ({
      id: p.professionalId,
      name: profMap[p.professionalId]?.name ?? "—",
      color: profMap[p.professionalId]?.color ?? null,
      appointments: p._count.id,
      revenue: Number(p._sum.price ?? 0),
    })),
    byPaymentMethod: revenueByMethod.map((m) => ({
      method: m.paymentMethod ?? "outro",
      amount: Number(m._sum.amount ?? 0),
    })),
  });
}
