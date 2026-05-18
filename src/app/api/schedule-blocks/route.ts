import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const createSchema = z.object({
  title: z.string().min(1),
  startDate: z.string(),
  endDate: z.string(),
  isAllDay: z.boolean().default(false),
  startTime: z.string().nullable().optional(),
  endTime: z.string().nullable().optional(),
  professionalId: z.number().nullable().optional(),
  unitId: z.number().nullable().optional(),
});

export async function GET(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { searchParams } = new URL(req.url);
  const search = searchParams.get("search") ?? "";
  const scope = searchParams.get("scope") ?? "all";
  const page = parseInt(searchParams.get("page") ?? "1");
  const perPage = parseInt(searchParams.get("perPage") ?? "20");

  const where: Record<string, unknown> = { tenantId: session.tenantId };

  if (search) where.title = { contains: search };
  if (scope === "professional") where.professionalId = { not: null };
  if (scope === "unit") where.unitId = { not: null };
  if (scope === "general") { where.professionalId = null; where.unitId = null; }

  const [blocks, total] = await Promise.all([
    prisma.scheduleBlock.findMany({
      where,
      include: {
        professional: { select: { id: true, name: true, color: true } },
        unit: { select: { id: true, name: true } },
      },
      orderBy: { startDate: "desc" },
      skip: (page - 1) * perPage,
      take: perPage,
    }),
    prisma.scheduleBlock.count({ where }),
  ]);

  return NextResponse.json({ data: blocks, total, page, perPage, totalPages: Math.ceil(total / perPage) });
}

export async function POST(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const body = await req.json();
  const parsed = createSchema.safeParse(body);
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos" }, { status: 400 });

  const d = parsed.data;
  const block = await prisma.scheduleBlock.create({
    data: {
      tenantId: session.tenantId,
      title: d.title,
      startDate: new Date(d.startDate),
      endDate: new Date(d.endDate),
      isAllDay: d.isAllDay,
      startTime: d.isAllDay ? null : (d.startTime ?? null),
      endTime: d.isAllDay ? null : (d.endTime ?? null),
      professionalId: d.professionalId ?? null,
      unitId: d.unitId ?? null,
    },
  });

  return NextResponse.json(block, { status: 201 });
}
