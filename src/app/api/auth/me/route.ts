import { NextResponse } from "next/server";
import { getSession } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET() {
  const session = await getSession();
  if (!session) {
    return NextResponse.json({ error: "Não autenticado" }, { status: 401 });
  }

  const user = await prisma.user.findUnique({
    where: { id: session.userId },
    select: {
      id: true,
      name: true,
      email: true,
      avatar: true,
      phone: true,
      role: { select: { name: true, label: true } },
    },
  });

  if (!user) {
    return NextResponse.json({ error: "Usuário não encontrado" }, { status: 404 });
  }

  const res = NextResponse.json({
    ...user,
    tenantId: session.tenantId,
    tenantSlug: session.tenantSlug,
    permissions: session.permissions,
  });

  // Cache no browser por 60s, revalidação silenciosa por mais 120s
  res.headers.set("Cache-Control", "private, max-age=60, stale-while-revalidate=120");

  return res;
}
