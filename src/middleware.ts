import { NextRequest, NextResponse } from "next/server";
import { jwtVerify } from "jose";

const secret = new TextEncoder().encode(
  process.env.JWT_SECRET ?? "fallback-secret-change-in-production"
);

const PUBLIC_PATHS = [
  "/login",
  "/register",
  "/book",
  "/auraflowstudio",
  "/api/auth",
  "/api/book",
  "/api/calendar",
  "/api/webhook",
  "/api/health",
];

// Matches /{tenant}/login — tenant slugs are lowercase alphanumeric + hyphens
const TENANT_LOGIN_RE = /^\/[a-z0-9][a-z0-9-]*\/login$/;

function isPublic(path: string): boolean {
  if (PUBLIC_PATHS.some((p) => path.startsWith(p))) return true;
  if (TENANT_LOGIN_RE.test(path)) return true;
  return false;
}

// Extract tenant from URL for redirect; falls back to /login for legacy routes
function loginUrl(pathname: string, base: string): URL {
  const match = pathname.match(/^\/([a-z0-9][a-z0-9-]*)\//);
  if (match) return new URL(`/${match[1]}/login`, base);
  return new URL("/login", base);
}

export async function middleware(req: NextRequest) {
  const { pathname } = req.nextUrl;

  if (isPublic(pathname)) return NextResponse.next();

  const token = req.cookies.get("agendapro_token")?.value;

  if (!token) {
    return NextResponse.redirect(loginUrl(pathname, req.url));
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
    const res = NextResponse.redirect(loginUrl(pathname, req.url));
    res.cookies.delete("agendapro_token");
    return res;
  }
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico|.*\\.png$).*)"],
};
