"use client";

import { useState, useRef, useEffect } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { format } from "date-fns";
import { ptBR } from "date-fns/locale";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import {
  Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetFooter,
} from "@/components/ui/sheet";
import {
  ChevronLeft, ChevronRight, Plus, CalendarDays, Clock, User, Scissors,
  Building2, Phone, CheckCircle2, XCircle, AlertCircle, PlayCircle,
  LayoutGrid, StretchHorizontal,
} from "lucide-react";

// ─── Types ────────────────────────────────────────────────────────────────────

interface Appointment {
  id: number;
  date: string;
  startTime: string;
  endTime: string;
  status: string;
  price: string | number;
  notes: string | null;
  client: { id: number; name: string; phone: string | null };
  professional: { id: number; name: string; color: string | null };
  service: { id: number; name: string; duration: number; color: string | null };
  unit: { id: number; name: string };
}

interface Professional { id: number; name: string; color: string | null; }
interface Service { id: number; name: string; duration: number; price: string | number; }
interface Unit { id: number; name: string; }
interface Client { id: number; name: string; phone: string | null; }

interface NewAptForm {
  professionalId: string;
  serviceId: string;
  unitId: string;
  date: string;
  startTime: string;
  notes: string;
}

// ─── Constants ────────────────────────────────────────────────────────────────

const HOUR_HEIGHT = 64;
const DAY_START = 7;
const DAY_END = 21;
const HOURS = Array.from({ length: DAY_END - DAY_START }, (_, i) => DAY_START + i);
const TOTAL_HEIGHT = (DAY_END - DAY_START) * HOUR_HEIGHT;
const WEEK_DAYS_SHORT = ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"];
const MONTH_DAYS_SHORT = ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"];

const STATUS_CONFIG: Record<string, { label: string; badge: string }> = {
  scheduled:             { label: "Agendado",           badge: "bg-blue-500/10 text-blue-700 border-blue-500/20" },
  confirmed:             { label: "Confirmado",          badge: "bg-indigo-500/10 text-indigo-700 border-indigo-500/20" },
  in_progress:           { label: "Em andamento",        badge: "bg-amber-500/10 text-amber-700 border-amber-500/20" },
  completed:             { label: "Concluído",            badge: "bg-green-500/10 text-green-700 border-green-500/20" },
  cancelled_by_client:   { label: "Cancelado (cliente)", badge: "bg-red-500/10 text-red-500 border-red-500/20" },
  cancelled_by_business: { label: "Cancelado",           badge: "bg-red-500/10 text-red-700 border-red-500/20" },
  no_show:               { label: "Não compareceu",      badge: "bg-orange-500/10 text-orange-700 border-orange-500/20" },
};

const emptyNewForm: NewAptForm = {
  professionalId: "", serviceId: "", unitId: "",
  date: new Date().toISOString().split("T")[0], startTime: "", notes: "",
};

// ─── Helpers ─────────────────────────────────────────────────────────────────

function timeToMinutes(t: string): number {
  const [h, m] = t.split(":").map(Number);
  return h * 60 + m;
}

function aptTop(startTime: string): number {
  return ((timeToMinutes(startTime) - DAY_START * 60) / 60) * HOUR_HEIGHT;
}

function aptHeight(startTime: string, endTime: string): number {
  const dur = timeToMinutes(endTime) - timeToMinutes(startTime);
  return Math.max((dur / 60) * HOUR_HEIGHT, 24);
}

function getWeekStart(date: Date): Date {
  const d = new Date(date);
  d.setHours(0, 0, 0, 0);
  const day = d.getDay();
  d.setDate(d.getDate() - (day === 0 ? 6 : day - 1));
  return d;
}

function addDaysTo(date: Date, n: number): Date {
  const d = new Date(date);
  d.setDate(d.getDate() + n);
  return d;
}

function dateToIso(d: Date): string {
  return d.toISOString().split("T")[0];
}

function brl(value: number | string) {
  return Number(value).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

function isToday(d: Date): boolean {
  const today = new Date();
  return d.getFullYear() === today.getFullYear() &&
    d.getMonth() === today.getMonth() &&
    d.getDate() === today.getDate();
}

function isSameDay(a: Date, b: Date): boolean {
  return a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate();
}

interface PositionedApt { apt: Appointment; colIndex: number; colCount: number; }

function resolveOverlaps(apts: Appointment[]): PositionedApt[] {
  const sorted = [...apts].sort((a, b) => timeToMinutes(a.startTime) - timeToMinutes(b.startTime));
  const colEnds: number[] = [];
  const assigned: PositionedApt[] = [];

  for (const apt of sorted) {
    const startMin = timeToMinutes(apt.startTime);
    let col = -1;
    for (let i = 0; i < colEnds.length; i++) {
      if (colEnds[i] <= startMin) { col = i; colEnds[i] = timeToMinutes(apt.endTime); break; }
    }
    if (col === -1) { col = colEnds.length; colEnds.push(timeToMinutes(apt.endTime)); }
    assigned.push({ apt, colIndex: col, colCount: 0 });
  }

  const totalCols = colEnds.length;
  return assigned.map(a => ({ apt: a.apt, colIndex: a.colIndex, colCount: totalCols }));
}

function getMonthCells(year: number, month: number): (Date | null)[] {
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  const startOffset = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;
  const cells: (Date | null)[] = [];
  for (let i = 0; i < startOffset; i++) cells.push(null);
  for (let d = 1; d <= lastDay.getDate(); d++) cells.push(new Date(year, month, d));
  while (cells.length % 7 !== 0) cells.push(null);
  return cells;
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function CalendarioPage() {
  const [viewMode, setViewMode] = useState<"week" | "month">("week");
  const [weekStart, setWeekStart] = useState(() => getWeekStart(new Date()));
  const [monthDate, setMonthDate] = useState(() => new Date());
  const [profFilter, setProfFilter] = useState("all");
  const [selectedAptId, setSelectedAptId] = useState<number | null>(null);
  const [newAptOpen, setNewAptOpen] = useState(false);
  const [newAptForm, setNewAptForm] = useState<NewAptForm>(emptyNewForm);
  const [clientSearch, setClientSearch] = useState("");
  const [clientSearchDb, setClientSearchDb] = useState("");
  const [selectedClient, setSelectedClient] = useState<Client | null>(null);
  const [showClientDropdown, setShowClientDropdown] = useState(false);
  const clientTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const queryClient = useQueryClient();

  // ── Date ranges ──────────────────────────────────────────────────────────

  const weekEnd = addDaysTo(weekStart, 6);
  const weekDays = Array.from({ length: 7 }, (_, i) => addDaysTo(weekStart, i));

  const monthYear = monthDate.getFullYear();
  const monthMonth = monthDate.getMonth();
  const monthFrom = dateToIso(new Date(monthYear, monthMonth, 1));
  const monthTo = dateToIso(new Date(monthYear, monthMonth + 1, 0));

  const from = viewMode === "week" ? dateToIso(weekStart) : monthFrom;
  const to = viewMode === "week" ? dateToIso(weekEnd) : monthTo;

  // ── Queries ──────────────────────────────────────────────────────────────

  const { data: aptsData } = useQuery({
    queryKey: ["calendar-apts", from, to, profFilter],
    queryFn: () => {
      const p = new URLSearchParams({ from, to, perPage: "300" });
      if (profFilter !== "all") p.set("professionalId", profFilter);
      return fetch(`/api/appointments?${p}`).then(r => r.json());
    },
  });
  const appointments: Appointment[] = aptsData?.data ?? [];

  const { data: profsData } = useQuery({
    queryKey: ["profs-cal"],
    queryFn: () => fetch("/api/professionals?perPage=100").then(r => r.json()),
  });
  const professionals: Professional[] = profsData?.data ?? [];

  const { data: servicesData } = useQuery({
    queryKey: ["services-cal"],
    queryFn: () => fetch("/api/services?perPage=100").then(r => r.json()),
  });
  const services: Service[] = servicesData?.data ?? [];

  const { data: unitsData } = useQuery({
    queryKey: ["units-cal"],
    queryFn: () => fetch("/api/units?perPage=100").then(r => r.json()),
  });
  const units: Unit[] = unitsData?.data ?? [];

  const { data: selectedApt, isLoading: loadingApt } = useQuery<Appointment>({
    queryKey: ["apt-detail", selectedAptId],
    queryFn: () => fetch(`/api/appointments/${selectedAptId}`).then(r => r.json()),
    enabled: selectedAptId !== null,
  });

  const { data: clientResults } = useQuery({
    queryKey: ["client-cal-search", clientSearchDb],
    queryFn: () =>
      fetch(`/api/clients?search=${encodeURIComponent(clientSearchDb)}&perPage=8`).then(r => r.json()),
    enabled: clientSearchDb.length >= 2,
  });
  const clientList: Client[] = clientResults?.data ?? [];

  const slotsEnabled = !!(newAptForm.professionalId && newAptForm.serviceId && newAptForm.unitId && newAptForm.date);
  const { data: slotsData } = useQuery({
    queryKey: ["slots-cal", newAptForm.professionalId, newAptForm.serviceId, newAptForm.unitId, newAptForm.date],
    queryFn: () => {
      const p = new URLSearchParams({
        professionalId: newAptForm.professionalId,
        serviceId: newAptForm.serviceId,
        unitId: newAptForm.unitId,
        date: newAptForm.date,
      });
      return fetch(`/api/appointments/slots?${p}`).then(r => r.json());
    },
    enabled: slotsEnabled,
  });
  const slots: string[] = slotsData?.slots ?? [];

  // ── Mutations ────────────────────────────────────────────────────────────

  const { mutate: updateStatus, isPending: updatingStatus } = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) =>
      fetch(`/api/appointments/${id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status }),
      }).then(r => r.json()),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["calendar-apts"] });
      queryClient.invalidateQueries({ queryKey: ["apt-detail", selectedAptId] });
      toast.success("Status atualizado!");
    },
    onError: () => toast.error("Erro ao atualizar status"),
  });

  const { mutate: createApt, isPending: creating } = useMutation({
    mutationFn: () =>
      fetch("/api/appointments", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          clientId: selectedClient!.id,
          professionalId: parseInt(newAptForm.professionalId),
          serviceId: parseInt(newAptForm.serviceId),
          unitId: parseInt(newAptForm.unitId),
          date: newAptForm.date,
          startTime: newAptForm.startTime,
          notes: newAptForm.notes || undefined,
          source: "manual",
        }),
      }).then(async r => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao criar agendamento"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["calendar-apts"] });
      setNewAptOpen(false);
      setNewAptForm(emptyNewForm);
      setSelectedClient(null);
      setClientSearch("");
      toast.success("Agendamento criado com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  // ── Helpers ──────────────────────────────────────────────────────────────

  function aptsForDay(date: Date): Appointment[] {
    const iso = dateToIso(date);
    return appointments.filter(a => a.date.startsWith(iso));
  }

  function handleClientSearch(value: string) {
    setClientSearch(value);
    setShowClientDropdown(true);
    if (clientTimer.current) clearTimeout(clientTimer.current);
    clientTimer.current = setTimeout(() => setClientSearchDb(value), 300);
  }

  function handleOpenNew(date?: Date, time?: string) {
    setNewAptForm({
      ...emptyNewForm,
      date: date ? dateToIso(date) : new Date().toISOString().split("T")[0],
      startTime: time ?? "",
    });
    setSelectedClient(null);
    setClientSearch("");
    setNewAptOpen(true);
  }

  function handleCreateSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!selectedClient) { toast.error("Selecione um cliente"); return; }
    if (!newAptForm.professionalId) { toast.error("Selecione um profissional"); return; }
    if (!newAptForm.serviceId) { toast.error("Selecione um serviço"); return; }
    if (!newAptForm.unitId) { toast.error("Selecione uma unidade"); return; }
    if (!newAptForm.startTime) { toast.error("Selecione um horário"); return; }
    createApt();
  }

  // ── Week navigation ──────────────────────────────────────────────────────

  function prevWeek() { setWeekStart(addDaysTo(weekStart, -7)); }
  function nextWeek() { setWeekStart(addDaysTo(weekStart, 7)); }
  function goToday() {
    if (viewMode === "week") setWeekStart(getWeekStart(new Date()));
    else setMonthDate(new Date());
  }

  function prevMonth() { setMonthDate(d => new Date(d.getFullYear(), d.getMonth() - 1, 1)); }
  function nextMonth() { setMonthDate(d => new Date(d.getFullYear(), d.getMonth() + 1, 1)); }

  // ── Current time indicator ───────────────────────────────────────────────
  const [nowMinutes, setNowMinutes] = useState(() => {
    const n = new Date();
    return n.getHours() * 60 + n.getMinutes();
  });
  useEffect(() => {
    const interval = setInterval(() => {
      const n = new Date();
      setNowMinutes(n.getHours() * 60 + n.getMinutes());
    }, 60_000);
    return () => clearInterval(interval);
  }, []);
  const nowTop = ((nowMinutes - DAY_START * 60) / 60) * HOUR_HEIGHT;
  const showNow = nowMinutes >= DAY_START * 60 && nowMinutes <= DAY_END * 60;

  // ─────────────────────────────────────────────────────────────────────────

  const weekRangeLabel = (() => {
    const startFmt = format(weekStart, "d 'de' MMM", { locale: ptBR });
    const endFmt = format(weekEnd, "d 'de' MMM 'de' yyyy", { locale: ptBR });
    return `${startFmt} – ${endFmt}`;
  })();

  const monthLabel = format(monthDate, "MMMM 'de' yyyy", { locale: ptBR });
  const monthCells = getMonthCells(monthYear, monthMonth);

  return (
    <>
      <div className="flex flex-col h-[calc(100vh-4rem)] overflow-hidden">
        {/* ── Header ─────────────────────────────────────────────────────── */}
        <div className="flex flex-wrap items-center gap-3 px-1 pb-4 shrink-0">
          <div className="flex-1">
            <h1 className="text-2xl font-bold">Calendário</h1>
          </div>

          {/* Navigation */}
          <div className="flex items-center gap-1">
            <Button variant="outline" size="sm" className="h-8 w-8 p-0"
              onClick={viewMode === "week" ? prevWeek : prevMonth}>
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <Button variant="outline" size="sm" className="h-8 px-3 text-sm min-w-[200px] text-center" onClick={goToday}>
              {viewMode === "week" ? weekRangeLabel : <span className="capitalize">{monthLabel}</span>}
            </Button>
            <Button variant="outline" size="sm" className="h-8 w-8 p-0"
              onClick={viewMode === "week" ? nextWeek : nextMonth}>
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>

          {/* Hoje */}
          <Button variant="outline" size="sm" className="h-8 px-3 text-sm" onClick={goToday}>
            Hoje
          </Button>

          {/* View toggle */}
          <div className="flex items-center rounded-md border overflow-hidden">
            <button
              onClick={() => setViewMode("week")}
              className={`flex items-center gap-1.5 px-3 h-8 text-sm transition-colors ${viewMode === "week" ? "bg-primary text-primary-foreground" : "hover:bg-accent"}`}>
              <StretchHorizontal className="h-3.5 w-3.5" />Semana
            </button>
            <button
              onClick={() => setViewMode("month")}
              className={`flex items-center gap-1.5 px-3 h-8 text-sm transition-colors border-l ${viewMode === "month" ? "bg-primary text-primary-foreground" : "hover:bg-accent"}`}>
              <LayoutGrid className="h-3.5 w-3.5" />Mês
            </button>
          </div>

          {/* Professional filter */}
          <Select
            value={profFilter}
            onValueChange={v => setProfFilter(v ?? "all")}
            items={{ all: "Todos profissionais", ...Object.fromEntries(professionals.map(p => [String(p.id), p.name])) }}>
            <SelectTrigger className="h-8 w-[160px] text-sm"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todos profissionais</SelectItem>
              {professionals.map(p => (
                <SelectItem key={p.id} value={String(p.id)} label={p.name}>
                  <div className="w-2 h-2 rounded-full shrink-0" style={{ backgroundColor: p.color ?? "#6366f1" }} />
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Button size="sm" className="h-8" onClick={() => handleOpenNew()}>
            <Plus className="h-4 w-4 mr-1.5" />Novo
          </Button>
        </div>

        {/* ── Week view ──────────────────────────────────────────────────── */}
        {viewMode === "week" && (
          <div className="flex-1 overflow-auto border rounded-lg bg-card">
            {/* Day headers */}
            <div className="flex border-b sticky top-0 bg-card z-20">
              <div className="w-14 shrink-0 border-r" />
              {weekDays.map((day, i) => {
                const today = isToday(day);
                const dayApts = aptsForDay(day);
                return (
                  <div key={i} className="flex-1 border-r last:border-r-0 px-2 py-2 text-center">
                    <p className="text-xs text-muted-foreground uppercase tracking-wide">{WEEK_DAYS_SHORT[i]}</p>
                    <div className={`inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-semibold mx-auto mt-0.5 ${today ? "bg-primary text-primary-foreground" : "text-foreground"}`}>
                      {day.getDate()}
                    </div>
                    {dayApts.length > 0 && (
                      <p className="text-xs text-muted-foreground mt-0.5">{dayApts.length} agend.</p>
                    )}
                  </div>
                );
              })}
            </div>

            {/* Time grid */}
            <div className="flex" style={{ height: TOTAL_HEIGHT }}>
              {/* Time axis */}
              <div className="w-14 shrink-0 border-r relative">
                {HOURS.map((h) => (
                  <div key={h} className="absolute w-full border-t border-border/50"
                    style={{ top: (h - DAY_START) * HOUR_HEIGHT }}>
                    <span className="absolute -top-2.5 right-1.5 text-[10px] text-muted-foreground/70 font-mono">
                      {String(h).padStart(2, "0")}:00
                    </span>
                  </div>
                ))}
              </div>

              {/* Day columns */}
              {weekDays.map((day, di) => {
                const dayApts = aptsForDay(day);
                const positioned = resolveOverlaps(dayApts);
                const todayCol = isToday(day);

                return (
                  <div key={di}
                    className={`flex-1 border-r last:border-r-0 relative ${todayCol ? "bg-primary/[0.02]" : ""}`}
                    onClick={(e) => {
                      const rect = e.currentTarget.getBoundingClientRect();
                      const y = e.clientY - rect.top;
                      const minutes = Math.floor((y / HOUR_HEIGHT) * 60) + DAY_START * 60;
                      const h = Math.floor(minutes / 60);
                      const m = Math.round((minutes % 60) / 15) * 15;
                      const time = `${String(h).padStart(2, "0")}:${String(m % 60).padStart(2, "0")}`;
                      handleOpenNew(day, time);
                    }}>

                    {/* Hour lines */}
                    {HOURS.map((h) => (
                      <div key={h} className="absolute left-0 right-0 border-t border-border/40"
                        style={{ top: (h - DAY_START) * HOUR_HEIGHT }} />
                    ))}
                    {/* Half-hour lines */}
                    {HOURS.map((h) => (
                      <div key={`h-${h}`} className="absolute left-0 right-0 border-t border-dashed border-border/20"
                        style={{ top: (h - DAY_START) * HOUR_HEIGHT + HOUR_HEIGHT / 2 }} />
                    ))}

                    {/* Current time indicator */}
                    {todayCol && showNow && (
                      <div className="absolute left-0 right-0 z-10 pointer-events-none" style={{ top: nowTop }}>
                        <div className="absolute left-0 w-2 h-2 bg-red-500 rounded-full -translate-y-1" />
                        <div className="absolute left-2 right-0 h-[1.5px] bg-red-500" />
                      </div>
                    )}

                    {/* Appointments */}
                    {positioned.map(({ apt, colIndex, colCount }) => {
                      const top = aptTop(apt.startTime);
                      const height = aptHeight(apt.startTime, apt.endTime);
                      const color = apt.professional.color ?? "#6366f1";
                      const compact = height < 40;
                      const isCancelled = apt.status.startsWith("cancelled");

                      return (
                        <div key={apt.id}
                          className="absolute rounded cursor-pointer border transition-opacity hover:opacity-90 overflow-hidden"
                          style={{
                            top: top + 1,
                            height: height - 2,
                            left: `calc(${(colIndex / colCount) * 100}% + 2px)`,
                            width: `calc(${(1 / colCount) * 100}% - 4px)`,
                            backgroundColor: color + "22",
                            borderColor: color + "80",
                            borderLeftColor: color,
                            borderLeftWidth: 3,
                            opacity: isCancelled ? 0.5 : 1,
                          }}
                          onClick={(e) => { e.stopPropagation(); setSelectedAptId(apt.id); }}>
                          <div className="px-1.5 py-0.5">
                            <p className="text-[11px] font-semibold truncate leading-tight" style={{ color }}>
                              {apt.startTime} {!compact && `– ${apt.endTime}`}
                            </p>
                            {!compact && (
                              <>
                                <p className="text-[11px] font-medium truncate text-foreground leading-tight">{apt.client.name}</p>
                                <p className="text-[10px] text-muted-foreground truncate leading-tight">{apt.service.name}</p>
                              </>
                            )}
                            {compact && (
                              <p className="text-[10px] truncate text-foreground leading-tight">{apt.client.name}</p>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {/* ── Month view ──────────────────────────────────────────────────── */}
        {viewMode === "month" && (
          <div className="flex-1 overflow-auto border rounded-lg bg-card">
            {/* Day names header */}
            <div className="grid grid-cols-7 border-b">
              {MONTH_DAYS_SHORT.map((d) => (
                <div key={d} className="py-2 text-center text-xs font-medium text-muted-foreground uppercase tracking-wide border-r last:border-r-0">
                  {d}
                </div>
              ))}
            </div>

            {/* Month grid */}
            <div className="grid grid-cols-7">
              {monthCells.map((day, i) => {
                if (!day) return <div key={`empty-${i}`} className="border-r border-b last:border-r-0 min-h-[100px] bg-muted/20" />;

                const dayApts = aptsForDay(day);
                const today = isToday(day);
                const inMonth = day.getMonth() === monthMonth;
                const visibleApts = dayApts.slice(0, 3);
                const moreCount = dayApts.length - 3;

                return (
                  <div key={i}
                    className={`border-r border-b last:border-r-0 min-h-[100px] p-1.5 ${!inMonth ? "bg-muted/20" : ""}`}>
                    <div className="flex items-center justify-between mb-1">
                      <span className={`inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold ${today ? "bg-primary text-primary-foreground" : inMonth ? "text-foreground" : "text-muted-foreground/50"}`}>
                        {day.getDate()}
                      </span>
                      {dayApts.length > 0 && (
                        <button
                          className="text-[10px] text-muted-foreground hover:text-foreground"
                          onClick={() => { setWeekStart(getWeekStart(day)); setViewMode("week"); }}>
                          {dayApts.length} →
                        </button>
                      )}
                    </div>
                    <div className="space-y-0.5">
                      {visibleApts.map(apt => (
                        <button key={apt.id}
                          onClick={() => setSelectedAptId(apt.id)}
                          className="w-full text-left px-1 py-0.5 rounded text-[10px] font-medium truncate leading-tight hover:opacity-80 transition-opacity"
                          style={{
                            backgroundColor: (apt.professional.color ?? "#6366f1") + "22",
                            color: apt.professional.color ?? "#6366f1",
                          }}>
                          {apt.startTime} {apt.client.name}
                        </button>
                      ))}
                      {moreCount > 0 && (
                        <button
                          className="text-[10px] text-muted-foreground hover:text-foreground pl-1"
                          onClick={() => { setWeekStart(getWeekStart(day)); setViewMode("week"); }}>
                          +{moreCount} mais
                        </button>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        )}
      </div>

      {/* ── Appointment detail sheet ────────────────────────────────────── */}
      <Sheet open={selectedAptId !== null} onOpenChange={(val) => { if (!val) setSelectedAptId(null); }}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-md">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Detalhes do agendamento</SheetTitle>
            <SheetDescription>Visualize e atualize o status do agendamento.</SheetDescription>
          </SheetHeader>

          {loadingApt ? (
            <div className="flex-1 flex items-center justify-center">
              <div className="h-6 w-6 border-2 border-primary border-t-transparent rounded-full animate-spin" />
            </div>
          ) : selectedApt ? (
            <div className="flex-1 overflow-y-auto px-6 py-5 space-y-5">
              {/* Status */}
              <div>
                {(() => {
                  const cfg = STATUS_CONFIG[selectedApt.status] ?? { label: selectedApt.status, badge: "" };
                  return <Badge className={`${cfg.badge} text-xs`}>{cfg.label}</Badge>;
                })()}
              </div>

              {/* Info grid */}
              <div className="space-y-3">
                <div className="flex items-start gap-3">
                  <User className="h-4 w-4 text-muted-foreground mt-0.5 shrink-0" />
                  <div>
                    <p className="font-medium text-sm">{selectedApt.client.name}</p>
                    {selectedApt.client.phone && (
                      <p className="text-xs text-muted-foreground flex items-center gap-1">
                        <Phone className="h-3 w-3" />{selectedApt.client.phone}
                      </p>
                    )}
                  </div>
                </div>

                <div className="flex items-start gap-3">
                  <Scissors className="h-4 w-4 text-muted-foreground mt-0.5 shrink-0" />
                  <div>
                    <p className="font-medium text-sm">{selectedApt.service.name}</p>
                    <p className="text-xs text-muted-foreground">{selectedApt.service.duration} min · {brl(selectedApt.price)}</p>
                  </div>
                </div>

                <div className="flex items-start gap-3">
                  <div className="w-4 h-4 flex items-center justify-center shrink-0 mt-0.5">
                    <div className="w-3 h-3 rounded-full" style={{ backgroundColor: selectedApt.professional.color ?? "#6366f1" }} />
                  </div>
                  <p className="font-medium text-sm">{selectedApt.professional.name}</p>
                </div>

                <div className="flex items-start gap-3">
                  <CalendarDays className="h-4 w-4 text-muted-foreground mt-0.5 shrink-0" />
                  <div>
                    <p className="font-medium text-sm">
                      {format(new Date(selectedApt.date.slice(0, 10) + "T12:00:00"), "EEEE, d 'de' MMMM 'de' yyyy", { locale: ptBR })}
                    </p>
                    <p className="text-xs text-muted-foreground flex items-center gap-1">
                      <Clock className="h-3 w-3" />{selectedApt.startTime} – {selectedApt.endTime}
                    </p>
                  </div>
                </div>

                <div className="flex items-start gap-3">
                  <Building2 className="h-4 w-4 text-muted-foreground mt-0.5 shrink-0" />
                  <p className="text-sm">{selectedApt.unit.name}</p>
                </div>

                {selectedApt.notes && (
                  <div className="p-3 rounded-lg bg-muted text-sm text-muted-foreground">
                    {selectedApt.notes}
                  </div>
                )}
              </div>

              {/* Status actions */}
              {!["cancelled_by_client", "cancelled_by_business", "completed"].includes(selectedApt.status) && (
                <div className="space-y-2 pt-2 border-t">
                  <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Atualizar status</p>
                  <div className="grid grid-cols-2 gap-2">
                    {selectedApt.status === "scheduled" && (
                      <Button variant="outline" size="sm" className="text-indigo-700 border-indigo-300 hover:bg-indigo-50"
                        disabled={updatingStatus}
                        onClick={() => updateStatus({ id: selectedApt.id, status: "confirmed" })}>
                        <CheckCircle2 className="h-3.5 w-3.5 mr-1.5" />Confirmar
                      </Button>
                    )}
                    {["scheduled", "confirmed"].includes(selectedApt.status) && (
                      <Button variant="outline" size="sm" className="text-amber-700 border-amber-300 hover:bg-amber-50"
                        disabled={updatingStatus}
                        onClick={() => updateStatus({ id: selectedApt.id, status: "in_progress" })}>
                        <PlayCircle className="h-3.5 w-3.5 mr-1.5" />Iniciar
                      </Button>
                    )}
                    {["scheduled", "confirmed", "in_progress"].includes(selectedApt.status) && (
                      <Button variant="outline" size="sm" className="text-green-700 border-green-300 hover:bg-green-50"
                        disabled={updatingStatus}
                        onClick={() => updateStatus({ id: selectedApt.id, status: "completed" })}>
                        <CheckCircle2 className="h-3.5 w-3.5 mr-1.5" />Concluir
                      </Button>
                    )}
                    {["scheduled", "confirmed"].includes(selectedApt.status) && (
                      <Button variant="outline" size="sm" className="text-orange-700 border-orange-300 hover:bg-orange-50"
                        disabled={updatingStatus}
                        onClick={() => updateStatus({ id: selectedApt.id, status: "no_show" })}>
                        <AlertCircle className="h-3.5 w-3.5 mr-1.5" />Não compareceu
                      </Button>
                    )}
                    <Button variant="outline" size="sm" className="text-destructive border-destructive/30 hover:bg-destructive/10"
                      disabled={updatingStatus}
                      onClick={() => updateStatus({ id: selectedApt.id, status: "cancelled_by_business" })}>
                      <XCircle className="h-3.5 w-3.5 mr-1.5" />Cancelar
                    </Button>
                  </div>
                </div>
              )}
            </div>
          ) : null}
        </SheetContent>
      </Sheet>

      {/* ── New appointment sheet ───────────────────────────────────────── */}
      <Sheet open={newAptOpen} onOpenChange={(val) => { if (!val) { setNewAptOpen(false); setSelectedClient(null); setClientSearch(""); } }}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-lg">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Novo agendamento</SheetTitle>
            <SheetDescription>Preencha os dados para criar o agendamento.</SheetDescription>
          </SheetHeader>
          <form onSubmit={handleCreateSubmit} className="flex flex-col flex-1 overflow-hidden">
            <div className="flex-1 overflow-y-auto px-6 py-5 space-y-5">

              {/* Client search */}
              <div className="space-y-1.5">
                <Label>Cliente <span className="text-destructive">*</span></Label>
                {selectedClient ? (
                  <div className="flex items-center gap-2 p-2 rounded-md border bg-muted/30">
                    <div className="flex-1">
                      <p className="text-sm font-medium">{selectedClient.name}</p>
                      {selectedClient.phone && <p className="text-xs text-muted-foreground">{selectedClient.phone}</p>}
                    </div>
                    <button type="button" onClick={() => { setSelectedClient(null); setClientSearch(""); }}
                      className="text-muted-foreground hover:text-foreground text-xs px-2">
                      Trocar
                    </button>
                  </div>
                ) : (
                  <div className="relative">
                    <Input placeholder="Buscar cliente pelo nome..."
                      value={clientSearch} onChange={e => handleClientSearch(e.target.value)}
                      onFocus={() => setShowClientDropdown(true)}
                      autoComplete="off" />
                    {showClientDropdown && clientSearch.length >= 2 && clientList.length > 0 && (
                      <div className="absolute z-50 top-full left-0 right-0 mt-1 bg-popover border rounded-md shadow-lg overflow-hidden">
                        {clientList.map(c => (
                          <button key={c.id} type="button"
                            className="w-full text-left px-3 py-2 text-sm hover:bg-accent transition-colors"
                            onMouseDown={e => { e.preventDefault(); setSelectedClient(c); setClientSearch(""); setShowClientDropdown(false); }}>
                            <p className="font-medium">{c.name}</p>
                            {c.phone && <p className="text-xs text-muted-foreground">{c.phone}</p>}
                          </button>
                        ))}
                      </div>
                    )}
                    {showClientDropdown && clientSearch.length >= 2 && clientList.length === 0 && (
                      <div className="absolute z-50 top-full left-0 right-0 mt-1 bg-popover border rounded-md shadow-lg p-3 text-sm text-muted-foreground">
                        Nenhum cliente encontrado
                      </div>
                    )}
                  </div>
                )}
              </div>

              {/* Professional + Service */}
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <Label>Profissional <span className="text-destructive">*</span></Label>
                  <Select
                    value={newAptForm.professionalId}
                    onValueChange={v => setNewAptForm(f => ({ ...f, professionalId: v ?? "", startTime: "" }))}
                    items={Object.fromEntries(professionals.map(p => [String(p.id), p.name]))}>
                    <SelectTrigger className="w-full"><SelectValue placeholder="Selecionar..." /></SelectTrigger>
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
                    value={newAptForm.serviceId}
                    onValueChange={v => setNewAptForm(f => ({ ...f, serviceId: v ?? "", startTime: "" }))}
                    items={Object.fromEntries(services.map(s => [String(s.id), s.duration ? `${s.name} (${s.duration}min)` : s.name]))}>
                    <SelectTrigger className="w-full"><SelectValue placeholder="Selecionar..." /></SelectTrigger>
                    <SelectContent>
                      {services.map(s => (
                        <SelectItem key={s.id} value={String(s.id)}>
                          {s.name} ({s.duration}min)
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              {/* Unit + Date */}
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <Label>Unidade <span className="text-destructive">*</span></Label>
                  <Select
                    value={newAptForm.unitId}
                    onValueChange={v => setNewAptForm(f => ({ ...f, unitId: v ?? "", startTime: "" }))}
                    items={Object.fromEntries(units.map(u => [String(u.id), u.name]))}>
                    <SelectTrigger className="w-full"><SelectValue placeholder="Selecionar..." /></SelectTrigger>
                    <SelectContent>
                      {units.map(u => <SelectItem key={u.id} value={String(u.id)}>{u.name}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-1.5">
                  <Label>Data <span className="text-destructive">*</span></Label>
                  <Input type="date" value={newAptForm.date}
                    onChange={e => setNewAptForm(f => ({ ...f, date: e.target.value, startTime: "" }))} />
                </div>
              </div>

              {/* Time slots */}
              <div className="space-y-1.5">
                <Label>Horário <span className="text-destructive">*</span></Label>
                {!slotsEnabled ? (
                  <p className="text-xs text-muted-foreground">Selecione profissional, serviço, unidade e data para ver os horários disponíveis.</p>
                ) : slots.length === 0 ? (
                  <p className="text-xs text-muted-foreground">Nenhum horário disponível para esta combinação.</p>
                ) : (
                  <div className="grid grid-cols-4 gap-1.5">
                    {slots.map(slot => (
                      <button key={slot} type="button"
                        onClick={() => setNewAptForm(f => ({ ...f, startTime: slot }))}
                        className={`h-8 rounded-md text-xs font-medium border transition-colors ${newAptForm.startTime === slot ? "bg-primary text-primary-foreground border-primary" : "hover:bg-accent border-input"}`}>
                        {slot}
                      </button>
                    ))}
                  </div>
                )}
              </div>

              {/* Notes */}
              <div className="space-y-1.5">
                <Label>Observações</Label>
                <Textarea placeholder="Informações adicionais..." rows={2}
                  value={newAptForm.notes} onChange={e => setNewAptForm(f => ({ ...f, notes: e.target.value }))} />
              </div>
            </div>
            <SheetFooter className="px-6 py-4 border-t">
              <Button type="button" variant="outline" onClick={() => setNewAptOpen(false)} disabled={creating}>Cancelar</Button>
              <Button type="submit" disabled={creating}>{creating ? "Agendando..." : "Criar agendamento"}</Button>
            </SheetFooter>
          </form>
        </SheetContent>
      </Sheet>
    </>
  );
}
