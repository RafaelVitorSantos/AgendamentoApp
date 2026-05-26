import { NextRequest, NextResponse } from "next/server";
import { getTenantBySlug } from "@/lib/tenant";
import { getAvailableSlots } from "@/lib/appointments";

export async function GET(
  req: NextRequest,
  { params }: { params: Promise<{ slug: string }> }
) {
  const { slug } = await params;
  const { searchParams } = new URL(req.url);
  const professionalId = parseInt(searchParams.get("professionalId") ?? "0");
  const serviceId = parseInt(searchParams.get("serviceId") ?? "0");
  const unitIdParam = parseInt(searchParams.get("unitId") ?? "0");
  const dateStr = searchParams.get("date");

  if (!professionalId || !serviceId || !dateStr) {
    return NextResponse.json({ slots: [] });
  }

  const tenant = await getTenantBySlug(slug);
  if (!tenant) return NextResponse.json({ slots: [] });

  const { prisma } = await import("@/lib/db");
  const service = await prisma.service.findFirst({
    where: { id: serviceId, tenantId: tenant.id, allowOnlineBooking: true },
  });
  if (!service) return NextResponse.json({ slots: [] });

  // Use the unitId provided by the client; fall back to the first active unit
  const unitWhere = unitIdParam
    ? { id: unitIdParam, tenantId: tenant.id, isActive: true }
    : { tenantId: tenant.id, isActive: true };

  const unit = await prisma.unit.findFirst({ where: unitWhere });
  if (!unit) return NextResponse.json({ slots: [] });

  // Parse date as local (YYYY-MM-DD) to avoid UTC off-by-one
  const [year, month, day] = dateStr.split("-").map(Number);
  const date = new Date(year, month - 1, day);

  const slots = await getAvailableSlots(
    tenant.id,
    professionalId,
    unit.id,
    date,
    service.duration
  );

  return NextResponse.json({ slots });
}
