import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { signToken, getUserPermissions } from "@/lib/auth";
import { z } from "zod";
import bcrypt from "bcryptjs";

const schema = z.object({
  email: z.string().email(),
  password: z.string().min(1),
  tenantSlug: z.string().min(1),
});

export async function POST(req: NextRequest) {
  const body = await req.json();
  const parsed = schema.safeParse(body);
  if (!parsed.success) {
    return NextResponse.json({ error: "Dados inválidos" }, { status: 400 });
  }

  const { email, password, tenantSlug } = parsed.data;

  const tenant = await prisma.tenant.findUnique({ where: { slug: tenantSlug } });
  if (!tenant) {
    return NextResponse.json({ error: "Empresa não encontrada" }, { status: 404 });
  }

  if (tenant.status === "suspended" || tenant.status === "cancelled") {
    return NextResponse.json({ error: "Conta suspensa ou cancelada" }, { status: 403 });
  }

  const user = await prisma.user.findFirst({
    where: { tenantId: tenant.id, email, deletedAt: null, isActive: true },
    include: { role: true },
  });

  if (!user || !(await bcrypt.compare(password, user.passwordHash))) {
    return NextResponse.json({ error: "Credenciais inválidas" }, { status: 401 });
  }

  const permissions = await getUserPermissions(user.id, user.roleId);

  const token = await signToken({
    sub: String(user.id),
    userId: user.id,
    tenantId: tenant.id,
    roleId: user.roleId,
    roleName: user.role.name,
    permissions,
    tenantSlug: tenant.slug,
    tenantStatus: tenant.status,
  });

  await prisma.user.update({
    where: { id: user.id },
    data: { lastLoginAt: new Date() },
  });

  const ttl = parseInt(process.env.JWT_TTL ?? "28800");
  const res = NextResponse.json({
    user: { id: user.id, name: user.name, email: user.email, role: user.role.name },
    tenantSlug: tenant.slug,
  });

  res.cookies.set("agendapro_token", token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    maxAge: ttl,
    path: "/",
  });

  return res;
}
