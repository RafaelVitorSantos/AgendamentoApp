import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const updateSchema = z.object({
  name: z.string().min(2).optional(),
  phone: z.string().nullable().optional(),
  address: z.string().nullable().optional(),
  city: z.string().nullable().optional(),
  state: z.string().max(2).nullable().optional(),
  zipCode: z.string().nullable().optional(),
  timezone: z.string().nullable().optional(),
  isActive: z.boolean().optional(),
});

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  const unit = await prisma.unit.findFirst({
    where: { id: parseInt(id), tenantId: session.tenantId, deletedAt: null },
    include: { workingHours: { orderBy: { dayOfWeek: "asc" } } },
  });

  if (!unit) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });
  return NextResponse.json(unit);
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

  const result = await prisma.unit.updateMany({
    where: { id: parseInt(id), tenantId: session.tenantId, deletedAt: null },
    data: parsed.data,
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
  await prisma.unit.updateMany({
    where: { id: parseInt(id), tenantId: session.tenantId },
    data: { deletedAt: new Date() },
  });

  return NextResponse.json({ ok: true });
}
