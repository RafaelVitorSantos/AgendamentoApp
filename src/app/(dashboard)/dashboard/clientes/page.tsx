"use client";

import { useState, useRef, useEffect } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
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
import { format } from "date-fns";
import { ptBR } from "date-fns/locale";
import {
  Search, Plus, Users, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight,
  AlertCircle, Pencil, Trash2,
} from "lucide-react";

// ─── Types ───────────────────────────────────────────────────────────────────

interface Client {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
  totalVisits: number;
  totalSpent: number | null;
  lastVisitAt: string | null;
}

interface ClientsResponse {
  data: Client[];
  total: number;
  page: number;
  perPage: number;
  totalPages: number;
}

interface ClientForm {
  name: string;
  email: string;
  phone: string;
  cpf: string;
  birthDate: string;
  gender: string;
  address: string;
  city: string;
  state: string;
  notes: string;
  lgpdConsent: boolean;
}

// ─── Constants ───────────────────────────────────────────────────────────────

const emptyForm: ClientForm = {
  name: "", email: "", phone: "", cpf: "", birthDate: "",
  gender: "", address: "", city: "", state: "", notes: "", lgpdConsent: false,
};

const PER_PAGE_OPTIONS = [10, 20, 50, 100];
const DEFAULT_PER_PAGE = 20;

function getPageNumbers(current: number, total: number): (number | "…")[] {
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  const pages: (number | "…")[] = [1];
  if (current > 3) pages.push("…");
  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) pages.push(i);
  if (current < total - 2) pages.push("…");
  pages.push(total);
  return pages;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function getInitials(name: string): string {
  return name.split(" ").slice(0, 2).map((n) => n[0]).join("").toUpperCase();
}

// ─── Sub-components ──────────────────────────────────────────────────────────

function ClientsTableSkeleton() {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Nome</TableHead>
          <TableHead>Email</TableHead>
          <TableHead>Telefone</TableHead>
          <TableHead>Visitas</TableHead>
          <TableHead>Gasto total</TableHead>
          <TableHead>Última visita</TableHead>
          <TableHead className="w-44">Ações</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {Array.from({ length: 8 }).map((_, i) => (
          <TableRow key={i}>
            <TableCell>
              <div className="flex items-center gap-3">
                <div className="h-8 w-8 rounded-full bg-muted animate-pulse shrink-0" />
                <div className="h-4 w-32 bg-muted animate-pulse rounded" />
              </div>
            </TableCell>
            <TableCell><div className="h-4 w-40 bg-muted animate-pulse rounded" /></TableCell>
            <TableCell><div className="h-4 w-28 bg-muted animate-pulse rounded" /></TableCell>
            <TableCell><div className="h-4 w-8 bg-muted animate-pulse rounded" /></TableCell>
            <TableCell><div className="h-4 w-20 bg-muted animate-pulse rounded" /></TableCell>
            <TableCell><div className="h-4 w-24 bg-muted animate-pulse rounded" /></TableCell>
            <TableCell>
              <div className="flex items-center gap-2">
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

interface TablePaginationProps {
  page: number;
  totalPages: number;
  total: number;
  perPage: number;
  onPageChange: (p: number) => void;
  onPerPageChange: (n: number) => void;
}

function TablePagination({ page, totalPages, total, perPage, onPageChange, onPerPageChange }: TablePaginationProps) {
  const start = total === 0 ? 0 : (page - 1) * perPage + 1;
  const end = Math.min(page * perPage, total);
  const pages = getPageNumbers(page, totalPages);

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t text-sm">
      <div className="flex items-center gap-3 text-muted-foreground">
        <span>
          {total === 0 ? "Nenhum registro" : `Mostrando ${start}–${end} de ${total} clientes`}
        </span>
        <div className="flex items-center gap-1.5">
          <span className="text-xs">Por página:</span>
          <Select value={String(perPage)} onValueChange={(v) => onPerPageChange(Number(v))}>
            <SelectTrigger className="h-7 w-[64px] text-xs">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {PER_PAGE_OPTIONS.map((n) => (
                <SelectItem key={n} value={String(n)}>{n}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {totalPages > 1 && (
        <div className="flex items-center gap-1">
          <Button
            variant="outline" size="sm" className="h-8 w-8 p-0"
            onClick={() => onPageChange(1)} disabled={page === 1}
          >
            <ChevronsLeft className="h-4 w-4" />
          </Button>
          <Button
            variant="outline" size="sm" className="h-8 w-8 p-0"
            onClick={() => onPageChange(page - 1)} disabled={page === 1}
          >
            <ChevronLeft className="h-4 w-4" />
          </Button>

          {pages.map((p, i) =>
            p === "…" ? (
              <span key={`el-${i}`} className="h-8 w-8 flex items-center justify-center text-muted-foreground select-none">
                …
              </span>
            ) : (
              <Button
                key={p}
                variant={p === page ? "default" : "outline"}
                size="sm"
                className="h-8 w-8 p-0"
                onClick={() => onPageChange(p as number)}
              >
                {p}
              </Button>
            )
          )}

          <Button
            variant="outline" size="sm" className="h-8 w-8 p-0"
            onClick={() => onPageChange(page + 1)} disabled={page === totalPages}
          >
            <ChevronRight className="h-4 w-4" />
          </Button>
          <Button
            variant="outline" size="sm" className="h-8 w-8 p-0"
            onClick={() => onPageChange(totalPages)} disabled={page === totalPages}
          >
            <ChevronsRight className="h-4 w-4" />
          </Button>
        </div>
      )}
    </div>
  );
}

interface ClientFormFieldsProps {
  form: ClientForm;
  onChange: (field: keyof ClientForm, value: string | boolean) => void;
  disabled?: boolean;
}

function ClientFormFields({ form, onChange, disabled }: ClientFormFieldsProps) {
  return (
    <div className="space-y-5">
      <div className="space-y-1.5">
        <Label htmlFor="cf-name">
          Nome <span className="text-destructive">*</span>
        </Label>
        <Input
          id="cf-name"
          placeholder="Nome completo"
          value={form.name}
          onChange={(e) => onChange("name", e.target.value)}
          disabled={disabled}
          autoFocus
        />
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="cf-email">Email</Label>
          <Input
            id="cf-email"
            type="email"
            placeholder="email@exemplo.com"
            value={form.email}
            onChange={(e) => onChange("email", e.target.value)}
            disabled={disabled}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="cf-phone">Telefone</Label>
          <Input
            id="cf-phone"
            placeholder="(11) 99999-9999"
            value={form.phone}
            onChange={(e) => onChange("phone", e.target.value)}
            disabled={disabled}
          />
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="cf-cpf">CPF</Label>
          <Input
            id="cf-cpf"
            placeholder="000.000.000-00"
            value={form.cpf}
            onChange={(e) => onChange("cpf", e.target.value)}
            disabled={disabled}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="cf-birth">Data de nascimento</Label>
          <Input
            id="cf-birth"
            type="date"
            value={form.birthDate}
            onChange={(e) => onChange("birthDate", e.target.value)}
            disabled={disabled}
          />
        </div>
      </div>

      <div className="space-y-1.5">
        <Label>Gênero</Label>
        <Select
          value={form.gender}
          onValueChange={(val) => onChange("gender", val ?? "")}
          disabled={disabled}
          items={{ masculino: "Masculino", feminino: "Feminino", outro: "Outro", nao_informado: "Prefiro não informar" }}
        >
          <SelectTrigger className="w-full">
            <SelectValue placeholder="Selecionar..." />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="masculino">Masculino</SelectItem>
            <SelectItem value="feminino">Feminino</SelectItem>
            <SelectItem value="outro">Outro</SelectItem>
            <SelectItem value="nao_informado">Prefiro não informar</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="cf-address">Endereço</Label>
        <Input
          id="cf-address"
          placeholder="Rua, número, complemento"
          value={form.address}
          onChange={(e) => onChange("address", e.target.value)}
          disabled={disabled}
        />
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="col-span-2 space-y-1.5">
          <Label htmlFor="cf-city">Cidade</Label>
          <Input
            id="cf-city"
            placeholder="São Paulo"
            value={form.city}
            onChange={(e) => onChange("city", e.target.value)}
            disabled={disabled}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="cf-state">UF</Label>
          <Input
            id="cf-state"
            placeholder="SP"
            maxLength={2}
            value={form.state}
            onChange={(e) => onChange("state", e.target.value.toUpperCase())}
            disabled={disabled}
          />
        </div>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="cf-notes">Observações</Label>
        <Textarea
          id="cf-notes"
          placeholder="Informações adicionais sobre o cliente..."
          value={form.notes}
          onChange={(e) => onChange("notes", e.target.value)}
          disabled={disabled}
        />
      </div>

      <label className="flex items-start gap-3 cursor-pointer">
        <input
          type="checkbox"
          className="mt-0.5 h-4 w-4 rounded border-input accent-primary"
          checked={form.lgpdConsent}
          onChange={(e) => onChange("lgpdConsent", e.target.checked)}
          disabled={disabled}
        />
        <span className="text-sm text-muted-foreground leading-snug">
          Cliente autorizou o armazenamento e uso de seus dados pessoais conforme a LGPD.
        </span>
      </label>
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function ClientesPage() {
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(DEFAULT_PER_PAGE);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [createOpen, setCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState<ClientForm>(emptyForm);

  const [editClientId, setEditClientId] = useState<number | null>(null);
  const [editForm, setEditForm] = useState<ClientForm>(emptyForm);

  const [deleteTarget, setDeleteTarget] = useState<{ id: number; name: string } | null>(null);

  const queryClient = useQueryClient();

  // ── Queries ──────────────────────────────────────────────────────────────

  const { data, isLoading, isError } = useQuery<ClientsResponse>({
    queryKey: ["clients", debouncedSearch, page, perPage],
    queryFn: () =>
      fetch(`/api/clients?search=${encodeURIComponent(debouncedSearch)}&page=${page}&perPage=${perPage}`)
        .then((r) => { if (!r.ok) throw new Error(); return r.json(); }),
  });

  const { data: fullClient, isLoading: loadingFull } = useQuery({
    queryKey: ["client", editClientId],
    queryFn: () =>
      fetch(`/api/clients/${editClientId}`).then(async (r) => {
        if (!r.ok) throw new Error("Erro ao carregar cliente");
        return r.json();
      }),
    enabled: editClientId !== null,
  });

  useEffect(() => {
    if (!fullClient) return;
    setEditForm({
      name: fullClient.name ?? "",
      email: fullClient.email ?? "",
      phone: fullClient.phone ?? "",
      cpf: fullClient.cpf ?? "",
      birthDate: fullClient.birthDate ? fullClient.birthDate.slice(0, 10) : "",
      gender: fullClient.gender ?? "",
      address: fullClient.address ?? "",
      city: fullClient.city ?? "",
      state: fullClient.state ?? "",
      notes: fullClient.notes ?? "",
      lgpdConsent: fullClient.lgpdConsent ?? false,
    });
  }, [fullClient]);

  // ── Mutations ────────────────────────────────────────────────────────────

  const { mutate: createClient, isPending: creating } = useMutation({
    mutationFn: (payload: ClientForm) =>
      fetch("/api/clients", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      }).then(async (r) => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao cadastrar cliente"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["clients"] });
      setCreateOpen(false);
      setCreateForm(emptyForm);
      toast.success("Cliente cadastrado com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: updateClient, isPending: updating } = useMutation({
    mutationFn: (payload: ClientForm) =>
      fetch(`/api/clients/${editClientId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      }).then(async (r) => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao atualizar cliente"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["clients"] });
      queryClient.invalidateQueries({ queryKey: ["client", editClientId] });
      setEditClientId(null);
      toast.success("Cliente atualizado com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: deleteClient, isPending: deleting } = useMutation({
    mutationFn: (id: number) =>
      fetch(`/api/clients/${id}`, { method: "DELETE" }).then(async (r) => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao excluir cliente"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["clients"] });
      setDeleteTarget(null);
      toast.success("Cliente excluído com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  // ── Handlers ─────────────────────────────────────────────────────────────

  function handleSearch(value: string) {
    setSearch(value);
    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => { setDebouncedSearch(value); setPage(1); }, 400);
  }

  function handlePerPageChange(n: number) {
    setPerPage(n);
    setPage(1);
  }

  function handleCreateField(field: keyof ClientForm, value: string | boolean) {
    setCreateForm((f) => ({ ...f, [field]: value }));
  }

  function handleEditField(field: keyof ClientForm, value: string | boolean) {
    setEditForm((f) => ({ ...f, [field]: value }));
  }

  function handleCreateSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (createForm.name.trim().length < 2) { toast.error("Nome deve ter pelo menos 2 caracteres"); return; }
    createClient(createForm);
  }

  function handleEditSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (editForm.name.trim().length < 2) { toast.error("Nome deve ter pelo menos 2 caracteres"); return; }
    updateClient(editForm);
  }

  function openCreate() {
    setCreateForm(emptyForm);
    setCreateOpen(true);
  }

  function openEdit(id: number) {
    setEditForm(emptyForm);
    setEditClientId(id);
  }

  // ─────────────────────────────────────────────────────────────────────────

  const clients = data?.data ?? [];
  const totalPages = data?.totalPages ?? 1;

  return (
    <>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold">Clientes</h1>
            <p className="text-muted-foreground text-sm">
              {isLoading ? "Carregando..." : `${data?.total ?? 0} clientes cadastrados`}
            </p>
          </div>
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4 mr-2" />
            Novo cliente
          </Button>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Users className="h-4 w-4" />
              Lista de clientes
            </CardTitle>
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                className="pl-9"
                placeholder="Buscar por nome, email ou telefone..."
                value={search}
                onChange={(e) => handleSearch(e.target.value)}
              />
            </div>
          </CardHeader>

          <CardContent className="p-0">
            {isLoading ? (
              <ClientsTableSkeleton />
            ) : isError ? (
              <div className="p-10 flex flex-col items-center gap-3 text-destructive">
                <AlertCircle className="h-8 w-8" />
                <p className="font-medium">Erro ao carregar clientes</p>
                <p className="text-sm text-muted-foreground">Verifique sua conexão e tente novamente.</p>
              </div>
            ) : clients.length === 0 ? (
              <div className="p-12 flex flex-col items-center gap-3 text-center">
                <Users className="h-10 w-10 text-muted-foreground/40" />
                <div>
                  <p className="font-medium text-muted-foreground">
                    {debouncedSearch ? `Nenhum resultado para "${debouncedSearch}"` : "Nenhum cliente cadastrado"}
                  </p>
                  {!debouncedSearch && (
                    <p className="text-sm text-muted-foreground/70 mt-1">
                      Cadastre seu primeiro cliente para começar
                    </p>
                  )}
                </div>
                {!debouncedSearch && (
                  <Button size="sm" className="mt-1" onClick={openCreate}>
                    <Plus className="h-4 w-4 mr-2" />
                    Cadastrar cliente
                  </Button>
                )}
              </div>
            ) : (
              <>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Nome</TableHead>
                      <TableHead>Email</TableHead>
                      <TableHead>Telefone</TableHead>
                      <TableHead>Visitas</TableHead>
                      <TableHead>Gasto total</TableHead>
                      <TableHead>Última visita</TableHead>
                      <TableHead className="w-44">Ações</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {clients.map((client) => (
                      <TableRow
                        key={client.id}
                        className="hover:bg-accent cursor-pointer"
                        onClick={() => openEdit(client.id)}
                      >
                        <TableCell className="font-medium">
                          <div className="flex items-center gap-3">
                            <Avatar className="h-8 w-8 shrink-0">
                              <AvatarFallback className="text-xs bg-primary/10 text-primary font-semibold">
                                {getInitials(client.name)}
                              </AvatarFallback>
                            </Avatar>
                            {client.name}
                          </div>
                        </TableCell>
                        <TableCell className="text-muted-foreground">{client.email ?? "—"}</TableCell>
                        <TableCell>{client.phone ?? "—"}</TableCell>
                        <TableCell>{client.totalVisits}</TableCell>
                        <TableCell>
                          {Number(client.totalSpent ?? 0).toLocaleString("pt-BR", {
                            style: "currency",
                            currency: "BRL",
                          })}
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                          {client.lastVisitAt
                            ? format(new Date(client.lastVisitAt), "dd/MM/yyyy", { locale: ptBR })
                            : "—"}
                        </TableCell>
                        <TableCell onClick={(e) => e.stopPropagation()}>
                          <div className="flex items-center gap-2">
                            <Button
                              variant="outline"
                              size="sm"
                              className="h-8 px-3 text-xs"
                              onClick={() => openEdit(client.id)}
                            >
                              <Pencil className="h-3.5 w-3.5 mr-1.5" />
                              Editar
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              className="h-8 px-3 text-xs text-destructive border-destructive/30 hover:bg-destructive/10 hover:text-destructive hover:border-destructive/50"
                              onClick={() => setDeleteTarget({ id: client.id, name: client.name })}
                            >
                              <Trash2 className="h-3.5 w-3.5 mr-1.5" />
                              Excluir
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>

                <TablePagination
                  page={page}
                  totalPages={totalPages}
                  total={data?.total ?? 0}
                  perPage={perPage}
                  onPageChange={setPage}
                  onPerPageChange={handlePerPageChange}
                />
              </>
            )}
          </CardContent>
        </Card>
      </div>

      {/* ── Cadastrar cliente ─────────────────────────────────────────────── */}
      <Sheet open={createOpen} onOpenChange={(val) => setCreateOpen(val)}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-lg">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Novo cliente</SheetTitle>
            <SheetDescription>Preencha os dados do cliente. Apenas o nome é obrigatório.</SheetDescription>
          </SheetHeader>
          <form onSubmit={handleCreateSubmit} className="flex flex-col flex-1 overflow-hidden">
            <div className="flex-1 overflow-y-auto px-6 py-5">
              <ClientFormFields form={createForm} onChange={handleCreateField} disabled={creating} />
            </div>
            <SheetFooter className="px-6 py-4 border-t">
              <Button type="button" variant="outline" onClick={() => setCreateOpen(false)} disabled={creating}>
                Cancelar
              </Button>
              <Button type="submit" disabled={creating}>
                {creating ? "Salvando..." : "Cadastrar cliente"}
              </Button>
            </SheetFooter>
          </form>
        </SheetContent>
      </Sheet>

      {/* ── Editar cliente ────────────────────────────────────────────────── */}
      <Sheet open={editClientId !== null} onOpenChange={(val) => { if (!val) setEditClientId(null); }}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 data-[side=right]:sm:max-w-lg">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Editar cliente</SheetTitle>
            <SheetDescription>Atualize os dados do cliente.</SheetDescription>
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
                <ClientFormFields form={editForm} onChange={handleEditField} disabled={updating} />
              </div>
              <SheetFooter className="px-6 py-4 border-t">
                <Button type="button" variant="outline" onClick={() => setEditClientId(null)} disabled={updating}>
                  Cancelar
                </Button>
                <Button type="submit" disabled={updating}>
                  {updating ? "Salvando..." : "Salvar alterações"}
                </Button>
              </SheetFooter>
            </form>
          )}
        </SheetContent>
      </Sheet>

      {/* ── Confirmar exclusão ────────────────────────────────────────────── */}
      <AlertDialog open={deleteTarget !== null} onOpenChange={(val) => { if (!val) setDeleteTarget(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir cliente</AlertDialogTitle>
            <AlertDialogDescription>
              Tem certeza que deseja excluir{" "}
              <strong className="text-foreground">{deleteTarget?.name}</strong>?
              Esta ação não pode ser desfeita.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              disabled={deleting}
              onClick={() => deleteTarget && deleteClient(deleteTarget.id)}
            >
              {deleting ? "Excluindo..." : "Excluir"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
