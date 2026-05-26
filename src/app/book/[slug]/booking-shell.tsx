"use client";

import { useState, useEffect } from "react";
import { Sun, Moon, Star, Phone } from "lucide-react";
import { PublicBookingForm } from "./booking-form";

interface Service {
  id: number;
  name: string;
  duration: number;
  price: number | string;
  description?: string | null;
  color?: string | null;
}

interface Unit {
  id: number;
  name: string;
}

interface Props {
  tenantSlug: string;
  tenantId: number;
  tenantName: string;
  tenantPhone?: string | null;
  services: Service[];
  units: Unit[];
  rating?: string | null;
  reviewCount?: number;
}

export function BookingShell({
  tenantSlug,
  tenantId,
  tenantName,
  tenantPhone,
  services,
  units,
  rating,
  reviewCount,
}: Props) {
  const [isDark, setIsDark] = useState(false);

  /* persist preference */
  useEffect(() => {
    try {
      const saved = localStorage.getItem("bp-theme");
      if (saved === "dark") setIsDark(true);
    } catch {}
  }, []);

  function toggle() {
    const next = !isDark;
    setIsDark(next);
    try {
      localStorage.setItem("bp-theme", next ? "dark" : "light");
    } catch {}
  }

  return (
    <div
      data-theme={isDark ? "dark" : "light"}
      style={{
        minHeight: "100vh",
        backgroundColor: "var(--bp-bg)",
        color: "var(--bp-text-1)",
        transition: "background-color 0.25s, color 0.25s",
      }}
    >
      <ThemeVars />

      {/* ── Hero ─────────────────────────────────────────── */}
      <div
        style={{
          background: "var(--bp-hero-gradient)",
          borderBottom: "1px solid var(--bp-card-border)",
          position: "relative",
        }}
      >
        {/* Glow — dark only */}
        {isDark && (
          <div
            style={{
              position: "absolute",
              inset: 0,
              pointerEvents: "none",
              background:
                "radial-gradient(ellipse 60% 50% at 50% 0%, rgba(197,160,40,0.07) 0%, transparent 70%)",
            }}
          />
        )}

        {/* Theme toggle */}
        <button
          onClick={toggle}
          aria-label={isDark ? "Mudar para modo claro" : "Mudar para modo escuro"}
          style={{
            position: "absolute",
            top: "16px",
            right: "16px",
            width: "38px",
            height: "38px",
            borderRadius: "50%",
            border: "1px solid var(--bp-card-border)",
            background: "var(--bp-surface)",
            color: "var(--bp-text-2)",
            cursor: "pointer",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            transition: "all 0.2s",
            zIndex: 10,
          }}
        >
          {isDark ? <Sun size={16} /> : <Moon size={16} />}
        </button>

        <div
          style={{
            maxWidth: "520px",
            margin: "0 auto",
            padding: "40px 20px 32px",
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            textAlign: "center",
            gap: "16px",
          }}
        >
          {/* Avatar */}
          <div
            style={{
              width: "72px",
              height: "72px",
              borderRadius: "20px",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              fontSize: "28px",
              fontWeight: 900,
              flexShrink: 0,
              background: isDark
                ? "linear-gradient(135deg, #C5A028, #8A6F1A)"
                : "linear-gradient(135deg, #C5A028, #F0C84A)",
              color: "#000",
              boxShadow: isDark
                ? "0 0 32px rgba(197,160,40,0.2)"
                : "0 4px 20px rgba(197,160,40,0.25)",
            }}
          >
            {tenantName.charAt(0).toUpperCase()}
          </div>

          {/* Name */}
          <div>
            <h1
              style={{
                fontSize: "22px",
                fontWeight: 800,
                color: "var(--bp-text-1)",
                letterSpacing: "-0.02em",
                margin: 0,
              }}
            >
              {tenantName}
            </h1>
            <p
              style={{
                marginTop: "4px",
                fontSize: "13px",
                color: "var(--bp-text-2)",
              }}
            >
              Agendamento Online
            </p>
          </div>

          {/* Pills */}
          <div
            style={{
              display: "flex",
              flexWrap: "wrap",
              alignItems: "center",
              justifyContent: "center",
              gap: "8px",
            }}
          >
            {rating && (
              <span
                style={{
                  display: "flex",
                  alignItems: "center",
                  gap: "5px",
                  padding: "6px 12px",
                  borderRadius: "999px",
                  fontSize: "12px",
                  fontWeight: 700,
                  background: "var(--bp-accent-subtle)",
                  color: "var(--bp-accent)",
                  border: "1px solid var(--bp-accent-border)",
                }}
              >
                <Star size={11} style={{ fill: "currentColor" }} />
                {rating}
                {reviewCount != null && reviewCount > 0 && (
                  <span style={{ opacity: 0.6 }}>({reviewCount})</span>
                )}
              </span>
            )}

            <span
              style={{
                display: "flex",
                alignItems: "center",
                gap: "5px",
                padding: "6px 12px",
                borderRadius: "999px",
                fontSize: "12px",
                fontWeight: 600,
                background: "var(--bp-surface)",
                color: "var(--bp-text-2)",
                border: "1px solid var(--bp-card-border)",
              }}
            >
              <span
                style={{
                  width: "6px",
                  height: "6px",
                  borderRadius: "50%",
                  background: "#22c55e",
                  flexShrink: 0,
                }}
              />
              Disponível agora
            </span>

            {tenantPhone && (
              <span
                style={{
                  display: "flex",
                  alignItems: "center",
                  gap: "5px",
                  padding: "6px 12px",
                  borderRadius: "999px",
                  fontSize: "12px",
                  fontWeight: 600,
                  background: "var(--bp-surface)",
                  color: "var(--bp-text-2)",
                  border: "1px solid var(--bp-card-border)",
                }}
              >
                <Phone size={11} />
                {tenantPhone}
              </span>
            )}
          </div>
        </div>
      </div>

      {/* ── Booking form ─────────────────────────────────── */}
      <div
        style={{
          maxWidth: "520px",
          margin: "0 auto",
          padding: "28px 16px 96px",
        }}
      >
        <PublicBookingForm
          tenantSlug={tenantSlug}
          tenantId={tenantId}
          services={services}
          units={units}
        />
      </div>

      {/* ── Footer ───────────────────────────────────────── */}
      <div
        style={{
          textAlign: "center",
          padding: "20px 16px",
          borderTop: "1px solid var(--bp-card-border)",
        }}
      >
        <p style={{ fontSize: "11px", color: "var(--bp-text-3)" }}>
          Agendamento seguro · Powered by{" "}
          <span style={{ color: "var(--bp-accent)", fontWeight: 600 }}>
            AgendaPRO
          </span>
        </p>
      </div>
    </div>
  );
}

/* ── CSS Variables ─────────────────────────────────────────────── */

function ThemeVars() {
  return (
    <style>{`
      [data-theme="light"] {
        --bp-bg:              #F5F4F1;
        --bp-surface:         #FFFFFF;
        --bp-card:            #FFFFFF;
        --bp-card-border:     #E8E8E6;
        --bp-text-1:          #111111;
        --bp-text-2:          #555555;
        --bp-text-3:          #999999;
        --bp-text-4:          #CCCCCC;
        --bp-accent:          #B8900A;
        --bp-accent-subtle:   rgba(184,144,10,0.08);
        --bp-accent-border:   rgba(184,144,10,0.25);
        --bp-ghost-bg:        #EFEFED;
        --bp-ghost-text:      #333333;
        --bp-ghost-border:    #DDDDD9;
        --bp-input-bg:        #FFFFFF;
        --bp-input-border:    #D5D5D0;
        --bp-input-text:      #111111;
        --bp-input-ph:        #AAAAAA;
        --bp-skeleton:        #EBEBEB;
        --bp-sep:             #EFEFED;
        --bp-icon-bg:         #F7F6F3;
        --bp-step-inactive:   #E5E5E2;
        --bp-step-inactive-t: #AAAAAA;
        --bp-sel-card-bg:     #FFFBEE;
        --bp-sel-card-border: #B8900A;
        --bp-hero-gradient:   linear-gradient(160deg,#FFFFFF 0%,#FFFDF5 60%,#FFF9E8 100%);
        --bp-back-bg:         #EFEFED;
        --bp-back-text:       #333333;
        --bp-back-border:     #DDDDD9;
        --bp-slot-bg:         #F5F4F1;
        --bp-slot-text:       #222222;
        --bp-slot-border:     #E0DFDB;
      }

      [data-theme="dark"] {
        --bp-bg:              #080808;
        --bp-surface:         #111111;
        --bp-card:            #141414;
        --bp-card-border:     #252525;
        --bp-text-1:          #F0F0F0;
        --bp-text-2:          #909090;
        --bp-text-3:          #555555;
        --bp-text-4:          #353535;
        --bp-accent:          #C5A028;
        --bp-accent-subtle:   rgba(197,160,40,0.08);
        --bp-accent-border:   rgba(197,160,40,0.25);
        --bp-ghost-bg:        transparent;
        --bp-ghost-text:      #BBBBBB;
        --bp-ghost-border:    #2A2A2A;
        --bp-input-bg:        #181818;
        --bp-input-border:    #2E2E2E;
        --bp-input-text:      #F0F0F0;
        --bp-input-ph:        #505050;
        --bp-skeleton:        #1E1E1E;
        --bp-sep:             #1C1C1C;
        --bp-icon-bg:         #1E1E1E;
        --bp-step-inactive:   #1E1E1E;
        --bp-step-inactive-t: #444444;
        --bp-sel-card-bg:     rgba(197,160,40,0.07);
        --bp-sel-card-border: #C5A028;
        --bp-hero-gradient:   linear-gradient(160deg,#0F0F0F 0%,#120F00 60%,#0F0F0F 100%);
        --bp-back-bg:         #1A1A1A;
        --bp-back-text:       #CCCCCC;
        --bp-back-border:     #2A2A2A;
        --bp-slot-bg:         #111111;
        --bp-slot-text:       #DDDDDD;
        --bp-slot-border:     #242424;
      }
    `}</style>
  );
}
