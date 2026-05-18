import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const daySchema = z.object({
  dayOfWeek: z.number().int().min(0).max(6),
  openTime:  z.string().regex(/^\d{2}:\d{2}$/),
  closeTime: z.string().regex(/^\d{2}:\d{2}$/),
  isOpen:    z.boolean(),
});

const schema = z.object({ hours: z.array(daySchema) });

export async function PUT(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  const unitId = parseInt(id);

  const unit = await prisma.unit.findFirst({
    where: { id: unitId, tenantId: session.tenantId, deletedAt: null },
  });
  if (!unit) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });

  const body = await req.json();
  const parsed = schema.safeParse(body);
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos" }, { status: 400 });

  await prisma.$transaction([
    prisma.unitWorkingHours.deleteMany({ where: { unitId } }),
    prisma.unitWorkingHours.createMany({
      data: parsed.data.hours.map(h => ({
        unitId,
        dayOfWeek: h.dayOfWeek,
        openTime:  h.openTime,
        closeTime: h.closeTime,
        isOpen:    h.isOpen,
      })),
    }),
  ]);

  return NextResponse.json({ ok: true });
}
