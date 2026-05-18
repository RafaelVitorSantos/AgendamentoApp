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

  const [workingHours, breaks, appointments, blocks, holidays] =
    await Promise.all([
      prisma.professionalWorkingHours.findFirst({
        where: { professionalId, unitId, dayOfWeek, isWorking: true },
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
          date,
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
          startDate: { lte: date },
          endDate: { gte: date },
        },
      }),
      prisma.holiday.findMany({
        where: {
          tenantId,
          OR: [
            { date, isRecurring: false },
            {
              isRecurring: true,
              date: {
                gte: new Date(date.getFullYear(), date.getMonth(), date.getDate()),
              },
            },
          ],
        },
      }),
    ]);

  if (!workingHours || holidays.length > 0) return [];

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
