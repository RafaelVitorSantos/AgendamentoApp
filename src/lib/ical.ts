import { prisma } from "./db";

function foldLine(line: string): string {
  const bytes = new TextEncoder().encode(line);
  if (bytes.length <= 75) return line;
  const parts: string[] = [];
  let start = 0;
  while (start < bytes.length) {
    const end = start === 0 ? 75 : start + 74;
    parts.push(
      (start > 0 ? " " : "") +
        new TextDecoder().decode(bytes.slice(start, end))
    );
    start = end;
  }
  return parts.join("\r\n");
}

function toICalDate(date: Date): string {
  return date
    .toISOString()
    .replace(/[-:]/g, "")
    .replace(/\.\d{3}/, "")
    .replace("T", "T")
    .slice(0, 15) + "Z";
}

function statusToICal(status: string): string {
  if (status.includes("cancelled")) return "CANCELLED";
  if (status === "scheduled") return "TENTATIVE";
  return "CONFIRMED";
}

function escapeICal(str: string): string {
  return str
    .replace(/\\/g, "\\\\")
    .replace(/;/g, "\\;")
    .replace(/,/g, "\\,")
    .replace(/\n/g, "\\n");
}

export async function generateICalFeed(
  token: string
): Promise<string | null> {
  const calToken = await prisma.calendarToken.findUnique({
    where: { token },
    include: { tenant: true, professional: true },
  });

  if (!calToken || calToken.revokedAt) return null;

  await prisma.calendarToken.update({
    where: { id: calToken.id },
    data: { lastUsedAt: new Date() },
  });

  const where =
    calToken.scope === "professional" && calToken.professionalId
      ? {
          tenantId: calToken.tenantId,
          professionalId: calToken.professionalId,
          deletedAt: null,
        }
      : { tenantId: calToken.tenantId, deletedAt: null };

  const appointments = await prisma.appointment.findMany({
    where,
    include: { client: true, service: true, professional: true },
    orderBy: { date: "asc" },
    take: 500,
  });

  const lines: string[] = [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    `PRODID:-//AgendaPRO//AgendaPRO//PT`,
    `X-WR-CALNAME:${escapeICal(calToken.tenant.name)}`,
    "X-WR-TIMEZONE:America/Sao_Paulo",
    "CALSCALE:GREGORIAN",
    "METHOD:PUBLISH",
  ];

  for (const apt of appointments) {
    const dateStr = apt.date.toISOString().split("T")[0].replace(/-/g, "");
    const startDt = new Date(`${dateStr.slice(0, 4)}-${dateStr.slice(4, 6)}-${dateStr.slice(6, 8)}T${apt.startTime}:00`);
    const endDt = new Date(`${dateStr.slice(0, 4)}-${dateStr.slice(4, 6)}-${dateStr.slice(6, 8)}T${apt.endTime}:00`);

    lines.push(
      "BEGIN:VEVENT",
      foldLine(`UID:${apt.id}-${calToken.tenant.uuid}@agendapro`),
      foldLine(`DTSTAMP:${toICalDate(new Date())}`),
      foldLine(`DTSTART:${toICalDate(startDt)}`),
      foldLine(`DTEND:${toICalDate(endDt)}`),
      foldLine(`SUMMARY:${escapeICal(`${apt.service.name} - ${apt.client.name}`)}`),
      foldLine(`DESCRIPTION:${escapeICal(`Profissional: ${apt.professional.name}`)}`),
      foldLine(`STATUS:${statusToICal(apt.status)}`),
      "BEGIN:VALARM",
      "TRIGGER:-PT60M",
      "ACTION:DISPLAY",
      foldLine(`DESCRIPTION:${escapeICal(`Lembrete: ${apt.service.name}`)}`),
      "END:VALARM",
      "END:VEVENT"
    );
  }

  lines.push("END:VCALENDAR");
  return lines.join("\r\n");
}
