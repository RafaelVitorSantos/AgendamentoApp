"use client";

import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ListOrdered, Play, CheckCircle, XCircle, Bell } from "lucide-react";

const STATUS_CONFIG: Record<string, { label: string; color: string }> = {
  waiting: { label: "Aguardando", color: "bg-yellow-100 text-yellow-800" },
  called: { label: "Chamado", color: "bg-blue-100 text-blue-800" },
  in_progress: { label: "Em atendimento", color: "bg-green-100 text-green-800" },
};

export default function FilaPage() {
  const queryClient = useQueryClient();

  const { data: queue, isLoading } = useQuery({
    queryKey: ["queue"],
    queryFn: () => fetch("/api/queue").then((r) => r.json()),
    refetchInterval: 15000,
  });

  const updateStatus = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) =>
      fetch(`/api/queue/${id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status }),
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["queue"] });
      toast.success("Fila atualizada");
    },
  });

  const entries = Array.isArray(queue) ? queue : [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Fila de Atendimento</h1>
          <p className="text-muted-foreground text-sm">
            {entries.length} pessoa{entries.length !== 1 ? "s" : ""} na fila
          </p>
        </div>
        <Button>
          <ListOrdered className="h-4 w-4 mr-2" />
          Adicionar à fila
        </Button>
      </div>

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
          entries.map((entry: {
            id: number;
            position: number;
            status: string;
            client?: { name: string; phone: string };
            professional?: { name: string; color: string };
            service?: { name: string; duration: number };
            checkedInAt: string;
            notes?: string;
          }, index: number) => {
            const statusCfg = STATUS_CONFIG[entry.status] ?? STATUS_CONFIG.waiting;
            return (
              <Card key={entry.id} className={index === 0 ? "border-primary shadow-md" : ""}>
                <CardHeader className="pb-2">
                  <CardTitle className="flex items-center justify-between text-base">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-sm">
                        {entry.position}
                      </div>
                      <span>{entry.client?.name ?? "Cliente avulso"}</span>
                    </div>
                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${statusCfg.color}`}>
                      {statusCfg.label}
                    </span>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="flex items-center justify-between">
                    <div className="text-sm text-muted-foreground space-y-1">
                      {entry.service && <p>Serviço: {entry.service.name} ({entry.service.duration} min)</p>}
                      {entry.professional && <p>Profissional: {entry.professional.name}</p>}
                      {entry.notes && <p>Obs: {entry.notes}</p>}
                    </div>
                    <div className="flex gap-2">
                      {entry.status === "waiting" && (
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => updateStatus.mutate({ id: entry.id, status: "called" })}
                        >
                          <Bell className="h-4 w-4 mr-1" />
                          Chamar
                        </Button>
                      )}
                      {entry.status === "called" && (
                        <Button
                          size="sm"
                          onClick={() => updateStatus.mutate({ id: entry.id, status: "in_progress" })}
                        >
                          <Play className="h-4 w-4 mr-1" />
                          Iniciar
                        </Button>
                      )}
                      {entry.status === "in_progress" && (
                        <Button
                          size="sm"
                          variant="default"
                          onClick={() => updateStatus.mutate({ id: entry.id, status: "completed" })}
                        >
                          <CheckCircle className="h-4 w-4 mr-1" />
                          Concluir
                        </Button>
                      )}
                      <Button
                        size="sm"
                        variant="ghost"
                        className="text-destructive"
                        onClick={() => updateStatus.mutate({ id: entry.id, status: "cancelled" })}
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
    </div>
  );
}
