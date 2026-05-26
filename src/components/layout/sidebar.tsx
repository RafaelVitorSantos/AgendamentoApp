"use client";

import Link from "next/link";
import { useParams, usePathname } from "next/navigation";
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
  Tag,
} from "lucide-react";

const NAV_SLUGS = [
  { slug: "dashboard",      label: "Dashboard",    icon: LayoutDashboard, exact: true },
  { slug: "agendamentos",   label: "Agendamentos", icon: Calendar },
  { slug: "clientes",       label: "Clientes",     icon: Users },
  { slug: "profissionais",  label: "Profissionais",icon: Briefcase },
  { slug: "servicos",       label: "Serviços",     icon: Scissors },
  { slug: "categorias",     label: "Categorias",   icon: Tag },
  { slug: "unidades",       label: "Unidades",     icon: Building2 },
  { slug: "fila",           label: "Fila",         icon: ListOrdered },
  { slug: "financeiro",     label: "Financeiro",   icon: DollarSign },
  { slug: "relatorios",     label: "Relatórios",   icon: BarChart3 },
  { slug: "bloqueios",      label: "Bloqueios",    icon: Ban },
  { slug: "calendario",     label: "Calendário",   icon: CalendarDays },
  { slug: "configuracoes",  label: "Configurações",icon: Settings },
];

export function Sidebar() {
  const pathname = usePathname();
  const params = useParams<{ tenant?: string }>();
  const tenant = params?.tenant;

  // Build hrefs with or without tenant prefix
  const navItems = NAV_SLUGS.map(({ slug, label, icon, exact }) => ({
    href: tenant ? `/${tenant}/dashboard${slug === "dashboard" ? "" : `/${slug}`}` : `/dashboard${slug === "dashboard" ? "" : `/${slug}`}`,
    label,
    icon,
    exact: exact ?? false,
  }));

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
          const active = item.exact
            ? pathname === item.href
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
