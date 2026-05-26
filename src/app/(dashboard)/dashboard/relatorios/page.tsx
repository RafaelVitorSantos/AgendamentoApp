"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from "@/components/ui/table";
import {
  BarChart3, Calendar, Users, DollarSign, TrendingUp, TrendingDown,
  CheckCircle2, XCircle, AlertCircle, Clock, Scissors, Briefcase,
} from "lucide-react";

// ─── Types ────────────────────────────────────────────────────────────────────

interface Overview {
  totalAppointments: number;
  completedAppointments: number;
  cancelledAppointments: number;
  completionRate: number;
  income: number;
  expense: number;
  balance: number;
}

interface ByStatus { status: string; count: number; }
interface TopService { id: number; name: string; color: string | null; count: number; revenue: number; }
interface TopClient { id: number; name: string; visits: number; spent: number; }
interface ByProfessional { id: number; name: string; color: string | null; appointments: number; revenue: number; }
interface ByPaymentMethod { method: string; amount: number; }

interface ReportsData {
  period: { from: string; to: string };
  overview: Overview;
  byStatus: ByStatus[];
  topServices: TopService[];
  topClients: TopClient[];
  byProfessional: ByProfessional[];
  byPaymentMethod: ByPaymentMethod[];
}

// ─── Constants ────────────────────────────────────────────────────────────────

const STATUS_META: Record<string, { label: string; color: string; icon: React.ReactNode }> = {
  scheduled:              { label: "Agendado",           color: "bg-blue-500",    icon: <Clock className="h-3.5 w-3.5" /> },
  confirmed:              { label: "Confirmado",          color: "bg-indigo-500",  icon: <CheckCircle2 className="h-3.5 w-3.5" /> },
  in_progress:            { label: "Em andamento",        color: "bg-amber-500",   icon: <Clock className="h-3.5 w-3.5" /> },
  completed:              { label: "Concluído",            color: "bg-green-500",   icon: <CheckCircle2 className="h-3.5 w-3.5" /> },
  no_show:                { label: "Não compareceu",      color: "bg-orange-500",  icon: <AlertCircle className="h-3.5 w-3.5" /> },
  cancelled_by_client:    { label: "Cancelado (cliente)", color: "bg-red-400",     icon: <XCircle className="h-3.5 w-3.5" /> },
  cancelled_by_business:  { label: "Cancelado (negócio)", color: "bg-red-600",    icon: <XCircle className="h-3.5 w-3.5" /> },
  rescheduled:            { label: "Reagendado",          color: "bg-yellow-500",  icon: <Clock className="h-3.5 w-3.5" /> },
};

const PAYMENT_LABELS: Record<string, string> = {
  dinheiro:       "Dinheiro",
  pix:            "Pix",
  cartao_credito: "Cartão de Crédito",
  cartao_debito:  "Cartão de Débito",
  transferencia:  "Transferência",
  boleto:         "Boleto",
  outro:          "Outro",
};

// ─── Helpers ─────────────────────────────────────────────────────────────────

function todayIso() { return new Date().toISOString().split("T")[0]; }
function monthStartIso() {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split("T")[0];
}

function brl(value: number) {
  return value.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

function pct(value: number) {
  return (value * 100).toFixed(1) + "%";
}

function initials(name: string) {
  return name.split(" ").slice(0, 2).map((w) => w[0]).join("").toUpperCase();
}

// ─── Sub-components ───────────────────────────────────────────────────────────

function ProgressBar({ value, max, color = "bg-primary" }: { value: number; max: number; color?: string }) {
  const pctNum = max > 0 ? Math.min((value / max) * 100, 100) : 0;
  return (
    <div className="h-1.5 w-full rounded-full bg-muted overflow-hidden">
      <div className={`h-full rounded-full transition-all ${color}`} style={{ width: `${pctNum}%` }} />
    </div>
  );
}

function KpiCard({
  title, value, sub, icon, color = "text-foreground",
}: { title: string; value: string; sub?: string; icon: React.ReactNode; color?: string }) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between pb-2">
        <CardTitle className="text-sm font-medium text-muted-foreground">{title}</CardTitle>
        <div className="text-muted-foreground">{icon}</div>
      </CardHeader>
      <CardContent>
        <div className={`text-2xl font-bold ${color}`}>{value}</div>
        {sub && <p className="text-xs text-muted-foreground mt-0.5">{sub}</p>}
      </CardContent>
    </Card>
  );
}

function SkeletonCard() {
  return (
    <Card>
      <CardHeader className="pb-2"><div className="h-4 w-28 bg-muted animate-pulse rounded" /></CardHeader>
      <CardContent><div className="h-7 w-24 bg-muted animate-pulse rounded" /></CardContent>
    </Card>
  );
}

function EmptyState({ icon, message }: { icon: React.ReactNode; message: string }) {
  return (
    <div className="py-12 flex flex-col items-center gap-3 text-muted-foreground/50">
      {icon}
      <p className="text-sm text-muted-foreground">{message}</p>
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function RelatoriosPage() {
  const [from, setFrom] = useState(monthStartIso);
  const [to, setTo] = useState(todayIso);
  const [applied, setApplied] = useState({ from: monthStartIso(), to: todayIso() });

  const { data, isLoading, isError } = useQuery<ReportsData>({
    queryKey: ["reports", applied.from, applied.to],
    queryFn: () =>
      fetch(`/api/reports?from=${applied.from}&to=${applied.to}`)
        .then((r) => { if (!r.ok) throw new Error(); return r.json(); }),
  });

  function applyFilters() { setApplied({ from, to }); }

  const ov = data?.overview;
  const maxService = Math.max(1, ...(data?.topServices.map((s) => s.count) ?? [1]));
  const maxClient = Math.max(1, ...(data?.topClients.map((c) => c.visits) ?? [1]));
  const maxProf = Math.max(1, ...(data?.byProfessional.map((p) => p.appointments) ?? [1]));
  const maxMethod = Math.max(1, ...(data?.byPaymentMethod.map((m) => m.amount) ?? [1]));

  return (
    <div className="space-y-6">
      {/* ── Header ─────────────────────────────────────────────────────────── */}
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold">Relatórios</h1>
          <p className="text-muted-foreground text-sm">Análise de desempenho do negócio</p>
        </div>

        <div className="flex flex-wrap items-end gap-3">
          <div className="flex items-center gap-2">
            <Label className="text-xs text-muted-foreground whitespace-nowrap">De</Label>
            <Input type="date" className="h-9 w-[140px] text-sm" value={from}
              onChange={(e) => setFrom(e.target.value)} />
            <Label className="text-xs text-muted-foreground whitespace-nowrap">Até</Label>
            <Input type="date" className="h-9 w-[140px] text-sm" value={to}
              onChange={(e) => setTo(e.target.value)} />
          </div>
          <Button size="sm" onClick={applyFilters} disabled={isLoading}>
            {isLoading ? "Carregando..." : "Aplicar"}
          </Button>
        </div>
      </div>

      {/* ── KPI Cards ──────────────────────────────────────────────────────── */}
      {isLoading ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {Array.from({ length: 4 }).map((_, i) => <SkeletonCard key={i} />)}
        </div>
      ) : isError ? (
        <div className="p-10 flex flex-col items-center gap-3 text-destructive">
          <AlertCircle className="h-8 w-8" />
          <p className="font-medium">Erro ao carregar relatórios</p>
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard
            title="Total de agendamentos"
            value={String(ov?.totalAppointments ?? 0)}
            sub={`${ov?.completedAppointments ?? 0} concluídos · ${ov?.cancelledAppointments ?? 0} cancelados`}
            icon={<Calendar className="h-4 w-4" />}
          />
          <KpiCard
            title="Taxa de conclusão"
            value={pct(ov?.completionRate ?? 0)}
            sub="Agendamentos concluídos / total"
            icon={<CheckCircle2 className="h-4 w-4" />}
            color={(ov?.completionRate ?? 0) >= 0.7 ? "text-green-700" : "text-amber-600"}
          />
          <KpiCard
            title="Receita (paga)"
            value={brl(ov?.income ?? 0)}
            sub={`Despesas: ${brl(ov?.expense ?? 0)}`}
            icon={<TrendingUp className="h-4 w-4" />}
            color="text-green-700"
          />
          <KpiCard
            title="Saldo do período"
            value={brl(ov?.balance ?? 0)}
            icon={<DollarSign className="h-4 w-4" />}
            color={(ov?.balance ?? 0) >= 0 ? "text-green-700" : "text-red-700"}
          />
        </div>
      )}

      {/* ── Tabs ───────────────────────────────────────────────────────────── */}
      {!isLoading && !isError && data && (
        <Tabs defaultValue="agendamentos">
          <TabsList className="w-full justify-start">
            <TabsTrigger value="agendamentos">
              <Calendar className="h-4 w-4" />
              Agendamentos
            </TabsTrigger>
            <TabsTrigger value="profissionais">
              <Briefcase className="h-4 w-4" />
              Profissionais
            </TabsTrigger>
            <TabsTrigger value="servicos">
              <Scissors className="h-4 w-4" />
              Serviços
            </TabsTrigger>
            <TabsTrigger value="clientes">
              <Users className="h-4 w-4" />
              Clientes
            </TabsTrigger>
            <TabsTrigger value="financeiro">
              <DollarSign className="h-4 w-4" />
              Financeiro
            </TabsTrigger>
          </TabsList>

          {/* ── Agendamentos tab ─────────────────────────────────────────── */}
          <TabsContent value="agendamentos">
            <Card>
              <CardHeader>
                <CardTitle className="text-base flex items-center gap-2">
                  <BarChart3 className="h-4 w-4" />
                  Agendamentos por status
                </CardTitle>
              </CardHeader>
              <CardContent>
                {data.byStatus.length === 0 ? (
                  <EmptyState icon={<Calendar className="h-8 w-8" />} message="Nenhum agendamento no período" />
                ) : (
                  <div className="space-y-4">
                    {data.byStatus
                      .sort((a, b) => b.count - a.count)
                      .map((s) => {
                        const meta = STATUS_META[s.status] ?? { label: s.status, color: "bg-muted-foreground", icon: null };
                        const pctNum = data.overview.totalAppointments > 0
                          ? (s.count / data.overview.totalAppointments) * 100 : 0;
                        return (
                          <div key={s.status} className="space-y-1.5">
                            <div className="flex items-center justify-between text-sm">
                              <div className="flex items-center gap-2">
                                <div className={`w-2.5 h-2.5 rounded-full ${meta.color}`} />
                                <span className="font-medium">{meta.label}</span>
                              </div>
                              <div className="flex items-center gap-3 text-muted-foreground">
                                <span>{s.count} agendamento{s.count !== 1 ? "s" : ""}</span>
                                <span className="w-12 text-right">{pctNum.toFixed(1)}%</span>
                              </div>
                            </div>
                            <ProgressBar value={s.count} max={data.overview.totalAppointments} color={meta.color} />
                          </div>
                        );
                      })}
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* ── Profissionais tab ────────────────────────────────────────── */}
          <TabsContent value="profissionais">
            <Card>
              <CardHeader>
                <CardTitle className="text-base flex items-center gap-2">
                  <Briefcase className="h-4 w-4" />
                  Desempenho por profissional
                </CardTitle>
              </CardHeader>
              <CardContent className="p-0">
                {data.byProfessional.length === 0 ? (
                  <EmptyState icon={<Briefcase className="h-8 w-8" />} message="Nenhum dado disponível" />
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Profissional</TableHead>
                        <TableHead>Agendamentos</TableHead>
                        <TableHead className="w-40">Volume</TableHead>
                        <TableHead className="text-right">Receita gerada</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data.byProfessional.map((p) => (
                        <TableRow key={p.id}>
                          <TableCell>
                            <div className="flex items-center gap-2.5">
                              <div
                                className="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
                                style={{ backgroundColor: p.color ?? "#6366f1" }}>
                                {initials(p.name)}
                              </div>
                              <span className="font-medium">{p.name}</span>
                            </div>
                          </TableCell>
                          <TableCell className="text-muted-foreground">{p.appointments}</TableCell>
                          <TableCell>
                            <ProgressBar value={p.appointments} max={maxProf} color="bg-primary" />
                          </TableCell>
                          <TableCell className="text-right font-medium">{brl(p.revenue)}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* ── Serviços tab ─────────────────────────────────────────────── */}
          <TabsContent value="servicos">
            <Card>
              <CardHeader>
                <CardTitle className="text-base flex items-center gap-2">
                  <Scissors className="h-4 w-4" />
                  Serviços mais agendados
                </CardTitle>
              </CardHeader>
              <CardContent className="p-0">
                {data.topServices.length === 0 ? (
                  <EmptyState icon={<Scissors className="h-8 w-8" />} message="Nenhum serviço no período" />
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>#</TableHead>
                        <TableHead>Serviço</TableHead>
                        <TableHead>Agendamentos</TableHead>
                        <TableHead className="w-40">Popularidade</TableHead>
                        <TableHead className="text-right">Receita gerada</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data.topServices.map((s, i) => (
                        <TableRow key={s.id}>
                          <TableCell className="text-muted-foreground font-mono text-sm">
                            {String(i + 1).padStart(2, "0")}
                          </TableCell>
                          <TableCell>
                            <div className="flex items-center gap-2">
                              {s.color && (
                                <div className="w-2.5 h-2.5 rounded-full shrink-0"
                                  style={{ backgroundColor: s.color }} />
                              )}
                              <span className="font-medium">{s.name}</span>
                            </div>
                          </TableCell>
                          <TableCell className="text-muted-foreground">{s.count}</TableCell>
                          <TableCell>
                            <ProgressBar value={s.count} max={maxService} color="bg-primary" />
                          </TableCell>
                          <TableCell className="text-right font-medium">{brl(s.revenue)}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* ── Clientes tab ─────────────────────────────────────────────── */}
          <TabsContent value="clientes">
            <div className="grid gap-4 lg:grid-cols-2">
              {/* Top por visitas */}
              <Card>
                <CardHeader>
                  <CardTitle className="text-base flex items-center gap-2">
                    <Users className="h-4 w-4" />
                    Mais visitas
                  </CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                  {data.topClients.length === 0 ? (
                    <EmptyState icon={<Users className="h-8 w-8" />} message="Nenhum dado disponível" />
                  ) : (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Cliente</TableHead>
                          <TableHead className="text-right">Visitas</TableHead>
                          <TableHead className="w-28">Volume</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {[...data.topClients].sort((a, b) => b.visits - a.visits).map((c, i) => (
                          <TableRow key={c.id}>
                            <TableCell>
                              <div className="flex items-center gap-2">
                                <div className="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center text-primary text-xs font-bold shrink-0">
                                  {initials(c.name)}
                                </div>
                                <div>
                                  <p className="font-medium text-sm">{c.name}</p>
                                  <p className="text-xs text-muted-foreground">#{i + 1}</p>
                                </div>
                              </div>
                            </TableCell>
                            <TableCell className="text-right font-medium">{c.visits}</TableCell>
                            <TableCell>
                              <ProgressBar value={c.visits} max={maxClient} color="bg-primary" />
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  )}
                </CardContent>
              </Card>

              {/* Top por gasto */}
              <Card>
                <CardHeader>
                  <CardTitle className="text-base flex items-center gap-2">
                    <TrendingUp className="h-4 w-4" />
                    Maior ticket
                  </CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                  {data.topClients.length === 0 ? (
                    <EmptyState icon={<Users className="h-8 w-8" />} message="Nenhum dado disponível" />
                  ) : (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Cliente</TableHead>
                          <TableHead className="text-right">Total gasto</TableHead>
                          <TableHead className="text-right w-20">Ticket médio</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {[...data.topClients].sort((a, b) => b.spent - a.spent).map((c) => (
                          <TableRow key={c.id}>
                            <TableCell>
                              <div className="flex items-center gap-2">
                                <div className="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center text-primary text-xs font-bold shrink-0">
                                  {initials(c.name)}
                                </div>
                                <span className="font-medium text-sm">{c.name}</span>
                              </div>
                            </TableCell>
                            <TableCell className="text-right font-medium text-green-700">{brl(c.spent)}</TableCell>
                            <TableCell className="text-right text-muted-foreground text-sm">
                              {c.visits > 0 ? brl(c.spent / c.visits) : "—"}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  )}
                </CardContent>
              </Card>
            </div>
          </TabsContent>

          {/* ── Financeiro tab ───────────────────────────────────────────── */}
          <TabsContent value="financeiro">
            <div className="grid gap-4 lg:grid-cols-2">
              {/* Resumo */}
              <Card>
                <CardHeader>
                  <CardTitle className="text-base flex items-center gap-2">
                    <DollarSign className="h-4 w-4" />
                    Resumo financeiro
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex items-center justify-between p-3 rounded-lg bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-800">
                    <div className="flex items-center gap-2 text-green-700">
                      <TrendingUp className="h-4 w-4" />
                      <span className="text-sm font-medium">Receitas pagas</span>
                    </div>
                    <span className="font-bold text-green-700">{brl(ov?.income ?? 0)}</span>
                  </div>
                  <div className="flex items-center justify-between p-3 rounded-lg bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800">
                    <div className="flex items-center gap-2 text-red-700">
                      <TrendingDown className="h-4 w-4" />
                      <span className="text-sm font-medium">Despesas pagas</span>
                    </div>
                    <span className="font-bold text-red-700">{brl(ov?.expense ?? 0)}</span>
                  </div>
                  <div className="h-px bg-border" />
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium">Saldo</span>
                    <span className={`font-bold text-lg ${(ov?.balance ?? 0) >= 0 ? "text-green-700" : "text-red-700"}`}>
                      {brl(ov?.balance ?? 0)}
                    </span>
                  </div>

                  {(ov?.income ?? 0) > 0 && (
                    <div className="space-y-1.5">
                      <p className="text-xs text-muted-foreground">Proporção receita/despesa</p>
                      <div className="h-3 w-full rounded-full bg-red-200 dark:bg-red-900/30 overflow-hidden">
                        <div className="h-full rounded-full bg-green-500 transition-all"
                          style={{ width: `${Math.min(((ov?.income ?? 0) / ((ov?.income ?? 0) + (ov?.expense ?? 0))) * 100, 100)}%` }} />
                      </div>
                      <div className="flex justify-between text-xs text-muted-foreground">
                        <span className="text-green-700">Receita {((ov?.income ?? 0) / ((ov?.income ?? 0) + (ov?.expense ?? 1)) * 100).toFixed(0)}%</span>
                        <span className="text-red-700">Despesa {((ov?.expense ?? 0) / ((ov?.income ?? 0) + (ov?.expense ?? 1)) * 100).toFixed(0)}%</span>
                      </div>
                    </div>
                  )}
                </CardContent>
              </Card>

              {/* Por forma de pagamento */}
              <Card>
                <CardHeader>
                  <CardTitle className="text-base flex items-center gap-2">
                    <BarChart3 className="h-4 w-4" />
                    Receitas por forma de pagamento
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  {data.byPaymentMethod.length === 0 ? (
                    <EmptyState icon={<DollarSign className="h-8 w-8" />} message="Sem receitas pagas no período" />
                  ) : (
                    <div className="space-y-4">
                      {data.byPaymentMethod.map((m) => {
                        const label = PAYMENT_LABELS[m.method] ?? m.method;
                        const pctNum = maxMethod > 0 ? (m.amount / maxMethod) * 100 : 0;
                        return (
                          <div key={m.method} className="space-y-1.5">
                            <div className="flex items-center justify-between text-sm">
                              <span className="font-medium">{label}</span>
                              <div className="flex items-center gap-3 text-muted-foreground">
                                <span>{brl(m.amount)}</span>
                                <Badge variant="secondary" className="text-xs font-normal">
                                  {pctNum.toFixed(0)}%
                                </Badge>
                              </div>
                            </div>
                            <ProgressBar value={m.amount} max={maxMethod} color="bg-primary" />
                          </div>
                        );
                      })}
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>
          </TabsContent>
        </Tabs>
      )}
    </div>
  );
}
