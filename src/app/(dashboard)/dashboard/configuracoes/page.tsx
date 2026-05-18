"use client";

import { useState, useEffect } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { format } from "date-fns";
import { ptBR } from "date-fns/locale";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import {
  User, Building2, CreditCard, Shield, Eye, EyeOff,
  Check, X, AlertTriangle, Briefcase, Users, LayoutGrid, Calendar,
} from "lucide-react";

// ─── Types ────────────────────────────────────────────────────────────────────

interface SettingsData {
  user: {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: { name: string; label: string };
  };
  tenant: {
    id: number;
    name: string;
    slug: string;
    email: string | null;
    phone: string | null;
    document: string | null;
    timezone: string | null;
    status: string;
    trialEndsAt: string | null;
  };
  subscription: {
    id: number;
    status: string;
    billingCycle: string;
    currentPeriodStart: string;
    currentPeriodEnd: string;
    plan: {
      name: string;
      slug: string;
      price: string | number;
      billingCycle: string;
      maxProfessionals: number;
      maxAppointmentsMonth: number;
      maxUnits: number;
      maxClients: number;
      hasReports: boolean;
      hasWhatsapp: boolean;
      hasLoyalty: boolean;
      hasFinancial: boolean;
      hasCommissions: boolean;
      hasReviews: boolean;
      hasCustomBrand: boolean;
    };
  } | null;
  usage: {
    professionals: number;
    clients: number;
    units: number;
    appointmentsMonth: number;
  };
}

// ─── Constants ────────────────────────────────────────────────────────────────

const TIMEZONES = [
  { value: "America/Sao_Paulo",   label: "Brasília (GMT-3)" },
  { value: "America/Manaus",      label: "Manaus (GMT-4)" },
  { value: "America/Cuiaba",      label: "Cuiabá (GMT-4)" },
  { value: "America/Porto_Velho", label: "Porto Velho (GMT-4)" },
  { value: "America/Boa_Vista",   label: "Boa Vista (GMT-4)" },
  { value: "America/Rio_Branco",  label: "Rio Branco (GMT-5)" },
  { value: "America/Noronha",     label: "Fernando de Noronha (GMT-2)" },
];

const PLAN_FEATURES = [
  { key: "hasReports",     label: "Relatórios avançados" },
  { key: "hasFinancial",   label: "Módulo financeiro" },
  { key: "hasCommissions", label: "Comissões de profissionais" },
  { key: "hasWhatsapp",    label: "Integração WhatsApp" },
  { key: "hasLoyalty",     label: "Programa de fidelidade" },
  { key: "hasReviews",     label: "Avaliações de clientes" },
  { key: "hasCustomBrand", label: "Marca personalizada" },
] as const;

const SUBSCRIPTION_STATUS: Record<string, { label: string; className: string }> = {
  active:    { label: "Ativo",     className: "bg-green-500/10 text-green-700 border-green-500/20" },
  trial:     { label: "Trial",     className: "bg-blue-500/10 text-blue-700 border-blue-500/20" },
  suspended: { label: "Suspenso",  className: "bg-red-500/10 text-red-700 border-red-500/20" },
  cancelled: { label: "Cancelado", className: "bg-muted text-muted-foreground" },
};

// ─── Helpers ─────────────────────────────────────────────────────────────────

function initials(name: string) {
  return name.split(" ").slice(0, 2).map(w => w[0]).join("").toUpperCase();
}

function brl(value: number | string) {
  return Number(value).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

function ProgressBar({ value, max }: { value: number; max: number }) {
  const pct = max > 0 ? Math.min((value / max) * 100, 100) : 0;
  const over = value > max;
  return (
    <div className="h-1.5 w-full rounded-full bg-muted overflow-hidden">
      <div
        className={`h-full rounded-full transition-all ${over ? "bg-destructive" : pct > 80 ? "bg-amber-500" : "bg-primary"}`}
        style={{ width: `${pct}%` }}
      />
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function ConfiguracoesPage() {
  const queryClient = useQueryClient();

  // ── Profile form ─────────────────────────────────────────────────────────
  const [profileForm, setProfileForm] = useState({ name: "", email: "", phone: "" });
  const [passForm, setPassForm] = useState({ current: "", next: "", confirm: "" });
  const [showCurrent, setShowCurrent] = useState(false);
  const [showNext, setShowNext] = useState(false);

  // ── Tenant form ──────────────────────────────────────────────────────────
  const [tenantForm, setTenantForm] = useState({
    name: "", email: "", phone: "", document: "", timezone: "",
  });

  // ── Query ────────────────────────────────────────────────────────────────

  const { data: settings, isLoading } = useQuery<SettingsData>({
    queryKey: ["settings"],
    queryFn: () => fetch("/api/settings").then(r => r.json()),
  });

  useEffect(() => {
    if (!settings) return;
    if (settings.user) {
      setProfileForm({
        name: settings.user.name,
        email: settings.user.email,
        phone: settings.user.phone ?? "",
      });
    }
    if (settings.tenant) {
      setTenantForm({
        name: settings.tenant.name,
        email: settings.tenant.email ?? "",
        phone: settings.tenant.phone ?? "",
        document: settings.tenant.document ?? "",
        timezone: settings.tenant.timezone ?? "America/Sao_Paulo",
      });
    }
  }, [settings]);

  // ── Mutations ────────────────────────────────────────────────────────────

  const { mutate: saveProfile, isPending: savingProfile } = useMutation({
    mutationFn: () =>
      fetch("/api/settings/profile", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: profileForm.name,
          email: profileForm.email,
          phone: profileForm.phone || null,
        }),
      }).then(async r => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["settings"] });
      toast.success("Perfil atualizado com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: changePassword, isPending: changingPassword } = useMutation({
    mutationFn: () =>
      fetch("/api/settings/password", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ currentPassword: passForm.current, newPassword: passForm.next }),
      }).then(async r => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error); }
        return r.json();
      }),
    onSuccess: () => {
      setPassForm({ current: "", next: "", confirm: "" });
      toast.success("Senha alterada com sucesso!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const { mutate: saveTenant, isPending: savingTenant } = useMutation({
    mutationFn: () =>
      fetch("/api/settings/tenant", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: tenantForm.name,
          email: tenantForm.email || null,
          phone: tenantForm.phone || null,
          document: tenantForm.document || null,
          timezone: tenantForm.timezone,
        }),
      }).then(async r => {
        if (!r.ok) { const e = await r.json(); throw new Error(e.error); }
        return r.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["settings"] });
      toast.success("Dados da empresa atualizados!");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  // ── Handlers ─────────────────────────────────────────────────────────────

  function handleProfileSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (profileForm.name.trim().length < 2) { toast.error("Nome deve ter pelo menos 2 caracteres"); return; }
    if (!profileForm.email) { toast.error("E-mail é obrigatório"); return; }
    saveProfile();
  }

  function handlePasswordSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!passForm.current) { toast.error("Informe a senha atual"); return; }
    if (passForm.next.length < 8) { toast.error("Nova senha deve ter pelo menos 8 caracteres"); return; }
    if (passForm.next !== passForm.confirm) { toast.error("As senhas não coincidem"); return; }
    changePassword();
  }

  function handleTenantSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (tenantForm.name.trim().length < 2) { toast.error("Nome da empresa deve ter pelo menos 2 caracteres"); return; }
    saveTenant();
  }

  // ─────────────────────────────────────────────────────────────────────────

  const plan = settings?.subscription?.plan;
  const sub = settings?.subscription;
  const usage = settings?.usage;

  const trialDaysLeft = settings?.tenant?.trialEndsAt
    ? Math.max(0, Math.ceil((new Date(settings.tenant.trialEndsAt).getTime() - Date.now()) / 86400000))
    : null;

  return (
    <div className="space-y-6 max-w-3xl">
      <div>
        <h1 className="text-2xl font-bold">Configurações</h1>
        <p className="text-muted-foreground text-sm">Gerencie seu perfil, empresa e plano</p>
      </div>

      {isLoading ? (
        <div className="space-y-4">
          {[1, 2, 3].map(i => (
            <Card key={i}>
              <CardContent className="p-6">
                <div className="space-y-3">
                  <div className="h-5 w-32 bg-muted animate-pulse rounded" />
                  <div className="h-9 w-full bg-muted animate-pulse rounded" />
                  <div className="h-9 w-full bg-muted animate-pulse rounded" />
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      ) : (
        <Tabs defaultValue="perfil">
          <TabsList>
            <TabsTrigger value="perfil"><User className="h-4 w-4" />Meu perfil</TabsTrigger>
            <TabsTrigger value="empresa"><Building2 className="h-4 w-4" />Empresa</TabsTrigger>
            <TabsTrigger value="plano"><CreditCard className="h-4 w-4" />Plano</TabsTrigger>
          </TabsList>

          {/* ── Perfil tab ──────────────────────────────────────────────── */}
          <TabsContent value="perfil" className="space-y-4">

            {/* Trial warning */}
            {settings?.tenant?.status === "trial" && trialDaysLeft !== null && (
              <div className={`flex items-start gap-3 p-4 rounded-lg border ${trialDaysLeft <= 3 ? "bg-red-50 border-red-200 dark:bg-red-950/20 dark:border-red-800" : "bg-amber-50 border-amber-200 dark:bg-amber-950/20 dark:border-amber-800"}`}>
                <AlertTriangle className={`h-4 w-4 mt-0.5 shrink-0 ${trialDaysLeft <= 3 ? "text-red-600" : "text-amber-600"}`} />
                <div>
                  <p className={`text-sm font-medium ${trialDaysLeft <= 3 ? "text-red-700" : "text-amber-700"}`}>
                    {trialDaysLeft === 0 ? "Seu período de trial termina hoje!" : `Período de trial termina em ${trialDaysLeft} dia${trialDaysLeft !== 1 ? "s" : ""}`}
                  </p>
                  <p className="text-xs text-muted-foreground mt-0.5">
                    Acesse a aba Plano para escolher uma assinatura.
                  </p>
                </div>
              </div>
            )}

            {/* Profile info */}
            <Card>
              <CardHeader>
                <CardTitle className="text-base flex items-center gap-2">
                  <User className="h-4 w-4" />Informações pessoais
                </CardTitle>
                <CardDescription>Atualize seus dados de acesso ao sistema.</CardDescription>
              </CardHeader>
              <CardContent>
                <form onSubmit={handleProfileSubmit} className="space-y-5">
                  {/* Avatar */}
                  <div className="flex items-center gap-4">
                    <div className="w-16 h-16 rounded-full bg-primary flex items-center justify-center text-primary-foreground text-xl font-bold shrink-0">
                      {initials(profileForm.name || settings?.user?.name || "?")}
                    </div>
                    <div>
                      <p className="font-medium">{settings?.user?.name}</p>
                      <Badge variant="outline" className="text-xs mt-1">
                        <Shield className="h-3 w-3 mr-1" />
                        {settings?.user?.role?.label ?? settings?.user?.role?.name}
                      </Badge>
                    </div>
                  </div>

                  <Separator />

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                      <Label htmlFor="pf-name">Nome completo <span className="text-destructive">*</span></Label>
                      <Input id="pf-name" value={profileForm.name}
                        onChange={e => setProfileForm(f => ({ ...f, name: e.target.value }))}
                        disabled={savingProfile} />
                    </div>
                    <div className="space-y-1.5">
                      <Label htmlFor="pf-phone">Telefone</Label>
                      <Input id="pf-phone" placeholder="(11) 99999-9999" value={profileForm.phone}
                        onChange={e => setProfileForm(f => ({ ...f, phone: e.target.value }))}
                        disabled={savingProfile} />
                    </div>
                  </div>

                  <div className="space-y-1.5">
                    <Label htmlFor="pf-email">E-mail <span className="text-destructive">*</span></Label>
                    <Input id="pf-email" type="email" value={profileForm.email}
                      onChange={e => setProfileForm(f => ({ ...f, email: e.target.value }))}
                      disabled={savingProfile} />
                  </div>

                  <div className="flex justify-end">
                    <Button type="submit" disabled={savingProfile}>
                      {savingProfile ? "Salvando..." : "Salvar perfil"}
                    </Button>
                  </div>
                </form>
              </CardContent>
            </Card>

            {/* Password */}
            <Card>
              <CardHeader>
                <CardTitle className="text-base flex items-center gap-2">
                  <Shield className="h-4 w-4" />Alterar senha
                </CardTitle>
                <CardDescription>Use uma senha forte com pelo menos 8 caracteres.</CardDescription>
              </CardHeader>
              <CardContent>
                <form onSubmit={handlePasswordSubmit} className="space-y-4">
                  <div className="space-y-1.5">
                    <Label htmlFor="pass-current">Senha atual <span className="text-destructive">*</span></Label>
                    <div className="relative">
                      <Input id="pass-current" type={showCurrent ? "text" : "password"}
                        value={passForm.current} onChange={e => setPassForm(f => ({ ...f, current: e.target.value }))}
                        disabled={changingPassword} className="pr-10" />
                      <button type="button" className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        onClick={() => setShowCurrent(v => !v)}>
                        {showCurrent ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </button>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                      <Label htmlFor="pass-next">Nova senha <span className="text-destructive">*</span></Label>
                      <div className="relative">
                        <Input id="pass-next" type={showNext ? "text" : "password"}
                          value={passForm.next} onChange={e => setPassForm(f => ({ ...f, next: e.target.value }))}
                          disabled={changingPassword} className="pr-10" />
                        <button type="button" className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                          onClick={() => setShowNext(v => !v)}>
                          {showNext ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                      </div>
                      {passForm.next && (
                        <div className="flex items-center gap-1.5 text-xs">
                          {passForm.next.length >= 8
                            ? <><Check className="h-3 w-3 text-green-600" /><span className="text-green-600">Mínimo 8 caracteres</span></>
                            : <><X className="h-3 w-3 text-destructive" /><span className="text-muted-foreground">{8 - passForm.next.length} caractere{8 - passForm.next.length !== 1 ? "s" : ""} restante{8 - passForm.next.length !== 1 ? "s" : ""}</span></>
                          }
                        </div>
                      )}
                    </div>
                    <div className="space-y-1.5">
                      <Label htmlFor="pass-confirm">Confirmar nova senha <span className="text-destructive">*</span></Label>
                      <Input id="pass-confirm" type="password"
                        value={passForm.confirm} onChange={e => setPassForm(f => ({ ...f, confirm: e.target.value }))}
                        disabled={changingPassword} />
                      {passForm.confirm && passForm.next && (
                        <div className="flex items-center gap-1.5 text-xs">
                          {passForm.next === passForm.confirm
                            ? <><Check className="h-3 w-3 text-green-600" /><span className="text-green-600">Senhas coincidem</span></>
                            : <><X className="h-3 w-3 text-destructive" /><span className="text-muted-foreground">Senhas não coincidem</span></>
                          }
                        </div>
                      )}
                    </div>
                  </div>

                  <div className="flex justify-end">
                    <Button type="submit" variant="outline" disabled={changingPassword}>
                      {changingPassword ? "Alterando..." : "Alterar senha"}
                    </Button>
                  </div>
                </form>
              </CardContent>
            </Card>
          </TabsContent>

          {/* ── Empresa tab ─────────────────────────────────────────────── */}
          <TabsContent value="empresa" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle className="text-base flex items-center gap-2">
                  <Building2 className="h-4 w-4" />Dados da empresa
                </CardTitle>
                <CardDescription>Informações que identificam seu negócio na plataforma.</CardDescription>
              </CardHeader>
              <CardContent>
                <form onSubmit={handleTenantSubmit} className="space-y-5">
                  <div className="space-y-1.5">
                    <Label htmlFor="te-name">Nome da empresa <span className="text-destructive">*</span></Label>
                    <Input id="te-name" value={tenantForm.name}
                      onChange={e => setTenantForm(f => ({ ...f, name: e.target.value }))}
                      disabled={savingTenant} />
                  </div>

                  {/* Slug (read-only) */}
                  <div className="space-y-1.5">
                    <Label>Slug (identificador)</Label>
                    <div className="flex items-center gap-2">
                      <Input value={settings?.tenant?.slug ?? ""} readOnly
                        className="bg-muted text-muted-foreground font-mono text-sm cursor-not-allowed" />
                    </div>
                    <p className="text-xs text-muted-foreground">
                      Usado na URL de agendamento online. Não pode ser alterado.
                    </p>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                      <Label htmlFor="te-email">E-mail comercial</Label>
                      <Input id="te-email" type="email" placeholder="contato@empresa.com.br"
                        value={tenantForm.email}
                        onChange={e => setTenantForm(f => ({ ...f, email: e.target.value }))}
                        disabled={savingTenant} />
                    </div>
                    <div className="space-y-1.5">
                      <Label htmlFor="te-phone">Telefone</Label>
                      <Input id="te-phone" placeholder="(11) 3333-4444"
                        value={tenantForm.phone}
                        onChange={e => setTenantForm(f => ({ ...f, phone: e.target.value }))}
                        disabled={savingTenant} />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                      <Label htmlFor="te-doc">CNPJ / CPF</Label>
                      <Input id="te-doc" placeholder="00.000.000/0001-00"
                        value={tenantForm.document}
                        onChange={e => setTenantForm(f => ({ ...f, document: e.target.value }))}
                        disabled={savingTenant} />
                    </div>
                    <div className="space-y-1.5">
                      <Label>Fuso horário</Label>
                      <Select value={tenantForm.timezone}
                        onValueChange={v => setTenantForm(f => ({ ...f, timezone: v }))}
                        disabled={savingTenant}>
                        <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                        <SelectContent>
                          {TIMEZONES.map(tz => (
                            <SelectItem key={tz.value} value={tz.value}>{tz.label}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                  </div>

                  <div className="flex justify-end">
                    <Button type="submit" disabled={savingTenant}>
                      {savingTenant ? "Salvando..." : "Salvar empresa"}
                    </Button>
                  </div>
                </form>
              </CardContent>
            </Card>

            {/* Booking link */}
            <Card>
              <CardHeader>
                <CardTitle className="text-base text-sm font-medium">Link de agendamento online</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="flex items-center gap-2">
                  <Input
                    readOnly
                    value={`${typeof window !== "undefined" ? window.location.origin : ""}/book/${settings?.tenant?.slug ?? ""}`}
                    className="font-mono text-sm bg-muted text-muted-foreground cursor-text"
                  />
                  <Button variant="outline" size="sm" type="button"
                    onClick={() => {
                      const url = `${window.location.origin}/book/${settings?.tenant?.slug ?? ""}`;
                      navigator.clipboard.writeText(url);
                      toast.success("Link copiado!");
                    }}>
                    Copiar
                  </Button>
                </div>
                <p className="text-xs text-muted-foreground mt-2">
                  Compartilhe este link com seus clientes para que eles possam agendar online.
                </p>
              </CardContent>
            </Card>
          </TabsContent>

          {/* ── Plano tab ───────────────────────────────────────────────── */}
          <TabsContent value="plano" className="space-y-4">
            {/* Plan card */}
            <Card>
              <CardHeader>
                <div className="flex items-start justify-between">
                  <div>
                    <CardTitle className="text-base flex items-center gap-2">
                      <CreditCard className="h-4 w-4" />
                      {plan ? plan.name : "Sem plano ativo"}
                    </CardTitle>
                    {plan && (
                      <CardDescription className="mt-1">
                        {brl(plan.price)}{plan.billingCycle === "monthly" ? "/mês" : "/ano"}
                      </CardDescription>
                    )}
                  </div>
                  {sub && (() => {
                    const sc = SUBSCRIPTION_STATUS[sub.status] ?? { label: sub.status, className: "" };
                    return <Badge className={`${sc.className} text-xs`}>{sc.label}</Badge>;
                  })()}
                </div>
              </CardHeader>
              {sub && (
                <CardContent className="space-y-4">
                  <div className="grid grid-cols-2 gap-3 text-sm">
                    <div>
                      <p className="text-muted-foreground text-xs">Período atual</p>
                      <p className="font-medium">
                        {format(new Date(sub.currentPeriodStart), "dd/MM/yyyy")} –{" "}
                        {format(new Date(sub.currentPeriodEnd), "dd/MM/yyyy")}
                      </p>
                    </div>
                    <div>
                      <p className="text-muted-foreground text-xs">Cobrança</p>
                      <p className="font-medium capitalize">
                        {sub.billingCycle === "monthly" ? "Mensal" : sub.billingCycle === "yearly" ? "Anual" : sub.billingCycle}
                      </p>
                    </div>
                  </div>

                  {settings?.tenant?.trialEndsAt && (
                    <div className="flex items-center gap-2 text-sm text-amber-700 bg-amber-50 dark:bg-amber-950/20 rounded-lg px-3 py-2 border border-amber-200 dark:border-amber-800">
                      <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                      Trial encerra em {format(new Date(settings.tenant.trialEndsAt), "d 'de' MMMM 'de' yyyy", { locale: ptBR })}
                    </div>
                  )}
                </CardContent>
              )}
            </Card>

            {/* Features */}
            {plan && (
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">Recursos incluídos</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-2 gap-x-6 gap-y-3">
                    {PLAN_FEATURES.map(f => {
                      const enabled = plan[f.key as keyof typeof plan] as boolean;
                      return (
                        <div key={f.key} className={`flex items-center gap-2 text-sm ${!enabled ? "opacity-40" : ""}`}>
                          {enabled
                            ? <Check className="h-4 w-4 text-green-600 shrink-0" />
                            : <X className="h-4 w-4 text-muted-foreground shrink-0" />}
                          <span className={enabled ? "font-medium" : "text-muted-foreground"}>{f.label}</span>
                        </div>
                      );
                    })}
                  </div>
                </CardContent>
              </Card>
            )}

            {/* Usage */}
            {plan && usage && (
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">Uso do plano</CardTitle>
                  <CardDescription>Acompanhe seu consumo em relação aos limites do plano.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                  {[
                    {
                      label: "Profissionais", icon: <Briefcase className="h-4 w-4" />,
                      value: usage.professionals, max: plan.maxProfessionals,
                    },
                    {
                      label: "Clientes", icon: <Users className="h-4 w-4" />,
                      value: usage.clients, max: plan.maxClients,
                    },
                    {
                      label: "Unidades", icon: <LayoutGrid className="h-4 w-4" />,
                      value: usage.units, max: plan.maxUnits,
                    },
                    {
                      label: "Agendamentos este mês", icon: <Calendar className="h-4 w-4" />,
                      value: usage.appointmentsMonth, max: plan.maxAppointmentsMonth,
                    },
                  ].map(item => {
                    const pct = item.max > 0 ? Math.min((item.value / item.max) * 100, 100) : 0;
                    const over = item.value > item.max;
                    return (
                      <div key={item.label} className="space-y-1.5">
                        <div className="flex items-center justify-between text-sm">
                          <div className="flex items-center gap-2 text-muted-foreground">
                            {item.icon}
                            <span>{item.label}</span>
                          </div>
                          <div className="flex items-center gap-1.5">
                            <span className={`font-semibold ${over ? "text-destructive" : ""}`}>{item.value}</span>
                            <span className="text-muted-foreground">/ {item.max}</span>
                            <span className={`text-xs ${over ? "text-destructive" : pct > 80 ? "text-amber-600" : "text-muted-foreground"}`}>
                              ({pct.toFixed(0)}%)
                            </span>
                          </div>
                        </div>
                        <ProgressBar value={item.value} max={item.max} />
                      </div>
                    );
                  })}
                </CardContent>
              </Card>
            )}
          </TabsContent>
        </Tabs>
      )}
    </div>
  );
}
