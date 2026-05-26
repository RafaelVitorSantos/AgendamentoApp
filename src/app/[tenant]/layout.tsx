import { notFound } from "next/navigation";
import { prisma } from "@/lib/db";

interface Props {
  children: React.ReactNode;
  params: Promise<{ tenant: string }>;
}

export default async function TenantLayout({ children, params }: Props) {
  const { tenant: slug } = await params;

  // Validate slug format before hitting the database
  if (!/^[a-z0-9][a-z0-9-]*$/.test(slug)) {
    notFound();
  }

  const tenant = await prisma.tenant.findUnique({
    where: { slug },
    select: { id: true },
  });

  if (!tenant) {
    notFound();
  }

  return <>{children}</>;
}
