"use client";

import { useState, useRef, useEffect } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { format } from "date-fns";
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
  DollarSign, TrendingUp, TrendingDown, Plus, Search,
  ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight,
  AlertCircle, Pencil, Trash2, ArrowUpCircle, ArrowDownCircle,
} from "lucide-react";

// ─── Types ────────────────────────────────────────────────────────────────────

interface FinancialCategory {
  id: number;
  name: string;
  type: string;
}

interface Transaction {
  id: number;
  type: "income" | "expense";
  description: string;
  amount: string | number;
  paymentMethod: string | null;
  status: "pending" | "paid" | "cancelled";
  dueDate: string | null;
  paidAt: string | null;
  notes: string | null;
  createdAt: string;
  categoryId: number | null;
  category?: { id: number; name: string } | null;
  client?: { id: number; name: string } | null;
}

interface TransactionsResponse {
  data: Transaction[];
  total: number;
  page: number;
  perPage: number;
  totalPages: number;
  summary: { income: number | string; expense: number | string; balance: number | string };
}

interface TransactionForm {
  type: "income" | "expense";
  description: string;
  amount: string;
  categoryId: string;
  paymentMethod: string;
  status: "pending" | "paid" | "cancelled";
  dueDate: string;
  notes: string;
}

// ─── Constants ────────────────────────────────────────────────────────────────

const emptyForm: TransactionForm = {
  type: "income",
  description: "",
  amount: "",
  categoryId: "",
  paymentMethod: "",
  status: "pending",
  dueDate: "",
  notes: "",
};

const PER_PAGE_OPTIONS = [10, 20, 50, 100];
const DEFAULT_PER_PAGE = 20;

const PAYMENT_METHODS = [
  { value: "dinheiro",       label: "Dinheiro" },
  { value: "pix",            label: "Pix" },
  { value: "cartao_credito", label: "Cartão de Crédito" },
  { value: "cartao_debito",  label: "Cartão de Débito" },
  { value: "transferencia",  label: "Transferência" },
  { value: "boleto",         label: "Boleto" },
  { value: "outro",          label: "Outro" },
];

const PAYMENT_METHOD_LABELS: Record<string, string> = Object.fromEntries(
  PAYMENT_METHODS.map((m) => [m.value, m.label])
);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function todayIso() { return new Date().toISOString().split("T")[0]; }
function monthStartIso() {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split("T")[0];
}

function brl(value: number | string) {
  return Number(value).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

function formatDate(iso: string | null) {
  if (!iso) return "—";
  return format(new Date(iso), "dd/MM/yyyy");
}

function statusLabel(status: string) {
  if (status === "paid")      return "Pago";
  if (status === "pending")   return "Pendente";
  if (status === "cancelled") return "Cancelado";
  return status;
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

function SummarySkeleton() {
  return (
    <div className="grid gap-4 md:grid-cols-3">
      {[0, 1, 2].map((i) => (
        <Card key={i}>
          <CardHeader className="pb-2"><div className="h-4 w-28 bg-muted animate-pulse rounded" /></CardHeader>
          <CardContent><div className="h-8 w-32 bg-muted animate-pulse rounded" /></CardContent>
        </Card>
      ))}
    </div>
  );
}

function TableSkeleton() {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Data</TableHead><TableHead>Tipo</TableHead><TableHead>Descrição</TableHead>
          <TableHead>Categoria</TableHead><TableHead>Forma</TableHead>
          <TableHead>Status</TableHead><TableHead className="text-right">Valor</TableHead>
          <TableHead className="w-44">Ações</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {Array.from({ length: 5 }).map((_, i) => (
          <TableRow key={i}>
            {[40, 20, 160, 80, 80, 60, 72, 80].map((w, j) => (
              <TableCell key={j}><div className={`h-4 w-${w} bg-muted animate-pulse rounded`} style={{ width: w }} /></TableCell>
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
        <span>{total === 0 ? "Nenhum registro" : `Mostrando ${start}–${end} de ${total} transações`}</span>
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
  form: TransactionForm;
  onChange: (field: keyof TransactionForm, value: string) => void;
  categories: FinancialCategory[];
  disabled?: boolean;
}

function TransactionFormFields({ form, onChange, categories, disabled }: FormFieldsProps) {
  const filteredCategories = categories.filter((c) => c.type === form.type);

  return (
    <div className="space-y-5">
      {/* Tipo */}
      <div className="space-y-1.5">
        <Label>Tipo <span className="text-destructive">*</span></Label>
        <div className="grid grid-cols-2 gap-2">
          <button type="button" disabled={disabled}
            onClick={() => { onChange("type", "income"); onChange("categoryId", ""); }}
            className={`flex items-center justify-center gap-2 rounded-md border px-3 py-2.5 text-sm font-medium transition-colors ${
              form.type === "income"
                ? "border-green-500 bg-green-50 text-green-700 dark:bg-green-950/30 dark:text-green-400"
                : "border-input hover:bg-accent"
            }`}>
            <ArrowUpCircle className="h-4 w-4" />Receita
          </button>
          <button type="button" disabled={disabled}
            onClick={() => { onChange("type", "expense"); onChange("categoryId", ""); }}
            className={`flex items-center justify-center gap-2 rounded-md border px-3 py-2.5 text-sm font-medium transition-colors ${
              form.type === "expense"
                ? "border-red-500 bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-400"
                : "border-input hover:bg-accent"
            }`}>
            <ArrowDownCircle className="h-4 w-4" />Despesa
          </button>
        </div>
      </div>

      {/* Descrição */}
      <div className="space-y-1.5">
        <Label htmlFor="tf-desc">Descrição <span className="text-destructive">*</span></Label>
        <Input id="tf-desc" placeholder="Ex: Pagamento de serviço" value={form.description}
          onChange={(e) => onChange("description", e.target.value)} disabled={disabled} autoFocus />
      </div>

      {/* Valor + Vencimento */}
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="tf-amount">Valor (R$) <span className="text-destructive">*</span></Label>
          <Input id="tf-amount" type="number" step="0.01" min="0.01" placeholder="0,00"
            value={form.amount} onChange={(e) => onChange("amount", e.target.value)} disabled={disabled} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="tf-due">Vencimento</Label>
          <Input id="tf-due" type="date" value={form.dueDate}
            onChange={(e) => onChange("dueDate", e.target.value)} disabled={disabled} />
        </div>
      </div>

      {/* Categoria + Forma de pagamento */}
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label>Categoria</Label>
          <Select value={form.categoryId} onValueChange={(v) => onChange("categoryId", v)} disabled={disabled}>
            <SelectTrigger className="w-full"><SelectValue placeholder="Selecionar..." /></SelectTrigger>
            <SelectContent>
              <SelectItem value="none">Sem categoria</SelectItem>
              {filteredCategories.map((c) => (
                <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label>Forma de pagamento</Label>
          <Select value={form.paymentMethod} onValueChange={(v) => onChange("paymentMethod", v)} disabled={disabled}>
            <SelectTrigger className="w-full"><SelectValue placeholder="Selecionar..." /></SelectTrigger>
            <SelectContent>
              <SelectItem value="none">Não informado</SelectItem>
              {PAYMENT_METHODS.map((m) => (
                <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Status */}
      <div className="space-y-1.5">
        <Label>Status</Label>
        <Select value={form.status} onValueChange={(v) => onChange("status", v as TransactionForm["status"])} disabled={disabled}>
          <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="pending">Pendente</SelectItem>
            <SelectItem value="paid">Pago</SelectItem>
            <SelectItem value="cancelled">Cancelado</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {/* Observações */}
      <div className="space-y-1.5">
        <Label htmlFor="tf-notes">Observações</Label>
        <Textarea id="tf-notes" placeholder="Informações adicionais..." rows={3}
          value={form.notes} onChange={(e) => onChange("notes", e.target.value)} disabled={disabled} />
      </div>
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function FinanceiroPage() {
  const [from, setFrom] = useState(monthStartIso);
  const [to, setTo] = useState(todayIso);
  const [typeFilter, setTypeFilter] = useState("all");
  const [statusFilter, setStatusFilter] = useState("all");
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(DEFAULT_PER_PAGE);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [createOpen, setCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState<TransactionForm>(emptyForm);

  const [editId, setEditId] = useState<number | null>(null);
  const [editForm, setEditForm] = useState<TransactionForm>(emptyForm);

  const [deleteTarget, setDeleteTarget] = useState<{ id: number; description: string } | null>(null);

  const queryClient = useQueryClient();

  // ── Queries ──────────────────────────────────────────────────────────────

  const params = new URLSearchParams({
    from, to, page: String(page), perPage: String(perPage),
    ...(typeFilter !== "all" ? { type: typeFilter } : {}),
    ...(statusFilter !== "all" ? { status: statusFilter } : {}),
    ...(debouncedSearch ? { search: debouncedSearch } : {}),
  });

  const { data, isLoading, isError } = useQuery<TransactionsResponse>({
    queryKey: ["financial", from, to, typeFilter, statusFilter, debouncedSearch, page, perPage],
    queryFn: () =>
      fetch(`/api/financial?${params}`).then((r) => { if (!r.ok) throw new Error(); return r.json(); }),
  });

  const { data: categories = [] } = useQuery<FinancialCategory[]>({
    queryKey: ["financial-categories"],
    queryFn: () => fetch("/api/financial-categories").then((r) => r.json()),
  });

  const { data: fullTx, isLoading: loadingFull } = useQuery<Transaction>({
    queryKey: ["financial-tx", editId],
    queryFn: () =>
      fetch(`/api/financial/${editId}`).then(async (r) => {
        if (!r.ok) throw new Error("Erro ao carregar transação");
        return r.json();
      }),
    enabled: editId !== null,
  });

  useEffect(() => {
    if (!fullTx) return;
    setEditForm({
      type: fullTx.type,
      description: fullTx.description,
      amount: String(Number(fullTx.amount)),
      categoryId: fullTx.categoryId ? String(fullTx.categoryId) : "",
      paymentMethod: fullTx.paymentMethod ?? "",
      status: fullTx.status,
      dueDate: fullTx.dueDate ? fullTx.dueDate.split("T")[0] : "",
      notes: fullTx.notes ?? "",
    });
  }, [fullTx]);

  // ── Mutations ────────────────────────────────────────────────────────────

  const { mutate: createTx, isPending: creating } = useMutation({
    mutationFn: (form: TransactionForm) =>
      fetch("/api/financial", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildCreatePayload(form)),
      }).then(async (r) => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao cadastrar transação"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["financial"] });
      setCreateOpen(false);
      setCreateForm(emptyForm);
      toast.success("Transação cadastrada com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: updateTx, isPending: updating } = useMutation({
    mutationFn: (form: TransactionForm) =>
      fetch(`/api/financial/${editId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildUpdatePayload(form)),
      }).then(async (r) => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao atualizar transação"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["financial"] });
      queryClient.invalidateQueries({ queryKey: ["financial-tx", editId] });
      setEditId(null);
      toast.success("Transação atualizada com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: deleteTx, isPending: deleting } = useMutation({
    mutationFn: (id: number) =>
      fetch(`/api/financial/${id}`, { method: "DELETE" }).then(async (r) => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao excluir transação"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["financial"] });
      setDeleteTarget(null);
      toast.success("Transação excluída com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  // ── Payload builders ─────────────────────────────────────────────────────

  function buildCreatePayload(form: TransactionForm) {
    const amount = parseFloat(form.amount.replace(",", "."));
    const payload: Record<string, unknown> = {
      type: form.type,
      description: form.description,
      amount,
      status: form.status,
    };
    if (form.categoryId && form.categoryId !== "none")   payload.categoryId   = parseInt(form.categoryId);
    if (form.paymentMethod && form.paymentMethod !== "none") payload.paymentMethod = form.paymentMethod;
    if (form.dueDate) payload.dueDate = form.dueDate;
    if (form.notes)   payload.notes   = form.notes;
    return payload;
  }

  function buildUpdatePayload(form: TransactionForm) {
    return {
      type:          form.type,
      description:   form.description,
      amount:        parseFloat(form.amount.replace(",", ".")),
      status:        form.status,
      categoryId:    (form.categoryId && form.categoryId !== "none") ? parseInt(form.categoryId) : null,
      paymentMethod: (form.paymentMethod && form.paymentMethod !== "none") ? form.paymentMethod : null,
      dueDate:       form.dueDate || null,
      notes:         form.notes || null,
    };
  }

  // ── Handlers ─────────────────────────────────────────────────────────────

  function handleSearch(value: string) {
    setSearch(value);
    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => { setDebouncedSearch(value); setPage(1); }, 400);
  }

  function handleFilterChange() { setPage(1); }

  function handlePerPageChange(n: number) { setPerPage(n); setPage(1); }

  function handleCreateField(field: keyof TransactionForm, value: string) {
    setCreateForm((f) => ({ ...f, [field]: value }));
  }

  function handleEditField(field: keyof TransactionForm, value: string) {
    setEditForm((f) => ({ ...f, [field]: value }));
  }

  function handleCreateSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!createForm.description.trim()) { toast.error("Descrição é obrigatória"); return; }
    const amount = parseFloat(createForm.amount.replace(",", "."));
    if (!createForm.amount || isNaN(amount) || amount <= 0) { toast.error("Informe um valor válido"); return; }
    createTx(createForm);
  }

  function handleEditSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!editForm.description.trim()) { toast.error("Descrição é obrigatória"); return; }
    const amount = parseFloat(editForm.amount.replace(",", "."));
    if (!editForm.amount || isNaN(amount) || amount <= 0) { toast.error("Informe um valor válido"); return; }
    updateTx(editForm);
  }

  function openCreate() { setCreateForm(emptyForm); setCreateOpen(true); }
  function openEdit(id: number) { setEditForm(emptyForm); setEditId(id); }

  // ─────────────────────────────────────────────────────────────────────────

  const transactions = data?.data ?? [];
  const summary = data?.summary ?? { income: 0, expense: 0, balance: 0 };
  const totalPages = data?.totalPages ?? 1;

  return (
    <>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold">Financeiro</h1>
            <p className="text-muted-foreground text-sm">Controle de receitas e despesas</p>
          </div>
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4 mr-2" />
            Nova transação
          </Button>
        </div>

        {/* ── Summary ──────────────────────────────────────────────────────── */}
        {isLoading ? <SummarySkeleton /> : (
          <div className="grid gap-4 md:grid-cols-3">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">Receitas (pagas)</CardTitle>
                <TrendingUp className="h-4 w-4 text-green-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-green-700">{brl(summary.income)}</div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">Despesas (pagas)</CardTitle>
                <TrendingDown className="h-4 w-4 text-red-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-red-700">{brl(summary.expense)}</div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">Saldo</CardTitle>
                <DollarSign className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className={`text-2xl font-bold ${Number(summary.balance) >= 0 ? "text-green-700" : "text-red-700"}`}>
                  {brl(summary.balance)}
                </div>
              </CardContent>
            </Card>
          </div>
        )}

        {/* ── Table card ───────────────────────────────────────────────────── */}
        <Card>
          <CardHeader className="space-y-4">
            <CardTitle className="flex items-center gap-2 text-base">
              <DollarSign className="h-4 w-4" />
              Transações
            </CardTitle>

            {/* Filters */}
            <div className="flex flex-wrap gap-3">
              {/* Search */}
              <div className="relative flex-1 min-w-[200px]">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input className="pl-9" placeholder="Buscar descrição..."
                  value={search} onChange={(e) => handleSearch(e.target.value)} />
              </div>

              {/* Date range */}
              <div className="flex items-center gap-2">
                <Label className="text-xs text-muted-foreground whitespace-nowrap">De</Label>
                <Input type="date" className="h-9 w-[140px] text-sm" value={from}
                  onChange={(e) => { setFrom(e.target.value); handleFilterChange(); }} />
                <Label className="text-xs text-muted-foreground whitespace-nowrap">Até</Label>
                <Input type="date" className="h-9 w-[140px] text-sm" value={to}
                  onChange={(e) => { setTo(e.target.value); handleFilterChange(); }} />
              </div>

              {/* Type filter */}
              <Select value={typeFilter} onValueChange={(v) => { setTypeFilter(v); handleFilterChange(); }}>
                <SelectTrigger className="h-9 w-[130px] text-sm"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos tipos</SelectItem>
                  <SelectItem value="income">Receitas</SelectItem>
                  <SelectItem value="expense">Despesas</SelectItem>
                </SelectContent>
              </Select>

              {/* Status filter */}
              <Select value={statusFilter} onValueChange={(v) => { setStatusFilter(v); handleFilterChange(); }}>
                <SelectTrigger className="h-9 w-[130px] text-sm"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos status</SelectItem>
                  <SelectItem value="pending">Pendente</SelectItem>
                  <SelectItem value="paid">Pago</SelectItem>
                  <SelectItem value="cancelled">Cancelado</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </CardHeader>

          <CardContent className="p-0">
            {isLoading ? (
              <TableSkeleton />
            ) : isError ? (
              <div className="p-10 flex flex-col items-center gap-3 text-destructive">
                <AlertCircle className="h-8 w-8" />
                <p className="font-medium">Erro ao carregar transações</p>
                <p className="text-sm text-muted-foreground">Verifique sua conexão e tente novamente.</p>
              </div>
            ) : transactions.length === 0 ? (
              <div className="p-12 flex flex-col items-center gap-3 text-center">
                <DollarSign className="h-10 w-10 text-muted-foreground/40" />
                <div>
                  <p className="font-medium text-muted-foreground">Nenhuma transação encontrada</p>
                  <p className="text-sm text-muted-foreground/70 mt-1">Tente ajustar os filtros ou cadastre uma nova transação</p>
                </div>
                <Button size="sm" className="mt-1" onClick={openCreate}>
                  <Plus className="h-4 w-4 mr-2" />Nova transação
                </Button>
              </div>
            ) : (
              <>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-28">Data</TableHead>
                      <TableHead className="w-24">Tipo</TableHead>
                      <TableHead>Descrição</TableHead>
                      <TableHead>Categoria</TableHead>
                      <TableHead>Forma</TableHead>
                      <TableHead className="w-24">Status</TableHead>
                      <TableHead className="text-right w-28">Valor</TableHead>
                      <TableHead className="w-44">Ações</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {transactions.map((tx) => (
                      <TableRow key={tx.id} className="hover:bg-accent cursor-pointer"
                        onClick={() => openEdit(tx.id)}>
                        <TableCell className="text-muted-foreground text-sm">
                          {formatDate(tx.dueDate ?? tx.createdAt)}
                        </TableCell>
                        <TableCell>
                          {tx.type === "income" ? (
                            <div className="flex items-center gap-1.5 text-green-700 text-xs font-medium">
                              <ArrowUpCircle className="h-3.5 w-3.5" />Receita
                            </div>
                          ) : (
                            <div className="flex items-center gap-1.5 text-red-700 text-xs font-medium">
                              <ArrowDownCircle className="h-3.5 w-3.5" />Despesa
                            </div>
                          )}
                        </TableCell>
                        <TableCell className="font-medium">
                          <div>
                            <p className="truncate max-w-[200px]">{tx.description}</p>
                            {tx.client && (
                              <p className="text-xs text-muted-foreground">{tx.client.name}</p>
                            )}
                          </div>
                        </TableCell>
                        <TableCell className="text-muted-foreground text-sm">
                          {tx.category?.name ?? "—"}
                        </TableCell>
                        <TableCell className="text-muted-foreground text-sm">
                          {tx.paymentMethod ? (PAYMENT_METHOD_LABELS[tx.paymentMethod] ?? tx.paymentMethod) : "—"}
                        </TableCell>
                        <TableCell>
                          {tx.status === "paid" && (
                            <Badge className="bg-green-500/10 text-green-700 border-green-500/20 hover:bg-green-500/10 text-xs">Pago</Badge>
                          )}
                          {tx.status === "pending" && (
                            <Badge className="bg-amber-500/10 text-amber-700 border-amber-500/20 hover:bg-amber-500/10 text-xs">Pendente</Badge>
                          )}
                          {tx.status === "cancelled" && (
                            <Badge variant="secondary" className="text-xs">Cancelado</Badge>
                          )}
                        </TableCell>
                        <TableCell className={`text-right font-semibold ${tx.type === "income" ? "text-green-700" : "text-red-700"}`}>
                          {tx.type === "expense" ? "−" : "+"} {brl(tx.amount)}
                        </TableCell>
                        <TableCell onClick={(e) => e.stopPropagation()}>
                          <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" className="h-8 px-3 text-xs"
                              onClick={() => openEdit(tx.id)}>
                              <Pencil className="h-3.5 w-3.5 mr-1.5" />Editar
                            </Button>
                            <Button variant="outline" size="sm"
                              className="h-8 px-3 text-xs text-destructive border-destructive/30 hover:bg-destructive/10 hover:text-destructive hover:border-destructive/50"
                              onClick={() => setDeleteTarget({ id: tx.id, description: tx.description })}>
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

      {/* ── Cadastrar transação ───────────────────────────────────────────── */}
      <Sheet open={createOpen} onOpenChange={(val) => setCreateOpen(val)}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-lg">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Nova transação</SheetTitle>
            <SheetDescription>Registre uma receita ou despesa.</SheetDescription>
          </SheetHeader>
          <form onSubmit={handleCreateSubmit} className="flex flex-col flex-1 overflow-hidden">
            <div className="flex-1 overflow-y-auto px-6 py-5">
              <TransactionFormFields form={createForm} onChange={handleCreateField}
                categories={categories} disabled={creating} />
            </div>
            <SheetFooter className="px-6 py-4 border-t">
              <Button type="button" variant="outline" onClick={() => setCreateOpen(false)} disabled={creating}>Cancelar</Button>
              <Button type="submit" disabled={creating}>{creating ? "Salvando..." : "Cadastrar transação"}</Button>
            </SheetFooter>
          </form>
        </SheetContent>
      </Sheet>

      {/* ── Editar transação ──────────────────────────────────────────────── */}
      <Sheet open={editId !== null} onOpenChange={(val) => { if (!val) setEditId(null); }}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-lg">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Editar transação</SheetTitle>
            <SheetDescription>Atualize os dados da transação.</SheetDescription>
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
                <TransactionFormFields form={editForm} onChange={handleEditField}
                  categories={categories} disabled={updating} />
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
            <AlertDialogTitle>Excluir transação</AlertDialogTitle>
            <AlertDialogDescription>
              Tem certeza que deseja excluir <strong className="text-foreground">{deleteTarget?.description}</strong>?
              Esta ação não pode ser desfeita.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>Cancelar</AlertDialogCancel>
            <AlertDialogAction variant="destructive" disabled={deleting}
              onClick={() => deleteTarget && deleteTx(deleteTarget.id)}>
              {deleting ? "Excluindo..." : "Excluir"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
