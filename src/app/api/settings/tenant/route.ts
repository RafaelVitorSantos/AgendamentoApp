import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const schema = z.object({
  name: z.string().min(2).optional(),
  email: z.string().email().optional(),
  phone: z.string().nullable().optional(),
  document: z.string().nullable().optional(),
  timezone: z.string().optional(),
});

export async function PATCH(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const body = await req.json();
  const parsed = schema.safeParse(body);
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos" }, { status: 400 });

  const d = parsed.data;
  const updateData: Record<string, unknown> = {};
  if (d.name !== undefined)     updateData.name     = d.name;
  if (d.email !== undefined)    updateData.email    = d.email;
  if (d.phone !== undefined)    updateData.phone    = d.phone;
  if (d.document !== undefined) updateData.document = d.document;
  if (d.timezone !== undefined) updateData.timezone = d.timezone;

  await prisma.tenant.update({ where: { id: session.tenantId }, data: updateData });
  return NextResponse.json({ ok: true });
}
