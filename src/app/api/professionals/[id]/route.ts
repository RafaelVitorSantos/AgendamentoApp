import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const updateSchema = z.object({
  name: z.string().min(2).optional(),
  email: z.string().email().or(z.literal("")).nullable().optional(),
  phone: z.string().nullable().optional(),
  color: z.string().regex(/^#[0-9A-Fa-f]{6}$/).or(z.literal("")).nullable().optional(),
  commissionType: z.enum(["percentage", "fixed"]).nullable().optional(),
  commissionValue: z.number().min(0).nullable().optional(),
  isActive: z.boolean().optional(),
});

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  const professional = await prisma.professional.findFirst({
    where: { id: parseInt(id), tenantId: session.tenantId, deletedAt: null },
    include: {
      professionalUnits: { include: { unit: true } },
      professionalServices: { include: { service: true } },
    },
  });

  if (!professional) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });
  return NextResponse.json(professional);
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

  const { commissionType, commissionValue, ...rest } = parsed.data;

  const result = await prisma.professional.updateMany({
    where: { id: parseInt(id), tenantId: session.tenantId, deletedAt: null },
    data: {
      ...rest,
      email: rest.email !== undefined ? rest.email || null : undefined,
      color: rest.color !== undefined ? rest.color || null : undefined,
      commissionType: commissionType !== undefined ? commissionType : undefined,
      commissionValue: commissionValue !== undefined ? commissionValue : undefined,
    },
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
  await prisma.professional.updateMany({
    where: { id: parseInt(id), tenantId: session.tenantId },
    data: { deletedAt: new Date() },
  });

  return NextResponse.json({ ok: true });
}
