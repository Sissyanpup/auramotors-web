"use client";

import { useState } from "react";

/**
 * Grafik laporan admin — SVG ringan tanpa library tambahan (proyek offline).
 * Mengikuti spesifikasi dataviz: satu seri per grafik (identitas dibawa label
 * sumbu, bukan warna), batang ≤ 24px dengan ujung data membulat 4px dan
 * pangkal rata di baseline, grid hairline, label langsung hanya di nilai
 * tertinggi, tooltip saat hover, dan tabel data sebagai alternatif.
 *
 * Warna data #b08a2e (emas tua) sudah divalidasi pada permukaan gelap
 * #1f2022: lightness band, chroma, dan kontras ≥ 3:1 lolos.
 */
const SERIES_COLOR = "#b08a2e";
const GRID_COLOR = "var(--color-border)";
const BAR_MAX = 24;
const RADIUS = 4;

export function formatCompactRupiah(value: number): string {
  const abs = Math.abs(value);
  const fmt = (n: number) => n.toLocaleString("id-ID", { maximumFractionDigits: n >= 100 ? 0 : 1 });
  if (abs >= 1e12) return `Rp${fmt(value / 1e12)} T`;
  if (abs >= 1e9) return `Rp${fmt(value / 1e9)} M`;
  if (abs >= 1e6) return `Rp${fmt(value / 1e6)} jt`;
  if (abs >= 1e3) return `Rp${fmt(value / 1e3)} rb`;
  return `Rp${fmt(value)}`;
}

function niceMax(value: number): number {
  if (value <= 0) return 1;
  const exponent = Math.pow(10, Math.floor(Math.log10(value)));
  const fraction = value / exponent;
  const nice = fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 5 ? 5 : 10;
  return nice * exponent;
}

/** Path batang dengan ujung atas membulat dan pangkal rata. */
function columnPath(x: number, y: number, width: number, height: number): string {
  const r = Math.min(RADIUS, width / 2, height);
  return `M${x},${y + height} V${y + r} Q${x},${y} ${x + r},${y} H${x + width - r} Q${x + width},${y} ${x + width},${y + r} V${y + height} Z`;
}

/** Path batang horizontal: pangkal rata di kiri, ujung kanan membulat. */
function barPath(x: number, y: number, width: number, height: number): string {
  const r = Math.min(RADIUS, height / 2, width);
  return `M${x},${y} H${x + width - r} Q${x + width},${y} ${x + width},${y + r} V${y + height - r} Q${x + width},${y + height} ${x + width - r},${y + height} H${x} Z`;
}

type Datum = { label: string; value: number; hint?: string };

export function ColumnChart({
  title,
  subtitle,
  data,
  formatValue,
}: {
  title: string;
  subtitle?: string;
  data: Datum[];
  formatValue: (value: number) => string;
}) {
  const [hover, setHover] = useState<number | null>(null);
  const width = 640;
  const height = 220;
  const pad = { top: 24, right: 8, bottom: 28, left: 64 };
  const plotW = width - pad.left - pad.right;
  const plotH = height - pad.top - pad.bottom;
  const max = niceMax(Math.max(...data.map((d) => d.value), 0));
  const band = plotW / Math.max(data.length, 1);
  const barW = Math.min(BAR_MAX, band * 0.6);
  const maxIndex = data.reduce((best, d, i) => (d.value > (data[best]?.value ?? -1) ? i : best), 0);
  const ticks = [0, 0.25, 0.5, 0.75, 1].map((t) => t * max);
  const allZero = data.every((d) => d.value === 0);

  return (
    <figure className="rounded-lg border border-border bg-surface-container p-4">
      <figcaption>
        <p className="text-sm font-semibold text-on-surface">{title}</p>
        {subtitle && <p className="text-xs text-on-surface-muted">{subtitle}</p>}
      </figcaption>
      <div className="relative mt-3">
        <svg viewBox={`0 0 ${width} ${height}`} className="h-auto w-full" role="img" aria-label={title}>
          {ticks.map((tick) => {
            const y = pad.top + plotH - (tick / max) * plotH;
            return (
              <g key={tick}>
                <line x1={pad.left} x2={width - pad.right} y1={y} y2={y} stroke={GRID_COLOR} strokeWidth={1} />
                <text x={pad.left - 8} y={y + 4} textAnchor="end" fontSize={11} fill="var(--color-on-surface-muted)">
                  {formatValue(tick)}
                </text>
              </g>
            );
          })}
          {data.map((d, i) => {
            const h = (d.value / max) * plotH;
            const x = pad.left + band * i + (band - barW) / 2;
            const y = pad.top + plotH - h;
            return (
              <g key={d.label} onMouseEnter={() => setHover(i)} onMouseLeave={() => setHover(null)}>
                <rect x={pad.left + band * i} y={pad.top} width={band} height={plotH} fill="transparent" />
                {d.value > 0 && <path d={columnPath(x, y, barW, h)} fill={SERIES_COLOR} opacity={hover === null || hover === i ? 1 : 0.55} />}
                <text x={x + barW / 2} y={height - 10} textAnchor="middle" fontSize={11} fill="var(--color-on-surface-muted)">
                  {d.label}
                </text>
                {i === maxIndex && d.value > 0 && hover === null && (
                  <text x={x + barW / 2} y={y - 6} textAnchor="middle" fontSize={11} fontWeight={600} fill="var(--color-on-surface)">
                    {formatValue(d.value)}
                  </text>
                )}
              </g>
            );
          })}
        </svg>
        {hover !== null && data[hover] && (
          <div
            className="pointer-events-none absolute top-0 z-10 -translate-x-1/2 rounded-md border border-border bg-surface px-2.5 py-1.5 text-xs shadow-lg"
            style={{ left: `${((pad.left + band * hover + band / 2) / width) * 100}%` }}
          >
            <p className="text-on-surface-muted">{data[hover].hint ?? data[hover].label}</p>
            <p className="font-semibold text-on-surface tabular-nums">{formatValue(data[hover].value)}</p>
          </div>
        )}
        {allZero && <p className="absolute inset-0 flex items-center justify-center text-xs text-on-surface-muted">Belum ada data pada rentang ini</p>}
      </div>
      <DataTable rows={data.map((d) => [d.hint ?? d.label, formatValue(d.value)])} />
    </figure>
  );
}

export function BarList({
  title,
  subtitle,
  data,
  formatValue,
}: {
  title: string;
  subtitle?: string;
  data: Datum[];
  formatValue: (value: number) => string;
}) {
  const [hover, setHover] = useState<number | null>(null);
  const max = Math.max(...data.map((d) => d.value), 0) || 1;
  const rowH = 30;
  const barH = 14;
  const labelW = 150;
  const width = 520;
  const valueW = 90;
  const plotW = width - labelW - valueW;
  const height = data.length * rowH + 4;

  return (
    <figure className="rounded-lg border border-border bg-surface-container p-4">
      <figcaption>
        <p className="text-sm font-semibold text-on-surface">{title}</p>
        {subtitle && <p className="text-xs text-on-surface-muted">{subtitle}</p>}
      </figcaption>
      {data.length === 0 ? (
        <p className="mt-3 text-xs text-on-surface-muted">Belum ada data pada rentang ini</p>
      ) : (
        <svg viewBox={`0 0 ${width} ${height}`} className="mt-3 h-auto w-full" role="img" aria-label={title}>
          <line x1={labelW} x2={labelW} y1={0} y2={height} stroke={GRID_COLOR} strokeWidth={1} />
          {data.map((d, i) => {
            const y = i * rowH + (rowH - barH) / 2;
            const w = (d.value / max) * plotW;
            return (
              <g key={d.label} onMouseEnter={() => setHover(i)} onMouseLeave={() => setHover(null)}>
                <rect x={0} y={i * rowH} width={width} height={rowH} fill={hover === i ? "var(--color-surface-container-high)" : "transparent"} />
                <text x={labelW - 8} y={y + barH - 2} textAnchor="end" fontSize={12} fill="var(--color-on-surface)">
                  {d.label}
                </text>
                {w > 0 && <path d={barPath(labelW, y, Math.max(w, 2), barH)} fill={SERIES_COLOR} />}
                <text x={labelW + Math.max(w, 2) + 8} y={y + barH - 2} fontSize={12} fill="var(--color-on-surface-muted)">
                  {formatValue(d.value)}
                  {hover === i && d.hint ? ` · ${d.hint}` : ""}
                </text>
              </g>
            );
          })}
        </svg>
      )}
      <DataTable rows={data.map((d) => [d.label, `${formatValue(d.value)}${d.hint ? ` (${d.hint})` : ""}`])} />
    </figure>
  );
}

export function StatTile({ label, value, hint, tone }: { label: string; value: string; hint?: string; tone?: "danger" }) {
  return (
    <div className="rounded-lg border border-border bg-surface-container p-3">
      <dt className="text-xs text-on-surface-muted">{label}</dt>
      <dd className={`mt-1 text-lg font-semibold tabular-nums ${tone === "danger" ? "text-error" : "text-on-surface"}`}>{value}</dd>
      {hint && <p className="text-[11px] text-on-surface-muted">{hint}</p>}
    </div>
  );
}

function DataTable({ rows }: { rows: string[][] }) {
  return (
    <details className="mt-2 text-xs">
      <summary className="cursor-pointer text-on-surface-muted hover:text-on-surface">Lihat data tabel</summary>
      <table className="mt-2 w-full">
        <tbody className="divide-y divide-border">
          {rows.map(([label, value]) => (
            <tr key={label}>
              <td className="py-1 pr-3 text-on-surface-muted">{label}</td>
              <td className="py-1 text-right tabular-nums text-on-surface">{value}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </details>
  );
}
