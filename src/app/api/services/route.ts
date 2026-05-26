import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const createSchema = z.object({
  name: z.string().min(2),
  categoryId: z.number().nullable().optional(),
  description: z.string().optional(),
  duration: z.number().int().min(5).default(60),
  price: z.number().min(0),
  color: z.string().regex(/^#[0-9A-Fa-f]{6}$/).or(z.literal("")).optional(),
  commissionType: z.enum(["percentage", "fixed"]).optional(),
  commissionValue: z.number().min(0).optional(),
  allowOnlineBooking: z.boolean().default(true),
  isActive: z.boolean().optional(),
});

export async function GET(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { searchParams } = new URL(req.url);
  const search = searchParams.get("search") ?? "";
  const page = parseInt(searchParams.get("page") ?? "1");
  const perPage = parseInt(searchParams.get("perPage") ?? "20");
  const professionalId = searchParams.get("professionalId");

  const where: Record<string, unknown> = {
    tenantId: session.tenantId,
    deletedAt: null,
  };

  if (search) {
    where.OR = [
      { name: { contains: search } },
      { description: { contains: search } },
    ];
  }

  if (professionalId) {
    where.professionalServices = { some: { professionalId: parseInt(professionalId) } };
  }

  const [services, total] = await Promise.all([
    prisma.service.findMany({
      where,
      select: {
        id: true,
        name: true,
        description: true,
        duration: true,
        price: true,
        color: true,
        commissionType: true,
        commissionValue: true,
        allowOnlineBooking: true,
        isActive: true,
        category: {
          select: { id: true, name: true, color: true },
        },
      },
      orderBy: { name: "asc" },
      skip: (page - 1) * perPage,
      take: perPage,
    }),
    prisma.service.count({ where }),
  ]);

  return NextResponse.json({
    data: services,
    total,
    page,
    perPage,
    totalPages: Math.ceil(total / perPage),
  });
}

export async function POST(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const body = await req.json();
  const parsed = createSchema.safeParse(body);
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos", details: parsed.error.flatten() }, { status: 400 });

  const service = await prisma.service.create({
    data: {
      tenantId: session.tenantId,
      ...parsed.data,
      color: parsed.data.color || null,
      categoryId: parsed.data.categoryId ?? null,
    },
    select: {
      id: true,
      name: true,
      description: true,
      duration: true,
      price: true,
      color: true,
      commissionType: true,
      commissionValue: true,
      allowOnlineBooking: true,
      isActive: true,
      category: { select: { id: true, name: true, color: true } },
    },
  });

  return NextResponse.json(service, { status: 201 });
}
