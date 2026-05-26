import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const createSchema = z.object({
  name: z.string().min(2),
  color: z.string().regex(/^#[0-9A-Fa-f]{6}$/).optional(),
});

export async function GET(_req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const categories = await prisma.serviceCategory.findMany({
    where: { tenantId: session.tenantId },
    include: {
      _count: { select: { services: { where: { deletedAt: null } } } },
    },
    orderBy: [{ sortOrder: "asc" }, { name: "asc" }],
  });

  return NextResponse.json(categories);
}

export async function POST(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const body = await req.json();
  const parsed = createSchema.safeParse(body);
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos" }, { status: 400 });

  const category = await prisma.serviceCategory.create({
    data: {
      tenantId: session.tenantId,
      name: parsed.data.name,
      color: parsed.data.color ?? null,
    },
  });

  return NextResponse.json(category, { status: 201 });
}
