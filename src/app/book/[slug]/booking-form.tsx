"use client";

import { useState, useMemo } from "react";
import { useQuery, useMutation } from "@tanstack/react-query";
import { toast } from "sonner";
import { Calendar } from "@/components/ui/calendar";
import { ptBR } from "date-fns/locale";
import { format, startOfDay } from "date-fns";
import {
  Check,
  ChevronLeft,
  Clock,
  CheckCircle2,
  Loader2,
  CalendarDays,
  Zap,
} from "lucide-react";

// ─── Types ────────────────────────────────────────────────────────────────────

interface Service {
  id: number;
  name: string;
  duration: number;
  price: number | string;
  description?: string | null;
  color?: string | null;
}
interface Unit { id: number; name: string }
interface Professional { id: number; name: string; avatar: string | null; color: string | null }
interface Slot { startTime: string; endTime: string }
type Step = 1 | 2 | 3 | 4;

interface Props {
  tenantSlug: string;
  tenantId: number;
  services: Service[];
  units: Unit[];
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function fmtPrice(p: number | string) {
  return Number(p).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}
function fmtDuration(min: number) {
  if (min < 60) return `${min} min`;
  const h = Math.floor(min / 60), m = min % 60;
  return m ? `${h}h${String(m).padStart(2, "0")}` : `${h}h`;
}
function getInitials(name: string) {
  return name.split(" ").slice(0, 2).map((n) => n[0]).join("").toUpperCase();
}
function groupSlots(slots: Slot[]) {
  const m: Slot[] = [], a: Slot[] = [], e: Slot[] = [];
  for (const s of slots) {
    const h = parseInt(s.startTime.split(":")[0]);
    if (h < 12) m.push(s); else if (h < 17) a.push(s); else e.push(s);
  }
  return { morning: m, afternoon: a, evening: e };
}
function fmtPhone(raw: string) {
  const d = raw.replace(/\D/g, "").slice(0, 11);
  if (d.length <= 2) return d;
  if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
  if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
  return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
}
function serviceIcon(name: string) {
  const n = name.toLowerCase();
  if (n.includes("barba") || n.includes("bigode")) return "🪒";
  if (n.includes("corte") || n.includes("cabelo")) return "✂️";
  if (n.includes("sobrancelha") || n.includes("design")) return "✨";
  if (n.includes("hidrat") || n.includes("tratamento")) return "💆";
  if (n.includes("pintura") || n.includes("mechas")) return "🎨";
  if (n.includes("manicure") || n.includes("pedicure") || n.includes("unha")) return "💅";
  if (n.includes("massagem")) return "💆";
  if (n.includes("limpeza") || n.includes("facial")) return "🌿";
  return "✂️";
}

// ─── Shared inline-style helpers (using CSS vars) ─────────────────────────────

const card = (selected: boolean): React.CSSProperties => ({
  background: selected ? "var(--bp-sel-card-bg)" : "var(--bp-card)",
  border: `${selected ? 2 : 1}px solid ${selected ? "var(--bp-sel-card-border)" : "var(--bp-card-border)"}`,
  borderRadius: "14px",
  transition: "border-color 0.15s, background 0.15s, transform 0.12s",
  transform: selected ? "scale(1.01)" : "scale(1)",
});

const btnPrimary: React.CSSProperties = {
  width: "100%",
  padding: "16px",
  borderRadius: "14px",
  fontSize: "15px",
  fontWeight: 700,
  cursor: "pointer",
  border: "none",
  background: "linear-gradient(135deg, var(--bp-accent), color-mix(in srgb, var(--bp-accent) 80%, #000 20%))",
  color: "#fff",
  transition: "opacity 0.15s, transform 0.12s",
  letterSpacing: "0.01em",
};

const btnDisabled: React.CSSProperties = {
  ...btnPrimary,
  background: "var(--bp-step-inactive)",
  color: "var(--bp-step-inactive-t)",
  cursor: "not-allowed",
};

const btnGhost: React.CSSProperties = {
  display: "flex",
  alignItems: "center",
  gap: "6px",
  padding: "8px 14px",
  borderRadius: "10px",
  background: "var(--bp-back-bg)",
  color: "var(--bp-back-text)",
  border: "1px solid var(--bp-back-border)",
  cursor: "pointer",
  fontSize: "13px",
  fontWeight: 600,
  transition: "opacity 0.15s",
  marginBottom: "20px",
};

const sectionTitle: React.CSSProperties = {
  fontSize: "18px",
  fontWeight: 700,
  color: "var(--bp-text-1)",
  letterSpacing: "-0.015em",
  marginBottom: "4px",
};
const sectionSub: React.CSSProperties = {
  fontSize: "13px",
  color: "var(--bp-text-2)",
  marginBottom: "20px",
  lineHeight: 1.5,
};
const labelStyle: React.CSSProperties = {
  display: "block",
  fontSize: "11px",
  fontWeight: 700,
  letterSpacing: "0.08em",
  textTransform: "uppercase",
  color: "var(--bp-text-2)",
  marginBottom: "8px",
};
const inputStyle: React.CSSProperties = {
  background: "var(--bp-input-bg)",
  border: "1.5px solid var(--bp-input-border)",
  borderRadius: "12px",
  color: "var(--bp-input-text)",
  padding: "14px 16px",
  fontSize: "16px",
  width: "100%",
  outline: "none",
  transition: "border-color 0.15s",
  boxSizing: "border-box",
};

// ─── Step Indicator ───────────────────────────────────────────────────────────

const STEP_LABELS = ["Serviço", "Profissional", "Data & Hora", "Seus dados"];

function StepIndicator({ current }: { current: Step }) {
  return (
    <div style={{ display: "flex", alignItems: "center", marginBottom: "28px" }}>
      {STEP_LABELS.map((label, i) => {
        const n = (i + 1) as Step;
        const done = current > n;
        const active = current === n;
        return (
          <div key={n} style={{ display: "flex", alignItems: "center", flex: i < 3 ? 1 : "none" }}>
            <div style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: "6px" }}>
              <div
                style={{
                  width: "32px",
                  height: "32px",
                  borderRadius: "50%",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  fontSize: "13px",
                  fontWeight: 700,
                  flexShrink: 0,
                  transition: "all 0.25s",
                  ...(done
                    ? { background: "var(--bp-accent)", color: "#fff" }
                    : active
                    ? { background: "var(--bp-accent)", color: "#fff", boxShadow: "0 0 0 4px var(--bp-accent-subtle)" }
                    : { background: "var(--bp-step-inactive)", color: "var(--bp-step-inactive-t)" }),
                }}
              >
                {done ? <Check size={14} strokeWidth={3} /> : n}
              </div>
              <span
                style={{
                  fontSize: "10px",
                  fontWeight: 600,
                  whiteSpace: "nowrap",
                  color: active ? "var(--bp-accent)" : done ? "var(--bp-text-2)" : "var(--bp-step-inactive-t)",
                }}
              >
                {label}
              </span>
            </div>
            {i < 3 && (
              <div
                style={{
                  flex: 1,
                  height: "2px",
                  margin: "0 4px",
                  marginBottom: "18px",
                  borderRadius: "2px",
                  transition: "background 0.4s",
                  background: done ? "var(--bp-accent)" : "var(--bp-step-inactive)",
                }}
              />
            )}
          </div>
        );
      })}
    </div>
  );
}

// ─── Step 1: Service ──────────────────────────────────────────────────────────

function ServiceStep({ services, selected, onSelect, onNext }: {
  services: Service[];
  selected: Service | null;
  onSelect: (s: Service | null) => void;
  onNext: () => void;
}) {
  return (
    <div style={{ animation: "bpFadeSlide 0.22s ease both" }}>
      <p style={sectionTitle}>Escolha o serviço</p>
      <p style={sectionSub}>Selecione o que você deseja agendar</p>

      <div style={{ display: "flex", flexDirection: "column", gap: "10px", marginBottom: "24px" }}>
        {services.map((svc) => {
          const isSel = selected?.id === svc.id;
          return (
            <button
              key={svc.id}
              onClick={() => onSelect(isSel ? null : svc)}
              style={{
                ...card(isSel),
                padding: "16px",
                cursor: "pointer",
                display: "flex",
                alignItems: "center",
                gap: "14px",
                textAlign: "left",
                width: "100%",
              }}
            >
              {/* Emoji icon */}
              <div
                style={{
                  width: "48px",
                  height: "48px",
                  borderRadius: "12px",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  fontSize: "22px",
                  flexShrink: 0,
                  background: isSel ? "var(--bp-accent-subtle)" : "var(--bp-icon-bg)",
                  border: `1px solid ${isSel ? "var(--bp-accent-border)" : "var(--bp-card-border)"}`,
                }}
              >
                {serviceIcon(svc.name)}
              </div>

              {/* Name + duration */}
              <div style={{ flex: 1, minWidth: 0 }}>
                <p style={{
                  fontSize: "15px",
                  fontWeight: 600,
                  color: "var(--bp-text-1)",
                  marginBottom: "4px",
                  whiteSpace: "nowrap",
                  overflow: "hidden",
                  textOverflow: "ellipsis",
                }}>
                  {svc.name}
                </p>
                <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
                  <span style={{ display: "flex", alignItems: "center", gap: "4px", fontSize: "12px", color: "var(--bp-text-2)" }}>
                    <Clock size={11} />
                    {fmtDuration(svc.duration)}
                  </span>
                  {svc.description && (
                    <span style={{
                      fontSize: "11px",
                      color: "var(--bp-text-3)",
                      whiteSpace: "nowrap",
                      overflow: "hidden",
                      textOverflow: "ellipsis",
                      maxWidth: "140px",
                    }}>
                      · {svc.description}
                    </span>
                  )}
                </div>
              </div>

              {/* Price (top) + radio circle (bottom) */}
              <div style={{
                display: "flex",
                flexDirection: "column",
                alignItems: "flex-end",
                gap: "8px",
                flexShrink: 0,
              }}>
                <span style={{
                  fontSize: "15px",
                  fontWeight: 800,
                  color: isSel ? "var(--bp-accent)" : "var(--bp-text-1)",
                  whiteSpace: "nowrap",
                }}>
                  {fmtPrice(svc.price)}
                </span>

                {/* Radio circle */}
                <div style={{
                  width: "22px",
                  height: "22px",
                  borderRadius: "50%",
                  flexShrink: 0,
                  transition: "all 0.18s",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  ...(isSel
                    ? { background: "var(--bp-accent)", border: "2px solid var(--bp-accent)" }
                    : { background: "transparent", border: "2px solid var(--bp-card-border)" }),
                }}>
                  {isSel && <Check size={12} color="#fff" strokeWidth={3.5} />}
                </div>
              </div>
            </button>
          );
        })}
      </div>

      <button
        onClick={onNext}
        disabled={!selected}
        style={selected ? btnPrimary : btnDisabled}
      >
        {selected ? `Continuar com ${selected.name}` : "Selecione um serviço"}
      </button>
    </div>
  );
}

// ─── Step 2: Professional ─────────────────────────────────────────────────────

const AVATAR_COLORS = ["#B8900A", "#2563eb", "#059669", "#dc2626", "#7c3aed", "#db2777", "#0891b2", "#65a30d"];

function ProfessionalStep({ units, selectedUnit, onSelectUnit, professionals, loading, selected, onSelect, onBack, onNext }: {
  units: Unit[];
  selectedUnit: Unit | null;
  onSelectUnit: (u: Unit) => void;
  professionals: Professional[];
  loading: boolean;
  selected: Professional | null;
  onSelect: (p: Professional) => void;
  onBack: () => void;
  onNext: () => void;
}) {
  return (
    <div style={{ animation: "bpFadeSlide 0.22s ease both" }}>
      <button onClick={onBack} style={btnGhost}>
        <ChevronLeft size={16} />
        Voltar
      </button>

      {/* Unit selector */}
      {units.length > 1 && (
        <div style={{ marginBottom: "24px" }}>
          <label style={labelStyle}>Unidade</label>
          <div style={{ display: "flex", flexWrap: "wrap", gap: "8px" }}>
            {units.map((u) => {
              const sel = selectedUnit?.id === u.id;
              return (
                <button
                  key={u.id}
                  onClick={() => onSelectUnit(u)}
                  style={{
                    padding: "10px 18px",
                    borderRadius: "10px",
                    fontSize: "13px",
                    fontWeight: 600,
                    cursor: "pointer",
                    transition: "all 0.15s",
                    ...(sel
                      ? { background: "var(--bp-accent-subtle)", border: "2px solid var(--bp-accent)", color: "var(--bp-accent)" }
                      : { background: "var(--bp-card)", border: "1.5px solid var(--bp-card-border)", color: "var(--bp-text-2)" }),
                  }}
                >
                  {u.name}
                </button>
              );
            })}
          </div>
        </div>
      )}

      <p style={sectionTitle}>Escolha o profissional</p>
      <p style={sectionSub}>
        {!selectedUnit
          ? "Selecione uma unidade primeiro"
          : loading
          ? "Buscando profissionais..."
          : professionals.length === 0
          ? "Nenhum profissional disponível"
          : "Quem vai te atender hoje?"}
      </p>

      {loading ? (
        <div style={{ display: "flex", flexDirection: "column", gap: "10px", marginBottom: "24px" }}>
          {[1, 2].map((i) => (
            <div key={i} style={{ ...card(false), padding: "16px", display: "flex", gap: "14px", alignItems: "center" }}>
              <div style={{ width: "52px", height: "52px", borderRadius: "50%", background: "var(--bp-skeleton)", flexShrink: 0 }} />
              <div style={{ flex: 1 }}>
                <div style={{ height: "13px", width: "55%", background: "var(--bp-skeleton)", borderRadius: "6px", marginBottom: "8px" }} />
                <div style={{ height: "11px", width: "35%", background: "var(--bp-skeleton)", borderRadius: "6px" }} />
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(130px, 1fr))", gap: "10px", marginBottom: "24px" }}>
          {professionals.map((prof) => {
            const isSel = selected?.id === prof.id;
            const color = prof.color || AVATAR_COLORS[prof.id % AVATAR_COLORS.length];
            return (
              <button
                key={prof.id}
                onClick={() => onSelect(prof)}
                style={{
                  ...card(isSel),
                  padding: "20px 12px",
                  cursor: "pointer",
                  display: "flex",
                  flexDirection: "column",
                  alignItems: "center",
                  gap: "12px",
                  position: "relative",
                }}
              >
                {isSel && (
                  <div style={{
                    position: "absolute", top: "8px", right: "8px",
                    width: "18px", height: "18px", borderRadius: "50%",
                    background: "var(--bp-accent)",
                    display: "flex", alignItems: "center", justifyContent: "center",
                  }}>
                    <Check size={10} color="#fff" strokeWidth={3} />
                  </div>
                )}

                {prof.avatar ? (
                  <img src={prof.avatar} alt={prof.name} style={{
                    width: "56px", height: "56px", borderRadius: "50%", objectFit: "cover",
                    border: `2px solid ${isSel ? "var(--bp-accent)" : "var(--bp-card-border)"}`,
                  }} />
                ) : (
                  <div style={{
                    width: "56px", height: "56px", borderRadius: "50%",
                    background: isSel ? "var(--bp-accent-subtle)" : "var(--bp-icon-bg)",
                    border: `2px solid ${isSel ? "var(--bp-accent)" : "var(--bp-card-border)"}`,
                    display: "flex", alignItems: "center", justifyContent: "center",
                    fontSize: "18px", fontWeight: 800,
                    color: isSel ? "var(--bp-accent)" : color,
                  }}>
                    {getInitials(prof.name)}
                  </div>
                )}

                <p style={{ fontSize: "13px", fontWeight: 600, color: "var(--bp-text-1)", textAlign: "center", lineHeight: 1.3 }}>
                  {prof.name.split(" ")[0]}
                </p>
              </button>
            );
          })}
        </div>
      )}

      <button onClick={onNext} disabled={!selected} style={selected ? btnPrimary : btnDisabled}>
        {selected ? `Continuar com ${selected.name.split(" ")[0]}` : "Selecione um profissional"}
      </button>
    </div>
  );
}

// ─── Step 3: Date & Time ──────────────────────────────────────────────────────

function DateTimeStep({ today, selectedDate, onSelectDate, groupedSlots, loading, selectedTime, onSelectTime, onBack, onNext }: {
  today: Date;
  selectedDate: Date | undefined;
  onSelectDate: (d: Date | undefined) => void;
  groupedSlots: ReturnType<typeof groupSlots>;
  loading: boolean;
  selectedTime: string;
  onSelectTime: (t: string) => void;
  onBack: () => void;
  onNext: () => void;
}) {
  const hasSlots =
    groupedSlots.morning.length + groupedSlots.afternoon.length + groupedSlots.evening.length > 0;

  const periods = [
    { label: "Manhã", icon: "🌅", slots: groupedSlots.morning },
    { label: "Tarde", icon: "☀️", slots: groupedSlots.afternoon },
    { label: "Noite", icon: "🌙", slots: groupedSlots.evening },
  ].filter((p) => p.slots.length > 0);

  return (
    <div style={{ animation: "bpFadeSlide 0.22s ease both" }}>
      <button onClick={onBack} style={btnGhost}>
        <ChevronLeft size={16} />
        Voltar
      </button>

      <p style={sectionTitle}>Escolha a data</p>
      <p style={sectionSub}>Selecione o melhor dia para você</p>

      {/* Calendar */}
      <div style={{ ...card(false), padding: "16px", marginBottom: "24px", display: "flex", justifyContent: "center", overflowX: "auto" }}>
        <style>{`
          .bp-cal { --rdp-accent-color: var(--bp-accent); }
          .bp-cal .rdp-root { color: var(--bp-text-1); }
          .bp-cal .rdp-day_button { color: var(--bp-text-1); border-radius: 10px; }
          .bp-cal .rdp-day_button:hover:not(:disabled) { background: var(--bp-accent-subtle) !important; color: var(--bp-accent) !important; }
          .bp-cal [data-selected-single=true] .rdp-day_button { background: var(--bp-accent) !important; color: #fff !important; font-weight: 700; }
          .bp-cal .rdp-weekday { color: var(--bp-text-3); font-size: 11px; }
          .bp-cal .rdp-month_caption, .bp-cal .rdp-caption_label { color: var(--bp-text-1); font-weight: 600; }
          .bp-cal [data-disabled=true] .rdp-day_button { color: var(--bp-text-4) !important; cursor: not-allowed; }
          .bp-cal .rdp-nav button { color: var(--bp-text-2) !important; }
          .bp-cal [data-today=true]:not([data-selected-single=true]) .rdp-day_button { outline: 2px solid var(--bp-accent-border); outline-offset: -2px; }
        `}</style>
        <div className="bp-cal">
          <Calendar
            mode="single"
            selected={selectedDate}
            onSelect={onSelectDate}
            locale={ptBR}
            disabled={{ before: today }}
            captionLayout="label"
          />
        </div>
      </div>

      {/* Time slots */}
      {selectedDate && (
        <div style={{ marginBottom: "24px", animation: "bpFadeSlide 0.18s ease both" }}>
          <p style={{ ...sectionTitle, marginBottom: "4px" }}>Escolha o horário</p>
          <p style={{ ...sectionSub, marginBottom: "16px" }}>
            {format(selectedDate, "EEEE, d 'de' MMMM", { locale: ptBR })}
          </p>

          {loading ? (
            <div style={{ display: "flex", alignItems: "center", justifyContent: "center", gap: "10px", padding: "32px", color: "var(--bp-text-3)" }}>
              <Loader2 size={18} style={{ animation: "bpSpin 1s linear infinite" }} />
              <span style={{ fontSize: "13px" }}>Buscando horários...</span>
            </div>
          ) : !hasSlots ? (
            <div style={{ ...card(false), padding: "32px", textAlign: "center" }}>
              <CalendarDays size={28} style={{ color: "var(--bp-text-3)", margin: "0 auto 10px" }} />
              <p style={{ color: "var(--bp-text-2)", fontSize: "14px" }}>Sem horários disponíveis</p>
              <p style={{ color: "var(--bp-text-3)", fontSize: "12px", marginTop: "4px" }}>Tente outra data</p>
            </div>
          ) : (
            <div style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
              {periods.map(({ label, icon, slots }) => (
                <div key={label}>
                  <p style={{ fontSize: "11px", fontWeight: 700, color: "var(--bp-text-2)", letterSpacing: "0.07em", textTransform: "uppercase", marginBottom: "10px" }}>
                    {icon} {label}
                  </p>
                  <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(74px, 1fr))", gap: "8px" }}>
                    {slots.map((slot) => {
                      const isSel = selectedTime === slot.startTime;
                      return (
                        <button
                          key={slot.startTime}
                          onClick={() => onSelectTime(slot.startTime)}
                          style={{
                            padding: "12px 4px",
                            borderRadius: "12px",
                            fontSize: "14px",
                            fontWeight: 700,
                            cursor: "pointer",
                            transition: "all 0.12s",
                            ...(isSel
                              ? { background: "var(--bp-accent)", color: "#fff", border: "none", transform: "scale(1.05)", boxShadow: "0 2px 8px var(--bp-accent-subtle)" }
                              : { background: "var(--bp-slot-bg)", color: "var(--bp-slot-text)", border: "1.5px solid var(--bp-slot-border)" }),
                          }}
                        >
                          {slot.startTime}
                        </button>
                      );
                    })}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      <button
        onClick={onNext}
        disabled={!selectedDate || !selectedTime}
        style={selectedDate && selectedTime ? btnPrimary : btnDisabled}
      >
        {!selectedDate
          ? "Selecione uma data"
          : !selectedTime
          ? "Selecione um horário"
          : `Continuar — ${format(selectedDate, "d MMM", { locale: ptBR })} às ${selectedTime}`}
      </button>
    </div>
  );
}

// ─── Step 4: Confirm ──────────────────────────────────────────────────────────

function SummaryRow({ icon, label, value, sub }: { icon: string; label: string; value: string; sub?: string }) {
  return (
    <div style={{ display: "flex", alignItems: "flex-start", gap: "12px" }}>
      <span style={{ fontSize: "18px", flexShrink: 0, width: "24px", textAlign: "center", marginTop: "1px" }}>{icon}</span>
      <div style={{ flex: 1, minWidth: 0 }}>
        <p style={{ fontSize: "11px", color: "var(--bp-text-2)", marginBottom: "2px" }}>{label}</p>
        <p style={{ fontSize: "13px", fontWeight: 600, color: "var(--bp-text-1)", wordBreak: "break-word" }}>{value}</p>
        {sub && <p style={{ fontSize: "12px", color: "var(--bp-accent)", fontWeight: 600, marginTop: "2px" }}>{sub}</p>}
      </div>
    </div>
  );
}

function ConfirmStep({ service, professional, unit, date, time, clientName, clientPhone, clientEmail, onChangeName, onChangePhone, onChangeEmail, errors, isPending, onBack, onConfirm }: {
  service: Service; professional: Professional; unit: Unit; date: Date; time: string;
  clientName: string; clientPhone: string; clientEmail: string;
  onChangeName: (v: string) => void; onChangePhone: (v: string) => void; onChangeEmail: (v: string) => void;
  errors: Record<string, string>; isPending: boolean; onBack: () => void; onConfirm: () => void;
}) {
  return (
    <div style={{ animation: "bpFadeSlide 0.22s ease both" }}>
      <button onClick={onBack} style={btnGhost}>
        <ChevronLeft size={16} />
        Voltar
      </button>

      {/* Summary card */}
      <div style={{
        background: "var(--bp-accent-subtle)",
        border: "1.5px solid var(--bp-accent-border)",
        borderRadius: "16px",
        padding: "18px",
        marginBottom: "24px",
      }}>
        <p style={{ fontSize: "10px", fontWeight: 800, letterSpacing: "0.1em", textTransform: "uppercase", color: "var(--bp-accent)", marginBottom: "14px" }}>
          Resumo do agendamento
        </p>
        <div style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
          <SummaryRow icon="✂️" label="Serviço" value={`${service.name} · ${fmtDuration(service.duration)}`} sub={fmtPrice(service.price)} />
          <div style={{ height: "1px", background: "var(--bp-accent-border)" }} />
          <SummaryRow icon="👤" label="Profissional" value={professional.name} sub={unit.name} />
          <div style={{ height: "1px", background: "var(--bp-accent-border)" }} />
          <SummaryRow
            icon="📅"
            label="Data e hora"
            value={format(date, "EEEE, d 'de' MMMM 'de' yyyy", { locale: ptBR })}
            sub={`às ${time}`}
          />
        </div>
      </div>

      {/* Personal data */}
      <p style={sectionTitle}>Seus dados</p>
      <p style={{ ...sectionSub, marginBottom: "20px" }}>Para confirmar seu agendamento</p>

      <div style={{ display: "flex", flexDirection: "column", gap: "16px", marginBottom: "24px" }}>
        {/* Name */}
        <div>
          <label style={labelStyle}>Nome completo *</label>
          <input
            style={{ ...inputStyle, ...(errors.clientName ? { borderColor: "#ef4444" } : {}) }}
            placeholder="Seu nome"
            value={clientName}
            onChange={(e) => onChangeName(e.target.value)}
            autoComplete="name"
            onFocus={(e) => { if (!errors.clientName) e.currentTarget.style.borderColor = "var(--bp-accent)"; }}
            onBlur={(e) => { e.currentTarget.style.borderColor = errors.clientName ? "#ef4444" : "var(--bp-input-border)"; }}
          />
          {errors.clientName && <p style={{ color: "#ef4444", fontSize: "12px", marginTop: "6px" }}>{errors.clientName}</p>}
        </div>

        {/* Phone */}
        <div>
          <label style={labelStyle}>WhatsApp / Telefone *</label>
          <input
            style={{ ...inputStyle, ...(errors.clientPhone ? { borderColor: "#ef4444" } : {}) }}
            type="tel"
            placeholder="(11) 99999-9999"
            value={clientPhone}
            onChange={(e) => onChangePhone(e.target.value)}
            autoComplete="tel"
            inputMode="tel"
            onFocus={(e) => { if (!errors.clientPhone) e.currentTarget.style.borderColor = "var(--bp-accent)"; }}
            onBlur={(e) => { e.currentTarget.style.borderColor = errors.clientPhone ? "#ef4444" : "var(--bp-input-border)"; }}
          />
          {errors.clientPhone && <p style={{ color: "#ef4444", fontSize: "12px", marginTop: "6px" }}>{errors.clientPhone}</p>}
        </div>

        {/* Email */}
        <div>
          <label style={labelStyle}>E-mail (opcional)</label>
          <input
            style={{ ...inputStyle, ...(errors.clientEmail ? { borderColor: "#ef4444" } : {}) }}
            type="email"
            placeholder="seu@email.com"
            value={clientEmail}
            onChange={(e) => onChangeEmail(e.target.value)}
            autoComplete="email"
            inputMode="email"
            onFocus={(e) => { if (!errors.clientEmail) e.currentTarget.style.borderColor = "var(--bp-accent)"; }}
            onBlur={(e) => { e.currentTarget.style.borderColor = errors.clientEmail ? "#ef4444" : "var(--bp-input-border)"; }}
          />
          {errors.clientEmail && <p style={{ color: "#ef4444", fontSize: "12px", marginTop: "6px" }}>{errors.clientEmail}</p>}
        </div>
      </div>

      <p style={{ fontSize: "11px", color: "var(--bp-text-3)", marginBottom: "20px", lineHeight: 1.6 }}>
        Ao confirmar, você concorda com o tratamento dos seus dados para fins de agendamento, conforme a LGPD.
      </p>

      <button
        onClick={onConfirm}
        disabled={isPending}
        style={{ ...btnPrimary, display: "flex", alignItems: "center", justifyContent: "center", gap: "10px", opacity: isPending ? 0.7 : 1 }}
      >
        {isPending ? (
          <><Loader2 size={18} style={{ animation: "bpSpin 1s linear infinite" }} /> Agendando...</>
        ) : (
          <><Zap size={16} /> Confirmar agendamento</>
        )}
      </button>
    </div>
  );
}

// ─── Success Screen ───────────────────────────────────────────────────────────

function SuccessScreen({ service, professional, date, time, onNew }: {
  service: Service; professional: Professional; date: Date; time: string; onNew: () => void;
}) {
  return (
    <div style={{ animation: "bpFadeSlide 0.3s ease both", textAlign: "center", padding: "8px 0 32px" }}>
      <div style={{
        width: "80px", height: "80px", borderRadius: "50%",
        background: "rgba(34,197,94,0.1)", border: "2px solid rgba(34,197,94,0.3)",
        display: "flex", alignItems: "center", justifyContent: "center",
        margin: "0 auto 24px",
        animation: "bpCheckPop 0.4s cubic-bezier(0.34,1.56,0.64,1) both",
      }}>
        <CheckCircle2 size={40} color="#22c55e" />
      </div>

      <h2 style={{ fontSize: "24px", fontWeight: 800, color: "var(--bp-text-1)", marginBottom: "8px" }}>
        Agendado com sucesso!
      </h2>
      <p style={{ color: "var(--bp-text-2)", fontSize: "14px", lineHeight: 1.6, marginBottom: "32px" }}>
        Seu horário foi confirmado.<br />Você receberá uma confirmação em breve.
      </p>

      <div style={{ ...card(false), padding: "20px", textAlign: "left", marginBottom: "24px" }}>
        <div style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
          <SummaryRow icon="✂️" label="Serviço" value={service.name} sub={fmtPrice(service.price)} />
          <div style={{ height: "1px", background: "var(--bp-sep)" }} />
          <SummaryRow icon="👤" label="Profissional" value={professional.name} />
          <div style={{ height: "1px", background: "var(--bp-sep)" }} />
          <SummaryRow icon="📅" label="Data e hora" value={format(date, "d 'de' MMMM 'de' yyyy", { locale: ptBR })} sub={`às ${time}`} />
        </div>
      </div>

      <button
        onClick={onNew}
        style={{
          width: "100%", padding: "15px", borderRadius: "14px",
          fontSize: "14px", fontWeight: 600, cursor: "pointer",
          background: "var(--bp-card)", color: "var(--bp-text-1)",
          border: "1.5px solid var(--bp-card-border)",
        }}
      >
        Fazer outro agendamento
      </button>
    </div>
  );
}

// ─── Keyframe styles ──────────────────────────────────────────────────────────

function GlobalStyles() {
  return (
    <style>{`
      @keyframes bpFadeSlide {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
      }
      @keyframes bpCheckPop {
        from { opacity: 0; transform: scale(0.4); }
        to   { opacity: 1; transform: scale(1); }
      }
      @keyframes bpSpin { to { transform: rotate(360deg); } }
    `}</style>
  );
}

// ─── Main Component ───────────────────────────────────────────────────────────

export function PublicBookingForm({ tenantSlug, tenantId, services, units }: Props) {
  const [step, setStep] = useState<Step>(1);
  const [booked, setBooked] = useState(false);

  const [selectedService, setSelectedService] = useState<Service | null>(null);
  const [selectedUnit, setSelectedUnit] = useState<Unit | null>(units.length === 1 ? units[0] : null);
  const [selectedProfessional, setSelectedProfessional] = useState<Professional | null>(null);
  const [selectedDate, setSelectedDate] = useState<Date | undefined>(undefined);
  const [selectedTime, setSelectedTime] = useState("");

  const [clientName, setClientName] = useState("");
  const [clientPhone, setClientPhone] = useState("");
  const [clientEmail, setClientEmail] = useState("");
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});

  const today = startOfDay(new Date());

  const { data: professionals, isLoading: loadingProfessionals } = useQuery<Professional[]>({
    queryKey: ["pub-profs", tenantSlug, selectedService?.id, selectedUnit?.id],
    enabled: !!selectedService && !!selectedUnit,
    queryFn: () =>
      fetch(`/api/book/${tenantSlug}/professionals?serviceId=${selectedService!.id}&unitId=${selectedUnit!.id}`)
        .then((r) => r.json()),
  });

  const dateStr = selectedDate ? format(selectedDate, "yyyy-MM-dd") : "";

  const { data: slotsData, isLoading: loadingSlots } = useQuery<{ slots: Slot[] }>({
    queryKey: ["pub-slots", tenantSlug, selectedProfessional?.id, selectedService?.id, selectedUnit?.id, dateStr],
    enabled: !!selectedProfessional && !!selectedService && !!selectedDate,
    queryFn: () =>
      fetch(
        `/api/book/${tenantSlug}/slots?professionalId=${selectedProfessional!.id}&serviceId=${selectedService!.id}&unitId=${selectedUnit!.id}&date=${dateStr}`
      ).then((r) => r.json()),
  });

  const slots = slotsData?.slots ?? [];
  const groupedSlots = useMemo(() => groupSlots(slots), [slots]);

  const book = useMutation({
    mutationFn: () =>
      fetch(`/api/book/${tenantSlug}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          clientName: clientName.trim(),
          clientPhone: clientPhone.replace(/\D/g, ""),
          clientEmail: clientEmail.trim() || undefined,
          serviceId: String(selectedService!.id),
          unitId: String(selectedUnit!.id),
          professionalId: String(selectedProfessional!.id),
          date: dateStr,
          startTime: selectedTime,
        }),
      }).then((r) => r.json()),
    onSuccess: (res) => {
      if (res.error) { toast.error(res.error); return; }
      setBooked(true);
    },
    onError: () => toast.error("Erro ao agendar. Tente novamente."),
  });

  function validateStep4() {
    const errs: Record<string, string> = {};
    if (!clientName.trim() || clientName.trim().length < 2) errs.clientName = "Informe seu nome";
    if (clientPhone.replace(/\D/g, "").length < 10) errs.clientPhone = "Telefone inválido";
    if (clientEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(clientEmail)) errs.clientEmail = "E-mail inválido";
    setFormErrors(errs);
    return Object.keys(errs).length === 0;
  }

  function handleReset() {
    setBooked(false); setStep(1); setSelectedService(null);
    setSelectedProfessional(null); setSelectedDate(undefined); setSelectedTime("");
    setClientName(""); setClientPhone(""); setClientEmail(""); setFormErrors({});
  }

  if (booked) {
    return (
      <>
        <GlobalStyles />
        <SuccessScreen service={selectedService!} professional={selectedProfessional!} date={selectedDate!} time={selectedTime} onNew={handleReset} />
      </>
    );
  }

  return (
    <>
      <GlobalStyles />
      <StepIndicator current={step} />

      {step === 1 && (
        <ServiceStep services={services} selected={selectedService}
          onSelect={(s) => { setSelectedService(s); setSelectedProfessional(null); setSelectedDate(undefined); setSelectedTime(""); }}
          onNext={() => setStep(2)}
        />
      )}

      {step === 2 && (
        <ProfessionalStep
          units={units} selectedUnit={selectedUnit}
          onSelectUnit={(u) => { setSelectedUnit(u); setSelectedProfessional(null); }}
          professionals={professionals ?? []}
          loading={loadingProfessionals && !!selectedUnit && !!selectedService}
          selected={selectedProfessional} onSelect={setSelectedProfessional}
          onBack={() => setStep(1)} onNext={() => setStep(3)}
        />
      )}
      {step === 3 && (
        <DateTimeStep
          today={today} selectedDate={selectedDate}
          onSelectDate={(d) => { setSelectedDate(d); setSelectedTime(""); }}
          groupedSlots={groupedSlots}
          loading={loadingSlots && !!selectedDate}
          selectedTime={selectedTime} onSelectTime={setSelectedTime}
          onBack={() => setStep(2)} onNext={() => setStep(4)}
        />
      )}
      {step === 4 && (
        <ConfirmStep
          service={selectedService!} professional={selectedProfessional!}
          unit={selectedUnit!} date={selectedDate!} time={selectedTime}
          clientName={clientName} clientPhone={clientPhone} clientEmail={clientEmail}
          onChangeName={setClientName}
          onChangePhone={(v) => setClientPhone(fmtPhone(v))}
          onChangeEmail={setClientEmail}
          errors={formErrors} isPending={book.isPending}
          onBack={() => setStep(3)} onConfirm={() => { if (validateStep4()) book.mutate(); }}
        />
      )}
    </>
  );
}
