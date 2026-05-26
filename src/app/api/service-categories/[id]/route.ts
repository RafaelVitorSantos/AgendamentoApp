import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const updateSchema = z.object({
  name: z.string().min(2).optional(),
  color: z.string().regex(/^#[0-9A-Fa-f]{6}$/).nullable().optional(),
  sortOrder: z.number().int().optional(),
});

async function getCategory(id: number, tenantId: number) {
  return prisma.serviceCategory.findFirst({ where: { id, tenantId } });
}

export async function PATCH(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id: idStr } = await params;
  const id = parseInt(idStr);
  if (isNaN(id)) return NextResponse.json({ error: "ID inválido" }, { status: 400 });

  const existing = await getCategory(id, session.tenantId);
  if (!existing) return NextResponse.json({ error: "Categoria não encontrada" }, { status: 404 });

  const body = await req.json();
  const parsed = updateSchema.safeParse(body);
  if (!parsed.success)
    return NextResponse.json({ error: "Dados inválidos", details: parsed.error.flatten() }, { status: 400 });

  const updated = await prisma.serviceCategory.update({
    where: { id },
    data: parsed.data,
  });

  return NextResponse.json(updated);
}

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id: idStr } = await params;
  const id = parseInt(idStr);
  if (isNaN(id)) return NextResponse.json({ error: "ID inválido" }, { status: 400 });

  const existing = await getCategory(id, session.tenantId);
  if (!existing) return NextResponse.json({ error: "Categoria não encontrada" }, { status: 404 });

  // Services with this category will have categoryId set to null (SetNull cascade)
  await prisma.serviceCategory.delete({ where: { id } });

  return NextResponse.json({ ok: true });
}
