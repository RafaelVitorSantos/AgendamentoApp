import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const updateSchema = z.object({
  name: z.string().min(2).optional(),
  categoryId: z.number().nullable().optional(),
  description: z.string().nullable().optional(),
  duration: z.number().int().min(5).optional(),
  price: z.number().min(0).optional(),
  color: z.string().regex(/^#[0-9A-Fa-f]{6}$/).or(z.literal("")).nullable().optional(),
  commissionType: z.enum(["percentage", "fixed"]).nullable().optional(),
  commissionValue: z.number().min(0).nullable().optional(),
  allowOnlineBooking: z.boolean().optional(),
  isActive: z.boolean().optional(),
});

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  const service = await prisma.service.findFirst({
    where: { id: parseInt(id), tenantId: session.tenantId, deletedAt: null },
    include: { category: true },
  });

  if (!service) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });
  return NextResponse.json(service);
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
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos", details: parsed.error.flatten() }, { status: 400 });

  const result = await prisma.service.updateMany({
    where: { id: parseInt(id), tenantId: session.tenantId, deletedAt: null },
    data: {
      ...parsed.data,
      color: parsed.data.color !== undefined ? parsed.data.color || null : undefined,
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
  await prisma.service.updateMany({
    where: { id: parseInt(id), tenantId: session.tenantId },
    data: { deletedAt: new Date() },
  });

  return NextResponse.json({ ok: true });
}
