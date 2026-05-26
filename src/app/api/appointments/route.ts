import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { hasConflict } from "@/lib/appointments";
import { checkPlanLimit } from "@/lib/tenant";
import { z } from "zod";
import crypto from "crypto";

const createSchema = z.object({
  clientId: z.number().int().positive(),
  professionalId: z.number().int().positive(),
  serviceId: z.number().int().positive(),
  unitId: z.number().int().positive(),
  date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  startTime: z.string().regex(/^\d{2}:\d{2}$/),
  notes: z.string().optional(),
  source: z.enum(["manual", "online", "walkin", "whatsapp"]).default("manual"),
});

export async function GET(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { searchParams } = new URL(req.url);
  const date = searchParams.get("date");
  const professionalId = searchParams.get("professionalId");
  const unitId = searchParams.get("unitId");
  const status = searchParams.get("status");
  const page = parseInt(searchParams.get("page") ?? "1");
  const perPage = parseInt(searchParams.get("perPage") ?? "50");

  const where: Record<string, unknown> = {
    tenantId: session.tenantId,
    deletedAt: null,
  };

  const from = searchParams.get("from");
  const to = searchParams.get("to");
  const search = searchParams.get("search");

  // Parse YYYY-MM-DD strings as local dates to avoid UTC off-by-one
  function localDate(str: string) {
    const [y, mo, d] = str.split("-").map(Number);
    return new Date(y, mo - 1, d);
  }

  if (from && to) {
    const toEnd = localDate(to);
    toEnd.setDate(toEnd.getDate() + 1); // inclusive: up to end-of-day
    where.date = { gte: localDate(from), lt: toEnd };
  } else if (date) {
    const dayStart = localDate(date);
    const dayEnd   = new Date(dayStart);
    dayEnd.setDate(dayEnd.getDate() + 1);
    where.date = { gte: dayStart, lt: dayEnd };
  }
  if (professionalId) where.professionalId = parseInt(professionalId);
  if (unitId) where.unitId = parseInt(unitId);
  if (status) where.status = status;
  if (search) {
    where.OR = [
      { client: { name: { contains: search } } },
      { service: { name: { contains: search } } },
    ];
  }

  const [appointments, total] = await Promise.all([
    prisma.appointment.findMany({
      where,
      include: {
        client: { select: { id: true, name: true, phone: true } },
        professional: { select: { id: true, name: true, color: true } },
        service: { select: { id: true, name: true, duration: true, color: true } },
        unit: { select: { id: true, name: true } },
      },
      orderBy: [{ date: "asc" }, { startTime: "asc" }],
      skip: (page - 1) * perPage,
      take: perPage,
    }),
    prisma.appointment.count({ where }),
  ]);

  return NextResponse.json({
    data: appointments,
    total,
    page,
    perPage,
    totalPages: Math.ceil(total / perPage),
  });
}

export async function POST(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const body = await req.json();
  const parsed = createSchema.safeParse(body);
  if (!parsed.success) {
    return NextResponse.json({ error: "Dados inválidos", details: parsed.error.flatten() }, { status: 400 });
  }

  const data = parsed.data;

  const limit = await checkPlanLimit(session.tenantId, "appointments_month");
  if (!limit.allowed) {
    return NextResponse.json(
      { error: `Limite do plano atingido (${limit.current}/${limit.max} agendamentos este mês)` },
      { status: 403 }
    );
  }

  const service = await prisma.service.findFirst({
    where: { id: data.serviceId, tenantId: session.tenantId, deletedAt: null },
  });
  if (!service) return NextResponse.json({ error: "Serviço não encontrado" }, { status: 404 });

  const [h, m] = data.startTime.split(":").map(Number);
  const endMinutes = h * 60 + m + service.duration;
  const endTime = `${Math.floor(endMinutes / 60).toString().padStart(2, "0")}:${(endMinutes % 60).toString().padStart(2, "0")}`;

  const [yd, md2, dd] = data.date.split("-").map(Number);
  const date = new Date(yd, md2 - 1, dd);

  const conflict = await hasConflict(data.professionalId, date, data.startTime, endTime);
  if (conflict) {
    return NextResponse.json({ error: "Conflito de horário detectado" }, { status: 409 });
  }

  const appointment = await prisma.appointment.create({
    data: {
      tenantId: session.tenantId,
      clientId: data.clientId,
      professionalId: data.professionalId,
      serviceId: data.serviceId,
      unitId: data.unitId,
      date,
      startTime: data.startTime,
      endTime,
      price: service.price,
      source: data.source,
      notes: data.notes,
      cancelToken: crypto.randomBytes(32).toString("hex"),
      rescheduleToken: crypto.randomBytes(32).toString("hex"),
    },
    include: {
      client: true,
      professional: true,
      service: true,
      unit: true,
    },
  });

  return NextResponse.json(appointment, { status: 201 });
}
