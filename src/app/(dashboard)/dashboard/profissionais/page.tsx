"use client";

import { useState, useRef, useEffect } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
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
  Search, Plus, Briefcase, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight,
  AlertCircle, Pencil, Trash2, Clock, Building2, Link2,
} from "lucide-react";

// ─── Types ───────────────────────────────────────────────────────────────────

interface Professional {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
  color: string | null;
  commissionType: string | null;
  commissionValue: number | null;
  isActive: boolean;
}

interface ProfessionalsResponse {
  data: Professional[];
  total: number;
  page: number;
  perPage: number;
  totalPages: number;
}

interface Unit { id: number; name: string }
interface Service { id: number; name: string; isActive: boolean }

interface ProfForm {
  name: string;
  email: string;
  phone: string;
  color: string;
  commissionType: "" | "none" | "percentage" | "fixed";
  commissionValue: string;
  isActive: boolean;
}

interface ProfDaySchedule {
  dayOfWeek: number;
  startTime: string;
  endTime: string;
  isWorking: boolean;
}

// ─── Constants ───────────────────────────────────────────────────────────────

const emptyForm: ProfForm = {
  name: "", email: "", phone: "", color: "#6366f1",
  commissionType: "", commissionValue: "", isActive: true,
};

const DAYS = [
  { value: 1, label: "Segunda-feira", short: "Seg" },
  { value: 2, label: "Terça-feira",   short: "Ter" },
  { value: 3, label: "Quarta-feira",  short: "Qua" },
  { value: 4, label: "Quinta-feira",  short: "Qui" },
  { value: 5, label: "Sexta-feira",   short: "Sex" },
  { value: 6, label: "Sábado",        short: "Sáb" },
  { value: 0, label: "Domingo",       short: "Dom" },
];

const DEFAULT_PROF_SCHEDULE: ProfDaySchedule[] = [
  { dayOfWeek: 1, startTime: "08:00", endTime: "18:00", isWorking: true },
  { dayOfWeek: 2, startTime: "08:00", endTime: "18:00", isWorking: true },
  { dayOfWeek: 3, startTime: "08:00", endTime: "18:00", isWorking: true },
  { dayOfWeek: 4, startTime: "08:00", endTime: "18:00", isWorking: true },
  { dayOfWeek: 5, startTime: "08:00", endTime: "18:00", isWorking: true },
  { dayOfWeek: 6, startTime: "09:00", endTime: "13:00", isWorking: false },
  { dayOfWeek: 0, startTime: "09:00", endTime: "13:00", isWorking: false },
];

const PER_PAGE_OPTIONS = [10, 20, 50, 100];
const DEFAULT_PER_PAGE = 20;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function getInitials(name: string): string {
  return name.split(" ").slice(0, 2).map(n => n[0]).join("").toUpperCase();
}

function formatCommission(type: string | null, value: number | null): string {
  if (!type || value == null) return "—";
  if (type === "percentage") return `${value}%`;
  return Number(value).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
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

function mergeProfSchedule(
  existing: { dayOfWeek: number; startTime: string; endTime: string; isWorking: boolean }[]
): ProfDaySchedule[] {
  return DEFAULT_PROF_SCHEDULE.map(d => {
    const found = existing.find(e => e.dayOfWeek === d.dayOfWeek);
    return found
      ? { dayOfWeek: d.dayOfWeek, startTime: found.startTime, endTime: found.endTime, isWorking: found.isWorking }
      : { ...d };
  });
}

// ─── Sub-components ──────────────────────────────────────────────────────────

function TableSkeleton() {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Nome</TableHead><TableHead>Email</TableHead>
          <TableHead>Telefone</TableHead><TableHead>Comissão</TableHead>
          <TableHead>Status</TableHead><TableHead className="w-44">Ações</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {Array.from({ length: 6 }).map((_, i) => (
          <TableRow key={i}>
            <TableCell><div className="flex items-center gap-3"><div className="h-8 w-8 rounded-full bg-muted animate-pulse" /><div className="h-4 w-32 bg-muted animate-pulse rounded" /></div></TableCell>
            {[40, 28, 16, 14, 44].map((w, j) => <TableCell key={j}><div className={`h-4 w-${w} bg-muted animate-pulse rounded`} /></TableCell>)}
          </TableRow>
        ))}
      </TableBody>
    </Table>
  );
}

interface PaginationProps {
  page: number; totalPages: number; total: number; perPage: number;
  onPageChange: (p: number) => void; onPerPageChange: (n: number) => void;
}

function TablePagination({ page, totalPages, total, perPage, onPageChange, onPerPageChange }: PaginationProps) {
  const start = total === 0 ? 0 : (page - 1) * perPage + 1;
  const end = Math.min(page * perPage, total);
  const pages = getPageNumbers(page, totalPages);
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t text-sm">
      <div className="flex items-center gap-3 text-muted-foreground">
        <span>{total === 0 ? "Nenhum registro" : `Mostrando ${start}–${end} de ${total} profissionais`}</span>
        <div className="flex items-center gap-1.5">
          <span className="text-xs">Por página:</span>
          <Select value={String(perPage)} onValueChange={v => onPerPageChange(Number(v))}>
            <SelectTrigger className="h-7 w-[64px] text-xs"><SelectValue /></SelectTrigger>
            <SelectContent>{PER_PAGE_OPTIONS.map(n => <SelectItem key={n} value={String(n)}>{n}</SelectItem>)}</SelectContent>
          </Select>
        </div>
      </div>
      {totalPages > 1 && (
        <div className="flex items-center gap-1">
          <Button variant="outline" size="sm" className="h-8 w-8 p-0" onClick={() => onPageChange(1)} disabled={page === 1}><ChevronsLeft className="h-4 w-4" /></Button>
          <Button variant="outline" size="sm" className="h-8 w-8 p-0" onClick={() => onPageChange(page - 1)} disabled={page === 1}><ChevronLeft className="h-4 w-4" /></Button>
          {pages.map((p, i) =>
            p === "…"
              ? <span key={`el-${i}`} className="h-8 w-8 flex items-center justify-center text-muted-foreground">…</span>
              : <Button key={p} variant={p === page ? "default" : "outline"} size="sm" className="h-8 w-8 p-0" onClick={() => onPageChange(p as number)}>{p}</Button>
          )}
          <Button variant="outline" size="sm" className="h-8 w-8 p-0" onClick={() => onPageChange(page + 1)} disabled={page === totalPages}><ChevronRight className="h-4 w-4" /></Button>
          <Button variant="outline" size="sm" className="h-8 w-8 p-0" onClick={() => onPageChange(totalPages)} disabled={page === totalPages}><ChevronsRight className="h-4 w-4" /></Button>
        </div>
      )}
    </div>
  );
}

interface ProfFormFieldsProps {
  form: ProfForm;
  onChange: (field: keyof ProfForm, value: string | boolean) => void;
  disabled?: boolean;
}

function ProfFormFields({ form, onChange, disabled }: ProfFormFieldsProps) {
  return (
    <div className="space-y-5">
      <div className="space-y-1.5">
        <Label htmlFor="pf-name">Nome <span className="text-destructive">*</span></Label>
        <Input id="pf-name" placeholder="Nome do profissional" value={form.name}
          onChange={e => onChange("name", e.target.value)} disabled={disabled} autoFocus />
      </div>
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="pf-email">Email</Label>
          <Input id="pf-email" type="email" placeholder="email@exemplo.com" value={form.email}
            onChange={e => onChange("email", e.target.value)} disabled={disabled} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="pf-phone">Telefone</Label>
          <Input id="pf-phone" placeholder="(11) 99999-9999" value={form.phone}
            onChange={e => onChange("phone", e.target.value)} disabled={disabled} />
        </div>
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="pf-color">Cor de identificação</Label>
        <div className="flex items-center gap-3">
          <input id="pf-color" type="color" value={form.color || "#6366f1"}
            onChange={e => onChange("color", e.target.value)} disabled={disabled}
            className="h-9 w-12 cursor-pointer rounded-md border border-input bg-transparent p-0.5 disabled:opacity-50" />
          <Input placeholder="#6366f1" value={form.color}
            onChange={e => onChange("color", e.target.value)}
            disabled={disabled} className="flex-1 font-mono text-sm" maxLength={7} />
        </div>
        <p className="text-xs text-muted-foreground">Usada para identificar o profissional no calendário.</p>
      </div>
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label>Tipo de comissão</Label>
          <Select
            value={form.commissionType}
            onValueChange={v => v && onChange("commissionType", v)}
            disabled={disabled}
            items={{ none: "Nenhuma", percentage: "Percentual (%)", fixed: "Valor fixo (R$)" }}>
            <SelectTrigger className="w-full"><SelectValue placeholder="Nenhuma" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="none">Nenhuma</SelectItem>
              <SelectItem value="percentage">Percentual (%)</SelectItem>
              <SelectItem value="fixed">Valor fixo (R$)</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="pf-cv">{form.commissionType === "percentage" ? "Percentual (%)" : "Valor (R$)"}</Label>
          <Input id="pf-cv" type="number" min="0"
            step={form.commissionType === "percentage" ? "0.1" : "0.01"}
            placeholder={form.commissionType === "percentage" ? "ex: 15" : "ex: 25.00"}
            value={form.commissionValue}
            onChange={e => onChange("commissionValue", e.target.value)}
            disabled={disabled || !form.commissionType || form.commissionType === "none"} />
        </div>
      </div>
      <label className="flex items-center gap-3 cursor-pointer">
        <input type="checkbox" className="h-4 w-4 rounded border-input accent-primary"
          checked={form.isActive} onChange={e => onChange("isActive", e.target.checked)} disabled={disabled} />
        <span className="text-sm font-medium">Profissional ativo</span>
      </label>
    </div>
  );
}

interface ProfScheduleEditorProps {
  schedule: ProfDaySchedule[];
  onChange: (s: ProfDaySchedule[]) => void;
  disabled?: boolean;
}

function ProfScheduleEditor({ schedule, onChange, disabled }: ProfScheduleEditorProps) {
  function update(dayOfWeek: number, field: keyof ProfDaySchedule, value: string | boolean) {
    onChange(schedule.map(d => d.dayOfWeek === dayOfWeek ? { ...d, [field]: value } : d));
  }

  function applyToWeekdays() {
    const mon = schedule.find(d => d.dayOfWeek === 1);
    if (!mon) return;
    onChange(schedule.map(d =>
      d.dayOfWeek >= 1 && d.dayOfWeek <= 5
        ? { ...d, startTime: mon.startTime, endTime: mon.endTime, isWorking: true }
        : d
    ));
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-xs text-muted-foreground">
          Configure os dias e horários de trabalho do profissional nesta unidade.
        </p>
        <Button type="button" variant="outline" size="sm" className="text-xs h-7"
          onClick={applyToWeekdays} disabled={disabled}>
          Replicar seg–sex
        </Button>
      </div>
      <div className="space-y-1">
        {DAYS.map(day => {
          const d = schedule.find(s => s.dayOfWeek === day.value) ?? DEFAULT_PROF_SCHEDULE.find(s => s.dayOfWeek === day.value)!;
          return (
            <div key={day.value}
              className={`flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors
                ${d.isWorking ? "bg-background" : "bg-muted/40"}`}>
              <label className="relative inline-flex items-center cursor-pointer shrink-0">
                <input type="checkbox" className="sr-only peer"
                  checked={d.isWorking}
                  onChange={e => update(day.value, "isWorking", e.target.checked)}
                  disabled={disabled} />
                <div className="w-9 h-5 bg-muted rounded-full peer peer-checked:bg-primary transition-colors
                  after:content-[''] after:absolute after:top-0.5 after:left-0.5
                  after:bg-white after:rounded-full after:h-4 after:w-4
                  after:transition-transform peer-checked:after:translate-x-4" />
              </label>
              <span className={`text-sm w-28 font-medium shrink-0 ${!d.isWorking ? "text-muted-foreground" : ""}`}>
                {day.label}
              </span>
              {d.isWorking ? (
                <div className="flex items-center gap-2 flex-1">
                  <Input type="time" value={d.startTime}
                    onChange={e => update(day.value, "startTime", e.target.value)}
                    disabled={disabled} className="h-8 w-28 text-sm" />
                  <span className="text-muted-foreground text-xs">até</span>
                  <Input type="time" value={d.endTime}
                    onChange={e => update(day.value, "endTime", e.target.value)}
                    disabled={disabled} className="h-8 w-28 text-sm" />
                </div>
              ) : (
                <span className="text-xs text-muted-foreground flex-1">Não trabalha</span>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

function TabButton({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button type="button" onClick={onClick}
      className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors
        ${active
          ? "border-primary text-foreground"
          : "border-transparent text-muted-foreground hover:text-foreground hover:border-border"
        }`}>
      {children}
    </button>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function ProfissionaisPage() {
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(DEFAULT_PER_PAGE);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [createOpen, setCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState<ProfForm>(emptyForm);

  const [editId, setEditId] = useState<number | null>(null);
  const [editForm, setEditForm] = useState<ProfForm>(emptyForm);
  const [editTab, setEditTab] = useState<"dados" | "horarios" | "vinculos">("dados");

  // Schedule state: per unit
  const [scheduleUnitId, setScheduleUnitId] = useState<string>("");

  // Vínculos state
  const [linkedUnitIds, setLinkedUnitIds] = useState<number[]>([]);
  const [linkedServiceIds, setLinkedServiceIds] = useState<number[]>([]);
  const [profSchedule, setProfSchedule] = useState<ProfDaySchedule[]>(DEFAULT_PROF_SCHEDULE);

  const [deleteTarget, setDeleteTarget] = useState<{ id: number; name: string } | null>(null);

  const queryClient = useQueryClient();

  // ── Queries ──────────────────────────────────────────────────────────────

  const { data, isLoading, isError } = useQuery<ProfessionalsResponse>({
    queryKey: ["professionals", debouncedSearch, page, perPage],
    queryFn: () =>
      fetch(`/api/professionals?search=${encodeURIComponent(debouncedSearch)}&page=${page}&perPage=${perPage}`)
        .then(r => { if (!r.ok) throw new Error(); return r.json(); }),
  });

  const { data: fullProf, isLoading: loadingFull } = useQuery({
    queryKey: ["professional", editId],
    queryFn: () =>
      fetch(`/api/professionals/${editId}`).then(async r => {
        if (!r.ok) throw new Error("Erro ao carregar profissional");
        return r.json();
      }),
    enabled: editId !== null,
  });

  // All units for the schedule unit selector and vínculos
  const { data: unitsData } = useQuery<{ data: Unit[] }>({
    queryKey: ["units-simple"],
    queryFn: () => fetch("/api/units?perPage=200").then(r => r.json()),
    enabled: editId !== null,
  });

  // All services for vínculos
  const { data: servicesData } = useQuery<{ data: Service[] }>({
    queryKey: ["services-simple"],
    queryFn: () => fetch("/api/services?perPage=200").then(r => r.json()),
    enabled: editId !== null,
  });

  // Current professional links
  const { data: linksData } = useQuery<{ unitIds: number[]; serviceIds: number[] }>({
    queryKey: ["prof-links", editId],
    queryFn: () => fetch(`/api/professionals/${editId}/links`).then(r => r.json()),
    enabled: editId !== null,
  });

  const allUnits = unitsData?.data ?? [];
  const allServices = servicesData?.data ?? [];

  // Professional working hours for the selected unit
  const { data: hoursData } = useQuery({
    queryKey: ["prof-hours", editId, scheduleUnitId],
    queryFn: () =>
      fetch(`/api/professionals/${editId}/working-hours?unitId=${scheduleUnitId}`)
        .then(r => r.json()),
    enabled: !!editId && !!scheduleUnitId,
  });

  useEffect(() => {
    if (!fullProf) return;
    setEditForm({
      name:            fullProf.name ?? "",
      email:           fullProf.email ?? "",
      phone:           fullProf.phone ?? "",
      color:           fullProf.color ?? "#6366f1",
      commissionType:  (fullProf.commissionType as ProfForm["commissionType"]) ?? "",
      commissionValue: fullProf.commissionValue != null ? String(fullProf.commissionValue) : "",
      isActive:        fullProf.isActive ?? true,
    });
  }, [fullProf]);

  // Sync hours when unit changes or data loads
  useEffect(() => {
    if (!hoursData) {
      setProfSchedule(DEFAULT_PROF_SCHEDULE);
      return;
    }
    setProfSchedule(mergeProfSchedule(hoursData));
  }, [hoursData]);

  // Sync links when data loads
  useEffect(() => {
    if (!linksData) return;
    setLinkedUnitIds(linksData.unitIds);
    setLinkedServiceIds(linksData.serviceIds);
  }, [linksData]);

  // Auto-select first unit when opening horarios tab
  useEffect(() => {
    if (editTab === "horarios" && allUnits.length > 0 && !scheduleUnitId) {
      setScheduleUnitId(String(allUnits[0].id));
    }
  }, [editTab, allUnits, scheduleUnitId]);

  // ── Mutations ────────────────────────────────────────────────────────────

  const { mutate: createProf, isPending: creating } = useMutation({
    mutationFn: (form: ProfForm) =>
      fetch("/api/professionals", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildCreatePayload(form)),
      }).then(async r => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao cadastrar profissional"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["professionals"] });
      setCreateOpen(false);
      setCreateForm(emptyForm);
      toast.success("Profissional cadastrado com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: updateProf, isPending: updating } = useMutation({
    mutationFn: (form: ProfForm) =>
      fetch(`/api/professionals/${editId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildUpdatePayload(form)),
      }).then(async r => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao atualizar profissional"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["professionals"] });
      queryClient.invalidateQueries({ queryKey: ["professional", editId] });
      toast.success("Dados salvos!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: saveSchedule, isPending: savingSchedule } = useMutation({
    mutationFn: () =>
      fetch(`/api/professionals/${editId}/working-hours`, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ unitId: Number(scheduleUnitId), hours: profSchedule }),
      }).then(async r => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao salvar horários"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["prof-hours", editId, scheduleUnitId] });
      toast.success("Horários salvos!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: saveLinks, isPending: savingLinks } = useMutation({
    mutationFn: () =>
      fetch(`/api/professionals/${editId}/links`, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ unitIds: linkedUnitIds, serviceIds: linkedServiceIds }),
      }).then(async r => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao salvar vínculos"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["prof-links", editId] });
      toast.success("Vínculos salvos!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: deleteProf, isPending: deleting } = useMutation({
    mutationFn: (id: number) =>
      fetch(`/api/professionals/${id}`, { method: "DELETE" }).then(async r => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao excluir profissional"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["professionals"] });
      setDeleteTarget(null);
      toast.success("Profissional excluído com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  // ── Payload builders ─────────────────────────────────────────────────────

  function buildCreatePayload(form: ProfForm) {
    const payload: Record<string, unknown> = { name: form.name, isActive: form.isActive };
    if (form.email) payload.email = form.email;
    if (form.phone) payload.phone = form.phone;
    if (form.color) payload.color = form.color;
    if (form.commissionType && form.commissionType !== "none") {
      payload.commissionType = form.commissionType;
      if (form.commissionValue) payload.commissionValue = Number(form.commissionValue);
    }
    return payload;
  }

  function buildUpdatePayload(form: ProfForm) {
    return {
      name:            form.name,
      email:           form.email || null,
      phone:           form.phone || null,
      color:           form.color || null,
      commissionType:  form.commissionType && form.commissionType !== "none" ? form.commissionType : null,
      commissionValue: form.commissionValue && form.commissionType && form.commissionType !== "none"
        ? Number(form.commissionValue) : null,
      isActive: form.isActive,
    };
  }

  // ── Handlers ─────────────────────────────────────────────────────────────

  function handleSearch(value: string) {
    setSearch(value);
    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => { setDebouncedSearch(value); setPage(1); }, 400);
  }

  function handlePerPageChange(n: number) { setPerPage(n); setPage(1); }

  function handleCreateSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (createForm.name.trim().length < 2) { toast.error("Nome deve ter pelo menos 2 caracteres"); return; }
    createProf(createForm);
  }

  function handleEditDadosSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (editForm.name.trim().length < 2) { toast.error("Nome deve ter pelo menos 2 caracteres"); return; }
    updateProf(editForm);
  }

  function handleEditHorariosSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!scheduleUnitId) { toast.error("Selecione uma unidade"); return; }
    saveSchedule();
  }

  function handleEditVinculosSubmit(e: React.FormEvent) {
    e.preventDefault();
    saveLinks();
  }

  function toggleUnit(id: number) {
    setLinkedUnitIds(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
  }

  function toggleService(id: number) {
    setLinkedServiceIds(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
  }

  function openCreate() { setCreateForm(emptyForm); setCreateOpen(true); }
  function openEdit(id: number) {
    setEditForm(emptyForm);
    setEditTab("dados");
    setScheduleUnitId("");
    setProfSchedule(DEFAULT_PROF_SCHEDULE);
    setLinkedUnitIds([]);
    setLinkedServiceIds([]);
    setEditId(id);
  }

  // ─────────────────────────────────────────────────────────────────────────

  const professionals = data?.data ?? [];
  const totalPages = data?.totalPages ?? 1;

  return (
    <>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold">Profissionais</h1>
            <p className="text-muted-foreground text-sm">
              {isLoading ? "Carregando..." : `${data?.total ?? 0} profissionais cadastrados`}
            </p>
          </div>
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4 mr-2" />Novo profissional
          </Button>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Briefcase className="h-4 w-4" />Lista de profissionais
            </CardTitle>
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input className="pl-9" placeholder="Buscar por nome, email ou telefone..."
                value={search} onChange={e => handleSearch(e.target.value)} />
            </div>
          </CardHeader>

          <CardContent className="p-0">
            {isLoading ? (
              <TableSkeleton />
            ) : isError ? (
              <div className="p-10 flex flex-col items-center gap-3 text-destructive">
                <AlertCircle className="h-8 w-8" />
                <p className="font-medium">Erro ao carregar profissionais</p>
              </div>
            ) : professionals.length === 0 ? (
              <div className="p-12 flex flex-col items-center gap-3 text-center">
                <Briefcase className="h-10 w-10 text-muted-foreground/40" />
                <div>
                  <p className="font-medium text-muted-foreground">
                    {debouncedSearch ? `Nenhum resultado para "${debouncedSearch}"` : "Nenhum profissional cadastrado"}
                  </p>
                  {!debouncedSearch && (
                    <p className="text-sm text-muted-foreground/70 mt-1">Cadastre o primeiro profissional para começar</p>
                  )}
                </div>
                {!debouncedSearch && (
                  <Button size="sm" className="mt-1" onClick={openCreate}>
                    <Plus className="h-4 w-4 mr-2" />Cadastrar profissional
                  </Button>
                )}
              </div>
            ) : (
              <>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Nome</TableHead><TableHead>Email</TableHead>
                      <TableHead>Telefone</TableHead><TableHead>Comissão</TableHead>
                      <TableHead>Status</TableHead><TableHead className="w-44">Ações</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {professionals.map(prof => (
                      <TableRow key={prof.id} className="hover:bg-accent cursor-pointer" onClick={() => openEdit(prof.id)}>
                        <TableCell className="font-medium">
                          <div className="flex items-center gap-3">
                            <Avatar className="h-8 w-8 shrink-0">
                              <AvatarFallback className="text-xs font-semibold text-white"
                                style={{ backgroundColor: prof.color ?? "#6366f1" }}>
                                {getInitials(prof.name)}
                              </AvatarFallback>
                            </Avatar>
                            {prof.name}
                          </div>
                        </TableCell>
                        <TableCell className="text-muted-foreground">{prof.email ?? "—"}</TableCell>
                        <TableCell>{prof.phone ?? "—"}</TableCell>
                        <TableCell>{formatCommission(prof.commissionType, prof.commissionValue)}</TableCell>
                        <TableCell>
                          {prof.isActive
                            ? <Badge className="bg-green-500/10 text-green-700 border-green-500/20 hover:bg-green-500/10">Ativo</Badge>
                            : <Badge variant="secondary">Inativo</Badge>}
                        </TableCell>
                        <TableCell onClick={e => e.stopPropagation()}>
                          <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" className="h-8 px-3 text-xs" onClick={() => openEdit(prof.id)}>
                              <Pencil className="h-3.5 w-3.5 mr-1.5" />Editar
                            </Button>
                            <Button variant="outline" size="sm"
                              className="h-8 px-3 text-xs text-destructive border-destructive/30 hover:bg-destructive/10 hover:text-destructive"
                              onClick={() => setDeleteTarget({ id: prof.id, name: prof.name })}>
                              <Trash2 className="h-3.5 w-3.5 mr-1.5" />Excluir
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
                <TablePagination
                  page={page} totalPages={totalPages} total={data?.total ?? 0}
                  perPage={perPage} onPageChange={setPage} onPerPageChange={handlePerPageChange}
                />
              </>
            )}
          </CardContent>
        </Card>
      </div>

      {/* ── Cadastrar profissional ────────────────────────────────────────── */}
      <Sheet open={createOpen} onOpenChange={val => setCreateOpen(val)}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-lg">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Novo profissional</SheetTitle>
            <SheetDescription>Preencha os dados do profissional. Apenas o nome é obrigatório.</SheetDescription>
          </SheetHeader>
          <form onSubmit={handleCreateSubmit} className="flex flex-col flex-1 overflow-hidden">
            <div className="flex-1 overflow-y-auto px-6 py-5">
              <ProfFormFields form={createForm} onChange={(f, v) => setCreateForm(prev => ({ ...prev, [f]: v }))} disabled={creating} />
            </div>
            <SheetFooter className="px-6 py-4 border-t">
              <Button type="button" variant="outline" onClick={() => setCreateOpen(false)} disabled={creating}>Cancelar</Button>
              <Button type="submit" disabled={creating}>{creating ? "Salvando..." : "Cadastrar profissional"}</Button>
            </SheetFooter>
          </form>
        </SheetContent>
      </Sheet>

      {/* ── Editar profissional ───────────────────────────────────────────── */}
      <Sheet open={editId !== null} onOpenChange={val => { if (!val) setEditId(null); }}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-xl">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Editar profissional</SheetTitle>
            <SheetDescription>Atualize os dados e os horários de trabalho.</SheetDescription>
          </SheetHeader>

          {/* Tab switcher */}
          <div className="flex border-b px-6 shrink-0">
            <TabButton active={editTab === "dados"} onClick={() => setEditTab("dados")}>
              <Briefcase className="h-3.5 w-3.5 inline mr-1.5" />Dados
            </TabButton>
            <TabButton active={editTab === "vinculos"} onClick={() => setEditTab("vinculos")}>
              <Link2 className="h-3.5 w-3.5 inline mr-1.5" />Vínculos
            </TabButton>
            <TabButton active={editTab === "horarios"} onClick={() => setEditTab("horarios")}>
              <Clock className="h-3.5 w-3.5 inline mr-1.5" />Horários
            </TabButton>
          </div>

          {loadingFull ? (
            <div className="flex-1 flex items-center justify-center">
              <div className="flex flex-col items-center gap-3 text-muted-foreground">
                <div className="h-6 w-6 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                <span className="text-sm">Carregando dados...</span>
              </div>
            </div>
          ) : editTab === "vinculos" ? (
            <form onSubmit={handleEditVinculosSubmit} className="flex flex-col flex-1 overflow-hidden">
              <div className="flex-1 overflow-y-auto px-6 py-5 space-y-6">
                <div>
                  <p className="text-xs text-muted-foreground mb-4">
                    Defina em quais <strong>unidades</strong> este profissional atende e quais <strong>serviços</strong> ele realiza.
                    Somente profissionais vinculados a uma unidade <em>e</em> a um serviço aparecem no agendamento online.
                  </p>

                  {/* Unidades */}
                  <div className="space-y-2 mb-6">
                    <Label className="flex items-center gap-2 text-sm font-semibold">
                      <Building2 className="h-4 w-4" />Unidades
                    </Label>
                    {allUnits.length === 0 ? (
                      <p className="text-sm text-muted-foreground">Nenhuma unidade cadastrada.</p>
                    ) : (
                      <div className="space-y-1 rounded-lg border p-3">
                        {allUnits.map(u => (
                          <label key={u.id} className="flex items-center gap-3 cursor-pointer rounded-md px-2 py-2 hover:bg-muted/50 transition-colors">
                            <input
                              type="checkbox"
                              className="h-4 w-4 rounded border-input accent-primary"
                              checked={linkedUnitIds.includes(u.id)}
                              onChange={() => toggleUnit(u.id)}
                              disabled={savingLinks}
                            />
                            <span className="text-sm font-medium">{u.name}</span>
                          </label>
                        ))}
                      </div>
                    )}
                  </div>

                  {/* Serviços */}
                  <div className="space-y-2">
                    <Label className="flex items-center gap-2 text-sm font-semibold">
                      <Briefcase className="h-4 w-4" />Serviços
                    </Label>
                    {allServices.length === 0 ? (
                      <p className="text-sm text-muted-foreground">Nenhum serviço cadastrado.</p>
                    ) : (
                      <div className="space-y-1 rounded-lg border p-3">
                        {allServices.map(s => (
                          <label key={s.id} className="flex items-center gap-3 cursor-pointer rounded-md px-2 py-2 hover:bg-muted/50 transition-colors">
                            <input
                              type="checkbox"
                              className="h-4 w-4 rounded border-input accent-primary"
                              checked={linkedServiceIds.includes(s.id)}
                              onChange={() => toggleService(s.id)}
                              disabled={savingLinks}
                            />
                            <span className="text-sm font-medium flex-1">{s.name}</span>
                            {!s.isActive && (
                              <span className="text-xs text-muted-foreground bg-muted rounded px-1.5 py-0.5">inativo</span>
                            )}
                          </label>
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              </div>
              <SheetFooter className="px-6 py-4 border-t">
                <Button type="button" variant="outline" onClick={() => setEditId(null)} disabled={savingLinks}>Fechar</Button>
                <Button type="submit" disabled={savingLinks}>
                  {savingLinks ? "Salvando..." : "Salvar vínculos"}
                </Button>
              </SheetFooter>
            </form>
          ) : editTab === "dados" ? (
            <form onSubmit={handleEditDadosSubmit} className="flex flex-col flex-1 overflow-hidden">
              <div className="flex-1 overflow-y-auto px-6 py-5">
                <ProfFormFields form={editForm} onChange={(f, v) => setEditForm(prev => ({ ...prev, [f]: v }))} disabled={updating} />
              </div>
              <SheetFooter className="px-6 py-4 border-t">
                <Button type="button" variant="outline" onClick={() => setEditId(null)} disabled={updating}>Cancelar</Button>
                <Button type="submit" disabled={updating}>{updating ? "Salvando..." : "Salvar dados"}</Button>
              </SheetFooter>
            </form>
          ) : (
            <form onSubmit={handleEditHorariosSubmit} className="flex flex-col flex-1 overflow-hidden">
              <div className="flex-1 overflow-y-auto px-6 py-5">
                {/* Unit selector */}
                <div className="space-y-1.5 mb-5">
                  <Label className="flex items-center gap-2">
                    <Building2 className="h-4 w-4" />Unidade
                  </Label>
                  {allUnits.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Nenhuma unidade cadastrada.</p>
                  ) : (
                    <Select
                      value={scheduleUnitId}
                      onValueChange={v => {
                        if (v) { setScheduleUnitId(v); setProfSchedule(DEFAULT_PROF_SCHEDULE); }
                      }}
                      items={Object.fromEntries(allUnits.map(u => [String(u.id), u.name]))}>
                      <SelectTrigger className="w-full"><SelectValue placeholder="Selecione a unidade" /></SelectTrigger>
                      <SelectContent>
                        {allUnits.map(u => <SelectItem key={u.id} value={String(u.id)}>{u.name}</SelectItem>)}
                      </SelectContent>
                    </Select>
                  )}
                  <p className="text-xs text-muted-foreground">
                    Os horários são configurados por unidade. Selecione a unidade para editar o expediente.
                  </p>
                </div>

                {scheduleUnitId ? (
                  <>
                    <Separator className="mb-4" />
                    <ProfScheduleEditor schedule={profSchedule} onChange={setProfSchedule} disabled={savingSchedule} />
                  </>
                ) : (
                  <div className="flex flex-col items-center gap-3 py-10 text-center text-muted-foreground">
                    <Clock className="h-8 w-8 opacity-40" />
                    <p className="text-sm">Selecione uma unidade acima para configurar os horários.</p>
                  </div>
                )}
              </div>
              <SheetFooter className="px-6 py-4 border-t">
                <Button type="button" variant="outline" onClick={() => setEditId(null)} disabled={savingSchedule}>Fechar</Button>
                <Button type="submit" disabled={savingSchedule || !scheduleUnitId}>
                  {savingSchedule ? "Salvando..." : "Salvar horários"}
                </Button>
              </SheetFooter>
            </form>
          )}
        </SheetContent>
      </Sheet>

      {/* ── Confirmar exclusão ────────────────────────────────────────────── */}
      <AlertDialog open={deleteTarget !== null} onOpenChange={val => { if (!val) setDeleteTarget(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir profissional</AlertDialogTitle>
            <AlertDialogDescription>
              Tem certeza que deseja excluir <strong className="text-foreground">{deleteTarget?.name}</strong>?
              Esta ação não pode ser desfeita.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>Cancelar</AlertDialogCancel>
            <AlertDialogAction variant="destructive" disabled={deleting}
              onClick={() => deleteTarget && deleteProf(deleteTarget.id)}>
              {deleting ? "Excluindo..." : "Excluir"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
