import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const updateSchema = z.object({
  status: z.enum(["waiting", "called", "in_progress", "completed", "cancelled", "no_show"]),
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

  const now = new Date();
  const timestamps: Record<string, Date> = {};
  if (parsed.data.status === "called") timestamps.calledAt = now;
  if (parsed.data.status === "in_progress") timestamps.startedAt = now;
  if (["completed", "cancelled", "no_show"].includes(parsed.data.status)) {
    timestamps.completedAt = now;
  }

  const entry = await prisma.serviceQueue.updateMany({
    where: { id: parseInt(id), tenantId: session.tenantId },
    data: { status: parsed.data.status, ...timestamps },
  });

  if (!entry.count) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });
  return NextResponse.json({ ok: true });
}
