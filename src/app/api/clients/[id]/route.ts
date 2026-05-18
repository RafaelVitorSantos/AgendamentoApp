import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const updateSchema = z.object({
  name: z.string().min(2).optional(),
  email: z.string().email().optional().or(z.literal("")),
  phone: z.string().optional(),
  cpf: z.string().optional(),
  birthDate: z.string().optional(),
  gender: z.string().optional(),
  address: z.string().optional(),
  city: z.string().optional(),
  state: z.string().max(2).optional(),
  notes: z.string().optional(),
  lgpdConsent: z.boolean().optional(),
});

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  const client = await prisma.client.findFirst({
    where: { id: parseInt(id), tenantId: session.tenantId, deletedAt: null },
    include: {
      appointments: {
        where: { deletedAt: null },
        include: { service: true, professional: true },
        orderBy: { date: "desc" },
        take: 10,
      },
    },
  });

  if (!client) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });
  return NextResponse.json(client);
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
  const client = await prisma.client.updateMany({
    where: { id: parseInt(id), tenantId: session.tenantId, deletedAt: null },
    data: {
      ...data,
      email: data.email || null,
      birthDate: data.birthDate ? new Date(data.birthDate) : undefined,
      lgpdConsentAt: data.lgpdConsent ? new Date() : undefined,
    },
  });

  if (!client.count) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });
  return NextResponse.json({ ok: true });
}

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { id } = await params;
  await prisma.client.updateMany({
    where: { id: parseInt(id), tenantId: session.tenantId },
    data: { deletedAt: new Date() },
  });

  return NextResponse.json({ ok: true });
}
