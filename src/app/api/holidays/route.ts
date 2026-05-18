import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const createSchema = z.object({
  name: z.string().min(1),
  date: z.string(),
  isRecurring: z.boolean().default(false),
});

export async function GET(_req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const holidays = await prisma.holiday.findMany({
    where: { tenantId: session.tenantId },
    orderBy: { date: "asc" },
  });

  return NextResponse.json(holidays);
}

export async function POST(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const body = await req.json();
  const parsed = createSchema.safeParse(body);
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos" }, { status: 400 });

  const holiday = await prisma.holiday.create({
    data: {
      tenantId: session.tenantId,
      name: parsed.data.name,
      date: new Date(parsed.data.date),
      isRecurring: parsed.data.isRecurring,
    },
  });

  return NextResponse.json(holiday, { status: 201 });
}
