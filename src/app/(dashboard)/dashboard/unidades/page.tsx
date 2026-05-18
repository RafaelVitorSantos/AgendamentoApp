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
  Search, Plus, Building2, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight,
  AlertCircle, Pencil, Trash2, MapPin, Phone, Clock,
} from "lucide-react";

// ─── Types ───────────────────────────────────────────────────────────────────

interface Unit {
  id: number;
  name: string;
  phone: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
  zipCode: string | null;
  timezone: string | null;
  isActive: boolean;
}

interface UnitsResponse {
  data: Unit[];
  total: number;
  page: number;
  perPage: number;
  totalPages: number;
}

interface UnitForm {
  name: string;
  phone: string;
  zipCode: string;
  address: string;
  city: string;
  state: string;
  timezone: string;
  isActive: boolean;
}

interface DaySchedule {
  dayOfWeek: number;
  openTime: string;
  closeTime: string;
  isOpen: boolean;
}

// ─── Constants ───────────────────────────────────────────────────────────────

const emptyForm: UnitForm = {
  name: "", phone: "", zipCode: "", address: "",
  city: "", state: "", timezone: "America/Sao_Paulo", isActive: true,
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

const DEFAULT_SCHEDULE: DaySchedule[] = [
  { dayOfWeek: 1, openTime: "08:00", closeTime: "18:00", isOpen: true },
  { dayOfWeek: 2, openTime: "08:00", closeTime: "18:00", isOpen: true },
  { dayOfWeek: 3, openTime: "08:00", closeTime: "18:00", isOpen: true },
  { dayOfWeek: 4, openTime: "08:00", closeTime: "18:00", isOpen: true },
  { dayOfWeek: 5, openTime: "08:00", closeTime: "18:00", isOpen: true },
  { dayOfWeek: 6, openTime: "09:00", closeTime: "13:00", isOpen: false },
  { dayOfWeek: 0, openTime: "09:00", closeTime: "13:00", isOpen: false },
];

const PER_PAGE_OPTIONS = [10, 20, 50, 100];
const DEFAULT_PER_PAGE = 20;

const TIMEZONES = [
  { value: "America/Sao_Paulo",   label: "Brasília (GMT-3)" },
  { value: "America/Manaus",      label: "Manaus (GMT-4)" },
  { value: "America/Cuiaba",      label: "Cuiabá (GMT-4)" },
  { value: "America/Porto_Velho", label: "Porto Velho (GMT-4)" },
  { value: "America/Boa_Vista",   label: "Boa Vista (GMT-4)" },
  { value: "America/Rio_Branco",  label: "Rio Branco (GMT-5)" },
  { value: "America/Noronha",     label: "Fernando de Noronha (GMT-2)" },
];

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatAddress(unit: Unit): string {
  const parts = [unit.city, unit.state].filter(Boolean);
  return parts.length ? parts.join("/") : "—";
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

function getTimezoneLabel(tz: string | null): string {
  return TIMEZONES.find(t => t.value === tz)?.label ?? tz ?? "—";
}

function mergeSchedule(
  existing: { dayOfWeek: number; openTime: string; closeTime: string; isOpen: boolean }[]
): DaySchedule[] {
  return DEFAULT_SCHEDULE.map(d => {
    const found = existing.find(e => e.dayOfWeek === d.dayOfWeek);
    return found
      ? { dayOfWeek: d.dayOfWeek, openTime: found.openTime, closeTime: found.closeTime, isOpen: found.isOpen }
      : { ...d };
  });
}

function countOpenDays(schedule: DaySchedule[]): string {
  const open = schedule.filter(d => d.isOpen);
  if (open.length === 0) return "Fechado";
  if (open.length === 7) return "Todos os dias";
  if (open.length === 5 && !schedule.find(d => d.dayOfWeek === 6)?.isOpen && !schedule.find(d => d.dayOfWeek === 0)?.isOpen)
    return "Seg–Sex";
  return `${open.length} dias/semana`;
}

// ─── Sub-components ──────────────────────────────────────────────────────────

function TableSkeleton() {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Unidade</TableHead><TableHead>Telefone</TableHead>
          <TableHead>Localização</TableHead><TableHead>Fuso horário</TableHead>
          <TableHead>Status</TableHead><TableHead className="w-44">Ações</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {Array.from({ length: 5 }).map((_, i) => (
          <TableRow key={i}>
            {[40, 28, 32, 36, 14, 44].map((w, j) => (
              <TableCell key={j}><div className={`h-4 w-${w} bg-muted animate-pulse rounded`} /></TableCell>
            ))}
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
        <span>{total === 0 ? "Nenhum registro" : `Mostrando ${start}–${end} de ${total} unidades`}</span>
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

interface UnitFormFieldsProps {
  form: UnitForm;
  onChange: (field: keyof UnitForm, value: string | boolean) => void;
  showIsActive?: boolean;
  disabled?: boolean;
}

function UnitFormFields({ form, onChange, showIsActive = false, disabled }: UnitFormFieldsProps) {
  return (
    <div className="space-y-5">
      <div className="space-y-1.5">
        <Label htmlFor="uf-name">Nome <span className="text-destructive">*</span></Label>
        <Input id="uf-name" placeholder="Ex: Unidade Centro" value={form.name}
          onChange={e => onChange("name", e.target.value)} disabled={disabled} autoFocus />
      </div>
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="uf-phone">Telefone</Label>
          <Input id="uf-phone" placeholder="(11) 3333-4444" value={form.phone}
            onChange={e => onChange("phone", e.target.value)} disabled={disabled} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="uf-zip">CEP</Label>
          <Input id="uf-zip" placeholder="00000-000" value={form.zipCode}
            onChange={e => onChange("zipCode", e.target.value)} disabled={disabled} maxLength={9} />
        </div>
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="uf-address">Endereço</Label>
        <Input id="uf-address" placeholder="Rua, número, complemento" value={form.address}
          onChange={e => onChange("address", e.target.value)} disabled={disabled} />
      </div>
      <div className="grid grid-cols-3 gap-4">
        <div className="col-span-2 space-y-1.5">
          <Label htmlFor="uf-city">Cidade</Label>
          <Input id="uf-city" placeholder="São Paulo" value={form.city}
            onChange={e => onChange("city", e.target.value)} disabled={disabled} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="uf-state">UF</Label>
          <Input id="uf-state" placeholder="SP" maxLength={2} value={form.state}
            onChange={e => onChange("state", e.target.value.toUpperCase())} disabled={disabled} />
        </div>
      </div>
      <div className="space-y-1.5">
        <Label>Fuso horário</Label>
        <Select value={form.timezone} onValueChange={v => v && onChange("timezone", v)} disabled={disabled}>
          <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
          <SelectContent>{TIMEZONES.map(tz => <SelectItem key={tz.value} value={tz.value}>{tz.label}</SelectItem>)}</SelectContent>
        </Select>
      </div>
      {showIsActive && (
        <label className="flex items-center gap-3 cursor-pointer">
          <input type="checkbox" className="h-4 w-4 rounded border-input accent-primary"
            checked={form.isActive} onChange={e => onChange("isActive", e.target.checked)} disabled={disabled} />
          <span className="text-sm font-medium">Unidade ativa</span>
        </label>
      )}
    </div>
  );
}

interface ScheduleEditorProps {
  schedule: DaySchedule[];
  onChange: (schedule: DaySchedule[]) => void;
  disabled?: boolean;
}

function UnitScheduleEditor({ schedule, onChange, disabled }: ScheduleEditorProps) {
  function update(dayOfWeek: number, field: keyof DaySchedule, value: string | boolean) {
    onChange(schedule.map(d => d.dayOfWeek === dayOfWeek ? { ...d, [field]: value } : d));
  }

  function applyToWeekdays() {
    const mon = schedule.find(d => d.dayOfWeek === 1);
    if (!mon) return;
    onChange(schedule.map(d =>
      d.dayOfWeek >= 1 && d.dayOfWeek <= 5
        ? { ...d, openTime: mon.openTime, closeTime: mon.closeTime, isOpen: true }
        : d
    ));
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-xs text-muted-foreground">Configure os horários de funcionamento da unidade para cada dia da semana.</p>
        <Button type="button" variant="outline" size="sm" className="text-xs h-7" onClick={applyToWeekdays} disabled={disabled}>
          Replicar seg–sex
        </Button>
      </div>
      <div className="space-y-1">
        {DAYS.map(day => {
          const d = schedule.find(s => s.dayOfWeek === day.value) ?? DEFAULT_SCHEDULE.find(s => s.dayOfWeek === day.value)!;
          return (
            <div key={day.value}
              className={`flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors
                ${d.isOpen ? "bg-background" : "bg-muted/40"}`}>
              {/* Toggle */}
              <label className="relative inline-flex items-center cursor-pointer shrink-0" title={d.isOpen ? "Aberto" : "Fechado"}>
                <input
                  type="checkbox"
                  className="sr-only peer"
                  checked={d.isOpen}
                  onChange={e => update(day.value, "isOpen", e.target.checked)}
                  disabled={disabled}
                />
                <div className="w-9 h-5 bg-muted rounded-full peer peer-checked:bg-primary transition-colors
                  after:content-[''] after:absolute after:top-0.5 after:left-0.5
                  after:bg-white after:rounded-full after:h-4 after:w-4
                  after:transition-transform peer-checked:after:translate-x-4" />
              </label>

              {/* Day name */}
              <span className={`text-sm w-28 font-medium shrink-0 ${!d.isOpen ? "text-muted-foreground" : ""}`}>
                {day.label}
              </span>

              {/* Times */}
              {d.isOpen ? (
                <div className="flex items-center gap-2 flex-1">
                  <Input
                    type="time"
                    value={d.openTime}
                    onChange={e => update(day.value, "openTime", e.target.value)}
                    disabled={disabled}
                    className="h-8 w-28 text-sm"
                  />
                  <span className="text-muted-foreground text-xs">até</span>
                  <Input
                    type="time"
                    value={d.closeTime}
                    onChange={e => update(day.value, "closeTime", e.target.value)}
                    disabled={disabled}
                    className="h-8 w-28 text-sm"
                  />
                </div>
              ) : (
                <span className="text-xs text-muted-foreground flex-1">Fechado</span>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

// ─── Tab Button ───────────────────────────────────────────────────────────────

function TabButton({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors
        ${active
          ? "border-primary text-foreground"
          : "border-transparent text-muted-foreground hover:text-foreground hover:border-border"
        }`}
    >
      {children}
    </button>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function UnidadesPage() {
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(DEFAULT_PER_PAGE);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [createOpen, setCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState<UnitForm>(emptyForm);

  const [editId, setEditId] = useState<number | null>(null);
  const [editForm, setEditForm] = useState<UnitForm>(emptyForm);
  const [editTab, setEditTab] = useState<"dados" | "horarios">("dados");
  const [editSchedule, setEditSchedule] = useState<DaySchedule[]>(DEFAULT_SCHEDULE);

  const [deleteTarget, setDeleteTarget] = useState<{ id: number; name: string } | null>(null);

  const queryClient = useQueryClient();

  // ── Queries ──────────────────────────────────────────────────────────────

  const { data, isLoading, isError } = useQuery<UnitsResponse>({
    queryKey: ["units", debouncedSearch, page, perPage],
    queryFn: () =>
      fetch(`/api/units?search=${encodeURIComponent(debouncedSearch)}&page=${page}&perPage=${perPage}`)
        .then(r => { if (!r.ok) throw new Error(); return r.json(); }),
  });

  const { data: fullUnit, isLoading: loadingFull } = useQuery({
    queryKey: ["unit", editId],
    queryFn: () => fetch(`/api/units/${editId}`).then(async r => {
      if (!r.ok) throw new Error("Erro ao carregar unidade");
      return r.json();
    }),
    enabled: editId !== null,
  });

  useEffect(() => {
    if (!fullUnit) return;
    setEditForm({
      name:     fullUnit.name ?? "",
      phone:    fullUnit.phone ?? "",
      zipCode:  fullUnit.zipCode ?? "",
      address:  fullUnit.address ?? "",
      city:     fullUnit.city ?? "",
      state:    fullUnit.state ?? "",
      timezone: fullUnit.timezone ?? "America/Sao_Paulo",
      isActive: fullUnit.isActive ?? true,
    });
    setEditSchedule(mergeSchedule(fullUnit.workingHours ?? []));
  }, [fullUnit]);

  // ── Mutations ────────────────────────────────────────────────────────────

  const { mutate: createUnit, isPending: creating } = useMutation({
    mutationFn: (form: UnitForm) =>
      fetch("/api/units", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildCreatePayload(form)),
      }).then(async r => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao cadastrar unidade"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["units"] });
      setCreateOpen(false);
      setCreateForm(emptyForm);
      toast.success("Unidade cadastrada com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: updateUnit, isPending: updating } = useMutation({
    mutationFn: (form: UnitForm) =>
      fetch(`/api/units/${editId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildUpdatePayload(form)),
      }).then(async r => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao atualizar unidade"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["units"] });
      queryClient.invalidateQueries({ queryKey: ["unit", editId] });
      toast.success("Dados salvos!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: saveSchedule, isPending: savingSchedule } = useMutation({
    mutationFn: () =>
      fetch(`/api/units/${editId}/working-hours`, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ hours: editSchedule }),
      }).then(async r => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao salvar horários"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["unit", editId] });
      toast.success("Horários salvos!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: deleteUnit, isPending: deleting } = useMutation({
    mutationFn: (id: number) =>
      fetch(`/api/units/${id}`, { method: "DELETE" }).then(async r => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao excluir unidade"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["units"] });
      setDeleteTarget(null);
      toast.success("Unidade excluída com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  // ── Payload builders ─────────────────────────────────────────────────────

  function buildCreatePayload(form: UnitForm) {
    const payload: Record<string, unknown> = { name: form.name };
    if (form.phone)    payload.phone    = form.phone;
    if (form.zipCode)  payload.zipCode  = form.zipCode;
    if (form.address)  payload.address  = form.address;
    if (form.city)     payload.city     = form.city;
    if (form.state)    payload.state    = form.state;
    if (form.timezone) payload.timezone = form.timezone;
    return payload;
  }

  function buildUpdatePayload(form: UnitForm) {
    return {
      name:     form.name,
      phone:    form.phone    || null,
      zipCode:  form.zipCode  || null,
      address:  form.address  || null,
      city:     form.city     || null,
      state:    form.state    || null,
      timezone: form.timezone || null,
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
    createUnit(createForm);
  }

  function handleEditDadosSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (editForm.name.trim().length < 2) { toast.error("Nome deve ter pelo menos 2 caracteres"); return; }
    updateUnit(editForm);
  }

  function handleEditHorariosSubmit(e: React.FormEvent) {
    e.preventDefault();
    saveSchedule();
  }

  function openCreate() { setCreateForm(emptyForm); setCreateOpen(true); }
  function openEdit(id: number) {
    setEditForm(emptyForm);
    setEditSchedule(DEFAULT_SCHEDULE);
    setEditTab("dados");
    setEditId(id);
  }

  // ─────────────────────────────────────────────────────────────────────────

  const units = data?.data ?? [];
  const totalPages = data?.totalPages ?? 1;

  return (
    <>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold">Unidades</h1>
            <p className="text-muted-foreground text-sm">
              {isLoading ? "Carregando..." : `${data?.total ?? 0} unidades cadastradas`}
            </p>
          </div>
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4 mr-2" />Nova unidade
          </Button>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Building2 className="h-4 w-4" />Lista de unidades
            </CardTitle>
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input className="pl-9" placeholder="Buscar por nome, cidade ou endereço..."
                value={search} onChange={e => handleSearch(e.target.value)} />
            </div>
          </CardHeader>

          <CardContent className="p-0">
            {isLoading ? (
              <TableSkeleton />
            ) : isError ? (
              <div className="p-10 flex flex-col items-center gap-3 text-destructive">
                <AlertCircle className="h-8 w-8" />
                <p className="font-medium">Erro ao carregar unidades</p>
              </div>
            ) : units.length === 0 ? (
              <div className="p-12 flex flex-col items-center gap-3 text-center">
                <Building2 className="h-10 w-10 text-muted-foreground/40" />
                <div>
                  <p className="font-medium text-muted-foreground">
                    {debouncedSearch ? `Nenhum resultado para "${debouncedSearch}"` : "Nenhuma unidade cadastrada"}
                  </p>
                  {!debouncedSearch && (
                    <p className="text-sm text-muted-foreground/70 mt-1">Cadastre a primeira unidade para começar</p>
                  )}
                </div>
                {!debouncedSearch && (
                  <Button size="sm" className="mt-1" onClick={openCreate}>
                    <Plus className="h-4 w-4 mr-2" />Nova unidade
                  </Button>
                )}
              </div>
            ) : (
              <>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Unidade</TableHead>
                      <TableHead>Telefone</TableHead>
                      <TableHead>Localização</TableHead>
                      <TableHead>Fuso horário</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead className="w-44">Ações</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {units.map(unit => (
                      <TableRow key={unit.id} className="hover:bg-accent cursor-pointer"
                        onClick={() => openEdit(unit.id)}>
                        <TableCell className="font-medium">
                          <div className="flex items-center gap-2">
                            <Building2 className="h-4 w-4 text-muted-foreground shrink-0" />
                            <div>
                              <p>{unit.name}</p>
                              {unit.address && (
                                <p className="text-xs text-muted-foreground truncate max-w-[180px]">{unit.address}</p>
                              )}
                            </div>
                          </div>
                        </TableCell>
                        <TableCell>
                          {unit.phone ? (
                            <div className="flex items-center gap-1.5 text-muted-foreground">
                              <Phone className="h-3.5 w-3.5" />{unit.phone}
                            </div>
                          ) : "—"}
                        </TableCell>
                        <TableCell>
                          {unit.city || unit.state ? (
                            <div className="flex items-center gap-1.5 text-muted-foreground">
                              <MapPin className="h-3.5 w-3.5" />{formatAddress(unit)}
                            </div>
                          ) : "—"}
                        </TableCell>
                        <TableCell className="text-muted-foreground text-sm">
                          {getTimezoneLabel(unit.timezone)}
                        </TableCell>
                        <TableCell>
                          {unit.isActive
                            ? <Badge className="bg-green-500/10 text-green-700 border-green-500/20 hover:bg-green-500/10">Ativa</Badge>
                            : <Badge variant="secondary">Inativa</Badge>}
                        </TableCell>
                        <TableCell onClick={e => e.stopPropagation()}>
                          <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" className="h-8 px-3 text-xs" onClick={() => openEdit(unit.id)}>
                              <Pencil className="h-3.5 w-3.5 mr-1.5" />Editar
                            </Button>
                            <Button variant="outline" size="sm"
                              className="h-8 px-3 text-xs text-destructive border-destructive/30 hover:bg-destructive/10 hover:text-destructive"
                              onClick={() => setDeleteTarget({ id: unit.id, name: unit.name })}>
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

      {/* ── Cadastrar unidade ─────────────────────────────────────────────── */}
      <Sheet open={createOpen} onOpenChange={val => setCreateOpen(val)}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-lg">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Nova unidade</SheetTitle>
            <SheetDescription>Preencha os dados da unidade. Apenas o nome é obrigatório.</SheetDescription>
          </SheetHeader>
          <form onSubmit={handleCreateSubmit} className="flex flex-col flex-1 overflow-hidden">
            <div className="flex-1 overflow-y-auto px-6 py-5">
              <UnitFormFields form={createForm} onChange={(f, v) => setCreateForm(prev => ({ ...prev, [f]: v }))} disabled={creating} />
            </div>
            <SheetFooter className="px-6 py-4 border-t">
              <Button type="button" variant="outline" onClick={() => setCreateOpen(false)} disabled={creating}>Cancelar</Button>
              <Button type="submit" disabled={creating}>{creating ? "Salvando..." : "Cadastrar unidade"}</Button>
            </SheetFooter>
          </form>
        </SheetContent>
      </Sheet>

      {/* ── Editar unidade ────────────────────────────────────────────────── */}
      <Sheet open={editId !== null} onOpenChange={val => { if (!val) setEditId(null); }}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-xl">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Editar unidade</SheetTitle>
            <SheetDescription>Atualize os dados e os horários de funcionamento.</SheetDescription>
          </SheetHeader>

          {/* Tab switcher */}
          <div className="flex border-b px-6 shrink-0">
            <TabButton active={editTab === "dados"} onClick={() => setEditTab("dados")}>
              <Building2 className="h-3.5 w-3.5 inline mr-1.5" />Dados
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
          ) : editTab === "dados" ? (
            <form onSubmit={handleEditDadosSubmit} className="flex flex-col flex-1 overflow-hidden">
              <div className="flex-1 overflow-y-auto px-6 py-5">
                <UnitFormFields form={editForm} onChange={(f, v) => setEditForm(prev => ({ ...prev, [f]: v }))}
                  showIsActive disabled={updating} />
              </div>
              <SheetFooter className="px-6 py-4 border-t">
                <Button type="button" variant="outline" onClick={() => setEditId(null)} disabled={updating}>Cancelar</Button>
                <Button type="submit" disabled={updating}>{updating ? "Salvando..." : "Salvar dados"}</Button>
              </SheetFooter>
            </form>
          ) : (
            <form onSubmit={handleEditHorariosSubmit} className="flex flex-col flex-1 overflow-hidden">
              <div className="flex-1 overflow-y-auto px-6 py-5">
                <div className="space-y-1.5 mb-4">
                  <h3 className="text-sm font-semibold flex items-center gap-2">
                    <Clock className="h-4 w-4" />Horários de funcionamento
                  </h3>
                  <p className="text-xs text-muted-foreground">
                    Define quando a unidade está disponível para receber agendamentos.
                  </p>
                </div>
                <Separator className="mb-4" />
                <UnitScheduleEditor schedule={editSchedule} onChange={setEditSchedule} disabled={savingSchedule} />

                {/* Summary */}
                <div className="mt-4 p-3 rounded-lg bg-muted/50 text-xs text-muted-foreground">
                  <span className="font-medium text-foreground">Resumo: </span>
                  {countOpenDays(editSchedule)}
                  {" · "}
                  {editSchedule.filter(d => d.isOpen).map(d => {
                    const day = DAYS.find(x => x.value === d.dayOfWeek);
                    return `${day?.short} ${d.openTime}–${d.closeTime}`;
                  }).join(", ") || "Nenhum dia aberto"}
                </div>
              </div>
              <SheetFooter className="px-6 py-4 border-t">
                <Button type="button" variant="outline" onClick={() => setEditId(null)} disabled={savingSchedule}>Fechar</Button>
                <Button type="submit" disabled={savingSchedule}>{savingSchedule ? "Salvando..." : "Salvar horários"}</Button>
              </SheetFooter>
            </form>
          )}
        </SheetContent>
      </Sheet>

      {/* ── Confirmar exclusão ────────────────────────────────────────────── */}
      <AlertDialog open={deleteTarget !== null} onOpenChange={val => { if (!val) setDeleteTarget(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir unidade</AlertDialogTitle>
            <AlertDialogDescription>
              Tem certeza que deseja excluir <strong className="text-foreground">{deleteTarget?.name}</strong>?
              Esta ação não pode ser desfeita.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>Cancelar</AlertDialogCancel>
            <AlertDialogAction variant="destructive" disabled={deleting}
              onClick={() => deleteTarget && deleteUnit(deleteTarget.id)}>
              {deleting ? "Excluindo..." : "Excluir"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
