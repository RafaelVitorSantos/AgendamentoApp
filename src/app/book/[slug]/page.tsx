import { prisma } from "@/lib/db";
import { notFound } from "next/navigation";
import { isTenantActive } from "@/lib/tenant";
import { BookingShell } from "./booking-shell";
import type { Metadata } from "next";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const tenant = await prisma.tenant.findUnique({ where: { slug } });
  if (!tenant) return { title: "Agendamento não encontrado" };

  return {
    title: `Agendar — ${tenant.name}`,
    description: `Agende seu horário online em ${tenant.name}. Rápido, fácil e sem complicação.`,
    openGraph: {
      title: `${tenant.name} — Agendamento Online`,
      description: `Agende seu horário em ${tenant.name} em poucos cliques.`,
      type: "website",
    },
    alternates: { canonical: `/auraflowstudio/${slug}` },
  };
}

export default async function PublicBookingPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;

  const tenant = await prisma.tenant.findUnique({ where: { slug } });
  if (!tenant || !isTenantActive(tenant.status, tenant.trialEndsAt)) {
    notFound();
  }

  const rawServices = await prisma.service.findMany({
    where: { tenantId: tenant.id, deletedAt: null, isActive: true, allowOnlineBooking: true },
    orderBy: { name: "asc" },
    select: { id: true, name: true, duration: true, price: true, description: true, color: true },
  });

  const services = rawServices.map((s) => ({
    id: s.id,
    name: s.name,
    duration: s.duration,
    price: Number(s.price),
    description: s.description,
    color: s.color,
  }));

  const units = await prisma.unit.findMany({
    where: { tenantId: tenant.id, deletedAt: null, isActive: true },
    orderBy: { name: "asc" },
    select: { id: true, name: true },
  });

  const [reviewCount, avgRating] = await Promise.all([
    prisma.review.count({ where: { tenantId: tenant.id } }),
    prisma.review.aggregate({ where: { tenantId: tenant.id }, _avg: { rating: true } }),
  ]);

  const rating =
    avgRating._avg.rating ? Number(avgRating._avg.rating).toFixed(1) : null;

  return (
    <BookingShell
      tenantSlug={slug}
      tenantId={tenant.id}
      tenantName={tenant.name}
      tenantPhone={tenant.phone}
      services={services}
      units={units}
      rating={rating}
      reviewCount={reviewCount}
    />
  );
}
