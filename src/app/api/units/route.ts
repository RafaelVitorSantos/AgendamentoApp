import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { checkPlanLimit } from "@/lib/tenant";
import { z } from "zod";

const createSchema = z.object({
  name: z.string().min(2),
  phone: z.string().optional(),
  address: z.string().optional(),
  city: z.string().optional(),
  state: z.string().max(2).optional(),
  zipCode: z.string().optional(),
  timezone: z.string().optional(),
  isActive: z.boolean().optional(),
});

export async function GET(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { searchParams } = new URL(req.url);
  const search = searchParams.get("search") ?? "";
  const page = parseInt(searchParams.get("page") ?? "1");
  const perPage = parseInt(searchParams.get("perPage") ?? "20");

  const where: Record<string, unknown> = {
    tenantId: session.tenantId,
    deletedAt: null,
  };

  if (search) {
    where.OR = [
      { name: { contains: search } },
      { city: { contains: search } },
      { address: { contains: search } },
    ];
  }

  const [units, total] = await Promise.all([
    prisma.unit.findMany({
      where,
      orderBy: { name: "asc" },
      skip: (page - 1) * perPage,
      take: perPage,
    }),
    prisma.unit.count({ where }),
  ]);

  return NextResponse.json({ data: units, total, page, perPage, totalPages: Math.ceil(total / perPage) });
}

export async function POST(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const limit = await checkPlanLimit(session.tenantId, "units");
  if (!limit.allowed) {
    return NextResponse.json(
      { error: `Limite de unidades atingido (${limit.current}/${limit.max})` },
      { status: 403 }
    );
  }

  const body = await req.json();
  const parsed = createSchema.safeParse(body);
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos" }, { status: 400 });

  const DEFAULT_WORKING_HOURS = [
    { dayOfWeek: 0, openTime: "09:00", closeTime: "13:00", isOpen: false }, // Dom
    { dayOfWeek: 1, openTime: "08:00", closeTime: "18:00", isOpen: true  }, // Seg
    { dayOfWeek: 2, openTime: "08:00", closeTime: "18:00", isOpen: true  }, // Ter
    { dayOfWeek: 3, openTime: "08:00", closeTime: "18:00", isOpen: true  }, // Qua
    { dayOfWeek: 4, openTime: "08:00", closeTime: "18:00", isOpen: true  }, // Qui
    { dayOfWeek: 5, openTime: "08:00", closeTime: "18:00", isOpen: true  }, // Sex
    { dayOfWeek: 6, openTime: "09:00", closeTime: "13:00", isOpen: false }, // Sáb
  ];

  const unit = await prisma.unit.create({
    data: {
      tenantId: session.tenantId,
      ...parsed.data,
      workingHours: {
        create: DEFAULT_WORKING_HOURS,
      },
    },
  });

  return NextResponse.json(unit, { status: 201 });
}
