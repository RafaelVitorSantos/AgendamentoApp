import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const schema = z.object({
  name: z.string().min(2).optional(),
  email: z.string().email().optional(),
  phone: z.string().nullable().optional(),
});

export async function PATCH(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const body = await req.json();
  const parsed = schema.safeParse(body);
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos" }, { status: 400 });

  const { name, email, phone } = parsed.data;

  if (email) {
    const existing = await prisma.user.findFirst({
      where: { tenantId: session.tenantId, email, id: { not: session.userId }, deletedAt: null },
    });
    if (existing) return NextResponse.json({ error: "E-mail já está em uso" }, { status: 409 });
  }

  await prisma.user.update({
    where: { id: session.userId },
    data: { ...(name && { name }), ...(email && { email }), ...(phone !== undefined && { phone }) },
  });

  return NextResponse.json({ ok: true });
}
