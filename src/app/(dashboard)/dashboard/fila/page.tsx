"use client";

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import {
  ListOrdered,
  Play,
  CheckCircle,
  XCircle,
  Bell,
  Plus,
  Clock,
} from "lucide-react";

// ─── Types ────────────────────────────────────────────────────────────────────

type QueueEntry = {
  id: number;
  position: number;
  status: string;
  client?: { name: string; phone: string | null };
  professional?: { name: string; color: string | null };
  service?: { name: string; duration: number };
  checkedInAt: string;
  notes?: string | null;
};

type AddForm = {
  unitId: string;
  clientId: string;
  professionalId: string;
  serviceId: string;
  priority: number;
  notes: string;
};

const EMPTY_FORM: AddForm = {
  unitId: "",
  clientId: "",
  professionalId: "",
  serviceId: "",
  priority: 0,
  notes: "",
};

// ─── Constants ────────────────────────────────────────────────────────────────

const STATUS_CONFIG: Record<string, { label: string; color: string }> = {
  waiting:     { label: "Aguardando",     color: "bg-yellow-100 text-yellow-800" },
  called:      { label: "Chamado",        color: "bg-blue-100 text-blue-800"    },
  in_progress: { label: "Em atendimento", color: "bg-green-100 text-green-800"  },
};

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatTime(iso: string) {
  return new Date(iso).toLocaleTimeString("pt-BR", {
    hour: "2-digit",
    minute: "2-digit",
  });
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function FilaPage() {
  const queryClient = useQueryClient();
  const [sheetOpen, setSheetOpen] = useState(false);
  const [form, setForm] = useState<AddForm>(EMPTY_FORM);

  // ── Queries ────────────────────────────────────────────────────────────────

  const { data: queue, isLoading } = useQuery<QueueEntry[]>({
    queryKey: ["queue"],
    queryFn: () => fetch("/api/queue").then((r) => r.json()),
    refetchInterval: 15_000,
  });

  const { data: unitsData } = useQuery({
    queryKey: ["units-list"],
    queryFn: () => fetch("/api/units?perPage=100").then((r) => r.json()),
    enabled: sheetOpen,
  });

  const { data: clientsData } = useQuery({
    queryKey: ["clients-list"],
    queryFn: () => fetch("/api/clients?perPage=200").then((r) => r.json()),
    enabled: sheetOpen,
  });

  const { data: profsData } = useQuery({
    queryKey: ["professionals-list"],
    queryFn: () => fetch("/api/professionals?perPage=100").then((r) => r.json()),
    enabled: sheetOpen,
  });

  const { data: servicesData } = useQuery({
    queryKey: ["services-list"],
    queryFn: () => fetch("/api/services?perPage=100").then((r) => r.json()),
    enabled: sheetOpen,
  });

  const units: { id: number; name: string }[]           = unitsData?.data   ?? [];
  const clients: { id: number; name: string }[]         = clientsData?.data ?? [];
  const profs: { id: number; name: string }[]           = profsData?.data   ?? [];
  const services: { id: number; name: string; duration: number }[] = servicesData?.data ?? [];

  // ── Mutations ──────────────────────────────────────────────────────────────

  const updateStatus = useMutation({
    mutationFn: async ({ id, status }: { id: number; status: string }) => {
      const r = await fetch(`/api/queue/${id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status }),
      });
      if (!r.ok) throw new Error("Falha ao atualizar status");
      return r.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["queue"] });
      toast.success("Fila atualizada");
    },
    onError: () => toast.error("Erro ao atualizar a fila"),
  });

  const addToQueue = useMutation({
    mutationFn: async (data: AddForm) => {
      const body = {
        unitId:         parseInt(data.unitId),
        clientId:       data.clientId       ? parseInt(data.clientId)       : undefined,
        professionalId: data.professionalId ? parseInt(data.professionalId) : undefined,
        serviceId:      data.serviceId      ? parseInt(data.serviceId)      : undefined,
        priority:       data.priority,
        notes:          data.notes || undefined,
      };
      const r = await fetch("/api/queue", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      if (!r.ok) {
        const err = await r.json().catch(() => ({}));
        throw new Error((err as { error?: string }).error ?? "Erro ao adicionar à fila");
      }
      return r.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["queue"] });
      toast.success("Adicionado à fila com sucesso");
      setSheetOpen(false);
      setForm(EMPTY_FORM);
    },
    onError: (e: Error) => toast.error(e.message),
  });

  // ── Render ─────────────────────────────────────────────────────────────────

  const entries: QueueEntry[] = Array.isArray(queue) ? queue : [];

  function onChange(k: keyof AddForm, v: string | number) {
    setForm((f) => ({ ...f, [k]: v }));
  }

  return (
    <div className="space-y-6">

      {/* ── Header ───────────────────────────────────────────────────────── */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Fila de Atendimento</h1>
          <p className="text-muted-foreground text-sm">
            {entries.length} pessoa{entries.length !== 1 ? "s" : ""} na fila · atualiza a cada 15s
          </p>
        </div>
        <Button onClick={() => setSheetOpen(true)}>
          <Plus className="h-4 w-4 mr-2" />
          Adicionar à fila
        </Button>
      </div>

      {/* ── Queue list ───────────────────────────────────────────────────── */}
      <div className="grid gap-4">
        {isLoading ? (
          <div className="p-8 text-center text-muted-foreground">Carregando fila...</div>
        ) : entries.length === 0 ? (
          <Card>
            <CardContent className="p-12 text-center">
              <ListOrdered className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
              <p className="text-muted-foreground">Fila vazia no momento</p>
            </CardContent>
          </Card>
        ) : (
          entries.map((entry, index) => {
            const statusCfg = STATUS_CONFIG[entry.status] ?? STATUS_CONFIG.waiting;
            return (
              <Card
                key={entry.id}
                className={index === 0 ? "border-primary shadow-md" : ""}
              >
                <CardHeader className="pb-2">
                  <CardTitle className="flex items-center justify-between text-base">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-sm shrink-0">
                        {entry.position}
                      </div>
                      <div>
                        <p>{entry.client?.name ?? "Cliente avulso"}</p>
                        <p className="text-xs font-normal text-muted-foreground flex items-center gap-1">
                          <Clock className="h-3 w-3" />
                          desde {formatTime(entry.checkedInAt)}
                        </p>
                      </div>
                    </div>
                    <span
                      className={`text-xs px-2 py-1 rounded-full font-medium ${statusCfg.color}`}
                    >
                      {statusCfg.label}
                    </span>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="flex items-center justify-between gap-4">
                    <div className="text-sm text-muted-foreground space-y-0.5">
                      {entry.service && (
                        <p>Serviço: {entry.service.name} ({entry.service.duration} min)</p>
                      )}
                      {entry.professional && (
                        <p>Profissional: {entry.professional.name}</p>
                      )}
                      {entry.notes && <p>Obs: {entry.notes}</p>}
                    </div>

                    <div className="flex gap-2 shrink-0">
                      {entry.status === "waiting" && (
                        <Button
                          size="sm"
                          variant="outline"
                          disabled={updateStatus.isPending}
                          onClick={() =>
                            updateStatus.mutate({ id: entry.id, status: "called" })
                          }
                        >
                          <Bell className="h-4 w-4 mr-1" />
                          Chamar
                        </Button>
                      )}
                      {entry.status === "called" && (
                        <Button
                          size="sm"
                          disabled={updateStatus.isPending}
                          onClick={() =>
                            updateStatus.mutate({ id: entry.id, status: "in_progress" })
                          }
                        >
                          <Play className="h-4 w-4 mr-1" />
                          Iniciar
                        </Button>
                      )}
                      {entry.status === "in_progress" && (
                        <Button
                          size="sm"
                          disabled={updateStatus.isPending}
                          onClick={() =>
                            updateStatus.mutate({ id: entry.id, status: "completed" })
                          }
                        >
                          <CheckCircle className="h-4 w-4 mr-1" />
                          Concluir
                        </Button>
                      )}
                      <Button
                        size="sm"
                        variant="ghost"
                        className="text-destructive hover:text-destructive"
                        disabled={updateStatus.isPending}
                        onClick={() =>
                          updateStatus.mutate({ id: entry.id, status: "cancelled" })
                        }
                      >
                        <XCircle className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </CardContent>
              </Card>
            );
          })
        )}
      </div>

      {/* ── Add to queue sheet ───────────────────────────────────────────── */}
      <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <SheetContent className="overflow-y-auto">
          <SheetHeader>
            <SheetTitle>Adicionar à fila</SheetTitle>
          </SheetHeader>

          <form
            className="mt-6 space-y-5"
            onSubmit={(e) => {
              e.preventDefault();
              if (!form.unitId) {
                toast.error("Selecione uma unidade");
                return;
              }
              addToQueue.mutate(form);
            }}
          >
            {/* Unit — required */}
            <div className="space-y-1.5">
              <Label>
                Unidade <span className="text-destructive">*</span>
              </Label>
              <Select
                value={form.unitId}
                onValueChange={(v) => onChange("unitId", v ?? "")}
                items={Object.fromEntries(units.map((u) => [String(u.id), u.name]))}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione a unidade" />
                </SelectTrigger>
                <SelectContent>
                  {units.map((u) => (
                    <SelectItem key={u.id} value={String(u.id)}>
                      {u.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Client — optional */}
            <div className="space-y-1.5">
              <Label>
                Cliente{" "}
                <span className="text-xs text-muted-foreground">(opcional)</span>
              </Label>
              <Select
                value={form.clientId}
                onValueChange={(v) => onChange("clientId", v ?? "")}
                items={{
                  "": "Nenhum (avulso)",
                  ...Object.fromEntries(clients.map((c) => [String(c.id), c.name])),
                }}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione o cliente" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">Nenhum (avulso)</SelectItem>
                  {clients.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {c.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Professional — optional */}
            <div className="space-y-1.5">
              <Label>
                Profissional{" "}
                <span className="text-xs text-muted-foreground">(opcional)</span>
              </Label>
              <Select
                value={form.professionalId}
                onValueChange={(v) => onChange("professionalId", v ?? "")}
                items={{
                  "": "Qualquer profissional",
                  ...Object.fromEntries(profs.map((p) => [String(p.id), p.name])),
                }}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione o profissional" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">Qualquer profissional</SelectItem>
                  {profs.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>
                      {p.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Service — optional */}
            <div className="space-y-1.5">
              <Label>
                Serviço{" "}
                <span className="text-xs text-muted-foreground">(opcional)</span>
              </Label>
              <Select
                value={form.serviceId}
                onValueChange={(v) => onChange("serviceId", v ?? "")}
                items={{
                  "": "Sem serviço",
                  ...Object.fromEntries(
                    services.map((s) => [
                      String(s.id),
                      `${s.name} (${s.duration} min)`,
                    ])
                  ),
                }}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione o serviço" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">Sem serviço</SelectItem>
                  {services.map((s) => (
                    <SelectItem key={s.id} value={String(s.id)}>
                      {s.name} ({s.duration} min)
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Priority */}
            <div className="space-y-1.5">
              <Label>Prioridade</Label>
              <Input
                type="number"
                min={0}
                value={form.priority}
                onChange={(e) => onChange("priority", parseInt(e.target.value) || 0)}
              />
              <p className="text-xs text-muted-foreground">
                0 = normal · valores maiores sobem na fila
              </p>
            </div>

            {/* Notes */}
            <div className="space-y-1.5">
              <Label>Observações</Label>
              <Textarea
                value={form.notes}
                onChange={(e) => onChange("notes", e.target.value)}
                placeholder="Observações sobre o atendimento..."
                rows={3}
              />
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => { setSheetOpen(false); setForm(EMPTY_FORM); }}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={addToQueue.isPending}>
                {addToQueue.isPending ? "Adicionando..." : "Adicionar à fila"}
              </Button>
            </div>
          </form>
        </SheetContent>
      </Sheet>
    </div>
  );
}
