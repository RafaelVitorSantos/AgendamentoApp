import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { z } from "zod";

const createSchema = z.object({
  type: z.enum(["income", "expense"]),
  description: z.string().min(1),
  amount: z.number().positive(),
  categoryId: z.number().optional(),
  clientId: z.number().optional(),
  appointmentId: z.number().optional(),
  paymentMethod: z.string().optional(),
  status: z.enum(["pending", "paid", "cancelled"]).default("pending"),
  dueDate: z.string().optional(),
  notes: z.string().optional(),
});

export async function GET(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const { searchParams } = new URL(req.url);
  const type = searchParams.get("type");
  const status = searchParams.get("status");
  const from = searchParams.get("from");
  const to = searchParams.get("to");
  const page = parseInt(searchParams.get("page") ?? "1");
  const perPage = parseInt(searchParams.get("perPage") ?? "20");

  const where: Record<string, unknown> = { tenantId: session.tenantId };
  if (type) where.type = type;
  if (status) where.status = status;
  if (from || to) {
    where.createdAt = {};
    if (from) (where.createdAt as Record<string, unknown>).gte = new Date(from + "T00:00:00");
    if (to)   (where.createdAt as Record<string, unknown>).lte = new Date(to + "T23:59:59");
  }

  const [transactions, total, summary] = await Promise.all([
    prisma.financialTransaction.findMany({
      where,
      include: {
        category: true,
        client: { select: { id: true, name: true } },
      },
      orderBy: { createdAt: "desc" },
      skip: (page - 1) * perPage,
      take: perPage,
    }),
    prisma.financialTransaction.count({ where }),
    prisma.financialTransaction.groupBy({
      by: ["type"],
      where: { ...where, status: "paid" },
      _sum: { amount: true },
    }),
  ]);

  const income = summary.find((s) => s.type === "income")?._sum.amount ?? 0;
  const expense = summary.find((s) => s.type === "expense")?._sum.amount ?? 0;

  return NextResponse.json({
    data: transactions,
    total,
    page,
    perPage,
    totalPages: Math.ceil(total / perPage),
    summary: { income, expense, balance: Number(income) - Number(expense) },
  });
}

export async function POST(req: NextRequest) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Não autenticado" }, { status: 401 });

  const body = await req.json();
  const parsed = createSchema.safeParse(body);
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos" }, { status: 400 });

  const data = parsed.data;
  const transaction = await prisma.financialTransaction.create({
    data: {
      tenantId: session.tenantId,
      type: data.type,
      description: data.description,
      amount: data.amount,
      categoryId: data.categoryId,
      clientId: data.clientId,
      appointmentId: data.appointmentId,
      paymentMethod: data.paymentMethod,
      status: data.status,
      dueDate: data.dueDate ? new Date(data.dueDate) : null,
      notes: data.notes,
      paidAt: data.status === "paid" ? new Date() : null,
    },
  });

  return NextResponse.json(transaction, { status: 201 });
}
