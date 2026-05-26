import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { checkPlanLimit } from "@/lib/tenant";
import { z } from "zod";

const createSchema = z.object({
  name: z.string().min(2),
  email: z.string().email().optional().or(z.literal("")),
  phone: z.string().optional(),
  color: z.string().regex(/^#[0-9A-Fa-f]{6}$/).optional().or(z.literal("")),
  commissionType: z.enum(["percentage", "fixed"]).optional(),
  commissionValue: z.number().min(0).optional(),
  isActive: z.boolean().optional(),
  unitIds: z.array(z.number()).optional(),
  serviceIds: z.array(z.number()).optional(),
});

export async function GET(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { searchParams } = new URL(req.url);
  const search = searchParams.get("search") ?? "";
  const page = parseInt(searchParams.get("page") ?? "1");
  const perPage = parseInt(searchParams.get("perPage") ?? "20");
  const unitId = searchParams.get("unitId");

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

  if (unitId) {
    where.professionalUnits = { some: { unitId: parseInt(unitId) } };
  }

  const [professionals, total] = await Promise.all([
    prisma.professional.findMany({
      where,
      select: {
        id: true,
        name: true,
        email: true,
        phone: true,
        color: true,
        avatar: true,
        commissionType: true,
        commissionValue: true,
        isActive: true,
      },
      orderBy: { name: "asc" },
      skip: (page - 1) * perPage,
      take: perPage,
    }),
    prisma.professional.count({ where }),
  ]);

  return NextResponse.json({
    data: professionals,
    total,
    page,
    perPage,
    totalPages: Math.ceil(total / perPage),
  });
}

export async function POST(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const limit = await checkPlanLimit(session.tenantId, "professionals");
  if (!limit.allowed) {
    return NextResponse.json(
      { error: `Limite de profissionais atingido (${limit.current}/${limit.max})` },
      { status: 403 }
    );
  }

  const body = await req.json();
  const parsed = createSchema.safeParse(body);
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos" }, { status: 400 });

  const { unitIds, serviceIds, ...rest } = parsed.data;

  const professional = await prisma.professional.create({
    data: {
      tenantId: session.tenantId,
      ...rest,
      email: rest.email || null,
      color: rest.color || null,
      professionalUnits: unitIds
        ? { create: unitIds.map((unitId) => ({ unitId })) }
        : undefined,
      professionalServices: serviceIds
        ? { create: serviceIds.map((serviceId) => ({ serviceId })) }
        : undefined,
    },
    select: {
      id: true,
      name: true,
      email: true,
      phone: true,
      color: true,
      avatar: true,
      commissionType: true,
      commissionValue: true,
      isActive: true,
    },
  });

  return NextResponse.json(professional, { status: 201 });
}
