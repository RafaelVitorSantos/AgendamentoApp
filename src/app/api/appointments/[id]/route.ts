import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { hasConflict } from "@/lib/appointments";
import { z } from "zod";

const updateSchema = z.object({
  status: z
    .enum([
      "scheduled",
      "confirmed",
      "in_progress",
      "completed",
      "cancelled_by_client",
      "cancelled_by_business",
      "no_show",
      "rescheduled",
    ])
    .optional(),
  notes: z.string().nullable().optional(),
  cancelReason: z.string().nullable().optional(),
  // Reschedule fields
  date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional(),
  startTime: z.string().regex(/^\d{2}:\d{2}$/).optional(),
  professionalId: z.number().int().positive().optional(),
  serviceId: z.number().int().positive().optional(),
  unitId: z.number().int().positive().optional(),
  clientId: z.number().int().positive().optional(),
  price: z.number().nonnegative().optional(),
});

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  const appointment = await prisma.appointment.findFirst({
    where: { id: parseInt(id), tenantId: session.tenantId, deletedAt: null },
    include: {
      client: { select: { id: true, name: true, phone: true, email: true } },
      professional: { select: { id: true, name: true, color: true } },
      service: { select: { id: true, name: true, duration: true, price: true, color: true } },
      unit: { select: { id: true, name: true } },
      review: true,
      financialTransactions: true,
    },
  });

  if (!appointment) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });
  return NextResponse.json(appointment);
}

export async function PATCH(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  const aptId = parseInt(id);

  const body = await req.json();
  const parsed = updateSchema.safeParse(body);
  if (!parsed.success) {
    return NextResponse.json({ error: "Dados inválidos", details: parsed.error.flatten() }, { status: 400 });
  }

  const existing = await prisma.appointment.findFirst({
    where: { id: aptId, tenantId: session.tenantId, deletedAt: null },
    include: { service: true },
  });
  if (!existing) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });

  const d = parsed.data;
  const updateData: Record<string, unknown> = {};

  if (d.status !== undefined)       updateData.status       = d.status;
  if (d.notes !== undefined)        updateData.notes        = d.notes;
  if (d.cancelReason !== undefined) updateData.cancelReason = d.cancelReason;
  if (d.clientId !== undefined)     updateData.clientId     = d.clientId;
  if (d.unitId !== undefined)       updateData.unitId       = d.unitId;
  if (d.price !== undefined)        updateData.price        = d.price;

  // Reschedule: recalculate endTime if date/time/service/professional changed
  const needsReschedule = d.date !== undefined || d.startTime !== undefined ||
    d.serviceId !== undefined || d.professionalId !== undefined;

  if (needsReschedule) {
    const newDate        = d.date           ? new Date(d.date)  : existing.date;
    const newStartTime   = d.startTime      ?? existing.startTime;
    const newProfId      = d.professionalId ?? existing.professionalId;
    const newServiceId   = d.serviceId      ?? existing.serviceId;

    let duration = existing.service.duration;
    if (d.serviceId && d.serviceId !== existing.serviceId) {
      const svc = await prisma.service.findFirst({
        where: { id: newServiceId, tenantId: session.tenantId, deletedAt: null },
      });
      if (!svc) return NextResponse.json({ error: "Serviço não encontrado" }, { status: 404 });
      duration = svc.duration;
      if (d.price === undefined) updateData.price = svc.price;
    }

    const [h, m] = newStartTime.split(":").map(Number);
    const endMin = h * 60 + m + duration;
    const newEndTime = `${Math.floor(endMin / 60).toString().padStart(2, "0")}:${(endMin % 60).toString().padStart(2, "0")}`;

    const conflict = await hasConflict(newProfId, newDate, newStartTime, newEndTime, aptId);
    if (conflict) {
      return NextResponse.json({ error: "Conflito de horário detectado" }, { status: 409 });
    }

    updateData.date          = newDate;
    updateData.startTime     = newStartTime;
    updateData.endTime       = newEndTime;
    updateData.professionalId = newProfId;
    updateData.serviceId     = newServiceId;
  }

  const appointment = await prisma.appointment.update({
    where: { id: aptId },
    data: updateData,
    include: {
      client: { select: { id: true, name: true, phone: true, email: true } },
      professional: { select: { id: true, name: true, color: true } },
      service: { select: { id: true, name: true, duration: true, price: true, color: true } },
      unit: { select: { id: true, name: true } },
    },
  });

  return NextResponse.json(appointment);
}

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  await prisma.appointment.updateMany({
    where: { id: parseInt(id), tenantId: session.tenantId },
    data: { deletedAt: new Date() },
  });

  return NextResponse.json({ ok: true });
}
