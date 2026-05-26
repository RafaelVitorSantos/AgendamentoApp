import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { redirect } from "next/navigation";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Calendar, Users, DollarSign, Clock, ListOrdered } from "lucide-react";
import { format } from "date-fns";
import { ptBR } from "date-fns/locale";

interface Props {
  params: Promise<{ tenant: string }>;
}

export default async function DashboardPage({ params }: Props) {
  const { tenant } = await params;
  const session = await getSession();
  if (!session) redirect(`/${tenant}/login`);

  const tenantId = session.tenantId;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const tomorrow = new Date(today);
  tomorrow.setDate(tomorrow.getDate() + 1);
  const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
  const monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);

  const [
    appointmentsToday,
    revenueMonth,
    newClientsMonth,
    pendingQueue,
    todayAppointments,
  ] = await Promise.all([
    prisma.appointment.count({
      where: {
        tenantId,
        date: { gte: today, lt: tomorrow },
        deletedAt: null,
        status: { notIn: ["cancelled_by_client", "cancelled_by_business"] },
      },
    }),
    prisma.financialTransaction.aggregate({
      where: { tenantId, type: "income", status: "paid", createdAt: { gte: monthStart, lte: monthEnd } },
      _sum: { amount: true },
    }),
    prisma.client.count({
      where: { tenantId, deletedAt: null, createdAt: { gte: monthStart, lte: monthEnd } },
    }),
    prisma.serviceQueue.count({
      where: { tenantId, status: { in: ["waiting", "called"] } },
    }),
    prisma.appointment.findMany({
      where: {
        tenantId,
        date: { gte: today, lt: tomorrow },
        deletedAt: null,
        status: { notIn: ["cancelled_by_client", "cancelled_by_business"] },
      },
      include: {
        client: { select: { name: true } },
        professional: { select: { name: true, color: true } },
        service: { select: { name: true } },
      },
      orderBy: { startTime: "asc" },
      take: 15,
    }),
  ]);

  const statusLabels: Record<string, { label: string; variant: "default" | "secondary" | "outline" | "destructive" }> = {
    scheduled: { label: "Agendado", variant: "secondary" },
    confirmed: { label: "Confirmado", variant: "default" },
    in_progress: { label: "Em andamento", variant: "default" },
    completed: { label: "Concluído", variant: "outline" },
    no_show: { label: "Não compareceu", variant: "destructive" },
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Dashboard</h1>
        <p className="text-muted-foreground">
          {format(new Date(), "EEEE, d 'de' MMMM 'de' yyyy", { locale: ptBR })}
        </p>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Agendamentos hoje</CardTitle>
            <Calendar className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{appointmentsToday}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Receita do mês</CardTitle>
            <DollarSign className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {Number(revenueMonth._sum.amount ?? 0).toLocaleString("pt-BR", {
                style: "currency",
                currency: "BRL",
              })}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Novos clientes</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{newClientsMonth}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Fila de espera</CardTitle>
            <ListOrdered className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{pendingQueue}</div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Clock className="h-5 w-5" />
            Agenda de hoje
          </CardTitle>
        </CardHeader>
        <CardContent>
          {todayAppointments.length === 0 ? (
            <p className="text-muted-foreground text-sm text-center py-8">
              Nenhum agendamento para hoje
            </p>
          ) : (
            <div className="space-y-3">
              {todayAppointments.map((apt) => {
                const status = statusLabels[apt.status] ?? { label: apt.status, variant: "secondary" as const };
                return (
                  <div key={apt.id} className="flex items-center gap-4 p-3 rounded-lg border hover:bg-accent transition-colors">
                    <div className="text-sm font-medium w-20 shrink-0 text-center">
                      <div>{apt.startTime}</div>
                      <div className="text-muted-foreground text-xs">{apt.endTime}</div>
                    </div>
                    <div
                      className="w-1 h-10 rounded-full shrink-0"
                      style={{ backgroundColor: apt.professional.color ?? "#6366f1" }}
                    />
                    <div className="flex-1 min-w-0">
                      <p className="font-medium text-sm truncate">{apt.client.name}</p>
                      <p className="text-xs text-muted-foreground truncate">
                        {apt.service.name} · {apt.professional.name}
                      </p>
                    </div>
                    <Badge variant={status.variant}>{status.label}</Badge>
                  </div>
                );
              })}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
