import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { checkPlanLimit } from "@/lib/tenant";
import { z } from "zod";

const createSchema = z.object({
  name: z.string().min(2),
  email: z.string().email().optional().or(z.literal("")),
  phone: z.string().optional(),
  cpf: z.string().optional(),
  birthDate: z.string().optional(),
  gender: z.string().optional(),
  address: z.string().optional(),
  city: z.string().optional(),
  state: z.string().max(2).optional(),
  notes: z.string().optional(),
  lgpdConsent: z.boolean().default(false),
});

export async function GET(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { searchParams } = new URL(req.url);
  const search = searchParams.get("search");
  const page = parseInt(searchParams.get("page") ?? "1");
  const perPage = parseInt(searchParams.get("perPage") ?? "20");

  const where: Record<string, unknown> = {
    tenantId: session.tenantId,
    deletedAt: null,
  };

  if (search) {
    where.OR = [
      { name: { contains: search } },
      { email: { contains: search } },
      { phone: { contains: search } },
    ];
  }

  const [clients, total] = await Promise.all([
    prisma.client.findMany({
      where,
      orderBy: { name: "asc" },
      skip: (page - 1) * perPage,
      take: perPage,
      select: {
        id: true,
        name: true,
        email: true,
        phone: true,
        totalVisits: true,
        totalSpent: true,
        lastVisitAt: true,
        createdAt: true,
      },
    }),
    prisma.client.count({ where }),
  ]);

  return NextResponse.json({ data: clients, total, page, perPage, totalPages: Math.ceil(total / perPage) });
}

export async function POST(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const limit = await checkPlanLimit(session.tenantId, "clients");
  if (!limit.allowed) {
    return NextResponse.json(
      { error: `Limite de clientes do plano atingido (${limit.current}/${limit.max})` },
      { status: 403 }
    );
  }

  const body = await req.json();
  const parsed = createSchema.safeParse(body);
  if (!parsed.success) {
    return NextResponse.json({ error: "Dados inválidos", details: parsed.error.flatten() }, { status: 400 });
  }

  const data = parsed.data;
  const client = await prisma.client.create({
    data: {
      tenantId: session.tenantId,
      name: data.name,
      email: data.email || null,
      phone: data.phone || null,
      cpf: data.cpf || null,
      birthDate: data.birthDate ? new Date(data.birthDate) : null,
      gender: data.gender || null,
      address: data.address || null,
      city: data.city || null,
      state: data.state || null,
      notes: data.notes || null,
      lgpdConsent: data.lgpdConsent,
      lgpdConsentAt: data.lgpdConsent ? new Date() : null,
    },
  });

  return NextResponse.json(client, { status: 201 });
}
