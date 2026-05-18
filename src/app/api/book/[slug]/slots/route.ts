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

  const unit = await prisma.unit.findFirst({
    where: { tenantId: tenant.id, isActive: true },
  });
  if (!unit) return NextResponse.json({ slots: [] });

  const slots = await getAvailableSlots(
    tenant.id,
    professionalId,
    unit.id,
    new Date(dateStr),
    service.duration
  );

  return NextResponse.json({ slots });
}
