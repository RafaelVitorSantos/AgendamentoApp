import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { signToken, getUserPermissions } from "@/lib/auth";
import { z } from "zod";
import bcrypt from "bcryptjs";

const schema = z.object({
  companyName: z.string().min(2),
  companySlug: z
    .string()
    .min(2)
    .regex(/^[a-z0-9-]+$/),
  companyEmail: z.string().email(),
  userName: z.string().min(2),
  userEmail: z.string().email(),
  password: z.string().min(8),
});

export async function POST(req: NextRequest) {
  const body = await req.json();
  const parsed = schema.safeParse(body);
  if (!parsed.success) {
    return NextResponse.json(
      { error: "Dados inválidos", details: parsed.error.flatten() },
      { status: 400 }
    );
  }

  const { companyName, companySlug, companyEmail, userName, userEmail, password } =
    parsed.data;

  const existing = await prisma.tenant.findUnique({ where: { slug: companySlug } });
  if (existing) {
    return NextResponse.json({ error: "Este slug já está em uso" }, { status: 409 });
  }

  const adminRole = await prisma.role.findUnique({ where: { name: "tenant_admin" } });
  if (!adminRole) {
    return NextResponse.json({ error: "Configuração interna inválida" }, { status: 500 });
  }

  const freePlan = await prisma.plan.findUnique({ where: { slug: "free" } });

  const passwordHash = await bcrypt.hash(password, 12);
  const trialEndsAt = new Date();
  trialEndsAt.setDate(trialEndsAt.getDate() + 14);

  const result = await prisma.$transaction(async (tx) => {
    const tenant = await tx.tenant.create({
      data: {
        name: companyName,
        slug: companySlug,
        email: companyEmail,
        status: "trial",
        trialEndsAt,
      },
    });

    const user = await tx.user.create({
      data: {
        tenantId: tenant.id,
        roleId: adminRole.id,
        name: userName,
        email: userEmail,
        passwordHash,
      },
    });

    await tx.unit.create({
      data: {
        tenantId: tenant.id,
        name: companyName,
        isActive: true,
      },
    });

    if (freePlan) {
      const now = new Date();
      const periodEnd = new Date(now);
      periodEnd.setDate(periodEnd.getDate() + 14);

      await tx.subscription.create({
        data: {
          tenantId: tenant.id,
          planId: freePlan.id,
          status: "trial",
          currentPeriodStart: now,
          currentPeriodEnd: periodEnd,
        },
      });
    }

    const defaultCategories = [
      { name: "Serviços", type: "income", isSystem: true },
      { name: "Produtos", type: "income", isSystem: true },
      { name: "Aluguel", type: "expense", isSystem: true },
      { name: "Salários", type: "expense", isSystem: true },
      { name: "Material", type: "expense", isSystem: true },
    ];

    await tx.financialCategory.createMany({
      data: defaultCategories.map((c) => ({ ...c, tenantId: tenant.id })),
    });

    return { tenant, user };
  });

  const permissions = await getUserPermissions(result.user.id, adminRole.id);

  const token = await signToken({
    sub: String(result.user.id),
    userId: result.user.id,
    tenantId: result.tenant.id,
    roleId: adminRole.id,
    roleName: adminRole.name,
    permissions,
    tenantSlug: result.tenant.slug,
    tenantStatus: result.tenant.status,
  });

  const ttl = parseInt(process.env.JWT_TTL ?? "28800");
  const res = NextResponse.json(
    {
      user: { id: result.user.id, name: result.user.name, email: result.user.email },
      tenantSlug: result.tenant.slug,
    },
    { status: 201 }
  );

  res.cookies.set("agendapro_token", token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    maxAge: ttl,
    path: "/",
  });

  return res;
}
