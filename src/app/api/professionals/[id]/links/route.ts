import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const schema = z.object({
  unitIds: z.array(z.number()).optional(),
  serviceIds: z.array(z.number()).optional(),
});

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  const profId = parseInt(id);

  const prof = await prisma.professional.findFirst({
    where: { id: profId, tenantId: session.tenantId, deletedAt: null },
    select: {
      professionalUnits: { select: { unitId: true } },
      professionalServices: { select: { serviceId: true } },
    },
  });

  if (!prof) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });

  return NextResponse.json({
    unitIds: prof.professionalUnits.map((u) => u.unitId),
    serviceIds: prof.professionalServices.map((s) => s.serviceId),
  });
}

export async function PUT(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  const profId = parseInt(id);

  // Ensure the professional belongs to this tenant
  const prof = await prisma.professional.findFirst({
    where: { id: profId, tenantId: session.tenantId, deletedAt: null },
  });
  if (!prof) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });

  const body = await req.json();
  const parsed = schema.safeParse(body);
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos" }, { status: 400 });

  const { unitIds, serviceIds } = parsed.data;

  await prisma.$transaction(async (tx) => {
    if (unitIds !== undefined) {
      await tx.professionalUnit.deleteMany({ where: { professionalId: profId } });
      if (unitIds.length > 0) {
        await tx.professionalUnit.createMany({
          data: unitIds.map((unitId) => ({ professionalId: profId, unitId })),
          skipDuplicates: true,
        });
      }
    }

    if (serviceIds !== undefined) {
      await tx.professionalService.deleteMany({ where: { professionalId: profId } });
      if (serviceIds.length > 0) {
        await tx.professionalService.createMany({
          data: serviceIds.map((serviceId) => ({ professionalId: profId, serviceId })),
          skipDuplicates: true,
        });
      }
    }
  });

  return NextResponse.json({ ok: true });
}
