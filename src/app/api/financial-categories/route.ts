import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";

export async function GET(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { searchParams } = new URL(req.url);
  const type = searchParams.get("type");

  const categories = await prisma.financialCategory.findMany({
    where: {
      tenantId: session.tenantId,
      ...(type ? { type } : {}),
    },
    orderBy: { name: "asc" },
  });

  return NextResponse.json(categories);
}
