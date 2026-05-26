import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ slug: string }> }
) {
  const { slug } = await params;

  if (!/^[a-z0-9-]+$/.test(slug)) {
    return NextResponse.json({ error: "Empresa não encontrada" }, { status: 404 });
  }

  const tenant = await prisma.tenant.findUnique({
    where: { slug },
    select: { name: true, slug: true, status: true },
  });

  if (!tenant) {
    return NextResponse.json({ error: "Empresa não encontrada" }, { status: 404 });
  }

  if (tenant.status === "suspended" || tenant.status === "cancelled") {
    return NextResponse.json({ error: "Conta suspensa ou cancelada" }, { status: 403 });
  }

  return NextResponse.json({ name: tenant.name, slug: tenant.slug });
}
