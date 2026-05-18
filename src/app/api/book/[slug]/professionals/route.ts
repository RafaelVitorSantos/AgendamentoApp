import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getTenantBySlug } from "@/lib/tenant";

export async function GET(
  req: NextRequest,
  { params }: { params: Promise<{ slug: string }> }
) {
  const { slug } = await params;
  const { searchParams } = new URL(req.url);
  const serviceId = parseInt(searchParams.get("serviceId") ?? "0");
  const unitId = parseInt(searchParams.get("unitId") ?? "0");

  const tenant = await getTenantBySlug(slug);
  if (!tenant) return NextResponse.json([], { status: 200 });

  const professionals = await prisma.professional.findMany({
    where: {
      tenantId: tenant.id,
      deletedAt: null,
      isActive: true,
      professionalUnits: { some: { unitId } },
      professionalServices: { some: { serviceId } },
    },
    select: { id: true, name: true, avatar: true, color: true },
    orderBy: { name: "asc" },
  });

  return NextResponse.json(professionals);
}
