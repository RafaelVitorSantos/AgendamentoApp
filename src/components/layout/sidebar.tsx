"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";
import {
  LayoutDashboard,
  Calendar,
  Users,
  Briefcase,
  Scissors,
  Building2,
  DollarSign,
  BarChart3,
  ListOrdered,
  Ban,
  Settings,
  CalendarDays,
} from "lucide-react";

const navItems = [
  { href: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
  { href: "/dashboard/agendamentos", label: "Agendamentos", icon: Calendar },
  { href: "/dashboard/clientes", label: "Clientes", icon: Users },
  { href: "/dashboard/profissionais", label: "Profissionais", icon: Briefcase },
  { href: "/dashboard/servicos", label: "Serviços", icon: Scissors },
  { href: "/dashboard/unidades", label: "Unidades", icon: Building2 },
  { href: "/dashboard/fila", label: "Fila", icon: ListOrdered },
  { href: "/dashboard/financeiro", label: "Financeiro", icon: DollarSign },
  { href: "/dashboard/relatorios", label: "Relatórios", icon: BarChart3 },
  { href: "/dashboard/bloqueios", label: "Bloqueios", icon: Ban },
  { href: "/dashboard/calendario", label: "Calendário", icon: CalendarDays },
  { href: "/dashboard/configuracoes", label: "Configurações", icon: Settings },
];

export function Sidebar() {
  const pathname = usePathname();

  return (
    <aside className="w-64 border-r bg-card flex flex-col shrink-0 overflow-y-auto">
      <div className="h-16 flex items-center px-6 border-b">
        <div className="flex items-center gap-2">
          <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center text-primary-foreground font-bold text-sm">
            A
          </div>
          <span className="font-bold text-lg">AgendaPRO</span>
        </div>
      </div>

      <nav className="flex-1 p-3 space-y-1">
        {navItems.map((item) => {
          const Icon = item.icon;
          const active =
            item.href === "/dashboard"
              ? pathname === "/dashboard"
              : pathname.startsWith(item.href);

          return (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                "flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors",
                active
                  ? "bg-primary text-primary-foreground font-medium"
                  : "text-muted-foreground hover:bg-accent hover:text-accent-foreground"
              )}
            >
              <Icon className="h-4 w-4 shrink-0" />
              {item.label}
            </Link>
          );
        })}
      </nav>
    </aside>
  );
}
