"use client";

import { useState, useRef, useEffect } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Textarea } from "@/components/ui/textarea";
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
  Search, Plus, Scissors, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight,
  AlertCircle, Pencil, Trash2, Clock, Globe,
} from "lucide-react";

// ─── Types ───────────────────────────────────────────────────────────────────

interface Category {
  id: number;
  name: string;
  color: string | null;
}

interface Service {
  id: number;
  name: string;
  description: string | null;
  duration: number;
  price: number;
  color: string | null;
  commissionType: string | null;
  commissionValue: number | null;
  allowOnlineBooking: boolean;
  isActive: boolean;
  category: Category | null;
}

interface ServicesResponse {
  data: Service[];
  total: number;
  page: number;
  perPage: number;
  totalPages: number;
}

interface ServiceForm {
  name: string;
  description: string;
  categoryId: string;
  duration: string;
  price: string;
  color: string;
  commissionType: "" | "percentage" | "fixed";
  commissionValue: string;
  allowOnlineBooking: boolean;
  isActive: boolean;
}

// ─── Constants ───────────────────────────────────────────────────────────────

const emptyForm: ServiceForm = {
  name: "", description: "", categoryId: "", duration: "60", price: "",
  color: "#6366f1", commissionType: "", commissionValue: "",
  allowOnlineBooking: true, isActive: true,
};

const PER_PAGE_OPTIONS = [10, 20, 50, 100];
const DEFAULT_PER_PAGE = 20;

const DURATION_OPTIONS = [
  { value: "15",  label: "15 min" },
  { value: "30",  label: "30 min" },
  { value: "45",  label: "45 min" },
  { value: "60",  label: "1h" },
  { value: "75",  label: "1h 15min" },
  { value: "90",  label: "1h 30min" },
  { value: "105", label: "1h 45min" },
  { value: "120", label: "2h" },
  { value: "150", label: "2h 30min" },
  { value: "180", label: "3h" },
  { value: "240", label: "4h" },
];

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatDuration(minutes: number): string {
  if (minutes < 60) return `${minutes} min`;
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return m === 0 ? `${h}h` : `${h}h ${m}min`;
}

function formatCurrency(value: number): string {
  return Number(value).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

function formatCommission(type: string | null, value: number | null): string {
  if (!type || value == null) return "—";
  return type === "percentage" ? `${value}%` : formatCurrency(value);
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

function TableSkeleton() {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Serviço</TableHead>
          <TableHead>Categoria</TableHead>
          <TableHead>Duração</TableHead>
          <TableHead>Preço</TableHead>
          <TableHead>Comissão</TableHead>
          <TableHead>Online</TableHead>
          <TableHead>Status</TableHead>
          <TableHead className="w-44">Ações</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {Array.from({ length: 6 }).map((_, i) => (
          <TableRow key={i}>
            <TableCell>
              <div className="flex items-center gap-3">
                <div className="h-3 w-3 rounded-full bg-muted animate-pulse shrink-0" />
                <div className="h-4 w-32 bg-muted animate-pulse rounded" />
              </div>
            </TableCell>
            {Array.from({ length: 5 }).map((__, j) => (
              <TableCell key={j}><div className="h-4 w-20 bg-muted animate-pulse rounded" /></TableCell>
            ))}
            <TableCell><div className="h-5 w-14 bg-muted animate-pulse rounded-full" /></TableCell>
            <TableCell><div className="h-5 w-14 bg-muted animate-pulse rounded-full" /></TableCell>
            <TableCell>
              <div className="flex gap-2">
                <div className="h-8 w-16 bg-muted animate-pulse rounded-md" />
                <div className="h-8 w-16 bg-muted animate-pulse rounded-md" />
              </div>
            </TableCell>
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
        <span>{total === 0 ? "Nenhum registro" : `Mostrando ${start}–${end} de ${total} serviços`}</span>
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

interface FormFieldsProps {
  form: ServiceForm;
  onChange: (field: keyof ServiceForm, value: string | boolean) => void;
  categories: Category[];
  showIsActive?: boolean;
  disabled?: boolean;
}

function ServiceFormFields({ form, onChange, categories, showIsActive = false, disabled }: FormFieldsProps) {
  return (
    <div className="space-y-5">
      <div className="space-y-1.5">
        <Label htmlFor="sf-name">Nome <span className="text-destructive">*</span></Label>
        <Input id="sf-name" placeholder="Ex: Corte masculino" value={form.name}
          onChange={(e) => onChange("name", e.target.value)} disabled={disabled} autoFocus />
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="sf-description">Descrição</Label>
        <Textarea id="sf-description" placeholder="Descreva o serviço..." value={form.description}
          onChange={(e) => onChange("description", e.target.value)} disabled={disabled} />
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label>Categoria</Label>
          <Select value={form.categoryId} onValueChange={(v) => onChange("categoryId", v)} disabled={disabled}>
            <SelectTrigger className="w-full"><SelectValue placeholder="Sem categoria" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="none">Sem categoria</SelectItem>
              {categories.map((c) => (
                <SelectItem key={c.id} value={String(c.id)}>
                  {c.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label>Duração</Label>
          <Select value={form.duration} onValueChange={(v) => onChange("duration", v)} disabled={disabled}>
            <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
            <SelectContent>
              {DURATION_OPTIONS.map((d) => (
                <SelectItem key={d.value} value={d.value}>{d.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="sf-price">Preço (R$) <span className="text-destructive">*</span></Label>
          <Input id="sf-price" type="number" min="0" step="0.01" placeholder="0,00"
            value={form.price} onChange={(e) => onChange("price", e.target.value)} disabled={disabled} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="sf-color">Cor de identificação</Label>
          <div className="flex items-center gap-2">
            <input type="color" value={form.color || "#6366f1"}
              onChange={(e) => onChange("color", e.target.value)} disabled={disabled}
              className="h-9 w-12 cursor-pointer rounded-md border border-input bg-transparent p-0.5 disabled:opacity-50" />
            <Input placeholder="#6366f1" value={form.color}
              onChange={(e) => onChange("color", e.target.value)} disabled={disabled}
              className="flex-1 font-mono text-sm" maxLength={7} />
          </div>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label>Tipo de comissão</Label>
          <Select value={form.commissionType} onValueChange={(v) => onChange("commissionType", v)} disabled={disabled}>
            <SelectTrigger className="w-full"><SelectValue placeholder="Nenhuma" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="none">Nenhuma</SelectItem>
              <SelectItem value="percentage">Percentual (%)</SelectItem>
              <SelectItem value="fixed">Valor fixo (R$)</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="sf-commission-value">
            {form.commissionType === "percentage" ? "Percentual (%)" : "Valor (R$)"}
          </Label>
          <Input id="sf-commission-value" type="number" min="0"
            step={form.commissionType === "percentage" ? "0.1" : "0.01"}
            placeholder={form.commissionType === "percentage" ? "ex: 15" : "ex: 25.00"}
            value={form.commissionValue}
            onChange={(e) => onChange("commissionValue", e.target.value)}
            disabled={disabled || !form.commissionType || form.commissionType === "none"} />
        </div>
      </div>

      <div className="space-y-3 pt-1">
        <label className="flex items-center gap-3 cursor-pointer">
          <input type="checkbox" className="h-4 w-4 rounded border-input accent-primary"
            checked={form.allowOnlineBooking} onChange={(e) => onChange("allowOnlineBooking", e.target.checked)}
            disabled={disabled} />
          <div>
            <span className="text-sm font-medium">Agendamento online</span>
            <p className="text-xs text-muted-foreground">Permitir que clientes agendem este serviço pelo link público</p>
          </div>
        </label>

        {showIsActive && (
          <label className="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" className="h-4 w-4 rounded border-input accent-primary"
              checked={form.isActive} onChange={(e) => onChange("isActive", e.target.checked)}
              disabled={disabled} />
            <span className="text-sm font-medium">Serviço ativo</span>
          </label>
        )}
      </div>
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function ServicosPage() {
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(DEFAULT_PER_PAGE);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [createOpen, setCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState<ServiceForm>(emptyForm);

  const [editId, setEditId] = useState<number | null>(null);
  const [editForm, setEditForm] = useState<ServiceForm>(emptyForm);

  const [deleteTarget, setDeleteTarget] = useState<{ id: number; name: string } | null>(null);

  const queryClient = useQueryClient();

  // ── Queries ──────────────────────────────────────────────────────────────

  const { data, isLoading, isError } = useQuery<ServicesResponse>({
    queryKey: ["services", debouncedSearch, page, perPage],
    queryFn: () =>
      fetch(`/api/services?search=${encodeURIComponent(debouncedSearch)}&page=${page}&perPage=${perPage}`)
        .then((r) => { if (!r.ok) throw new Error(); return r.json(); }),
  });

  const { data: categories = [] } = useQuery<Category[]>({
    queryKey: ["service-categories"],
    queryFn: () => fetch("/api/service-categories").then((r) => r.json()),
  });

  const { data: fullService, isLoading: loadingFull } = useQuery({
    queryKey: ["service", editId],
    queryFn: () =>
      fetch(`/api/services/${editId}`).then(async (r) => {
        if (!r.ok) throw new Error("Erro ao carregar serviço");
        return r.json();
      }),
    enabled: editId !== null,
  });

  useEffect(() => {
    if (!fullService) return;
    setEditForm({
      name: fullService.name ?? "",
      description: fullService.description ?? "",
      categoryId: fullService.categoryId ? String(fullService.categoryId) : "",
      duration: String(fullService.duration ?? 60),
      price: String(fullService.price ?? ""),
      color: fullService.color ?? "#6366f1",
      commissionType: (fullService.commissionType as ServiceForm["commissionType"]) ?? "",
      commissionValue: fullService.commissionValue != null ? String(fullService.commissionValue) : "",
      allowOnlineBooking: fullService.allowOnlineBooking ?? true,
      isActive: fullService.isActive ?? true,
    });
  }, [fullService]);

  // ── Mutations ────────────────────────────────────────────────────────────

  const { mutate: createService, isPending: creating } = useMutation({
    mutationFn: (form: ServiceForm) =>
      fetch("/api/services", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildCreatePayload(form)),
      }).then(async (r) => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao cadastrar serviço"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["services"] });
      setCreateOpen(false);
      setCreateForm(emptyForm);
      toast.success("Serviço cadastrado com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: updateService, isPending: updating } = useMutation({
    mutationFn: (form: ServiceForm) =>
      fetch(`/api/services/${editId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildUpdatePayload(form)),
      }).then(async (r) => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao atualizar serviço"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["services"] });
      queryClient.invalidateQueries({ queryKey: ["service", editId] });
      setEditId(null);
      toast.success("Serviço atualizado com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: deleteService, isPending: deleting } = useMutation({
    mutationFn: (id: number) =>
      fetch(`/api/services/${id}`, { method: "DELETE" }).then(async (r) => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao excluir serviço"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["services"] });
      setDeleteTarget(null);
      toast.success("Serviço excluído com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  // ── Payload builders ─────────────────────────────────────────────────────

  function buildCreatePayload(form: ServiceForm) {
    const payload: Record<string, unknown> = {
      name: form.name,
      duration: parseInt(form.duration) || 60,
      price: parseFloat(form.price) || 0,
      allowOnlineBooking: form.allowOnlineBooking,
    };
    if (form.description) payload.description = form.description;
    if (form.categoryId && form.categoryId !== "none") payload.categoryId = parseInt(form.categoryId);
    if (form.color) payload.color = form.color;
    if (form.commissionType && form.commissionType !== "none") {
      payload.commissionType = form.commissionType;
      if (form.commissionValue) payload.commissionValue = parseFloat(form.commissionValue);
    }
    return payload;
  }

  function buildUpdatePayload(form: ServiceForm) {
    return {
      name: form.name,
      description: form.description || null,
      categoryId: form.categoryId && form.categoryId !== "none" ? parseInt(form.categoryId) : null,
      duration: parseInt(form.duration) || 60,
      price: parseFloat(form.price) || 0,
      color: form.color || null,
      commissionType: form.commissionType && form.commissionType !== "none" ? form.commissionType : null,
      commissionValue: form.commissionValue && form.commissionType && form.commissionType !== "none"
        ? parseFloat(form.commissionValue) : null,
      allowOnlineBooking: form.allowOnlineBooking,
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

  function handleCreateField(field: keyof ServiceForm, value: string | boolean) {
    setCreateForm((f) => ({ ...f, [field]: value }));
  }

  function handleEditField(field: keyof ServiceForm, value: string | boolean) {
    setEditForm((f) => ({ ...f, [field]: value }));
  }

  function handleCreateSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (createForm.name.trim().length < 2) { toast.error("Nome deve ter pelo menos 2 caracteres"); return; }
    if (!createForm.price) { toast.error("Informe o preço do serviço"); return; }
    createService(createForm);
  }

  function handleEditSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (editForm.name.trim().length < 2) { toast.error("Nome deve ter pelo menos 2 caracteres"); return; }
    if (!editForm.price) { toast.error("Informe o preço do serviço"); return; }
    updateService(editForm);
  }

  function openCreate() { setCreateForm(emptyForm); setCreateOpen(true); }
  function openEdit(id: number) { setEditForm(emptyForm); setEditId(id); }

  // ─────────────────────────────────────────────────────────────────────────

  const services = data?.data ?? [];
  const totalPages = data?.totalPages ?? 1;

  return (
    <>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold">Serviços</h1>
            <p className="text-muted-foreground text-sm">
              {isLoading ? "Carregando..." : `${data?.total ?? 0} serviços cadastrados`}
            </p>
          </div>
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4 mr-2" />
            Novo serviço
          </Button>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Scissors className="h-4 w-4" />
              Lista de serviços
            </CardTitle>
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input className="pl-9" placeholder="Buscar por nome ou descrição..."
                value={search} onChange={(e) => handleSearch(e.target.value)} />
            </div>
          </CardHeader>

          <CardContent className="p-0">
            {isLoading ? (
              <TableSkeleton />
            ) : isError ? (
              <div className="p-10 flex flex-col items-center gap-3 text-destructive">
                <AlertCircle className="h-8 w-8" />
                <p className="font-medium">Erro ao carregar serviços</p>
                <p className="text-sm text-muted-foreground">Verifique sua conexão e tente novamente.</p>
              </div>
            ) : services.length === 0 ? (
              <div className="p-12 flex flex-col items-center gap-3 text-center">
                <Scissors className="h-10 w-10 text-muted-foreground/40" />
                <div>
                  <p className="font-medium text-muted-foreground">
                    {debouncedSearch ? `Nenhum resultado para "${debouncedSearch}"` : "Nenhum serviço cadastrado"}
                  </p>
                  {!debouncedSearch && (
                    <p className="text-sm text-muted-foreground/70 mt-1">Cadastre o primeiro serviço para começar</p>
                  )}
                </div>
                {!debouncedSearch && (
                  <Button size="sm" className="mt-1" onClick={openCreate}>
                    <Plus className="h-4 w-4 mr-2" />Cadastrar serviço
                  </Button>
                )}
              </div>
            ) : (
              <>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Serviço</TableHead>
                      <TableHead>Categoria</TableHead>
                      <TableHead>Duração</TableHead>
                      <TableHead>Preço</TableHead>
                      <TableHead>Comissão</TableHead>
                      <TableHead>Online</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead className="w-44">Ações</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {services.map((service) => (
                      <TableRow key={service.id} className="hover:bg-accent cursor-pointer"
                        onClick={() => openEdit(service.id)}>
                        <TableCell className="font-medium">
                          <div className="flex items-center gap-3">
                            <span
                              className="h-3 w-3 rounded-full shrink-0 ring-1 ring-black/10"
                              style={{ backgroundColor: service.color ?? "#6366f1" }}
                            />
                            <div>
                              <p>{service.name}</p>
                              {service.description && (
                                <p className="text-xs text-muted-foreground truncate max-w-[200px]">
                                  {service.description}
                                </p>
                              )}
                            </div>
                          </div>
                        </TableCell>
                        <TableCell>
                          {service.category ? (
                            <Badge variant="outline" className="font-normal">
                              {service.category.name}
                            </Badge>
                          ) : (
                            <span className="text-muted-foreground">—</span>
                          )}
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-1.5 text-muted-foreground">
                            <Clock className="h-3.5 w-3.5" />
                            {formatDuration(service.duration)}
                          </div>
                        </TableCell>
                        <TableCell className="font-medium">{formatCurrency(service.price)}</TableCell>
                        <TableCell>{formatCommission(service.commissionType, service.commissionValue)}</TableCell>
                        <TableCell>
                          {service.allowOnlineBooking ? (
                            <Globe className="h-4 w-4 text-green-600" />
                          ) : (
                            <span className="text-muted-foreground">—</span>
                          )}
                        </TableCell>
                        <TableCell>
                          {service.isActive ? (
                            <Badge className="bg-green-500/10 text-green-700 border-green-500/20 hover:bg-green-500/10">Ativo</Badge>
                          ) : (
                            <Badge variant="secondary">Inativo</Badge>
                          )}
                        </TableCell>
                        <TableCell onClick={(e) => e.stopPropagation()}>
                          <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" className="h-8 px-3 text-xs"
                              onClick={() => openEdit(service.id)}>
                              <Pencil className="h-3.5 w-3.5 mr-1.5" />Editar
                            </Button>
                            <Button variant="outline" size="sm"
                              className="h-8 px-3 text-xs text-destructive border-destructive/30 hover:bg-destructive/10 hover:text-destructive hover:border-destructive/50"
                              onClick={() => setDeleteTarget({ id: service.id, name: service.name })}>
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

      {/* ── Cadastrar serviço ─────────────────────────────────────────────── */}
      <Sheet open={createOpen} onOpenChange={(val) => setCreateOpen(val)}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-lg">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Novo serviço</SheetTitle>
            <SheetDescription>Preencha os dados do serviço. Nome e preço são obrigatórios.</SheetDescription>
          </SheetHeader>
          <form onSubmit={handleCreateSubmit} className="flex flex-col flex-1 overflow-hidden">
            <div className="flex-1 overflow-y-auto px-6 py-5">
              <ServiceFormFields form={createForm} onChange={handleCreateField}
                categories={categories} disabled={creating} />
            </div>
            <SheetFooter className="px-6 py-4 border-t">
              <Button type="button" variant="outline" onClick={() => setCreateOpen(false)} disabled={creating}>Cancelar</Button>
              <Button type="submit" disabled={creating}>{creating ? "Salvando..." : "Cadastrar serviço"}</Button>
            </SheetFooter>
          </form>
        </SheetContent>
      </Sheet>

      {/* ── Editar serviço ────────────────────────────────────────────────── */}
      <Sheet open={editId !== null} onOpenChange={(val) => { if (!val) setEditId(null); }}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-lg">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Editar serviço</SheetTitle>
            <SheetDescription>Atualize os dados do serviço.</SheetDescription>
          </SheetHeader>
          {loadingFull ? (
            <div className="flex-1 flex items-center justify-center">
              <div className="flex flex-col items-center gap-3 text-muted-foreground">
                <div className="h-6 w-6 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                <span className="text-sm">Carregando dados...</span>
              </div>
            </div>
          ) : (
            <form onSubmit={handleEditSubmit} className="flex flex-col flex-1 overflow-hidden">
              <div className="flex-1 overflow-y-auto px-6 py-5">
                <ServiceFormFields form={editForm} onChange={handleEditField}
                  categories={categories} showIsActive disabled={updating} />
              </div>
              <SheetFooter className="px-6 py-4 border-t">
                <Button type="button" variant="outline" onClick={() => setEditId(null)} disabled={updating}>Cancelar</Button>
                <Button type="submit" disabled={updating}>{updating ? "Salvando..." : "Salvar alterações"}</Button>
              </SheetFooter>
            </form>
          )}
        </SheetContent>
      </Sheet>

      {/* ── Confirmar exclusão ────────────────────────────────────────────── */}
      <AlertDialog open={deleteTarget !== null} onOpenChange={(val) => { if (!val) setDeleteTarget(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir serviço</AlertDialogTitle>
            <AlertDialogDescription>
              Tem certeza que deseja excluir <strong className="text-foreground">{deleteTarget?.name}</strong>?
              Esta ação não pode ser desfeita.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>Cancelar</AlertDialogCancel>
            <AlertDialogAction variant="destructive" disabled={deleting}
              onClick={() => deleteTarget && deleteService(deleteTarget.id)}>
              {deleting ? "Excluindo..." : "Excluir"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
