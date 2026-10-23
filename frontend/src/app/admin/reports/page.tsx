"use client";

import { useEffect, useMemo, useState } from "react";
import { apiFetch } from "@/lib/api";
import { formatDate, formatRupiah } from "@/lib/format";
import type { AdminReport } from "@/lib/types";
import { BarList, ColumnChart, StatTile, formatCompactRupiah } from "@/components/ReportCharts";

const MONTHS = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];

const ESCROW_LABEL: Record<string, string> = {
  escrow_hold: "Dana ditahan",
  serah_terima: "Serah terima",
  payout_release: "Payout disetujui",
  selesai: "Selesai",
  dispute: "Sengketa",
  refunded: "Dikembalikan",
};
const SCHEME_LABEL: Record<string, string> = { down_payment: "DP + pelunasan", full: "Bayar penuh", credit: "Kredit leasing" };
const STATUS_LABEL: Record<string, string> = {
  pending: "Menunggu",
  approved: "Disetujui",
  rejected: "Ditolak",
  draft: "Draf",
  pending_review: "Menunggu review",
  sold: "Terjual",
};

type TabKey = "sales" | "payouts" | "bonuses" | "promos" | "loans";

type Column<T> = { header: string; value: (row: T) => string | number | null; numeric?: boolean; format?: (row: T) => string };

function monthLabel(month: string): string {
  const [year, m] = month.split("-");
  return `${MONTHS[Number(m) - 1]} ${year.slice(2)}`;
}

function toInputDate(date: Date): string {
  return date.toISOString().slice(0, 10);
}

function downloadCsv<T>(filename: string, columns: Column<T>[], rows: T[]) {
  const escape = (value: string | number | null) => {
    const text = value === null || value === undefined ? "" : String(value);
    return /[",\n;]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
  };
  const csv = [columns.map((c) => escape(c.header)).join(","), ...rows.map((row) => columns.map((c) => escape(c.value(row))).join(","))].join("\n");
  const url = URL.createObjectURL(new Blob(["﻿" + csv], { type: "text/csv;charset=utf-8" }));
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(url);
}

/**
 * Laporan lengkap admin (catatan dosen 16 Okt): KPI, grafik bulanan, komposisi,
 * dan tabel rinci yang bisa diekspor ke CSV atau dicetak/disimpan sebagai PDF.
 */
export default function AdminReportsPage() {
  const today = new Date();
  const [range, setRange] = useState(() => {
    const from = new Date(today.getFullYear(), today.getMonth() - 11, 1);
    return { from: toInputDate(from), to: toInputDate(today) };
  });
  const [report, setReport] = useState<AdminReport | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [tab, setTab] = useState<TabKey>("sales");

  useEffect(() => {
    let cancelled = false;
    // eslint-disable-next-line react-hooks/set-state-in-effect -- loading indicator for refetch
    setIsLoading(true);
    apiFetch<AdminReport>(`/api/admin/reports?from=${range.from}&to=${range.to}`).then((data) => {
      if (cancelled) return;
      setReport(data);
      setIsLoading(false);
    });
    return () => {
      cancelled = true;
    };
  }, [range]);

  function preset(months: number) {
    const end = new Date();
    const start = new Date(end.getFullYear(), end.getMonth() - (months - 1), 1);
    setRange({ from: toInputDate(start), to: toInputDate(end) });
  }

  const tables = useMemo(() => {
    if (!report) return null;
    const date = (value: string | null) => (value ? formatDate(value) : "—");
    return {
      sales: {
        label: "Penjualan",
        rows: report.tables.sales,
        columns: [
          { header: "Invoice", value: (r) => r.invoice_number ?? `#${r.id}` },
          { header: "Dibayar", value: (r) => r.paid_at, format: (r) => date(r.paid_at) },
          { header: "Kendaraan", value: (r) => r.vehicle },
          { header: "Pembeli", value: (r) => r.buyer },
          { header: "Penjual", value: (r) => r.seller },
          { header: "Skema", value: (r) => SCHEME_LABEL[r.scheme ?? ""] ?? r.scheme },
          { header: "Harga", value: (r) => r.vehicle_price, numeric: true, format: (r) => formatRupiah(r.vehicle_price) },
          { header: "Dibayar ke escrow", value: (r) => r.amount, numeric: true, format: (r) => formatRupiah(r.amount) },
          { header: "Promo", value: (r) => r.promo_code ?? "" },
          { header: "Status escrow", value: (r) => ESCROW_LABEL[r.escrow_status ?? ""] ?? r.escrow_status ?? "" },
        ] as Column<AdminReport["tables"]["sales"][number]>[],
      },
      payouts: {
        label: "Pencairan",
        rows: report.tables.payouts,
        columns: [
          { header: "Dicairkan", value: (r) => r.paid_at, format: (r) => date(r.paid_at) },
          { header: "Transaksi", value: (r) => `#${r.transaction_id}` },
          { header: "Kendaraan", value: (r) => r.vehicle },
          { header: "Penjual", value: (r) => r.seller },
          { header: "Metode", value: (r) => r.method },
          { header: "Komisi", value: (r) => r.commission_amount, numeric: true, format: (r) => formatRupiah(r.commission_amount) },
          { header: "Ke penjual", value: (r) => r.payout_amount, numeric: true, format: (r) => formatRupiah(r.payout_amount) },
          { header: "Bonus", value: (r) => r.bonus_amount, numeric: true, format: (r) => formatRupiah(r.bonus_amount) },
          { header: "Referensi", value: (r) => r.reference },
        ] as Column<AdminReport["tables"]["payouts"][number]>[],
      },
      bonuses: {
        label: "Bonus Penjual",
        rows: report.tables.bonuses,
        columns: [
          { header: "Tanggal", value: (r) => r.created_at, format: (r) => date(r.created_at) },
          { header: "Penjual", value: (r) => r.seller },
          { header: "Jenis", value: (r) => (r.type === "tier" ? "Tier" : "Per unit") },
          { header: "Dasar", value: (r) => r.basis },
          { header: "Transaksi", value: (r) => (r.transaction_id ? `#${r.transaction_id}` : "") },
          { header: "Jumlah", value: (r) => r.amount, numeric: true, format: (r) => formatRupiah(r.amount) },
        ] as Column<AdminReport["tables"]["bonuses"][number]>[],
      },
      promos: {
        label: "Pemakaian Promo",
        rows: report.tables.promos,
        columns: [
          { header: "Tanggal", value: (r) => r.created_at, format: (r) => date(r.created_at) },
          { header: "Promo", value: (r) => r.promo },
          { header: "Kode", value: (r) => r.code ?? "otomatis" },
          { header: "Pembeli", value: (r) => r.buyer },
          { header: "Transaksi", value: (r) => `#${r.transaction_id}` },
          { header: "Manfaat", value: (r) => r.benefit_amount, numeric: true, format: (r) => formatRupiah(r.benefit_amount) },
        ] as Column<AdminReport["tables"]["promos"][number]>[],
      },
      loans: {
        label: "Kredit & Cicilan",
        rows: report.tables.loans,
        columns: [
          { header: "Kontrak", value: (r) => r.contract_number },
          { header: "Pembeli", value: (r) => r.buyer },
          { header: "Kendaraan", value: (r) => r.vehicle },
          { header: "Status", value: (r) => ({ submitted: "Dianalisis", approved: "Berjalan", paid_off: "Lunas" })[r.status] ?? r.status },
          { header: "Pokok", value: (r) => r.principal, numeric: true, format: (r) => formatRupiah(r.principal) },
          { header: "Terbayar", value: (r) => `${r.paid_count}/${r.tenor_months}` },
          { header: "Sisa pokok", value: (r) => r.outstanding_principal, numeric: true, format: (r) => formatRupiah(r.outstanding_principal) },
          { header: "Terlambat", value: (r) => r.overdue_count, numeric: true },
          { header: "Denda tertunggak", value: (r) => r.late_fees_due, numeric: true, format: (r) => formatRupiah(r.late_fees_due) },
        ] as Column<AdminReport["tables"]["loans"][number]>[],
      },
    };
  }, [report]);

  const k = report?.kpis;
  const active = tables?.[tab];

  return (
    <div className="report-page">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold text-on-surface">Laporan</h1>
          <p className="mt-1 text-sm text-on-surface-muted">
            Ringkasan penjualan, keuangan, kredit, bonus, promo, dan verifikasi
            {report ? ` · ${formatDay(report.range.from)} – ${formatDay(report.range.to)}` : ""}.
          </p>
        </div>
        <button onClick={() => window.print()} className="rounded-md border border-border px-3 py-1.5 text-sm text-on-surface hover:bg-surface-container-high print:hidden">
          Cetak / Simpan PDF
        </button>
      </div>

      <div className="mt-4 flex flex-wrap items-end gap-3 print:hidden">
        {[3, 6, 12].map((months) => (
          <button key={months} onClick={() => preset(months)} className="rounded-full bg-surface-container-high px-3 py-1 text-xs font-medium text-on-surface-muted hover:bg-border">
            {months} bulan terakhir
          </button>
        ))}
        <label className="text-xs text-on-surface-muted">
          Dari
          <input type="date" value={range.from} max={range.to} onChange={(e) => setRange({ ...range, from: e.target.value })} className="input-field mt-1 py-1.5" />
        </label>
        <label className="text-xs text-on-surface-muted">
          Sampai
          <input type="date" value={range.to} min={range.from} onChange={(e) => setRange({ ...range, to: e.target.value })} className="input-field mt-1 py-1.5" />
        </label>
      </div>

      {!report || !k ? (
        <p className="mt-6 text-sm text-on-surface-muted">Memuat laporan...</p>
      ) : (
        <div className={isLoading ? "opacity-60" : undefined}>
          <section className="mt-6 rounded-lg border border-border bg-surface-container p-5">
            <p className="text-xs uppercase tracking-wider text-on-surface-muted">Nilai penjualan (GMV)</p>
            <p className="mt-1 text-5xl font-semibold tabular-nums text-on-surface">{formatCompactRupiah(k.gmv)}</p>
            <p className="mt-1 text-sm text-on-surface-muted">
              {k.transactions_paid} transaksi lunas · rata-rata {formatRupiah(k.average_ticket)} per unit
            </p>
          </section>

          <dl className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatTile label="Pendapatan komisi" value={formatCompactRupiah(k.commission_revenue)} />
            <StatTile label="Dicairkan ke penjual" value={formatCompactRupiah(k.payouts_paid)} />
            <StatTile label="Dana tertahan di escrow" value={formatCompactRupiah(k.escrow_held)} hint="saat ini" />
            <StatTile label="Transaksi dibatalkan" value={String(k.transactions_cancelled)} hint={`dari ${k.transactions_created} pesanan`} />
            <StatTile label="Bonus penjual dibayar" value={formatCompactRupiah(k.bonuses_paid)} />
            <StatTile label="Manfaat promo pembeli" value={formatCompactRupiah(k.promo_benefit)} />
            <StatTile label="Kredit berjalan" value={String(k.loans_active)} hint={`sisa pokok ${formatCompactRupiah(k.loan_outstanding_principal)}`} />
            <StatTile
              label="Kredit terlambat"
              value={String(k.overdue_loans)}
              hint={`angsuran tertagih ${formatCompactRupiah(k.installments_collected)}`}
              tone={k.overdue_loans > 0 ? "danger" : undefined}
            />
          </dl>

          <div className="mt-4 grid gap-4 lg:grid-cols-2">
            <ColumnChart
              title="Nilai penjualan per bulan"
              subtitle="Total harga kendaraan dari transaksi lunas"
              data={report.monthly.map((m) => ({ label: monthLabel(m.month).split(" ")[0], hint: monthLabel(m.month), value: m.gmv }))}
              formatValue={formatCompactRupiah}
            />
            <ColumnChart
              title="Transaksi lunas per bulan"
              subtitle="Jumlah unit yang dibayar"
              data={report.monthly.map((m) => ({ label: monthLabel(m.month).split(" ")[0], hint: monthLabel(m.month), value: m.transactions }))}
              formatValue={(v) => v.toLocaleString("id-ID")}
            />
            <ColumnChart
              title="Pendapatan komisi per bulan"
              subtitle="Biaya layanan escrow dari pencairan yang berhasil"
              data={report.monthly.map((m) => ({ label: monthLabel(m.month).split(" ")[0], hint: monthLabel(m.month), value: m.commission }))}
              formatValue={formatCompactRupiah}
            />
            <BarList
              title="Merek terlaris"
              subtitle="Berdasarkan nilai penjualan"
              data={report.top_brands.map((b) => ({ label: b.brand, value: b.gmv, hint: `${b.count} unit` }))}
              formatValue={formatCompactRupiah}
            />
            <BarList
              title="Skema pembayaran"
              subtitle="Jumlah transaksi lunas per skema"
              data={report.payment_schemes.map((s) => ({ label: SCHEME_LABEL[s.key] ?? s.key, value: s.count, hint: formatCompactRupiah(s.gmv) }))}
              formatValue={(v) => `${v} transaksi`}
            />
            <BarList
              title="Status escrow"
              subtitle="Posisi transaksi lunas pada rentang ini"
              data={report.escrow_status.map((s) => ({ label: ESCROW_LABEL[s.key] ?? s.key, value: s.count }))}
              formatValue={(v) => `${v}`}
            />
          </div>

          <div className="mt-4 grid gap-4 lg:grid-cols-3">
            <BarList
              title="Verifikasi KYC penjual"
              data={report.verification.seller_kyc.map((s) => ({ label: STATUS_LABEL[s.key] ?? s.key, value: s.count }))}
              formatValue={(v) => `${v}`}
            />
            <BarList
              title="Verifikasi KYC pembeli"
              data={report.verification.buyer_kyc.map((s) => ({ label: STATUS_LABEL[s.key] ?? s.key, value: s.count }))}
              formatValue={(v) => `${v}`}
            />
            <BarList
              title="Status listing kendaraan"
              data={report.verification.vehicles.map((s) => ({ label: STATUS_LABEL[s.key] ?? s.key, value: s.count }))}
              formatValue={(v) => `${v}`}
            />
          </div>

          {tables && active && (
            <section className="mt-6 rounded-lg border border-border bg-surface-container p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-2 print:hidden" role="tablist">
                  {(Object.keys(tables) as TabKey[]).map((key) => (
                    <button
                      key={key}
                      role="tab"
                      aria-selected={tab === key}
                      onClick={() => setTab(key)}
                      className={`rounded-full px-3 py-1 text-xs font-medium ${
                        tab === key ? "bg-primary-container text-on-primary" : "bg-surface-container-high text-on-surface-muted hover:bg-border"
                      }`}
                    >
                      {tables[key].label} ({tables[key].rows.length})
                    </button>
                  ))}
                </div>
                <button
                  onClick={() => downloadCsv(`laporan-${tab}-${report.range.from}_${report.range.to}.csv`, active.columns as Column<unknown>[], active.rows as unknown[])}
                  className="rounded-md border border-border px-3 py-1 text-xs text-on-surface hover:bg-surface-container-high print:hidden"
                >
                  Ekspor CSV
                </button>
              </div>
              <h2 className="mt-3 hidden text-sm font-semibold text-on-surface print:block">{active.label}</h2>
              <ReportTable columns={active.columns as Column<unknown>[]} rows={active.rows as unknown[]} />
            </section>
          )}
        </div>
      )}
    </div>
  );
}

function ReportTable({ columns, rows }: { columns: Column<unknown>[]; rows: unknown[] }) {
  if (rows.length === 0) {
    return <p className="mt-3 text-sm text-on-surface-muted">Belum ada data pada rentang ini.</p>;
  }

  return (
    <div className="mt-3 overflow-x-auto">
      <table className="w-full min-w-[820px] text-sm">
        <thead>
          <tr className="border-b border-border text-[11px] uppercase tracking-wider text-on-surface-muted">
            {columns.map((column) => (
              <th key={column.header} className={`py-2 pr-3 font-medium ${column.numeric ? "text-right" : "text-left"}`}>
                {column.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          {rows.map((row, index) => (
            <tr key={index}>
              {columns.map((column) => (
                <td key={column.header} className={`py-2 pr-3 ${column.numeric ? "text-right tabular-nums text-on-surface" : "text-on-surface-muted"}`}>
                  {column.format ? column.format(row) : String(column.value(row) ?? "—")}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function formatDay(value: string): string {
  return new Intl.DateTimeFormat("id-ID", { dateStyle: "medium" }).format(new Date(value));
}
