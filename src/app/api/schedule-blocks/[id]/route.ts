import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const updateSchema = z.object({
  title: z.string().min(1).optional(),
  startDate: z.string().optional(),
  endDate: z.string().optional(),
  isAllDay: z.boolean().optional(),
  startTime: z.string().nullable().optional(),
  endTime: z.string().nullable().optional(),
  professionalId: z.number().nullable().optional(),
  unitId: z.number().nullable().optional(),
});

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  const block = await prisma.scheduleBlock.findFirst({
    where: { id: parseInt(id), tenantId: session.tenantId },
    include: {
      professional: { select: { id: true, name: true, color: true } },
      unit: { select: { id: true, name: true } },
    },
  });

  if (!block) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });
  return NextResponse.json(block);
}

export async function PATCH(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  const body = await req.json();
  const parsed = updateSchema.safeParse(body);
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos" }, { status: 400 });

  const d = parsed.data;
  const updateData: Record<string, unknown> = {};

  if (d.title !== undefined)          updateData.title          = d.title;
  if (d.startDate !== undefined)      updateData.startDate      = new Date(d.startDate);
  if (d.endDate !== undefined)        updateData.endDate        = new Date(d.endDate);
  if (d.isAllDay !== undefined)       updateData.isAllDay       = d.isAllDay;
  if (d.professionalId !== undefined) updateData.professionalId = d.professionalId;
  if (d.unitId !== undefined)         updateData.unitId         = d.unitId;

  if (d.isAllDay === true) {
    updateData.startTime = null;
    updateData.endTime   = null;
  } else {
    if (d.startTime !== undefined) updateData.startTime = d.startTime;
    if (d.endTime !== undefined)   updateData.endTime   = d.endTime;
  }

  const result = await prisma.scheduleBlock.updateMany({
    where: { id: parseInt(id), tenantId: session.tenantId },
    data: updateData,
  });

  if (!result.count) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });
  return NextResponse.json({ ok: true });
}

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  const result = await prisma.scheduleBlock.deleteMany({
    where: { id: parseInt(id), tenantId: session.tenantId },
  });

  if (!result.count) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });
  return NextResponse.json({ ok: true });
}
