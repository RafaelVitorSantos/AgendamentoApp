import { redirect } from "next/navigation";
import { getSession } from "@/lib/auth";
import { Sidebar } from "@/components/layout/sidebar";
import { Header } from "@/components/layout/header";

interface Props {
  children: React.ReactNode;
  params: Promise<{ tenant: string }>;
}

export default async function TenantDashboardLayout({ children, params }: Props) {
  const { tenant } = await params;
  const session = await getSession();

  if (!session) {
    redirect(`/${tenant}/login`);
  }

  // Security: JWT tenant must match URL tenant — prevents cross-tenant access
  if (session.tenantSlug !== tenant) {
    redirect(`/${tenant}/login`);
  }

  return (
    <div className="flex h-screen overflow-hidden bg-background">
      <Sidebar />
      <div className="flex flex-col flex-1 overflow-hidden">
        <Header />
        <main className="flex-1 overflow-y-auto p-6">{children}</main>
      </div>
    </div>
  );
}
