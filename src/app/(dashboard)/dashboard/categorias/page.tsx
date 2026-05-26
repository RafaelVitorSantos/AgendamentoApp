"use client";

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetFooter,
} from "@/components/ui/sheet";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Plus, Pencil, Trash2, Tag, Scissors, GripVertical } from "lucide-react";

// ─── Types ────────────────────────────────────────────────────────────────────

interface ServiceCategory {
  id: number;
  name: string;
  color: string | null;
  sortOrder: number;
  createdAt: string;
  _count: { services: number };
}

interface CategoryForm {
  name: string;
  color: string;
  sortOrder: string;
}

// ─── Constants ───────────────────────────────────────────────────────────────

const PRESET_COLORS = [
  "#6366f1", "#8b5cf6", "#ec4899", "#ef4444",
  "#f97316", "#eab308", "#22c55e", "#14b8a6",
  "#3b82f6", "#64748b",
];

const emptyForm: CategoryForm = { name: "", color: "#6366f1", sortOrder: "0" };

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function CategoriasPage() {
  const queryClient = useQueryClient();

  const [createOpen, setCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState<CategoryForm>(emptyForm);

  const [editCategory, setEditCategory] = useState<ServiceCategory | null>(null);
  const [editForm, setEditForm] = useState<CategoryForm>(emptyForm);

  const [deleteTarget, setDeleteTarget] = useState<ServiceCategory | null>(null);

  // ── Queries ──────────────────────────────────────────────────────────────

  const { data: categories = [], isLoading } = useQuery<ServiceCategory[]>({
    queryKey: ["service-categories"],
    queryFn: () => fetch("/api/service-categories").then((r) => r.json()),
  });

  // ── Mutations ────────────────────────────────────────────────────────────

  const { mutate: createCategory, isPending: creating } = useMutation({
    mutationFn: (form: CategoryForm) =>
      fetch("/api/service-categories", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: form.name.trim(),
          color: form.color || null,
          sortOrder: parseInt(form.sortOrder) || 0,
        }),
      }).then(async (r) => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao criar categoria"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["service-categories"] });
      queryClient.invalidateQueries({ queryKey: ["services"] });
      setCreateOpen(false);
      setCreateForm(emptyForm);
      toast.success("Categoria criada com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: updateCategory, isPending: updating } = useMutation({
    mutationFn: (form: CategoryForm) =>
      fetch(`/api/service-categories/${editCategory!.id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: form.name.trim(),
          color: form.color || null,
          sortOrder: parseInt(form.sortOrder) || 0,
        }),
      }).then(async (r) => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao atualizar categoria"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["service-categories"] });
      queryClient.invalidateQueries({ queryKey: ["services"] });
      setEditCategory(null);
      toast.success("Categoria atualizada com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: deleteCategory, isPending: deleting } = useMutation({
    mutationFn: (id: number) =>
      fetch(`/api/service-categories/${id}`, { method: "DELETE" }).then(async (r) => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error ?? "Erro ao excluir"); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["service-categories"] });
      queryClient.invalidateQueries({ queryKey: ["services"] });
      setDeleteTarget(null);
      toast.success("Categoria excluída com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  // ── Handlers ─────────────────────────────────────────────────────────────

  function openCreate() {
    setCreateForm(emptyForm);
    setCreateOpen(true);
  }

  function openEdit(cat: ServiceCategory) {
    setEditForm({ name: cat.name, color: cat.color ?? "#6366f1", sortOrder: String(cat.sortOrder) });
    setEditCategory(cat);
  }

  function handleCreateSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (createForm.name.trim().length < 2) { toast.error("Nome deve ter pelo menos 2 caracteres"); return; }
    createCategory(createForm);
  }

  function handleEditSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (editForm.name.trim().length < 2) { toast.error("Nome deve ter pelo menos 2 caracteres"); return; }
    updateCategory(editForm);
  }

  // ─────────────────────────────────────────────────────────────────────────

  return (
    <>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold">Categorias de serviços</h1>
            <p className="text-muted-foreground text-sm">
              Organize seus serviços em categorias para facilitar a navegação
            </p>
          </div>
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4 mr-2" />
            Nova categoria
          </Button>
        </div>

        {/* List */}
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="flex items-center gap-2 text-base">
              <Tag className="h-4 w-4" />
              {isLoading ? "Carregando..." : `${categories.length} ${categories.length === 1 ? "categoria" : "categorias"}`}
            </CardTitle>
          </CardHeader>

          <CardContent className="p-0">
            {isLoading ? (
              <div className="divide-y">
                {[1, 2, 3].map((i) => (
                  <div key={i} className="flex items-center gap-4 px-6 py-4">
                    <div className="w-8 h-8 rounded-full bg-muted animate-pulse shrink-0" />
                    <div className="flex-1 space-y-1.5">
                      <div className="h-4 w-36 bg-muted animate-pulse rounded" />
                      <div className="h-3 w-24 bg-muted animate-pulse rounded" />
                    </div>
                  </div>
                ))}
              </div>
            ) : categories.length === 0 ? (
              <div className="py-16 flex flex-col items-center gap-4 text-center px-6">
                <div className="w-16 h-16 rounded-full bg-muted flex items-center justify-center">
                  <Tag className="h-8 w-8 text-muted-foreground/40" />
                </div>
                <div>
                  <p className="font-medium text-muted-foreground">Nenhuma categoria cadastrada</p>
                  <p className="text-sm text-muted-foreground/70 mt-1">
                    Crie categorias para organizar melhor seus serviços
                  </p>
                </div>
                <Button size="sm" onClick={openCreate}>
                  <Plus className="h-4 w-4 mr-2" />
                  Criar primeira categoria
                </Button>
              </div>
            ) : (
              <div className="divide-y">
                {categories.map((cat) => (
                  <div
                    key={cat.id}
                    className="flex items-center gap-4 px-6 py-4 hover:bg-accent/30 transition-colors group"
                  >
                    {/* Drag handle (visual only) */}
                    <GripVertical className="h-4 w-4 text-muted-foreground/30 shrink-0 group-hover:text-muted-foreground/60 transition-colors" />

                    {/* Color dot */}
                    <div
                      className="w-9 h-9 rounded-full shrink-0 flex items-center justify-center text-white shadow-sm"
                      style={{ backgroundColor: cat.color ?? "#6366f1" }}
                    >
                      <Tag className="h-4 w-4" />
                    </div>

                    {/* Info */}
                    <div className="flex-1 min-w-0">
                      <p className="font-medium truncate">{cat.name}</p>
                      <div className="flex items-center gap-1.5 mt-0.5">
                        <Scissors className="h-3 w-3 text-muted-foreground" />
                        <span className="text-xs text-muted-foreground">
                          {cat._count.services === 0
                            ? "Nenhum serviço"
                            : `${cat._count.services} ${cat._count.services === 1 ? "serviço" : "serviços"}`}
                        </span>
                      </div>
                    </div>

                    {/* Badges */}
                    <div className="flex items-center gap-2 shrink-0">
                      {cat._count.services > 0 && (
                        <Badge
                          variant="secondary"
                          className="text-xs font-medium"
                          style={{ backgroundColor: `${cat.color ?? "#6366f1"}20`, color: cat.color ?? "#6366f1", border: `1px solid ${cat.color ?? "#6366f1"}30` }}
                        >
                          {cat._count.services}
                        </Badge>
                      )}
                    </div>

                    {/* Actions */}
                    <div className="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                      <Button
                        variant="outline" size="sm"
                        className="h-8 px-3 text-xs"
                        onClick={() => openEdit(cat)}
                      >
                        <Pencil className="h-3.5 w-3.5 mr-1.5" />
                        Editar
                      </Button>
                      <Button
                        variant="outline" size="sm"
                        className="h-8 px-3 text-xs text-destructive border-destructive/30 hover:bg-destructive/10 hover:text-destructive hover:border-destructive/50"
                        onClick={() => setDeleteTarget(cat)}
                      >
                        <Trash2 className="h-3.5 w-3.5 mr-1.5" />
                        Excluir
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* ── Sheet: Nova categoria ─────────────────────────────────────────────── */}
      <Sheet open={createOpen} onOpenChange={(v) => { if (!creating) setCreateOpen(v); }}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 sm:max-w-md">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Nova categoria</SheetTitle>
            <SheetDescription>Crie uma categoria para organizar seus serviços.</SheetDescription>
          </SheetHeader>

          <form onSubmit={handleCreateSubmit} className="flex flex-col flex-1 overflow-hidden">
            <div className="flex-1 overflow-y-auto px-6 py-5 space-y-5">
              <CategoryFormFields form={createForm} onChange={(f) => setCreateForm(f)} disabled={creating} />
            </div>
            <SheetFooter className="px-6 py-4 border-t">
              <Button type="button" variant="outline" onClick={() => setCreateOpen(false)} disabled={creating}>
                Cancelar
              </Button>
              <Button type="submit" disabled={creating}>
                {creating ? "Salvando..." : "Criar categoria"}
              </Button>
            </SheetFooter>
          </form>
        </SheetContent>
      </Sheet>

      {/* ── Sheet: Editar categoria ───────────────────────────────────────────── */}
      <Sheet open={editCategory !== null} onOpenChange={(v) => { if (!v && !updating) setEditCategory(null); }}>
        <SheetContent side="right" className="flex flex-col gap-0 p-0 sm:max-w-md">
          <SheetHeader className="px-6 py-5 border-b">
            <SheetTitle>Editar categoria</SheetTitle>
            <SheetDescription>Atualize os dados da categoria.</SheetDescription>
          </SheetHeader>

          <form onSubmit={handleEditSubmit} className="flex flex-col flex-1 overflow-hidden">
            <div className="flex-1 overflow-y-auto px-6 py-5 space-y-5">
              <CategoryFormFields form={editForm} onChange={(f) => setEditForm(f)} disabled={updating} />
            </div>
            <SheetFooter className="px-6 py-4 border-t">
              <Button type="button" variant="outline" onClick={() => setEditCategory(null)} disabled={updating}>
                Cancelar
              </Button>
              <Button type="submit" disabled={updating}>
                {updating ? "Salvando..." : "Salvar alterações"}
              </Button>
            </SheetFooter>
          </form>
        </SheetContent>
      </Sheet>

      {/* ── Confirmar exclusão ────────────────────────────────────────────────── */}
      <AlertDialog open={deleteTarget !== null} onOpenChange={(v) => { if (!v) setDeleteTarget(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir categoria</AlertDialogTitle>
            <AlertDialogDescription>
              Tem certeza que deseja excluir a categoria{" "}
              <strong className="text-foreground">"{deleteTarget?.name}"</strong>?
              {deleteTarget && deleteTarget._count.services > 0 && (
                <span className="block mt-2 text-amber-600">
                  ⚠️ {deleteTarget._count.services}{" "}
                  {deleteTarget._count.services === 1 ? "serviço ficará" : "serviços ficarão"} sem categoria.
                </span>
              )}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              disabled={deleting}
              onClick={() => deleteTarget && deleteCategory(deleteTarget.id)}
            >
              {deleting ? "Excluindo..." : "Excluir"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

// ─── Form fields ──────────────────────────────────────────────────────────────

function CategoryFormFields({
  form,
  onChange,
  disabled,
}: {
  form: CategoryForm;
  onChange: (f: CategoryForm) => void;
  disabled?: boolean;
}) {
  return (
    <>
      {/* Name */}
      <div className="space-y-1.5">
        <Label htmlFor="cat-name">
          Nome <span className="text-destructive">*</span>
        </Label>
        <Input
          id="cat-name"
          placeholder="Ex: Cabelo, Estética, Massagem..."
          value={form.name}
          onChange={(e) => onChange({ ...form, name: e.target.value })}
          disabled={disabled}
          autoFocus
        />
      </div>

      {/* Color */}
      <div className="space-y-2">
        <Label>Cor de identificação</Label>

        {/* Preset swatches */}
        <div className="flex flex-wrap gap-2">
          {PRESET_COLORS.map((c) => (
            <button
              key={c}
              type="button"
              disabled={disabled}
              onClick={() => onChange({ ...form, color: c })}
              className="w-8 h-8 rounded-full border-2 transition-transform hover:scale-110 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
              style={{
                backgroundColor: c,
                borderColor: form.color === c ? "white" : c,
                boxShadow: form.color === c ? `0 0 0 3px ${c}` : undefined,
              }}
              title={c}
            />
          ))}
        </div>

        {/* Custom color picker + hex input */}
        <div className="flex items-center gap-3">
          <input
            type="color"
            value={form.color}
            onChange={(e) => onChange({ ...form, color: e.target.value })}
            disabled={disabled}
            className="h-9 w-12 cursor-pointer rounded-md border border-input bg-transparent p-0.5 disabled:opacity-50"
          />
          <Input
            placeholder="#6366f1"
            value={form.color}
            onChange={(e) => {
              const val = e.target.value;
              if (/^#[0-9A-Fa-f]{0,6}$/.test(val)) onChange({ ...form, color: val });
            }}
            disabled={disabled}
            className="flex-1 font-mono text-sm"
            maxLength={7}
          />
          {/* Preview */}
          <div
            className="w-9 h-9 rounded-full shrink-0 flex items-center justify-center text-white shadow-sm"
            style={{ backgroundColor: /^#[0-9A-Fa-f]{6}$/.test(form.color) ? form.color : "#6366f1" }}
          >
            <Tag className="h-4 w-4" />
          </div>
        </div>
      </div>

      {/* Sort order */}
      <div className="space-y-1.5">
        <Label htmlFor="cat-order">Ordem de exibição</Label>
        <Input
          id="cat-order"
          type="number"
          min="0"
          placeholder="0"
          value={form.sortOrder}
          onChange={(e) => onChange({ ...form, sortOrder: e.target.value })}
          disabled={disabled}
        />
        <p className="text-xs text-muted-foreground">
          Categorias com menor número aparecem primeiro. Use 0 para ordenação alfabética.
        </p>
      </div>
    </>
  );
}
