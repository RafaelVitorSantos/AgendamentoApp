import { SignJWT, jwtVerify } from "jose";
import { cookies } from "next/headers";
import { prisma } from "./db";

const secret = new TextEncoder().encode(
  process.env.JWT_SECRET ?? "fallback-secret-change-in-production"
);

export interface JWTPayload {
  sub: string;
  userId: number;
  tenantId: number;
  roleId: number;
  roleName: string;
  permissions: string[];
  tenantSlug: string;
  tenantStatus: string;
}

export async function signToken(payload: JWTPayload): Promise<string> {
  const ttl = parseInt(process.env.JWT_TTL ?? "28800");
  return new SignJWT({ ...payload })
    .setProtectedHeader({ alg: "HS256" })
    .setIssuedAt()
    .setExpirationTime(`${ttl}s`)
    .sign(secret);
}

export async function verifyToken(token: string): Promise<JWTPayload | null> {
  try {
    const { payload } = await jwtVerify(token, secret);
    return payload as unknown as JWTPayload;
  } catch {
    return null;
  }
}

export async function getSession(): Promise<JWTPayload | null> {
  const cookieStore = await cookies();
  const token = cookieStore.get("agendapro_token")?.value;
  if (!token) return null;
  return verifyToken(token);
}

export async function getUserPermissions(
  userId: number,
  roleId: number
): Promise<string[]> {
  const [rolePerms, userPerms] = await Promise.all([
    prisma.rolePermission.findMany({
      where: { roleId },
      include: { permission: true },
    }),
    prisma.userPermission.findMany({
      where: { userId },
      include: { permission: true },
    }),
  ]);

  const perms = new Map<string, boolean>();
  for (const rp of rolePerms) perms.set(rp.permission.name, true);
  for (const up of userPerms) perms.set(up.permission.name, up.granted);

  return Array.from(perms.entries())
    .filter(([, granted]) => granted)
    .map(([name]) => name);
}

export function hasPermission(
  permissions: string[],
  required: string
): boolean {
  return permissions.includes(required);
}
