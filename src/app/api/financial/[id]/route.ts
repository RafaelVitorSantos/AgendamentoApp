import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const updateSchema = z.object({
  type: z.enum(["income", "expense"]).optional(),
  description: z.string().min(1).optional(),
  amount: z.number().positive().optional(),
  categoryId: z.number().nullable().optional(),
  clientId: z.number().nullable().optional(),
  paymentMethod: z.string().nullable().optional(),
  status: z.enum(["pending", "paid", "cancelled"]).optional(),
  dueDate: z.string().nullable().optional(),
  notes: z.string().nullable().optional(),
});

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  const tx = await prisma.financialTransaction.findFirst({
    where: { id: parseInt(id), tenantId: session.tenantId },
    include: {
      category: true,
      client: { select: { id: true, name: true } },
    },
  });

  if (!tx) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });
  return NextResponse.json(tx);
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

  const data = parsed.data;
  const updateData: Record<string, unknown> = {};

  if (data.type !== undefined)          updateData.type          = data.type;
  if (data.description !== undefined)   updateData.description   = data.description;
  if (data.amount !== undefined)        updateData.amount        = data.amount;
  if (data.categoryId !== undefined)    updateData.categoryId    = data.categoryId;
  if (data.clientId !== undefined)      updateData.clientId      = data.clientId;
  if (data.paymentMethod !== undefined) updateData.paymentMethod = data.paymentMethod;
  if (data.notes !== undefined)         updateData.notes         = data.notes;
  if (data.dueDate !== undefined)       updateData.dueDate       = data.dueDate ? new Date(data.dueDate) : null;
  if (data.status !== undefined) {
    updateData.status = data.status;
    updateData.paidAt = data.status === "paid" ? new Date() : null;
  }

  const result = await prisma.financialTransaction.updateMany({
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
  const result = await prisma.financialTransaction.deleteMany({
    where: { id: parseInt(id), tenantId: session.tenantId },
  });

  if (!result.count) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });
  return NextResponse.json({ ok: true });
}
