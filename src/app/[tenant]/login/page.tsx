"use client";

import { useState, useEffect } from "react";
import { useParams, useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

const schema = z.object({
  email: z.string().email("Email inválido"),
  password: z.string().min(1, "Senha obrigatória"),
});

type FormData = z.infer<typeof schema>;

interface TenantInfo {
  name: string;
  slug: string;
}

export default function TenantLoginPage() {
  const router = useRouter();
  const params = useParams<{ tenant: string }>();
  const tenantSlug = params.tenant;

  const [loading, setLoading] = useState(false);
  const [tenantInfo, setTenantInfo] = useState<TenantInfo | null>(null);
  const [tenantError, setTenantError] = useState("");
  const [tenantLoading, setTenantLoading] = useState(true);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormData>({ resolver: zodResolver(schema) });

  useEffect(() => {
    if (!tenantSlug) return;
    setTenantLoading(true);
    fetch(`/api/auth/tenant/${tenantSlug}`)
      .then(async (res) => {
        const data = await res.json();
        if (!res.ok) {
          setTenantError(data.error ?? "Empresa não encontrada");
        } else {
          setTenantInfo(data);
        }
      })
      .catch(() => setTenantError("Erro ao carregar dados da empresa"))
      .finally(() => setTenantLoading(false));
  }, [tenantSlug]);

  async function onSubmit(data: FormData) {
    setLoading(true);
    try {
      const res = await fetch("/api/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ...data, tenantSlug }),
      });

      const json = await res.json();

      if (!res.ok) {
        toast.error(json.error ?? "Credenciais inválidas");
        return;
      }

      router.push(`/${tenantSlug}/dashboard`);
    } catch {
      toast.error("Erro de conexão. Tente novamente.");
    } finally {
      setLoading(false);
    }
  }

  if (tenantLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="h-8 w-8 border-2 border-primary border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  if (tenantError) {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <Card className="w-full max-w-md text-center shadow-xl">
          <CardHeader className="space-y-1">
            <div className="flex justify-center mb-2">
              <div className="w-12 h-12 bg-destructive/10 rounded-xl flex items-center justify-center text-destructive font-bold text-xl">
                !
              </div>
            </div>
            <CardTitle className="text-xl">Empresa não encontrada</CardTitle>
            <CardDescription>{tenantError}</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground mb-4">
              O endereço <span className="font-mono font-medium">/{tenantSlug}</span> não
              corresponde a nenhuma empresa cadastrada.
            </p>
            <a href="/register" className="text-primary hover:underline text-sm font-medium">
              Criar uma conta grátis
            </a>
          </CardContent>
        </Card>
      </div>
    );
  }

  const initial = tenantInfo?.name?.charAt(0)?.toUpperCase() ?? "A";

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary/5 to-primary/10 p-4">
      <Card className="w-full max-w-md shadow-xl">
        <CardHeader className="text-center space-y-1">
          <div className="flex justify-center mb-2">
            <div className="w-12 h-12 bg-primary rounded-xl flex items-center justify-center text-primary-foreground font-bold text-xl">
              {initial}
            </div>
          </div>
          <CardTitle className="text-2xl font-bold">{tenantInfo?.name}</CardTitle>
          <CardDescription>
            Entre com seu email e senha para acessar o painel
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                type="email"
                placeholder="seu@email.com"
                autoComplete="email"
                {...register("email")}
              />
              {errors.email && (
                <p className="text-sm text-destructive">{errors.email.message}</p>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="password">Senha</Label>
              <Input
                id="password"
                type="password"
                placeholder="••••••••"
                autoComplete="current-password"
                {...register("password")}
              />
              {errors.password && (
                <p className="text-sm text-destructive">{errors.password.message}</p>
              )}
            </div>

            <Button type="submit" className="w-full" disabled={loading}>
              {loading ? "Entrando..." : "Entrar"}
            </Button>
          </form>

          <div className="mt-6 text-center text-sm text-muted-foreground">
            <p className="text-xs text-muted-foreground/60">
              Acessando:{" "}
              <span className="font-mono">
                /{tenantSlug}/login
              </span>
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
