import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getTenantBySlug, isTenantActive } from "@/lib/tenant";
import { hasConflict } from "@/lib/appointments";
import { z } from "zod";
import crypto from "crypto";

const schema = z.object({
  clientName: z.string().min(2),
  clientPhone: z.string().min(10),
  clientEmail: z.string().email().optional().or(z.literal("")),
  serviceId: z.string(),
  unitId: z.string(),
  professionalId: z.string(),
  date: z.string(),
  startTime: z.string(),
});

export async function POST(
  req: NextRequest,
  { params }: { params: Promise<{ slug: string }> }
) {
  const { slug } = await params;
  const tenant = await getTenantBySlug(slug);

  if (!tenant || !isTenantActive(tenant.status, tenant.trialEndsAt)) {
    return NextResponse.json({ error: "Empresa não disponível" }, { status: 404 });
  }

  const body = await req.json();
  const parsed = schema.safeParse(body);
  if (!parsed.success) return NextResponse.json({ error: "Dados inválidos" }, { status: 400 });

  const data = parsed.data;
  const serviceId = parseInt(data.serviceId);
  const professionalId = parseInt(data.professionalId);
  const unitId = parseInt(data.unitId);

  const service = await prisma.service.findFirst({
    where: { id: serviceId, tenantId: tenant.id, deletedAt: null, allowOnlineBooking: true },
  });
  if (!service) return NextResponse.json({ error: "Serviço não disponível" }, { status: 404 });

  const [h, m] = data.startTime.split(":").map(Number);
  const endMin = h * 60 + m + service.duration;
  const endTime = `${Math.floor(endMin / 60).toString().padStart(2, "0")}:${(endMin % 60).toString().padStart(2, "0")}`;

  // Parse YYYY-MM-DD as local date to avoid UTC off-by-one
  const [dy, dm, dd] = data.date.split("-").map(Number);
  const date = new Date(dy, dm - 1, dd);
  const conflict = await hasConflict(professionalId, date, data.startTime, endTime);
  if (conflict) return NextResponse.json({ error: "Horário não disponível" }, { status: 409 });

  let client = await prisma.client.findFirst({
    where: { tenantId: tenant.id, phone: data.clientPhone, deletedAt: null },
  });

  if (!client) {
    client = await prisma.client.create({
      data: {
        tenantId: tenant.id,
        name: data.clientName,
        phone: data.clientPhone,
        email: data.clientEmail || null,
        lgpdConsent: true,
        lgpdConsentAt: new Date(),
      },
    });
  }

  const appointment = await prisma.appointment.create({
    data: {
      tenantId: tenant.id,
      clientId: client.id,
      professionalId,
      serviceId,
      unitId,
      date,
      startTime: data.startTime,
      endTime,
      price: service.price,
      source: "online",
      cancelToken: crypto.randomBytes(32).toString("hex"),
      rescheduleToken: crypto.randomBytes(32).toString("hex"),
    },
  });

  return NextResponse.json({ ok: true, appointmentId: appointment.id }, { status: 201 });
}
