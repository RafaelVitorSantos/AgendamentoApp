export type AppointmentStatus =
  | "scheduled"
  | "confirmed"
  | "in_progress"
  | "completed"
  | "cancelled_by_client"
  | "cancelled_by_business"
  | "rescheduled"
  | "no_show";

export type QueueStatus =
  | "waiting"
  | "called"
  | "in_progress"
  | "completed"
  | "cancelled"
  | "no_show";

export type TenantStatus = "trial" | "active" | "suspended" | "cancelled";

export type TransactionType = "income" | "expense";
export type TransactionStatus = "pending" | "paid" | "cancelled" | "refunded";

export type CommissionType = "percentage" | "fixed";
export type CommissionStatus = "pending" | "paid";

export type MessageChannel = "email" | "whatsapp" | "sms";
export type MessageStatus = "queued" | "sent" | "delivered" | "failed" | "read";

export type SyncDirection = "push_only" | "pull_only" | "bidirectional";

export interface ApiResponse<T = unknown> {
  data?: T;
  error?: string;
  message?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  perPage: number;
  totalPages: number;
}

export interface AvailableSlot {
  startTime: string;
  endTime: string;
}

export interface DashboardMetrics {
  appointmentsToday: number;
  appointmentsWeek: number;
  revenueMonth: number;
  newClientsMonth: number;
  occupancyRate: number;
  pendingQueue: number;
}
