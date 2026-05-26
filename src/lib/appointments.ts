import { prisma } from "./db";
import type { AvailableSlot } from "./types";

function timeToMinutes(time: string): number {
  const [h, m] = time.split(":").map(Number);
  return h * 60 + m;
}

function minutesToTime(minutes: number): string {
  const h = Math.floor(minutes / 60).toString().padStart(2, "0");
  const m = (minutes % 60).toString().padStart(2, "0");
  return `${h}:${m}`;
}

export async function getAvailableSlots(
  tenantId: number,
  professionalId: number,
  unitId: number,
  date: Date,
  durationMinutes: number,
  intervalMinutes = 30
): Promise<AvailableSlot[]> {
  const dayOfWeek = date.getDay();

  // Build an exact date range for the queried day (midnight–midnight) so
  // Prisma date comparisons work regardless of the server's timezone.
  const dayStart = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  const dayEnd   = new Date(date.getFullYear(), date.getMonth(), date.getDate() + 1);

  const [profWorkingHours, unitWorkingHours, breaks, appointments, blocks, holidays] =
    await Promise.all([
      // Professional-specific schedule for this unit+day
      prisma.professionalWorkingHours.findFirst({
        where: { professionalId, unitId, dayOfWeek, isWorking: true },
      }),
      // Unit-level fallback schedule
      prisma.unitWorkingHours.findFirst({
        where: { unitId, dayOfWeek, isOpen: true },
      }),
      prisma.professionalBreak.findMany({
        where: {
          professionalId,
          unitId: unitId ?? undefined,
          dayOfWeek: dayOfWeek ?? undefined,
        },
      }),
      prisma.appointment.findMany({
        where: {
          tenantId,
          professionalId,
          date: { gte: dayStart, lt: dayEnd },
          deletedAt: null,
          status: {
            notIn: [
              "cancelled_by_client",
              "cancelled_by_business",
              "no_show",
              "rescheduled",
            ],
          },
        },
        select: { startTime: true, endTime: true },
      }),
      prisma.scheduleBlock.findMany({
        where: {
          tenantId,
          OR: [{ professionalId }, { unitId }],
          startDate: { lte: dayEnd },
          endDate: { gte: dayStart },
        },
      }),
      prisma.holiday.findMany({
        where: {
          tenantId,
          OR: [
            {
              isRecurring: false,
              date: { gte: dayStart, lt: dayEnd },
            },
            {
              isRecurring: true,
            },
          ],
        },
      }),
    ]);

  // Default fallback: Mon-Fri 08:00-18:00, Sat 09:00-13:00, Sun closed
  const DEFAULT_HOURS: Record<number, { startTime: string; endTime: string } | null> = {
    0: null, // Sun — closed
    1: { startTime: "08:00", endTime: "18:00" },
    2: { startTime: "08:00", endTime: "18:00" },
    3: { startTime: "08:00", endTime: "18:00" },
    4: { startTime: "08:00", endTime: "18:00" },
    5: { startTime: "08:00", endTime: "18:00" },
    6: { startTime: "09:00", endTime: "13:00" }, // Sat
  };

  // Use professional's own hours if configured, otherwise fall back to unit hours,
  // then to the built-in default schedule so slots appear even without explicit config.
  const workingHours = profWorkingHours
    ? { startTime: profWorkingHours.startTime, endTime: profWorkingHours.endTime }
    : unitWorkingHours
    ? { startTime: unitWorkingHours.openTime, endTime: unitWorkingHours.closeTime }
    : DEFAULT_HOURS[dayOfWeek] ?? null;

  // Filter recurring holidays to only those matching the same month/day
  const activeHolidays = holidays.filter((h) => {
    if (!h.isRecurring) return true;
    return h.date.getMonth() === date.getMonth() && h.date.getDate() === date.getDate();
  });

  if (!workingHours || activeHolidays.length > 0) return [];

  const hasAllDayBlock = blocks.some((b) => b.isAllDay);
  if (hasAllDayBlock) return [];

  const startMin = timeToMinutes(workingHours.startTime);
  const endMin = timeToMinutes(workingHours.endTime);

  const busyRanges: Array<[number, number]> = [];

  for (const apt of appointments) {
    busyRanges.push([timeToMinutes(apt.startTime), timeToMinutes(apt.endTime)]);
  }

  for (const brk of breaks) {
    busyRanges.push([timeToMinutes(brk.startTime), timeToMinutes(brk.endTime)]);
  }

  for (const block of blocks) {
    if (!block.isAllDay && block.startTime && block.endTime) {
      busyRanges.push([
        timeToMinutes(block.startTime),
        timeToMinutes(block.endTime),
      ]);
    }
  }

  const slots: AvailableSlot[] = [];

  for (
    let start = startMin;
    start + durationMinutes <= endMin;
    start += intervalMinutes
  ) {
    const end = start + durationMinutes;
    const conflict = busyRanges.some(([bs, be]) => start < be && end > bs);
    if (!conflict) {
      slots.push({
        startTime: minutesToTime(start),
        endTime: minutesToTime(end),
      });
    }
  }

  return slots;
}

export async function hasConflict(
  professionalId: number,
  date: Date,
  startTime: string,
  endTime: string,
  excludeId?: number
): Promise<boolean> {
  const conflict = await prisma.appointment.findFirst({
    where: {
      professionalId,
      date,
      deletedAt: null,
      id: excludeId ? { not: excludeId } : undefined,
      status: {
        notIn: [
          "cancelled_by_client",
          "cancelled_by_business",
          "no_show",
          "rescheduled",
        ],
      },
      AND: [
        { startTime: { lt: endTime } },
        { endTime: { gt: startTime } },
      ],
    },
  });
  return !!conflict;
}
