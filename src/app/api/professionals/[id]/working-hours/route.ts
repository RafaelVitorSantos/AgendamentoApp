import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const daySchema = z.object({
  dayOfWeek: z.number().int().min(0).max(6),
  startTime: z.string().regex(/^\d{2}:\d{2}$/),
  endTime:   z.string().regex(/^\d{2}:\d{2}$/),
  isWorking: z.boolean(),
});

const schema = z.object({
  unitId: z.number().int().positive(),
  hours:  z.array(daySchema),
});

export async function GET(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  const professionalId = parseInt(id);
  const { searchParams } = new URL(req.url);
  const unitId = parseInt(searchParams.get("unitId") ?? "0");
  if (!unitId) return NextResponse.json({ error: "unitId obrigatório" }, { status: 400 });

  const professional = await prisma.professional.findFirst({
    where: { id: professionalId, tenantId: session.tenantId, deletedAt: null },
  });
  if (!professional) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });

  const hours = await prisma.professionalWorkingHours.findMany({
    where: { professionalId, unitId },
    orderBy: { dayOfWeek: "asc" },
  });

  return NextResponse.json(hours);
}

export async function PUT(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  const professionalId = parseInt(id);

  const professional = await prisma.professional.findFirst({
    where: { id: professionalId, tenantId: session.tenantId, deletedAt: null },
  });
  if (!professional) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });

  const body = await req.json();
  const parsed = schema.safeParse(body);
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos" }, { status: 400 });

  const { unitId, hours } = parsed.data;

  await prisma.$transaction([
    prisma.professionalWorkingHours.deleteMany({ where: { professionalId, unitId } }),
    prisma.professionalWorkingHours.createMany({
      data: hours.map(h => ({
        professionalId,
        unitId,
        dayOfWeek: h.dayOfWeek,
        startTime: h.startTime,
        endTime:   h.endTime,
        isWorking: h.isWorking,
      })),
    }),
  ]);

  return NextResponse.json({ ok: true });
}
