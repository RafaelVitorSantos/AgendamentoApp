import { NextRequest, NextResponse } from "next/server";
import { getSession } from "@/lib/auth";
import { getAvailableSlots } from "@/lib/appointments";
import { prisma } from "@/lib/db";

export async function GET(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { searchParams } = new URL(req.url);
  const professionalId = parseInt(searchParams.get("professionalId") ?? "0");
  const unitId = parseInt(searchParams.get("unitId") ?? "0");
  const serviceId = parseInt(searchParams.get("serviceId") ?? "0");
  const dateStr = searchParams.get("date");

  if (!professionalId || !unitId || !serviceId || !dateStr) {
    return NextResponse.json({ error: "Parâmetros obrigatórios ausentes" }, { status: 400 });
  }

  const service = await prisma.service.findFirst({
    where: { id: serviceId, tenantId: session.tenantId, deletedAt: null },
  });
  if (!service) return NextResponse.json({ error: "Serviço não encontrado" }, { status: 404 });

  const date = new Date(dateStr);
  const slots = await getAvailableSlots(
    session.tenantId,
    professionalId,
    unitId,
    date,
    service.duration
  );

  return NextResponse.json({ slots });
}
