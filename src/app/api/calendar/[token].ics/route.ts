import { NextRequest, NextResponse } from "next/server";
import { generateICalFeed } from "@/lib/ical";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ "token.ics": string }> }
) {
  const rawParam = (await params)["token.ics"];
  const token = rawParam.replace(/\.ics$/, "");

  const ical = await generateICalFeed(token);
  if (!ical) {
    return new NextResponse("Token inválido ou revogado", { status: 404 });
  }

  return new NextResponse(ical, {
    headers: {
      "Content-Type": "text/calendar; charset=utf-8",
      "Content-Disposition": `attachment; filename="agenda.ics"`,
      "Cache-Control": "no-cache, no-store",
    },
  });
}
