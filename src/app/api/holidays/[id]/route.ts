import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const updateSchema = z.object({
  name: z.string().min(1).optional(),
  date: z.string().optional(),
  isRecurring: z.boolean().optional(),
});

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
  if (d.name !== undefined)        updateData.name        = d.name;
  if (d.date !== undefined)        updateData.date        = new Date(d.date);
  if (d.isRecurring !== undefined) updateData.isRecurring = d.isRecurring;

  const result = await prisma.holiday.updateMany({
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
  const result = await prisma.holiday.deleteMany({
    where: { id: parseInt(id), tenantId: session.tenantId },
  });

  if (!result.count) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });
  return NextResponse.json({ ok: true });
}
