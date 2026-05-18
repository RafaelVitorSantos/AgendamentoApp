import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const addSchema = z.object({
  unitId: z.number().int().positive(),
  clientId: z.number().int().positive().optional(),
  professionalId: z.number().int().positive().optional(),
  serviceId: z.number().int().positive().optional(),
  priority: z.number().int().default(0),
  notes: z.string().optional(),
});

export async function GET(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { searchParams } = new URL(req.url);
  const unitId = searchParams.get("unitId");

  const queue = await prisma.serviceQueue.findMany({
    where: {
      tenantId: session.tenantId,
      unitId: unitId ? parseInt(unitId) : undefined,
      status: { in: ["waiting", "called", "in_progress"] },
    },
    include: {
      client: { select: { id: true, name: true, phone: true } },
      professional: { select: { id: true, name: true, color: true } },
      service: { select: { id: true, name: true, duration: true } },
    },
    orderBy: [{ priority: "desc" }, { position: "asc" }, { checkedInAt: "asc" }],
  });

  return NextResponse.json(queue);
}

export async function POST(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const body = await req.json();
  const parsed = addSchema.safeParse(body);
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos" }, { status: 400 });

  const maxPosition = await prisma.serviceQueue.aggregate({
    where: {
      tenantId: session.tenantId,
      unitId: parsed.data.unitId,
      status: { in: ["waiting", "called"] },
    },
    _max: { position: true },
  });

  const entry = await prisma.serviceQueue.create({
    data: {
      tenantId: session.tenantId,
      unitId: parsed.data.unitId,
      clientId: parsed.data.clientId,
      professionalId: parsed.data.professionalId,
      serviceId: parsed.data.serviceId,
      priority: parsed.data.priority,
      notes: parsed.data.notes,
      position: (maxPosition._max.position ?? 0) + 1,
    },
    include: {
      client: true,
      professional: true,
      service: true,
    },
  });

  return NextResponse.json(entry, { status: 201 });
}
