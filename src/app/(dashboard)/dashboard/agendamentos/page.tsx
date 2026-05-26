"use client";

import { useState, useEffect, useRef, useCallback } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { format } from "date-fns";
import { ptBR } from "date-fns/locale";
import { toast } from "sonner";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from "@/components/ui/table";
import {
  Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetFooter,
} from "@/components/ui/sheet";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import {
  AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle,
  AlertDialogDescription, AlertDialogFooter, AlertDialogAction, AlertDialogCancel,
} from "@/components/ui/alert-dialog";
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuSeparator, DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Calendar, Plus, MoreHorizontal, CheckCircle, XCircle, Clock,
  Search, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight,
  Pencil, Trash2, Eye, User, Briefcase, Scissors, Building2, AlertCircle,
} from "lucide-react";

// ─── Types ────────────────────────────────────────────────────────────────────

interface Appointment {
  id: number;
  date: string;
  startTime: string;
  endTime: string;
  status: string;
  price: number | string;
  notes: string | null;
  cancelReason: string | null;
  source: string;
  client: { id: number; name: string; phone: string | null; email: string | null };
  professional: { id: number; name: string; color: string | null };
  service: { id: number; name: string; duration: number; price: number | string; color: string | null };
  unit: { id: number; name: string };
}

interface AppointmentsResponse {
  data: Appointment[];
  total: number;
  page: number;
  perPage: number;
  totalPages: number;
}

interface Professional { id: number; name: string; color: string | null }
interface Service      { id: number; name: string; duration: number; price: number | string }
interface Unit         { id: number; name: string }
interface Client       { id: number; name: string; phone: string | null; email: string | null }

interface Slot { startTime: string; endTime: string }

interface AppForm {
  clientId: number | null;
  clientSearch: string;
  professionalId: number | null;
  serviceId: number | null;
  unitId: number | null;
  date: string;
  startTime: string;
  notes: string;
  status: string;
}

// ─── Constants ────────────────────────────────────────────────────────────────

const STATUS_CONFIG: Record<string, { label: string; className: string }> = {
  scheduled:            { label: "Agendado",          className: "bg-blue-500/10 text-blue-700 border-blue-500/20" },
  confirmed:            { label: "Confirmado",         className: "bg-green-500/10 text-green-700 border-green-500/20" },
  in_progress:          { label: "Em andamento",       className: "bg-purple-500/10 text-purple-700 border-purple-500/20" },
  completed:            { label: "Concluído",          className: "bg-emerald-500/10 text-emerald-700 border-emerald-500/20" },
  cancelled_by_client:  { label: "Cancelado (cliente)", className: "bg-red-500/10 text-red-700 border-red-500/20" },
  cancelled_by_business:{ label: "Cancelado",          className: "bg-red-500/10 text-red-700 border-red-500/20" },
  no_show:              { label: "Não compareceu",     className: "bg-orange-500/10 text-orange-700 border-orange-500/20" },
  rescheduled:          { label: "Reagendado",         className: "bg-gray-500/10 text-gray-600 border-gray-500/20" },
};

const STATUS_OPTIONS = [
  { value: "all",                   label: "Todos os status" },
  { value: "scheduled",             label: "Agendado" },
  { value: "confirmed",             label: "Confirmado" },
  { value: "in_progress",           label: "Em andamento" },
  { value: "completed",             label: "Concluído" },
  { value: "cancelled_by_client",   label: "Cancelado (cliente)" },
  { value: "cancelled_by_business", label: "Cancelado" },
  { value: "no_show",               label: "Não compareceu" },
];

const PER_PAGE = 20;

const emptyForm: AppForm = {
  clientId: null, clientSearch: "",
  professionalId: null, serviceId: null, unitId: null,
  date: format(new Date(), "yyyy-MM-dd"), startTime: "", notes: "", status: "scheduled",
};

function brl(v: number | string) {
  return Number(v).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

function getPageNumbers(current: number, total: number): (number | "…")[] {
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  const pages: (number | "…")[] = [1];
  if (current > 3) pages.push("…");
  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) pages.push(i);
  if (current < total - 2) pages.push("…");
  pages.push(total);
  return pages;
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function AgendamentosPage() {
  const queryClient = useQueryClient();

  // ── Filters ──────────────────────────────────────────────────────────────
  const [date, setDate] = useState(format(new Date(), "yyyy-MM-dd"));
  const [search, setSearch] = useState("");
  const [searchDebounced, setSearchDebounced] = useState("");
  const [filterStatus, setFilterStatus] = useState("all");
  const [filterProfessional, setFilterProfessional] = useState("all");
  const [page, setPage] = useState(1);

  // Debounce search
  useEffect(() => {
    const t = setTimeout(() => { setSearchDebounced(search); setPage(1); }, 350);
    return () => clearTimeout(t);
  }, [search]);

  // Reset page on filter change
  useEffect(() => { setPage(1); }, [date, filterStatus, filterProfessional]);

  // ── Sheets / dialogs ─────────────────────────────────────────────────────
  const [createOpen, setCreateOpen] = useState(false);
  const [editOpen, setEditOpen] = useState(false);
  const [detailOpen, setDetailOpen] = useState(false);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [selected, setSelected] = useState<Appointment | null>(null);

  // ── Forms ─────────────────────────────────────────────────────────────────
  const [form, setForm] = useState<AppForm>(emptyForm);
  const [clientResults, setClientResults] = useState<Client[]>([]);
  const [showClientDropdown, setShowClientDropdown] = useState(false);
  const [slots, setSlots] = useState<Slot[]>([]);
  const [loadingSlots, setLoadingSlots] = useState(false);
  const clientInputRef = useRef<HTMLInputElement>(null);

  // ─────────────────────────────────────────────────────────────────────────

  // Professionals, services, units for form selects
  const { data: profData } = useQuery<{ data: Professional[] }>({
    queryKey: ["professionals-list"],
    queryFn: () => fetch("/api/professionals?perPage=200").then(r => r.json()),
  });
  const { data: svcData } = useQuery<{ data: Service[] }>({
    queryKey: ["services-list"],
    queryFn: () => fetch("/api/services?perPage=200").then(r => r.json()),
  });
  const { data: unitData } = useQuery<{ data: Unit[] }>({
    queryKey: ["units-list"],
    queryFn: () => fetch("/api/units?perPage=200").then(r => r.json()),
  });

  const professionals = profData?.data ?? [];
  const services = svcData?.data ?? [];
  const units = unitData?.data ?? [];

  // Appointments list
  const params = new URLSearchParams({
    date,
    page: String(page),
    perPage: String(PER_PAGE),
  });
  if (searchDebounced) params.set("search", searchDebounced);
  if (filterStatus !== "all") params.set("status", filterStatus);
  if (filterProfessional !== "all") params.set("professionalId", filterProfessional);

  const { data, isLoading } = useQuery<AppointmentsResponse>({
    queryKey: ["appointments", date, searchDebounced, filterStatus, filterProfessional, page],
    queryFn: () => fetch(`/api/appointments?${params}`).then(r => r.json()),
  });

  const appointments = data?.data ?? [];
  const totalPages = data?.totalPages ?? 1;

  // ── Client autocomplete ──────────────────────────────────────────────────

  const searchClients = useCallback(async (q: string) => {
    if (q.trim().length < 2) { setClientResults([]); return; }
    const res = await fetch(`/api/clients?search=${encodeURIComponent(q)}&perPage=8`);
    const json = await res.json();
    setClientResults(json.data ?? []);
  }, []);

  useEffect(() => {
    const t = setTimeout(() => searchClients(form.clientSearch), 300);
    return () => clearTimeout(t);
  }, [form.clientSearch, searchClients]);

  // ── Available slots ──────────────────────────────────────────────────────

  const canLoadSlots = !!(form.professionalId && form.serviceId && form.unitId && form.date);

  useEffect(() => {
    if (!canLoadSlots) { setSlots([]); return; }
    setLoadingSlots(true);
    const p = new URLSearchParams({
      professionalId: String(form.professionalId),
      serviceId:      String(form.serviceId),
      unitId:         String(form.unitId),
      date:           form.date,
    });
    fetch(`/api/appointments/slots?${p}`)
      .then(r => r.json())
      .then(j => { setSlots(j.slots ?? []); })
      .catch(() => setSlots([]))
      .finally(() => setLoadingSlots(false));
  }, [form.professionalId, form.serviceId, form.unitId, form.date, canLoadSlots]);

  // ── Mutations ────────────────────────────────────────────────────────────

  const { mutate: createApt, isPending: creating } = useMutation({
    mutationFn: () =>
      fetch("/api/appointments", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          clientId:      form.clientId,
          professionalId: form.professionalId,
          serviceId:     form.serviceId,
          unitId:        form.unitId,
          date:          form.date,
          startTime:     form.startTime,
          notes:         form.notes || undefined,
          source:        "manual",
        }),
      }).then(async r => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao criar"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["appointments"] });
      toast.success("Agendamento criado!");
      setCreateOpen(false);
      setForm(emptyForm);
      setSlots([]);
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const { mutate: updateApt, isPending: updating } = useMutation({
    mutationFn: (body: Record<string, unknown>) =>
      fetch(`/api/appointments/${selected!.id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      }).then(async r => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao atualizar"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["appointments"] });
      toast.success("Agendamento atualizado!");
      setEditOpen(false);
      setDetailOpen(false);
      setSelected(null);
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const { mutate: deleteApt, isPending: deleting } = useMutation({
    mutationFn: (id: number) =>
      fetch(`/api/appointments/${id}`, { method: "DELETE" }).then(r => r.json()),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["appointments"] });
      toast.success("Agendamento excluído!");
      setDeleteId(null);
    },
    onError: () => toast.error("Erro ao excluir"),
  });

  // ── Handlers ─────────────────────────────────────────────────────────────

  function openCreate() {
    setForm(emptyForm);
    setSlots([]);
    setClientResults([]);
    setCreateOpen(true);
  }

  function openEdit(apt: Appointment) {
    setSelected(apt);
    setForm({
      clientId:       apt.client.id,
      clientSearch:   apt.client.name,
      professionalId: apt.professional.id,
      serviceId:      apt.service.id,
      unitId:         apt.unit.id,
      date:           apt.date.slice(0, 10),
      startTime:      apt.startTime,
      notes:          apt.notes ?? "",
      status:         apt.status,
    });
    setSlots([]);
    setEditOpen(true);
  }

  function handleCreateSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!form.clientId)      { toast.error("Selecione um cliente");       return; }
    if (!form.professionalId){ toast.error("Selecione um profissional");  return; }
    if (!form.serviceId)     { toast.error("Selecione um serviço");       return; }
    if (!form.unitId)        { toast.error("Selecione uma unidade");      return; }
    if (!form.date)          { toast.error("Informe a data");             return; }
    if (!form.startTime)     { toast.error("Selecione um horário");       return; }
    createApt();
  }

  function handleEditSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!selected) return;
    if (!form.clientId)      { toast.error("Selecione um cliente");       return; }
    if (!form.professionalId){ toast.error("Selecione um profissional");  return; }
    if (!form.serviceId)     { toast.error("Selecione um serviço");       return; }
    if (!form.unitId)        { toast.error("Selecione uma unidade");      return; }
    if (!form.startTime)     { toast.error("Selecione um horário");       return; }
    updateApt({
      clientId:       form.clientId,
      professionalId: form.professionalId,
      serviceId:      form.serviceId,
      unitId:         form.unitId,
      date:           form.date,
      startTime:      form.startTime,
      notes:          form.notes || null,
      status:         form.status,
    });
  }

  function handleStatusChange(apt: Appointment, status: string) {
    setSelected(apt);
    updateApt({ status });
  }

  // ─────────────────────────────────────────────────────────────────────────

  const statusCfg = (s: string) => STATUS_CONFIG[s] ?? { label: s, className: "bg-muted text-muted-foreground" };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Agendamentos</h1>
          <p className="text-muted-foreground text-sm">
            {format(new Date(date + "T12:00:00"), "EEEE, d 'de' MMMM 'de' yyyy", { locale: ptBR })}
          </p>
        </div>
        <Button onClick={openCreate}>
          <Plus className="h-4 w-4 mr-2" />
          Novo agendamento
        </Button>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3">
        <Input
          type="date"
          value={date}
          onChange={e => setDate(e.target.value)}
          className="w-40"
        />
        <div className="relative flex-1 min-w-48">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Buscar por cliente ou serviço..."
            value={search}
            onChange={e => setSearch(e.target.value)}
            className="pl-9"
          />
        </div>
        <Select
          value={filterStatus}
          onValueChange={v => v && setFilterStatus(v)}
          items={Object.fromEntries(STATUS_OPTIONS.map(o => [o.value, o.label]))}>
          <SelectTrigger className="w-52">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {STATUS_OPTIONS.map(o => (
              <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select
          value={filterProfessional}
          onValueChange={v => v && setFilterProfessional(v)}
          items={{ all: "Todos profissionais", ...Object.fromEntries(professionals.map(p => [String(p.id), p.name])) }}>
          <SelectTrigger className="w-48">
            <SelectValue placeholder="Profissional" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos profissionais</SelectItem>
            {professionals.map(p => (
              <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {/* Table */}
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="flex items-center gap-2 text-base">
            <Calendar className="h-4 w-4" />
            {isLoading ? "Carregando..." : `${data?.total ?? 0} agendamento${data?.total !== 1 ? "s" : ""}`}
          </CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          {isLoading ? (
            <div className="p-8 space-y-3">
              {[1,2,3,4,5].map(i => (
                <div key={i} className="h-12 bg-muted animate-pulse rounded" />
              ))}
            </div>
          ) : appointments.length === 0 ? (
            <div className="flex flex-col items-center gap-2 p-12 text-center">
              <AlertCircle className="h-8 w-8 text-muted-foreground/40" />
              <p className="text-muted-foreground">Nenhum agendamento encontrado</p>
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Horário</TableHead>
                  <TableHead>Cliente</TableHead>
                  <TableHead>Serviço</TableHead>
                  <TableHead>Profissional</TableHead>
                  <TableHead>Unidade</TableHead>
                  <TableHead>Valor</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="w-10" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {appointments.map(apt => {
                  const sc = statusCfg(apt.status);
                  return (
                    <TableRow key={apt.id} className="cursor-pointer" onClick={() => { setSelected(apt); setDetailOpen(true); }}>
                      <TableCell className="font-medium">
                        <div className="flex items-center gap-1 text-sm">
                          <Clock className="h-3 w-3 text-muted-foreground" />
                          {apt.startTime} – {apt.endTime}
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="font-medium text-sm">{apt.client.name}</div>
                        {apt.client.phone && <div className="text-xs text-muted-foreground">{apt.client.phone}</div>}
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          {apt.service.color && (
                            <div className="w-2 h-2 rounded-full shrink-0" style={{ backgroundColor: apt.service.color }} />
                          )}
                          <span className="text-sm">{apt.service.name}</span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <div className="w-2 h-2 rounded-full shrink-0"
                            style={{ backgroundColor: apt.professional.color ?? "#6366f1" }} />
                          <span className="text-sm">{apt.professional.name}</span>
                        </div>
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground">{apt.unit.name}</TableCell>
                      <TableCell className="font-medium text-sm">{brl(apt.price)}</TableCell>
                      <TableCell>
                        <Badge className={`${sc.className} text-xs`}>{sc.label}</Badge>
                      </TableCell>
                      <TableCell onClick={e => e.stopPropagation()}>
                        <DropdownMenu>
                          <DropdownMenuTrigger className="inline-flex items-center justify-center h-8 w-8 rounded-md hover:bg-accent outline-none">
                            <MoreHorizontal className="h-4 w-4" />
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={() => { setSelected(apt); setDetailOpen(true); }}>
                              <Eye className="mr-2 h-4 w-4" />Visualizar
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => openEdit(apt)}>
                              <Pencil className="mr-2 h-4 w-4" />Editar
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            {apt.status === "scheduled" && (
                              <DropdownMenuItem onClick={() => handleStatusChange(apt, "confirmed")}>
                                <CheckCircle className="mr-2 h-4 w-4 text-green-600" />Confirmar
                              </DropdownMenuItem>
                            )}
                            {(apt.status === "scheduled" || apt.status === "confirmed") && (
                              <DropdownMenuItem onClick={() => handleStatusChange(apt, "in_progress")}>
                                <Clock className="mr-2 h-4 w-4 text-purple-600" />Iniciar atendimento
                              </DropdownMenuItem>
                            )}
                            {apt.status === "in_progress" && (
                              <DropdownMenuItem onClick={() => handleStatusChange(apt, "completed")}>
                                <CheckCircle className="mr-2 h-4 w-4 text-emerald-600" />Concluir
                              </DropdownMenuItem>
                            )}
                            {!["completed","cancelled_by_client","cancelled_by_business","no_show"].includes(apt.status) && (
                              <>
                                <DropdownMenuItem onClick={() => handleStatusChange(apt, "no_show")}>
                                  <XCircle className="mr-2 h-4 w-4 text-orange-600" />Não compareceu
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                  onClick={() => handleStatusChange(apt, "cancelled_by_business")}
                                  className="text-destructive">
                                  <XCircle className="mr-2 h-4 w-4" />Cancelar
                                </DropdownMenuItem>
                              </>
                            )}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                              onClick={() => setDeleteId(apt.id)}
                              className="text-destructive">
                              <Trash2 className="mr-2 h-4 w-4" />Excluir
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between">
          <p className="text-sm text-muted-foreground">
            Página {page} de {totalPages}
          </p>
          <div className="flex items-center gap-1">
            <Button variant="outline" size="icon-sm" onClick={() => setPage(1)} disabled={page === 1}>
              <ChevronsLeft className="h-4 w-4" />
            </Button>
            <Button variant="outline" size="icon-sm" onClick={() => setPage(p => p - 1)} disabled={page === 1}>
              <ChevronLeft className="h-4 w-4" />
            </Button>
            {getPageNumbers(page, totalPages).map((p, i) =>
              p === "…"
                ? <span key={i} className="px-2 text-muted-foreground">…</span>
                : <Button key={p} variant={page === p ? "default" : "outline"} size="icon-sm"
                    onClick={() => setPage(p as number)}>{p}</Button>
            )}
            <Button variant="outline" size="icon-sm" onClick={() => setPage(p => p + 1)} disabled={page === totalPages}>
              <ChevronRight className="h-4 w-4" />
            </Button>
            <Button variant="outline" size="icon-sm" onClick={() => setPage(totalPages)} disabled={page === totalPages}>
              <ChevronsRight className="h-4 w-4" />
            </Button>
          </div>
        </div>
      )}

      {/* ── Create Sheet ──────────────────────────────────────────────────── */}
      <Sheet open={createOpen} onOpenChange={open => { if (!open) { setCreateOpen(false); setForm(emptyForm); setSlots([]); } }}>
        <SheetContent className="sm:max-w-lg overflow-y-auto">
          <SheetHeader>
            <SheetTitle>Novo agendamento</SheetTitle>
            <SheetDescription>Preencha os dados para criar um agendamento.</SheetDescription>
          </SheetHeader>
          <form onSubmit={handleCreateSubmit} className="px-4 space-y-4 flex-1">
            <AppointmentFormFields
              form={form} setForm={setForm}
              professionals={professionals} services={services} units={units}
              slots={slots} loadingSlots={loadingSlots}
              clientResults={clientResults} setClientResults={setClientResults}
              showClientDropdown={showClientDropdown} setShowClientDropdown={setShowClientDropdown}
              clientInputRef={clientInputRef}
            />
          </form>
          <SheetFooter className="px-4">
            <Button variant="outline" type="button" onClick={() => setCreateOpen(false)} disabled={creating}>
              Cancelar
            </Button>
            <Button onClick={handleCreateSubmit} disabled={creating}>
              {creating ? "Criando..." : "Criar agendamento"}
            </Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>

      {/* ── Edit Sheet ────────────────────────────────────────────────────── */}
      <Sheet open={editOpen} onOpenChange={open => { if (!open) { setEditOpen(false); setSlots([]); } }}>
        <SheetContent className="sm:max-w-lg overflow-y-auto">
          <SheetHeader>
            <SheetTitle>Editar agendamento</SheetTitle>
            <SheetDescription>Altere os dados do agendamento.</SheetDescription>
          </SheetHeader>
          <form onSubmit={handleEditSubmit} className="px-4 space-y-4 flex-1">
            <AppointmentFormFields
              form={form} setForm={setForm}
              professionals={professionals} services={services} units={units}
              slots={slots} loadingSlots={loadingSlots}
              clientResults={clientResults} setClientResults={setClientResults}
              showClientDropdown={showClientDropdown} setShowClientDropdown={setShowClientDropdown}
              clientInputRef={clientInputRef}
              editMode
            />
          </form>
          <SheetFooter className="px-4">
            <Button variant="outline" type="button" onClick={() => setEditOpen(false)} disabled={updating}>
              Cancelar
            </Button>
            <Button onClick={handleEditSubmit} disabled={updating}>
              {updating ? "Salvando..." : "Salvar alterações"}
            </Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>

      {/* ── Detail Sheet ──────────────────────────────────────────────────── */}
      <Sheet open={detailOpen} onOpenChange={setDetailOpen}>
        <SheetContent className="sm:max-w-md overflow-y-auto">
          {selected && (
            <>
              <SheetHeader>
                <SheetTitle>Detalhes do agendamento</SheetTitle>
                <SheetDescription>
                  {format(new Date(selected.date.slice(0, 10) + "T12:00:00"), "EEEE, d 'de' MMMM 'de' yyyy", { locale: ptBR })}
                  {" · "}{selected.startTime} – {selected.endTime}
                </SheetDescription>
              </SheetHeader>
              <div className="px-4 space-y-5 flex-1">
                {/* Status badge */}
                <div className="flex items-center gap-2">
                  <Badge className={`${statusCfg(selected.status).className} text-sm px-3 py-1`}>
                    {statusCfg(selected.status).label}
                  </Badge>
                  {selected.source !== "manual" && (
                    <Badge variant="outline" className="text-xs capitalize">{selected.source}</Badge>
                  )}
                </div>

                <Separator />

                {/* Info grid */}
                <div className="space-y-3 text-sm">
                  <div className="flex items-start gap-3">
                    <User className="h-4 w-4 text-muted-foreground mt-0.5 shrink-0" />
                    <div>
                      <p className="font-medium">{selected.client.name}</p>
                      {selected.client.phone && <p className="text-muted-foreground">{selected.client.phone}</p>}
                      {selected.client.email && <p className="text-muted-foreground">{selected.client.email}</p>}
                    </div>
                  </div>
                  <div className="flex items-start gap-3">
                    <Scissors className="h-4 w-4 text-muted-foreground mt-0.5 shrink-0" />
                    <div>
                      <p className="font-medium">{selected.service.name}</p>
                      <p className="text-muted-foreground">{selected.service.duration} min · {brl(selected.service.price)}</p>
                    </div>
                  </div>
                  <div className="flex items-center gap-3">
                    <Briefcase className="h-4 w-4 text-muted-foreground shrink-0" />
                    <div className="flex items-center gap-2">
                      <div className="w-3 h-3 rounded-full" style={{ backgroundColor: selected.professional.color ?? "#6366f1" }} />
                      <span className="font-medium">{selected.professional.name}</span>
                    </div>
                  </div>
                  <div className="flex items-center gap-3">
                    <Building2 className="h-4 w-4 text-muted-foreground shrink-0" />
                    <span>{selected.unit.name}</span>
                  </div>
                  <div className="flex items-center gap-3">
                    <span className="text-muted-foreground w-4 shrink-0 text-center font-bold">R$</span>
                    <span className="font-semibold">{brl(selected.price)}</span>
                  </div>
                </div>

                {selected.notes && (
                  <>
                    <Separator />
                    <div>
                      <p className="text-xs text-muted-foreground mb-1">Observações</p>
                      <p className="text-sm">{selected.notes}</p>
                    </div>
                  </>
                )}

                {selected.cancelReason && (
                  <>
                    <Separator />
                    <div>
                      <p className="text-xs text-muted-foreground mb-1">Motivo do cancelamento</p>
                      <p className="text-sm text-destructive">{selected.cancelReason}</p>
                    </div>
                  </>
                )}

                <Separator />

                {/* Status actions */}
                <div className="space-y-2">
                  <p className="text-xs text-muted-foreground font-medium uppercase tracking-wide">Ações</p>
                  <div className="flex flex-wrap gap-2">
                    {selected.status === "scheduled" && (
                      <Button size="sm" variant="outline" className="text-green-700 border-green-500/30 hover:bg-green-50"
                        onClick={() => updateApt({ status: "confirmed" })}>
                        <CheckCircle className="h-3.5 w-3.5 mr-1.5" />Confirmar
                      </Button>
                    )}
                    {(selected.status === "scheduled" || selected.status === "confirmed") && (
                      <Button size="sm" variant="outline" className="text-purple-700 border-purple-500/30 hover:bg-purple-50"
                        onClick={() => updateApt({ status: "in_progress" })}>
                        <Clock className="h-3.5 w-3.5 mr-1.5" />Iniciar
                      </Button>
                    )}
                    {selected.status === "in_progress" && (
                      <Button size="sm" variant="outline" className="text-emerald-700 border-emerald-500/30 hover:bg-emerald-50"
                        onClick={() => updateApt({ status: "completed" })}>
                        <CheckCircle className="h-3.5 w-3.5 mr-1.5" />Concluir
                      </Button>
                    )}
                    {!["completed","cancelled_by_client","cancelled_by_business","no_show","rescheduled"].includes(selected.status) && (
                      <>
                        <Button size="sm" variant="outline" className="text-orange-700 border-orange-500/30 hover:bg-orange-50"
                          onClick={() => updateApt({ status: "no_show" })}>
                          <XCircle className="h-3.5 w-3.5 mr-1.5" />Não compareceu
                        </Button>
                        <Button size="sm" variant="outline" className="text-destructive border-destructive/30 hover:bg-red-50"
                          onClick={() => updateApt({ status: "cancelled_by_business" })}>
                          <XCircle className="h-3.5 w-3.5 mr-1.5" />Cancelar
                        </Button>
                      </>
                    )}
                    <Button size="sm" variant="outline" onClick={() => { setDetailOpen(false); openEdit(selected); }}>
                      <Pencil className="h-3.5 w-3.5 mr-1.5" />Editar
                    </Button>
                    <Button size="sm" variant="outline" className="text-destructive"
                      onClick={() => { setDetailOpen(false); setDeleteId(selected.id); }}>
                      <Trash2 className="h-3.5 w-3.5 mr-1.5" />Excluir
                    </Button>
                  </div>
                </div>
              </div>
            </>
          )}
        </SheetContent>
      </Sheet>

      {/* ── Delete Dialog ─────────────────────────────────────────────────── */}
      <AlertDialog open={deleteId !== null} onOpenChange={open => { if (!open) setDeleteId(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir agendamento</AlertDialogTitle>
            <AlertDialogDescription>
              Esta ação não pode ser desfeita. O agendamento será removido permanentemente.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => deleteId && deleteApt(deleteId)}
              disabled={deleting}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
              {deleting ? "Excluindo..." : "Excluir"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

// ─── Form Fields Component ────────────────────────────────────────────────────

interface FormFieldsProps {
  form: AppForm;
  setForm: React.Dispatch<React.SetStateAction<AppForm>>;
  professionals: Professional[];
  services: Service[];
  units: Unit[];
  slots: Slot[];
  loadingSlots: boolean;
  clientResults: Client[];
  setClientResults: React.Dispatch<React.SetStateAction<Client[]>>;
  showClientDropdown: boolean;
  setShowClientDropdown: React.Dispatch<React.SetStateAction<boolean>>;
  clientInputRef: React.RefObject<HTMLInputElement | null>;
  editMode?: boolean;
}

function AppointmentFormFields({
  form, setForm, professionals, services, units, slots, loadingSlots,
  clientResults, setClientResults, showClientDropdown, setShowClientDropdown,
  clientInputRef, editMode,
}: FormFieldsProps) {
  const canLoadSlots = !!(form.professionalId && form.serviceId && form.unitId && form.date);

  return (
    <div className="space-y-4 py-2">
      {/* Client */}
      <div className="space-y-1.5">
        <Label>Cliente <span className="text-destructive">*</span></Label>
        <div className="relative">
          <User className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            ref={clientInputRef}
            className="pl-9"
            placeholder="Buscar cliente..."
            value={form.clientSearch}
            onChange={e => {
              setForm(f => ({ ...f, clientSearch: e.target.value, clientId: null }));
              setShowClientDropdown(true);
            }}
            onFocus={() => setShowClientDropdown(true)}
            onBlur={() => setTimeout(() => setShowClientDropdown(false), 150)}
          />
          {showClientDropdown && clientResults.length > 0 && (
            <div className="absolute z-50 top-full left-0 right-0 mt-1 bg-popover border rounded-lg shadow-md overflow-hidden">
              {clientResults.map(c => (
                <button
                  key={c.id}
                  type="button"
                  className="w-full text-left px-3 py-2 hover:bg-accent transition-colors text-sm"
                  onMouseDown={e => {
                    e.preventDefault();
                    setForm(f => ({ ...f, clientId: c.id, clientSearch: c.name }));
                    setClientResults([]);
                    setShowClientDropdown(false);
                  }}>
                  <div className="font-medium">{c.name}</div>
                  {c.phone && <div className="text-xs text-muted-foreground">{c.phone}</div>}
                </button>
              ))}
            </div>
          )}
        </div>
        {form.clientId && (
          <p className="text-xs text-green-600 flex items-center gap-1">
            <CheckCircle className="h-3 w-3" />Cliente selecionado
          </p>
        )}
      </div>

      {/* Professional + Service */}
      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label>Profissional <span className="text-destructive">*</span></Label>
          <Select
            value={form.professionalId ? String(form.professionalId) : ""}
            onValueChange={v => setForm(f => ({ ...f, professionalId: Number(v), startTime: "" }))}
            items={Object.fromEntries(professionals.map(p => [String(p.id), p.name]))}>
            <SelectTrigger className="w-full"><SelectValue placeholder="Selecione" /></SelectTrigger>
            <SelectContent>
              {professionals.map(p => (
                <SelectItem key={p.id} value={String(p.id)} label={p.name}>
                  <div className="w-2 h-2 rounded-full shrink-0" style={{ backgroundColor: p.color ?? "#6366f1" }} />
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label>Serviço <span className="text-destructive">*</span></Label>
          <Select
            value={form.serviceId ? String(form.serviceId) : ""}
            onValueChange={v => setForm(f => ({ ...f, serviceId: Number(v), startTime: "" }))}
            items={Object.fromEntries(services.map(s => [String(s.id), s.duration ? `${s.name} (${s.duration}min)` : s.name]))}>
            <SelectTrigger className="w-full"><SelectValue placeholder="Selecione" /></SelectTrigger>
            <SelectContent>
              {services.map(s => (
                <SelectItem key={s.id} value={String(s.id)}>
                  {s.name}{s.duration ? ` (${s.duration}min)` : ""}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Unit + Date */}
      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label>Unidade <span className="text-destructive">*</span></Label>
          <Select
            value={form.unitId ? String(form.unitId) : ""}
            onValueChange={v => setForm(f => ({ ...f, unitId: Number(v), startTime: "" }))}
            items={Object.fromEntries(units.map(u => [String(u.id), u.name]))}>
            <SelectTrigger className="w-full"><SelectValue placeholder="Selecione" /></SelectTrigger>
            <SelectContent>
              {units.map(u => (
                <SelectItem key={u.id} value={String(u.id)}>{u.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label>Data <span className="text-destructive">*</span></Label>
          <Input
            type="date"
            value={form.date}
            onChange={e => setForm(f => ({ ...f, date: e.target.value, startTime: "" }))}
          />
        </div>
      </div>

      {/* Time slots */}
      <div className="space-y-1.5">
        <Label>Horário <span className="text-destructive">*</span></Label>
        {!canLoadSlots ? (
          <p className="text-xs text-muted-foreground py-2">
            Selecione profissional, serviço, unidade e data para ver os horários disponíveis.
          </p>
        ) : loadingSlots ? (
          <div className="grid grid-cols-4 gap-2">
            {[1,2,3,4,5,6,7,8].map(i => (
              <div key={i} className="h-9 bg-muted animate-pulse rounded" />
            ))}
          </div>
        ) : slots.length === 0 ? (
          <p className="text-xs text-muted-foreground py-2 text-center border rounded-lg p-4">
            Nenhum horário disponível para esta data.
          </p>
        ) : (
          <div className="grid grid-cols-4 gap-2 max-h-40 overflow-y-auto pr-1">
            {slots.map(s => (
              <button
                key={s.startTime}
                type="button"
                onClick={() => setForm(f => ({ ...f, startTime: s.startTime }))}
                className={`text-xs py-2 px-1 rounded-md border font-medium transition-colors
                  ${form.startTime === s.startTime
                    ? "bg-primary text-primary-foreground border-primary"
                    : "hover:bg-accent border-border"
                  }`}>
                {s.startTime}
              </button>
            ))}
          </div>
        )}
        {editMode && form.startTime && !slots.find(s => s.startTime === form.startTime) && slots.length > 0 && (
          <p className="text-xs text-amber-600">
            Horário atual ({form.startTime}) não está nos slots — pode ter conflito.
          </p>
        )}
      </div>

      {/* Status — edit mode only */}
      {editMode && (
        <div className="space-y-1.5">
          <Label>Status</Label>
          <Select
            value={form.status}
            onValueChange={v => v && setForm(f => ({ ...f, status: v }))}
            items={{
              scheduled: "Agendado",
              confirmed: "Confirmado",
              in_progress: "Em andamento",
              completed: "Concluído",
              no_show: "Não compareceu",
              cancelled_by_client: "Cancelado (cliente)",
              cancelled_by_business: "Cancelado (empresa)",
              rescheduled: "Reagendado",
            }}>
            <SelectTrigger className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="scheduled">Agendado</SelectItem>
              <SelectItem value="confirmed">Confirmado</SelectItem>
              <SelectItem value="in_progress">Em andamento</SelectItem>
              <SelectItem value="completed">Concluído</SelectItem>
              <SelectItem value="no_show">Não compareceu</SelectItem>
              <SelectItem value="cancelled_by_client">Cancelado (cliente)</SelectItem>
              <SelectItem value="cancelled_by_business">Cancelado (empresa)</SelectItem>
              <SelectItem value="rescheduled">Reagendado</SelectItem>
            </SelectContent>
          </Select>
        </div>
      )}

      {/* Notes */}
      <div className="space-y-1.5">
        <Label>Observações</Label>
        <Textarea
          placeholder="Alguma observação sobre o agendamento..."
          value={form.notes}
          onChange={e => setForm(f => ({ ...f, notes: e.target.value }))}
          rows={3}
        />
      </div>
    </div>
  );
}
