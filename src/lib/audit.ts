import { prisma } from "./db";

export async function audit(
  tenantId: number,
  userId: number | null,
  action: string,
  entityType: string,
  entityId: number,
  oldData?: object,
  newData?: object,
  ipAddress?: string
) {
  await prisma.auditLog.create({
    data: {
      tenantId,
      userId,
      action,
      entityType,
      entityId,
      oldData: oldData ?? undefined,
      newData: newData ?? undefined,
      ipAddress,
    },
  });
}
