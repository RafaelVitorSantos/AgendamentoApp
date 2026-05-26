"use client";

import { useState, useRef, useEffect } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { format } from "date-fns";
import { ptBR } from "date-fns/locale";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
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
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import {
  Ban, Plus, Search, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight,
  AlertCircle, Pencil, Trash2, CalendarX, CalendarDays, Clock, Briefcase,
  Building2, Globe,
} from "lucide-react";

// ─── Types ────────────────────────────────────────────────────────────────────

interface Professional { id: number; name: string; color: string | null; }
interface Unit { id: number; name: string; }

interface ScheduleBlock {
  id: number;
  title: string;
  startDate: string;
  endDate: string;
  isAllDay: boolean;
  startTime: string | null;
  endTime: string | null;
  professionalId: number | null;
  unitId: number | null;
  professional?: { id: number; name: string; color: string | null } | null;
  unit?: { id: number; name: string } | null;
}

interface BlocksResponse {
  data: ScheduleBlock[];
  total: number;
  page: number;
  perPage: number;
  totalPages: number;
}

interface Holiday {
  id: number;
  name: string;
  date: string;
  isRecurring: boolean;
}

interface BlockForm {
  title: string;
  startDate: string;
  endDate: string;
  isAllDay: boolean;
  startTime: string;
  endTime: string;
  professionalId: string;
  unitId: string;
}

interface HolidayForm {
  name: string;
  date: string;
  isRecurring: boolean;
}

// ─── Constants ────────────────────────────────────────────────────────────────

const todayIso = () => new Date().toISOString().split("T")[0];

const emptyBlock: BlockForm = {
  title: "", startDate: todayIso(), endDate: todayIso(),
  isAllDay: true, startTime: "09:00", endTime: "18:00",
  professionalId: "", unitId: "",
};

const emptyHoliday: HolidayForm = { name: "", date: todayIso(), isRecurring: false };

const PER_PAGE_OPTIONS = [10, 20, 50];
const DEFAULT_PER_PAGE = 20;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function fmtDate(iso: string) {
  return format(new Date(iso), "dd/MM/yyyy");
}

function fmtDateShort(iso: string, recurring: boolean) {
  const d = new Date(iso);
  return recurring
    ? format(d, "dd 'de' MMMM", { locale: ptBR })
    : format(d, "dd/MM/yyyy");
}

function periodLabel(block: ScheduleBlock) {
  const start = fmtDate(block.startDate);
  const end = fmtDate(block.endDate);
  return start === end ? start : `${start} – ${end}`;
}

function timeLabel(block: ScheduleBlock) {
  if (block.isAllDay) return "Dia todo";
  if (block.startTime && block.endTime) return `${block.startTime} – ${block.endTime}`;
  return "—";
}

function initials(name: string) {
  return name.split(" ").slice(0, 2).map((w) => w[0]).join("").toUpperCase();
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

// ─── Sub-components ──────────────────────────────────────────────────────────

function TableSkeleton({ cols }: { cols: number }) {
  return (
    <Table>
      <TableBody>
        {Array.from({ length: 4 }).map((_, i) => (
          <TableRow key={i}>
            {Array.from({ length: cols }).map((__, j) => (
              <TableCell key={j}><div className="h-4 bg-muted animate-pulse rounded" style={{ width: `${60 + (j * 30) % 80}px` }} /></TableCell>
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
  noun: string;
}

function TablePagination({ page, totalPages, total, perPage, onPageChange, onPerPageChange, noun }: PaginationProps) {
  const start = total === 0 ? 0 : (page - 1) * perPage + 1;
  const end = Math.min(page * perPage, total);
  const pages = getPageNumbers(page, totalPages);
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t text-sm">
      <div className="flex items-center gap-3 text-muted-foreground">
        <span>{total === 0 ? `Nenhum ${noun}` : `Mostrando ${start}–${end} de ${total} ${noun}s`}</span>
        <div className="flex items-center gap-1.5">
          <span className="text-xs">Por página:</span>
          <Select value={String(perPage)} onValueChange={(v) => onPerPageChange(Number(v))}>
            <SelectTrigger className="h-7 w-[64px] text-xs"><SelectValue /></SelectTrigger>
            <SelectContent>
              {PER_PAGE_OPTIONS.map((n) => <SelectItem key={n} value={String(n)}>{n}</SelectItem>)}
            </SelectContent>
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

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function BloqueiosPage() {
  // ── Blocks state ────────────────────────────────────────────────────────
  const [blockSearch, setBlockSearch] = useState("");
  const [blockDebouncedSearch, setBlockDebouncedSearch] = useState("");
  const [blockScope, setBlockScope] = useState("all");
  const [blockPage, setBlockPage] = useState(1);
  const [blockPerPage, setBlockPerPage] = useState(DEFAULT_PER_PAGE);
  const blockTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [createBlockOpen, setCreateBlockOpen] = useState(false);
  const [createBlockForm, setCreateBlockForm] = useState<BlockForm>(emptyBlock);
  const [editBlockId, setEditBlockId] = useState<number | null>(null);
  const [editBlockForm, setEditBlockForm] = useState<BlockForm>(emptyBlock);
  const [deleteBlock, setDeleteBlock] = useState<{ id: number; title: string } | null>(null);

  // ── Holidays state ───────────────────────────────────────────────────────
  const [createHolidayOpen, setCreateHolidayOpen] = useState(false);
  const [createHolidayForm, setCreateHolidayForm] = useState<HolidayForm>(emptyHoliday);
  const [editHolidayId, setEditHolidayId] = useState<number | null>(null);
  const [editHolidayForm, setEditHolidayForm] = useState<HolidayForm>(emptyHoliday);
  const [deleteHoliday, setDeleteHoliday] = useState<{ id: number; name: string } | null>(null);

  const queryClient = useQueryClient();

  // ── Queries ──────────────────────────────────────────────────────────────

  const blockParams = new URLSearchParams({
    search: blockDebouncedSearch, scope: blockScope,
    page: String(blockPage), perPage: String(blockPerPage),
  });

  const { data: blocksData, isLoading: loadingBlocks } = useQuery<BlocksResponse>({
    queryKey: ["schedule-blocks", blockDebouncedSearch, blockScope, blockPage, blockPerPage],
    queryFn: () => fetch(`/api/schedule-blocks?${blockParams}`).then((r) => r.json()),
  });

  const { data: holidays = [], isLoading: loadingHolidays } = useQuery<Holiday[]>({
    queryKey: ["holidays"],
    queryFn: () => fetch("/api/holidays").then((r) => r.json()),
  });

  const { data: professionals = [] } = useQuery<{ data: Professional[] }>({
    queryKey: ["professionals-list"],
    queryFn: () => fetch("/api/professionals?perPage=100").then((r) => r.json()),
    select: (d) => d,
  });

  const { data: units = [] } = useQuery<{ data: Unit[] }>({
    queryKey: ["units-list"],
    queryFn: () => fetch("/api/units?perPage=100").then((r) => r.json()),
    select: (d) => d,
  });

  const profList = (professionals as unknown as { data: Professional[] })?.data ?? [];
  const unitList = (units as unknown as { data: Unit[] })?.data ?? [];

  const { data: fullBlock, isLoading: loadingFullBlock } = useQuery<ScheduleBlock>({
    queryKey: ["schedule-block", editBlockId],
    queryFn: () => fetch(`/api/schedule-blocks/${editBlockId}`).then((r) => r.json()),
    enabled: editBlockId !== null,
  });

  useEffect(() => {
    if (!fullBlock) return;
    setEditBlockForm({
      title: fullBlock.title,
      startDate: fullBlock.startDate.split("T")[0],
      endDate: fullBlock.endDate.split("T")[0],
      isAllDay: fullBlock.isAllDay,
      startTime: fullBlock.startTime ?? "09:00",
      endTime: fullBlock.endTime ?? "18:00",
      professionalId: fullBlock.professionalId ? String(fullBlock.professionalId) : "",
      unitId: fullBlock.unitId ? String(fullBlock.unitId) : "",
    });
  }, [fullBlock]);

  // ── Block mutations ──────────────────────────────────────────────────────

  const { mutate: createBlock, isPending: creatingBlock } = useMutation({
    mutationFn: (form: BlockForm) =>
      fetch("/api/schedule-blocks", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildBlockPayload(form)),
      }).then(async (r) => { if (!r.ok) { const e = await r.json(); throw new Error(e.error); } return r.json(); }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["schedule-blocks"] });
      setCreateBlockOpen(false);
      setCreateBlockForm(emptyBlock);
      toast.success("Bloqueio criado com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: updateBlock, isPending: updatingBlock } = useMutation({
    mutationFn: (form: BlockForm) =>
      fetch(`/api/schedule-blocks/${editBlockId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildBlockPayload(form)),
      }).then(async (r) => { if (!r.ok) { const e = await r.json(); throw new Error(e.error); } return r.json(); }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["schedule-blocks"] });
      queryClient.invalidateQueries({ queryKey: ["schedule-block", editBlockId] });
      setEditBlockId(null);
      toast.success("Bloqueio atualizado com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: removeBlock, isPending: deletingBlock } = useMutation({
    mutationFn: (id: number) =>
      fetch(`/api/schedule-blocks/${id}`, { method: "DELETE" }).then(async (r) => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error); } return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["schedule-blocks"] });
      setDeleteBlock(null);
      toast.success("Bloqueio excluído com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  // ── Holiday mutations ────────────────────────────────────────────────────

  const { mutate: createHoliday, isPending: creatingHoliday } = useMutation({
    mutationFn: (form: HolidayForm) =>
      fetch("/api/holidays", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name: form.name, date: form.date, isRecurring: form.isRecurring }),
      }).then(async (r) => { if (!r.ok) { const e = await r.json(); throw new Error(e.error); } return r.json(); }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["holidays"] });
      setCreateHolidayOpen(false);
      setCreateHolidayForm(emptyHoliday);
      toast.success("Feriado cadastrado com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: updateHoliday, isPending: updatingHoliday } = useMutation({
    mutationFn: (form: HolidayForm) =>
      fetch(`/api/holidays/${editHolidayId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name: form.name, date: form.date, isRecurring: form.isRecurring }),
      }).then(async (r) => { if (!r.ok) { const e = await r.json(); throw new Error(e.error); } return r.json(); }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["holidays"] });
      setEditHolidayId(null);
      toast.success("Feriado atualizado com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: removeHoliday, isPending: deletingHoliday } = useMutation({
    mutationFn: (id: number) =>
      fetch(`/api/holidays/${id}`, { method: "DELETE" }).then(async (r) => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error); } return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["holidays"] });
      setDeleteHoliday(null);
      toast.success("Feriado excluído com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  // ── Payload builders ─────────────────────────────────────────────────────

  function buildBlockPayload(form: BlockForm) {
    return {
      title: form.title,
      startDate: form.startDate,
      endDate: form.endDate,
      isAllDay: form.isAllDay,
      startTime: form.isAllDay ? null : form.startTime || null,
      endTime: form.isAllDay ? null : form.endTime || null,
      professionalId: form.professionalId ? parseInt(form.professionalId) : null,
      unitId: form.unitId ? parseInt(form.unitId) : null,
    };
  }

  // ── Handlers ─────────────────────────────────────────────────────────────

  function handleBlockSearch(value: string) {
    setBlockSearch(value);
    if (blockTimer.current) clearTimeout(blockTimer.current);
    blockTimer.current = setTimeout(() => { setBlockDebouncedSearch(value); setBlockPage(1); }, 400);
  }

  function handleBlockField(field: keyof BlockForm, value: string | boolean) {
    setCreateBlockForm((f) => ({ ...f, [field]: value }));
  }

  function handleEditBlockField(field: keyof BlockForm, value: string | boolean) {
    setEditBlockForm((f) => ({ ...f, [field]: value }));
  }

  function handleHolidayField(field: keyof HolidayForm, value: string | boolean) {
    setCreateHolidayForm((f) => ({ ...f, [field]: value }));
  }

  function handleEditHolidayField(field: keyof HolidayForm, value: string | boolean) {
    setEditHolidayForm((f) => ({ ...f, [field]: value }));
  }

  function handleBlockSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!createBlockForm.title.trim()) { toast.error("Título é obrigatório"); return; }
    if (!createBlockForm.startDate) { toast.error("Data de início é obrigatória"); return; }
    if (!createBlockForm.endDate) { toast.error("Data de fim é obrigatória"); return; }
    createBlock(createBlockForm);
  }

  function handleEditBlockSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!editBlockForm.title.trim()) { toast.error("Título é obrigatório"); return; }
    updateBlock(editBlockForm);
  }

  function handleHolidaySubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!createHolidayForm.name.trim()) { toast.error("Nome é obrigatório"); return; }
    if (!createHolidayForm.date) { toast.error("Data é obrigatória"); return; }
    createHoliday(createHolidayForm);
  }

  function handleEditHolidaySubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!editHolidayForm.name.trim()) { toast.error("Nome é obrigatório"); return; }
    updateHoliday(editHolidayForm);
  }

  // ── Block form component ─────────────────────────────────────────────────

  function BlockFormFields({
    form, onChange, disabled,
  }: { form: BlockForm; onChange: (f: keyof BlockForm, v: string | boolean) => void; disabled?: boolean }) {
    return (
      <div className="space-y-5">
        <div className="space-y-1.5">
          <Label htmlFor="bf-title">Título <span className="text-destructive">*</span></Label>
          <Input id="bf-title" placeholder="Ex: Férias, Manutenção, Reunião..." value={form.title}
            onChange={(e) => onChange("title", e.target.value)} disabled={disabled} autoFocus />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label htmlFor="bf-start">Data início <span className="text-destructive">*</span></Label>
            <Input id="bf-start" type="date" value={form.startDate}
              onChange={(e) => { onChange("startDate", e.target.value); if (e.target.value > form.endDate) onChange("endDate", e.target.value); }}
              disabled={disabled} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="bf-end">Data fim <span className="text-destructive">*</span></Label>
            <Input id="bf-end" type="date" value={form.endDate} min={form.startDate}
              onChange={(e) => onChange("endDate", e.target.value)} disabled={disabled} />
          </div>
        </div>

        <label className="flex items-center gap-3 cursor-pointer">
          <input type="checkbox" className="h-4 w-4 rounded border-input accent-primary"
            checked={form.isAllDay} onChange={(e) => onChange("isAllDay", e.target.checked)} disabled={disabled} />
          <span className="text-sm font-medium">Bloquear o dia todo</span>
        </label>

        {!form.isAllDay && (
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="bf-stime">Hora início</Label>
              <Input id="bf-stime" type="time" value={form.startTime}
                onChange={(e) => onChange("startTime", e.target.value)} disabled={disabled} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="bf-etime">Hora fim</Label>
              <Input id="bf-etime" type="time" value={form.endTime}
                onChange={(e) => onChange("endTime", e.target.value)} disabled={disabled} />
            </div>
          </div>
        )}

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label>Profissional</Label>
            <Select
              value={form.professionalId || "none"}
              onValueChange={(v) => onChange("professionalId", v === "none" || !v ? "" : v)}
              disabled={disabled}
              items={{ none: "Todos (geral)", ...Object.fromEntries(profList.map((p) => [String(p.id), p.name])) }}>
              <SelectTrigger className="w-full"><SelectValue placeholder="Todos" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="none">Todos (geral)</SelectItem>
                {profList.map((p) => <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>Unidade</Label>
            <Select
              value={form.unitId || "none"}
              onValueChange={(v) => onChange("unitId", v === "none" || !v ? "" : v)}
              disabled={disabled}
              items={{ none: "Todas (geral)", ...Object.fromEntries(unitList.map((u) => [String(u.id), u.name])) }}>
              <SelectTrigger className="w-full"><SelectValue placeholder="Todas" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="none">Todas (geral)</SelectItem>
                {unitList.map((u) => <SelectItem key={u.id} value={String(u.id)}>{u.name}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
        </div>
      </div>
    );
  }

  // ── Holiday form component ────────────────────────────────────────────────

  function HolidayFormFields({
    form, onChange, disabled,
  }: { form: HolidayForm; onChange: (f: keyof HolidayForm, v: string | boolean) => void; disabled?: boolean }) {
    return (
      <div className="space-y-5">
        <div className="space-y-1.5">
          <Label htmlFor="hf-name">Nome <span className="text-destructive">*</span></Label>
          <Input id="hf-name" placeholder="Ex: Natal, Carnaval, Aniversário da empresa..." value={form.name}
            onChange={(e) => onChange("name", e.target.value)} disabled={disabled} autoFocus />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="hf-date">Data <span className="text-destructive">*</span></Label>
          <Input id="hf-date" type="date" value={form.date}
            onChange={(e) => onChange("date", e.target.value)} disabled={disabled} />
        </div>

        <label className="flex items-center gap-3 cursor-pointer">
          <input type="checkbox" className="h-4 w-4 rounded border-input accent-primary"
            checked={form.isRecurring} onChange={(e) => onChange("isRecurring", e.target.checked)} disabled={disabled} />
          <div>
            <span className="text-sm font-medium">Repetir anualmente</span>
            <p className="text-xs text-muted-foreground">O feriado se repete todo ano nessa data</p>
          </div>
        </label>
      </div>
    );
  }

  // ─────────────────────────────────────────────────────────────────────────

  const blocks = blocksData?.data ?? [];
  const blockTotalPages = blocksData?.totalPages ?? 1;

  const scopeBadge = (b: ScheduleBlock) => {
    if (b.professional) return { label: b.professional.name, color: b.professional.color ?? "#6366f1", icon: <Briefcase className="h-3 w-3" /> };
    if (b.unit)         return { label: b.unit.name, color: "#64748b", icon: <Building2 className="h-3 w-3" /> };
    return { label: "Geral", color: "#64748b", icon: <Globe className="h-3 w-3" /> };
  };

  return (
    <>
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold">Bloqueios</h1>
          <p className="text-muted-foreground text-sm">Gerencie períodos bloqueados na agenda e feriados</p>
        </div>

        <Tabs defaultValue="bloqueios">
          <TabsList>
            <TabsTrigger value="bloqueios">
              <Ban className="h-4 w-4" />
              Bloqueios de agenda
            </TabsTrigger>
            <TabsTrigger value="feriados">
              <CalendarX className="h-4 w-4" />
              Feriados
            </TabsTrigger>
          </TabsList>

          {/* ── Bloqueios tab ─────────────────────────────────────────────── */}
          <TabsContent value="bloqueios">
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="flex items-center gap-2 text-base">
                    <Ban className="h-4 w-4" />
                    Bloqueios de agenda
                  </CardTitle>
                  <Button size="sm" onClick={() => { setCreateBlockForm(emptyBlock); setCreateBlockOpen(true); }}>
                    <Plus className="h-4 w-4 mr-1.5" />Novo bloqueio
                  </Button>
                </div>
                <div className="flex flex-wrap gap-3 mt-2">
                  <div className="relative flex-1 min-w-[200px]">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input className="pl-9" placeholder="Buscar por título..."
                      value={blockSearch} onChange={(e) => handleBlockSearch(e.target.value)} />
                  </div>
                  <Select
                    value={blockScope}
                    onValueChange={(v) => { setBlockScope(v ?? "all"); setBlockPage(1); }}
                    items={{ all: "Todos", professional: "Por profissional", unit: "Por unidade", general: "Gerais" }}>
                    <SelectTrigger className="h-9 w-[150px] text-sm"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">Todos</SelectItem>
                      <SelectItem value="professional">Por profissional</SelectItem>
                      <SelectItem value="unit">Por unidade</SelectItem>
                      <SelectItem value="general">Gerais</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </CardHeader>

              <CardContent className="p-0">
                {loadingBlocks ? (
                  <TableSkeleton cols={6} />
                ) : blocks.length === 0 ? (
                  <div className="p-12 flex flex-col items-center gap-3 text-center">
                    <Ban className="h-10 w-10 text-muted-foreground/40" />
                    <div>
                      <p className="font-medium text-muted-foreground">
                        {blockDebouncedSearch ? `Nenhum resultado para "${blockDebouncedSearch}"` : "Nenhum bloqueio cadastrado"}
                      </p>
                      {!blockDebouncedSearch && (
                        <p className="text-xs text-muted-foreground/70 mt-1">Bloqueie períodos para impedir novos agendamentos</p>
                      )}
                    </div>
                    {!blockDebouncedSearch && (
                      <Button size="sm" onClick={() => { setCreateBlockForm(emptyBlock); setCreateBlockOpen(true); }}>
                        <Plus className="h-4 w-4 mr-1.5" />Novo bloqueio
                      </Button>
                    )}
                  </div>
                ) : (
                  <>
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Título</TableHead>
                          <TableHead>Escopo</TableHead>
                          <TableHead>Período</TableHead>
                          <TableHead>Horário</TableHead>
                          <TableHead className="w-44">Ações</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {blocks.map((b) => {
                          const scope = scopeBadge(b);
                          return (
                            <TableRow key={b.id} className="hover:bg-accent cursor-pointer"
                              onClick={() => { setEditBlockForm(emptyBlock); setEditBlockId(b.id); }}>
                              <TableCell className="font-medium">{b.title}</TableCell>
                              <TableCell>
                                <div className="flex items-center gap-1.5">
                                  <div className="w-5 h-5 rounded-full flex items-center justify-center text-white text-[10px] font-bold shrink-0"
                                    style={{ backgroundColor: scope.color }}>
                                    {b.professional ? initials(b.professional.name) : scope.icon}
                                  </div>
                                  <span className="text-sm text-muted-foreground">{scope.label}</span>
                                </div>
                              </TableCell>
                              <TableCell>
                                <div className="flex items-center gap-1.5 text-sm">
                                  <CalendarDays className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                                  {periodLabel(b)}
                                </div>
                              </TableCell>
                              <TableCell>
                                <div className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                  <Clock className="h-3.5 w-3.5 shrink-0" />
                                  {timeLabel(b)}
                                </div>
                              </TableCell>
                              <TableCell onClick={(e) => e.stopPropagation()}>
                                <div className="flex items-center gap-2">
                                  <Button variant="outline" size="sm" className="h-8 px-3 text-xs"
                                    onClick={() => { setEditBlockForm(emptyBlock); setEditBlockId(b.id); }}>
                                    <Pencil className="h-3.5 w-3.5 mr-1.5" />Editar
                                  </Button>
                                  <Button variant="outline" size="sm"
                                    className="h-8 px-3 text-xs text-destructive border-destructive/30 hover:bg-destructive/10 hover:text-destructive hover:border-destructive/50"
                                    onClick={() => setDeleteBlock({ id: b.id, title: b.title })}>
                                    <Trash2 className="h-3.5 w-3.5 mr-1.5" />Excluir
                                  </Button>
                                </div>
                              </TableCell>
                            </TableRow>
                          );
                        })}
                      </TableBody>
                    </Table>
                    <TablePagination
                      page={blockPage} totalPages={blockTotalPages} total={blocksData?.total ?? 0}
                      perPage={blockPerPage} onPageChange={setBlockPage}
                      onPerPageChange={(n) => { setBlockPerPage(n); setBlockPage(1); }}
                      noun="bloqueio"
                    />
                  </>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* ── Feriados tab ──────────────────────────────────────────────── */}
          <TabsContent value="feriados">
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="flex items-center gap-2 text-base">
                    <CalendarX className="h-4 w-4" />
                    Feriados e dias especiais
                  </CardTitle>
                  <Button size="sm" onClick={() => { setCreateHolidayForm(emptyHoliday); setCreateHolidayOpen(true); }}>
                    <Plus className="h-4 w-4 mr-1.5" />Novo feriado
                  </Button>
                </div>
              </CardHeader>

              <CardContent className="p-0">
                {loadingHolidays ? (
                  <TableSkeleton cols={4} />
                ) : holidays.length === 0 ? (
                  <div className="p-12 flex flex-col items-center gap-3 text-center">
                    <CalendarX className="h-10 w-10 text-muted-foreground/40" />
                    <div>
                      <p className="font-medium text-muted-foreground">Nenhum feriado cadastrado</p>
                      <p className="text-xs text-muted-foreground/70 mt-1">Cadastre feriados para bloquear datas automaticamente</p>
                    </div>
                    <Button size="sm" onClick={() => { setCreateHolidayForm(emptyHoliday); setCreateHolidayOpen(true); }}>
                      <Plus className="h-4 w-4 mr-1.5" />Novo feriado
                    </Button>
                  </div>
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Nome</TableHead>
                        <TableHead>Data</TableHead>
                        <TableHead>Recorrência</TableHead>
                        <TableHead className="w-44">Ações</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {holidays.map((h) => (
                        <TableRow key={h.id} className="hover:bg-accent cursor-pointer"
                          onClick={() => {
                            setEditHolidayForm({ name: h.name, date: h.date.split("T")[0], isRecurring: h.isRecurring });
                            setEditHolidayId(h.id);
                          }}>
                          <TableCell className="font-medium">{h.name}</TableCell>
                          <TableCell className="text-muted-foreground">
                            <div className="flex items-center gap-1.5">
                              <CalendarDays className="h-3.5 w-3.5 shrink-0" />
                              {fmtDateShort(h.date, h.isRecurring)}
                            </div>
                          </TableCell>
                          <TableCell>
                            {h.isRecurring ? (
                              <Badge className="bg-blue-500/10 text-blue-700 border-blue-500/20 hover:bg-blue-500/10 text-xs">
                                Todo ano
                              </Badge>
                            ) : (
                              <Badge variant="secondary" className="text-xs">Uma vez</Badge>
                            )}
                          </TableCell>
                          <TableCell onClick={(e) => e.stopPropagation()}>
                            <div className="flex items-center gap-2">
                              <Button variant="outline" size="sm" className="h-8 px-3 text-xs"
                                onClick={() => {
                                  setEditHolidayForm({ name: h.name, date: h.date.split("T")[0], isRecurring: h.isRecurring });
                                  setEditHolidayId(h.id);
                                }}>
                                <Pencil className="h-3.5 w-3.5 mr-1.5" />Editar
                              </Button>
                              <Button variant="outline" size="sm"
                                className="h-8 px-3 text-xs text-destructive border-destructive/30 hover:bg-destructive/10 hover:text-destructive hover:border-destructive/50"
                                onClick={() => setDeleteHoliday({ id: h.id, name: h.name })}>
                                <Trash2 className="h-3.5 w-3.5 mr-1.5" />Excluir
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>

      {/* ── Criar bloqueio ─────────────────────────────────────────────────── */}
      <Sheet open={createBlockOpen} onOpenChange={(val) => setCreateBlockOpen(val)}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-lg">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Novo bloqueio</SheetTitle>
            <SheetDescription>Bloqueie um período na agenda para impedir novos agendamentos.</SheetDescription>
          </SheetHeader>
          <form onSubmit={handleBlockSubmit} className="flex flex-col flex-1 overflow-hidden">
            <div className="flex-1 overflow-y-auto px-6 py-5">
              <BlockFormFields form={createBlockForm} onChange={handleBlockField} disabled={creatingBlock} />
            </div>
            <SheetFooter className="px-6 py-4 border-t">
              <Button type="button" variant="outline" onClick={() => setCreateBlockOpen(false)} disabled={creatingBlock}>Cancelar</Button>
              <Button type="submit" disabled={creatingBlock}>{creatingBlock ? "Salvando..." : "Criar bloqueio"}</Button>
            </SheetFooter>
          </form>
        </SheetContent>
      </Sheet>

      {/* ── Editar bloqueio ───────────────────────────────────────────────── */}
      <Sheet open={editBlockId !== null} onOpenChange={(val) => { if (!val) setEditBlockId(null); }}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-lg">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Editar bloqueio</SheetTitle>
            <SheetDescription>Atualize os dados do bloqueio.</SheetDescription>
          </SheetHeader>
          {loadingFullBlock ? (
            <div className="flex-1 flex items-center justify-center">
              <div className="flex flex-col items-center gap-3 text-muted-foreground">
                <div className="h-6 w-6 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                <span className="text-sm">Carregando...</span>
              </div>
            </div>
          ) : (
            <form onSubmit={handleEditBlockSubmit} className="flex flex-col flex-1 overflow-hidden">
              <div className="flex-1 overflow-y-auto px-6 py-5">
                <BlockFormFields form={editBlockForm} onChange={handleEditBlockField} disabled={updatingBlock} />
              </div>
              <SheetFooter className="px-6 py-4 border-t">
                <Button type="button" variant="outline" onClick={() => setEditBlockId(null)} disabled={updatingBlock}>Cancelar</Button>
                <Button type="submit" disabled={updatingBlock}>{updatingBlock ? "Salvando..." : "Salvar alterações"}</Button>
              </SheetFooter>
            </form>
          )}
        </SheetContent>
      </Sheet>

      {/* ── Criar feriado ─────────────────────────────────────────────────── */}
      <Sheet open={createHolidayOpen} onOpenChange={(val) => setCreateHolidayOpen(val)}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-md">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Novo feriado</SheetTitle>
            <SheetDescription>Cadastre um feriado ou dia especial.</SheetDescription>
          </SheetHeader>
          <form onSubmit={handleHolidaySubmit} className="flex flex-col flex-1 overflow-hidden">
            <div className="flex-1 overflow-y-auto px-6 py-5">
              <HolidayFormFields form={createHolidayForm} onChange={handleHolidayField} disabled={creatingHoliday} />
            </div>
            <SheetFooter className="px-6 py-4 border-t">
              <Button type="button" variant="outline" onClick={() => setCreateHolidayOpen(false)} disabled={creatingHoliday}>Cancelar</Button>
              <Button type="submit" disabled={creatingHoliday}>{creatingHoliday ? "Salvando..." : "Cadastrar feriado"}</Button>
            </SheetFooter>
          </form>
        </SheetContent>
      </Sheet>

      {/* ── Editar feriado ────────────────────────────────────────────────── */}
      <Sheet open={editHolidayId !== null} onOpenChange={(val) => { if (!val) setEditHolidayId(null); }}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-md">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Editar feriado</SheetTitle>
            <SheetDescription>Atualize os dados do feriado.</SheetDescription>
          </SheetHeader>
          <form onSubmit={handleEditHolidaySubmit} className="flex flex-col flex-1 overflow-hidden">
            <div className="flex-1 overflow-y-auto px-6 py-5">
              <HolidayFormFields form={editHolidayForm} onChange={handleEditHolidayField} disabled={updatingHoliday} />
            </div>
            <SheetFooter className="px-6 py-4 border-t">
              <Button type="button" variant="outline" onClick={() => setEditHolidayId(null)} disabled={updatingHoliday}>Cancelar</Button>
              <Button type="submit" disabled={updatingHoliday}>{updatingHoliday ? "Salvando..." : "Salvar alterações"}</Button>
            </SheetFooter>
          </form>
        </SheetContent>
      </Sheet>

      {/* ── Confirmar exclusão de bloqueio ───────────────────────────────── */}
      <AlertDialog open={deleteBlock !== null} onOpenChange={(val) => { if (!val) setDeleteBlock(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir bloqueio</AlertDialogTitle>
            <AlertDialogDescription>
              Tem certeza que deseja excluir o bloqueio <strong className="text-foreground">{deleteBlock?.title}</strong>?
              Esta ação não pode ser desfeita.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deletingBlock}>Cancelar</AlertDialogCancel>
            <AlertDialogAction variant="destructive" disabled={deletingBlock}
              onClick={() => deleteBlock && removeBlock(deleteBlock.id)}>
              {deletingBlock ? "Excluindo..." : "Excluir"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* ── Confirmar exclusão de feriado ────────────────────────────────── */}
      <AlertDialog open={deleteHoliday !== null} onOpenChange={(val) => { if (!val) setDeleteHoliday(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir feriado</AlertDialogTitle>
            <AlertDialogDescription>
              Tem certeza que deseja excluir <strong className="text-foreground">{deleteHoliday?.name}</strong>?
              Esta ação não pode ser desfeita.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deletingHoliday}>Cancelar</AlertDialogCancel>
            <AlertDialogAction variant="destructive" disabled={deletingHoliday}
              onClick={() => deleteHoliday && removeHoliday(deleteHoliday.id)}>
              {deletingHoliday ? "Excluindo..." : "Excluir"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
