"use client";

import { useState } from "react";
import { useQuery, useMutation } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import { CheckCircle } from "lucide-react";

const schema = z.object({
  clientName: z.string().min(2, "Nome obrigatório"),
  clientPhone: z.string().min(10, "Telefone obrigatório"),
  clientEmail: z.string().email("Email inválido").optional().or(z.literal("")),
  serviceId: z.string().min(1, "Selecione um serviço"),
  unitId: z.string().min(1, "Selecione uma unidade"),
  professionalId: z.string().min(1, "Selecione um profissional"),
  date: z.string().min(1, "Selecione uma data"),
  startTime: z.string().min(1, "Selecione um horário"),
});

type FormData = z.infer<typeof schema>;

interface Service { id: number; name: string; duration: number; price: number | string }
interface Unit { id: number; name: string }

export function PublicBookingForm({
  tenantSlug,
  services,
  units,
}: {
  tenantSlug: string;
  tenantId: number;
  services: Service[];
  units: Unit[];
}) {
  const [booked, setBooked] = useState(false);
  const [selectedService, setSelectedService] = useState<Service | null>(null);
  const [selectedUnit, setSelectedUnit] = useState<Unit | null>(null);
  const [selectedProfId, setSelectedProfId] = useState<string>("");
  const [selectedDate, setSelectedDate] = useState<string>("");

  const { register, handleSubmit, setValue, watch, formState: { errors } } = useForm<FormData>({
    resolver: zodResolver(schema),
  });

  const serviceId = watch("serviceId");
  const unitId = watch("unitId");

  const { data: professionals } = useQuery<{ id: number; name: string }[]>({
    queryKey: ["public-professionals", tenantSlug, serviceId, unitId],
    enabled: !!serviceId && !!unitId,
    queryFn: () =>
      fetch(`/api/book/${tenantSlug}/professionals?serviceId=${serviceId}&unitId=${unitId}`).then((r) => r.json()),
  });

  const { data: slots } = useQuery<{ slots: { startTime: string; endTime: string }[] }>({
    queryKey: ["public-slots", tenantSlug, selectedProfId, serviceId, selectedDate],
    enabled: !!selectedProfId && !!serviceId && !!selectedDate,
    queryFn: () =>
      fetch(
        `/api/book/${tenantSlug}/slots?professionalId=${selectedProfId}&serviceId=${serviceId}&date=${selectedDate}`
      ).then((r) => r.json()),
  });

  const book = useMutation({
    mutationFn: (data: FormData) =>
      fetch(`/api/book/${tenantSlug}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      }).then((r) => r.json()),
    onSuccess: (res) => {
      if (res.error) { toast.error(res.error); return; }
      setBooked(true);
    },
    onError: () => toast.error("Erro ao agendar"),
  });

  if (booked) {
    return (
      <Card className="shadow-xl">
        <CardContent className="p-12 text-center">
          <CheckCircle className="h-16 w-16 text-green-600 mx-auto mb-4" />
          <h2 className="text-2xl font-bold mb-2">Agendamento confirmado!</h2>
          <p className="text-muted-foreground">
            Você receberá uma confirmação por email/WhatsApp em breve.
          </p>
          <Button className="mt-6" onClick={() => setBooked(false)}>
            Fazer outro agendamento
          </Button>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="shadow-xl">
      <CardHeader>
        <CardTitle>Preencha seus dados</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit((data) => book.mutate(data))} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label>Seu nome *</Label>
              <Input placeholder="Nome completo" {...register("clientName")} />
              {errors.clientName && <p className="text-sm text-destructive">{errors.clientName.message}</p>}
            </div>
            <div className="space-y-2">
              <Label>Telefone / WhatsApp *</Label>
              <Input placeholder="(11) 99999-9999" {...register("clientPhone")} />
              {errors.clientPhone && <p className="text-sm text-destructive">{errors.clientPhone.message}</p>}
            </div>
          </div>

          <div className="space-y-2">
            <Label>Email (opcional)</Label>
            <Input type="email" placeholder="seu@email.com" {...register("clientEmail")} />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label>Serviço *</Label>
              <Select onValueChange={(v: unknown) => {
                const val = String(v);
                setValue("serviceId", val);
                setSelectedService(services.find((s) => String(s.id) === val) ?? null);
              }}>
                <SelectTrigger>
                  <SelectValue placeholder="Selecione..." />
                </SelectTrigger>
                <SelectContent>
                  {services.map((s) => (
                    <SelectItem key={s.id} value={String(s.id)}>
                      {s.name} — {Number(s.price).toLocaleString("pt-BR", { style: "currency", currency: "BRL" })}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.serviceId && <p className="text-sm text-destructive">{errors.serviceId.message}</p>}
            </div>

            <div className="space-y-2">
              <Label>Unidade *</Label>
              <Select onValueChange={(v: unknown) => {
                const val = String(v);
                setValue("unitId", val);
                setSelectedUnit(units.find((u) => String(u.id) === val) ?? null);
              }}>
                <SelectTrigger>
                  <SelectValue placeholder="Selecione..." />
                </SelectTrigger>
                <SelectContent>
                  {units.map((u) => (
                    <SelectItem key={u.id} value={String(u.id)}>{u.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.unitId && <p className="text-sm text-destructive">{errors.unitId.message}</p>}
            </div>
          </div>

          {(professionals ?? []).length > 0 && (
            <div className="space-y-2">
              <Label>Profissional *</Label>
              <Select onValueChange={(v: unknown) => {
                const val = String(v);
                setValue("professionalId", val);
                setSelectedProfId(val);
              }}>
                <SelectTrigger>
                  <SelectValue placeholder="Selecione..." />
                </SelectTrigger>
                <SelectContent>
                  {(professionals ?? []).map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.professionalId && <p className="text-sm text-destructive">{errors.professionalId.message}</p>}
            </div>
          )}

          {selectedProfId && (
            <div className="space-y-2">
              <Label>Data *</Label>
              <Input
                type="date"
                min={new Date().toISOString().split("T")[0]}
                {...register("date")}
                onChange={(e) => {
                  setValue("date", e.target.value);
                  setSelectedDate(e.target.value);
                }}
              />
              {errors.date && <p className="text-sm text-destructive">{errors.date.message}</p>}
            </div>
          )}

          {(slots?.slots?.length ?? 0) > 0 && (
            <div className="space-y-2">
              <Label>Horário *</Label>
              <div className="grid grid-cols-4 gap-2">
                {(slots?.slots ?? []).map((slot) => (
                  <Button
                    key={slot.startTime}
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setValue("startTime", slot.startTime)}
                    className={watch("startTime") === slot.startTime ? "border-primary bg-primary/10" : ""}
                  >
                    {slot.startTime}
                  </Button>
                ))}
              </div>
              {errors.startTime && <p className="text-sm text-destructive">{errors.startTime.message}</p>}
            </div>
          )}

          {selectedService && (
            <div className="rounded-lg bg-muted p-4 text-sm">
              <p><strong>{selectedService.name}</strong></p>
              <p className="text-muted-foreground">Duração: {selectedService.duration} min</p>
              <p className="font-semibold">
                {Number(selectedService.price).toLocaleString("pt-BR", { style: "currency", currency: "BRL" })}
              </p>
            </div>
          )}

          <Button type="submit" className="w-full" disabled={book.isPending}>
            {book.isPending ? "Agendando..." : "Confirmar agendamento"}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
