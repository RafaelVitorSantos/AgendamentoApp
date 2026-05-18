import { prisma } from "@/lib/db";
import { notFound } from "next/navigation";
import { isTenantActive } from "@/lib/tenant";
import { PublicBookingForm } from "./booking-form";

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const tenant = await prisma.tenant.findUnique({ where: { slug } });
  if (!tenant) return { title: "Agendamento não encontrado" };
  return { title: `Agendar — ${tenant.name}` };
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
  });

  const services = rawServices.map((s) => ({
    id: s.id,
    name: s.name,
    duration: s.duration,
    price: Number(s.price),
  }));

  const units = await prisma.unit.findMany({
    where: { tenantId: tenant.id, deletedAt: null, isActive: true },
    orderBy: { name: "asc" },
    select: { id: true, name: true },
  });

  return (
    <div className="min-h-screen bg-gradient-to-br from-primary/5 to-primary/10">
      <div className="max-w-2xl mx-auto px-4 py-12">
        <div className="text-center mb-8">
          <div className="w-16 h-16 bg-primary rounded-2xl flex items-center justify-center text-primary-foreground font-bold text-2xl mx-auto mb-4">
            {tenant.name.charAt(0)}
          </div>
          <h1 className="text-3xl font-bold">{tenant.name}</h1>
          <p className="text-muted-foreground mt-2">Agende seu horário online</p>
        </div>

        <PublicBookingForm
          tenantSlug={slug}
          tenantId={tenant.id}
          services={services}
          units={units}
        />
      </div>
    </div>
  );
}
