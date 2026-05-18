import { NextRequest, NextResponse } from "next/server";
import { jwtVerify } from "jose";

const secret = new TextEncoder().encode(
  process.env.JWT_SECRET ?? "fallback-secret-change-in-production"
);

const PUBLIC_PATHS = [
  "/login",
  "/register",
  "/book",
  "/api/auth",
  "/api/book",
  "/api/calendar",
  "/api/webhook",
  "/api/health",
];

function isPublic(path: string): boolean {
  return PUBLIC_PATHS.some((p) => path.startsWith(p));
}

export async function middleware(req: NextRequest) {
  const { pathname } = req.nextUrl;

  if (isPublic(pathname)) return NextResponse.next();

  const token = req.cookies.get("agendapro_token")?.value;

  if (!token) {
    return NextResponse.redirect(new URL("/login", req.url));
  }

  try {
    const { payload } = await jwtVerify(token, secret);
    const tenantStatus = payload.tenantStatus as string;

    if (tenantStatus === "suspended" || tenantStatus === "cancelled") {
      return NextResponse.redirect(new URL("/conta-suspensa", req.url));
    }

    const res = NextResponse.next();
    res.headers.set("x-user-id", String(payload.userId));
    res.headers.set("x-tenant-id", String(payload.tenantId));
    res.headers.set("x-tenant-slug", String(payload.tenantSlug));
    res.headers.set("x-role", String(payload.roleName));
    return res;
  } catch {
    const res = NextResponse.redirect(new URL("/login", req.url));
    res.cookies.delete("agendapro_token");
    return res;
  }
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico|.*\\.png$).*)"],
};
