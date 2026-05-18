import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { audit } from "@/lib/audit";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ clientId: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const clientId = parseInt((await params).clientId);

  const client = await prisma.client.findFirst({
    where: { id: clientId, tenantId: session.tenantId, deletedAt: null },
    include: {
      appointments: {
        include: { service: true, professional: true },
        orderBy: { date: "desc" },
      },
      financialTransactions: true,
      reviews: true,
      loyaltyPoints: true,
      loyaltyTransactions: true,
    },
  });

  if (!client) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });

  await audit(session.tenantId, session.userId, "lgpd_export", "client", clientId);

  return NextResponse.json(client);
}

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ clientId: string }> }
) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const clientId = parseInt((await params).clientId);

  const client = await prisma.client.findFirst({
    where: { id: clientId, tenantId: session.tenantId, deletedAt: null },
  });
  if (!client) return NextResponse.json({ error: "Não encontrado" }, { status: 404 });

  await audit(
    session.tenantId,
    session.userId,
    "lgpd_anonymize",
    "client",
    clientId,
    { name: client.name, email: client.email, phone: client.phone }
  );

  await prisma.client.update({
    where: { id: clientId },
    data: {
      name: `Anonimizado #${clientId}`,
      email: null,
      phone: null,
      cpf: null,
      birthDate: null,
      address: null,
      city: null,
      state: null,
      notes: null as never,
      tags: null as never,
      lgpdAnonymizedAt: new Date(),
    },
  });

  return NextResponse.json({ ok: true, message: "Dados pessoais anonimizados com sucesso" });
}
