import { PrismaClient } from "@prisma/client";
import { PrismaMariaDb } from "@prisma/adapter-mariadb";

function createAdapter() {
  const url = new URL(process.env.DATABASE_URL!);
  return new PrismaMariaDb({
    host: url.hostname,
    port: parseInt(url.port || "3306"),
    user: url.username,
    password: url.password || undefined,
    database: url.pathname.slice(1),
  });
}

const globalForPrisma = globalThis as unknown as { prisma: PrismaClient };

export const prisma =
  globalForPrisma.prisma ??
  new PrismaClient({
    adapter: createAdapter(),
    log: process.env.NODE_ENV === "development" ? ["error", "warn"] : ["error"],
  });

if (process.env.NODE_ENV !== "production") globalForPrisma.prisma = prisma;
