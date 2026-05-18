"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

const schema = z.object({
  companyName: z.string().min(2, "Nome da empresa obrigatório"),
  companySlug: z
    .string()
    .min(2, "Slug obrigatório")
    .regex(/^[a-z0-9-]+$/, "Use apenas letras minúsculas, números e hífens"),
  companyEmail: z.string().email("Email da empresa inválido"),
  userName: z.string().min(2, "Seu nome obrigatório"),
  userEmail: z.string().email("Seu email inválido"),
  password: z.string().min(8, "Mínimo 8 caracteres"),
});

type FormData = z.infer<typeof schema>;

export default function RegisterPage() {
  const router = useRouter();
  const [loading, setLoading] = useState(false);

  const { register, handleSubmit, formState: { errors } } = useForm<FormData>({
    resolver: zodResolver(schema),
  });

  async function onSubmit(data: FormData) {
    setLoading(true);
    try {
      const res = await fetch("/api/auth/register", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });

      const json = await res.json();

      if (!res.ok) {
        toast.error(json.error ?? "Erro ao criar conta");
        return;
      }

      toast.success("Conta criada! Período de teste de 14 dias iniciado.");
      router.push("/dashboard");
    } catch {
      toast.error("Erro de conexão");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary/5 to-primary/10 p-4">
      <Card className="w-full max-w-lg shadow-xl">
        <CardHeader className="text-center space-y-1">
          <div className="flex justify-center mb-2">
            <div className="w-12 h-12 bg-primary rounded-xl flex items-center justify-center text-primary-foreground font-bold text-xl">
              A
            </div>
          </div>
          <CardTitle className="text-2xl font-bold">Criar conta grátis</CardTitle>
          <CardDescription>14 dias de teste sem cartão de crédito</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2 col-span-2">
                <Label>Nome da empresa</Label>
                <Input placeholder="Meu Salão" {...register("companyName")} />
                {errors.companyName && <p className="text-sm text-destructive">{errors.companyName.message}</p>}
              </div>

              <div className="space-y-2">
                <Label>Slug (URL)</Label>
                <Input placeholder="meu-salao" {...register("companySlug")} />
                {errors.companySlug && <p className="text-sm text-destructive">{errors.companySlug.message}</p>}
              </div>

              <div className="space-y-2">
                <Label>Email da empresa</Label>
                <Input type="email" placeholder="contato@empresa.com" {...register("companyEmail")} />
                {errors.companyEmail && <p className="text-sm text-destructive">{errors.companyEmail.message}</p>}
              </div>
            </div>

            <hr className="my-2" />

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Seu nome</Label>
                <Input placeholder="João Silva" {...register("userName")} />
                {errors.userName && <p className="text-sm text-destructive">{errors.userName.message}</p>}
              </div>

              <div className="space-y-2">
                <Label>Seu email</Label>
                <Input type="email" placeholder="joao@email.com" {...register("userEmail")} />
                {errors.userEmail && <p className="text-sm text-destructive">{errors.userEmail.message}</p>}
              </div>

              <div className="space-y-2 col-span-2">
                <Label>Senha</Label>
                <Input type="password" placeholder="Mínimo 8 caracteres" {...register("password")} />
                {errors.password && <p className="text-sm text-destructive">{errors.password.message}</p>}
              </div>
            </div>

            <Button type="submit" className="w-full" disabled={loading}>
              {loading ? "Criando conta..." : "Criar conta grátis"}
            </Button>
          </form>

          <div className="mt-4 text-center text-sm text-muted-foreground">
            Já tem conta?{" "}
            <a href="/login" className="text-primary hover:underline font-medium">
              Fazer login
            </a>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
