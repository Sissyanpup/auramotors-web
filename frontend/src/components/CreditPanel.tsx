"use client";

import { useEffect } from "react";
import Link from "next/link";
import Badge, { type BadgeTone } from "@/components/Badge";
import { formatDate, formatRupiah } from "@/lib/format";
import type { InstallmentStatus, LoanStatus, VehicleLoan } from "@/lib/types";

export const LOAN_STATUS_TONE: Record<LoanStatus, BadgeTone> = {
  submitted: "warning",
  approved: "info",
  paid_off: "success",
};

export const INSTALLMENT_STATUS: Record<InstallmentStatus, { label: string; tone: BadgeTone }> = {
  paid: { label: "Lunas", tone: "success" },
  overdue: { label: "Terlambat", tone: "danger" },
  due: { label: "Jatuh tempo", tone: "warning" },
  upcoming: { label: "Belum jatuh tempo", tone: "neutral" },
};

export function formatDay(value: string): string {
  return new Intl.DateTimeFormat("id-ID", { dateStyle: "medium" }).format(new Date(value));
}

export function formatRate(value: string | number): string {
  return `${(Number(value) * 100).toLocaleString("id-ID", { maximumFractionDigits: 2 })}%`;
}

type CreditPanelProps = {
  loan: VehicleLoan;
  perspective: "buyer" | "seller" | "admin";
  /** gateway_reference transaksi — dipakai untuk membuka portal bank mode angsuran. */
  paymentReference?: string | null;
  onRefresh?: () => void;
};

/**
 * Kredit leasing di halaman transaksi. Pembeli melihat jadwal & membayar
 * angsuran lewat portal bank; penjual cukup melihat bahwa sisa harga sudah
 * dilunasi leasing; admin mengelola cicilan di /admin/loans.
 */
export default function CreditPanel({ loan, perspective, paymentReference, onRefresh }: CreditPanelProps) {
  const isSubmitted = loan.status === "submitted";

  // Muat ulang otomatis begitu jeda analisis leasing selesai, supaya
  // persetujuan terasa "masuk" tanpa pengguna menekan apa pun.
  useEffect(() => {
    if (!isSubmitted || !onRefresh) return;
    const waitMs = Math.max(0, new Date(loan.auto_approve_at).getTime() - Date.now()) + 1500;
    const timer = setTimeout(onRefresh, waitMs);
    return () => clearTimeout(timer);
  }, [isSubmitted, loan.auto_approve_at, onRefresh]);

  const installments = loan.installments ?? [];
  const nextPayable = installments.find((item) => !item.paid_at);
  const summary = loan.summary;

  return (
    <section className="rounded-lg border border-border bg-surface-container p-5">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h2 className="font-display text-lg text-on-surface">Kredit Leasing</h2>
          <p className="text-xs text-on-surface-muted">
            {loan.lender_name} · No. kontrak <span className="font-mono">{loan.contract_number}</span>
          </p>
        </div>
        <Badge tone={LOAN_STATUS_TONE[loan.status]}>{loan.status_label}</Badge>
      </div>

      {isSubmitted && (
        <div className="mt-4 rounded-md border border-warning/40 bg-warning/5 p-3 text-xs text-on-surface-muted">
          <p className="font-semibold text-warning">Pengajuan sedang dianalisis leasing</p>
          <p className="mt-1">
            Perkiraan keputusan {formatDate(loan.auto_approve_at)}. Setelah disetujui, leasing melunasi sisa harga ke penjual
            dan jadwal angsuran terbit. Kendaraan belum bisa diserahkan sebelum kredit disetujui.
          </p>
          {onRefresh && (
            <button type="button" onClick={onRefresh} className="mt-2 font-medium text-primary underline">
              Perbarui status
            </button>
          )}
        </div>
      )}

      <dl className="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
        <Stat label="Pokok pinjaman" value={formatRupiah(loan.principal)} />
        <Stat label="Bunga" value={`${formatRate(loan.flat_rate)} flat`} hint={`efektif ±${formatRate(loan.effective_rate)}/thn`} />
        <Stat label="Angsuran" value={formatRupiah(loan.monthly_installment)} hint={`× ${loan.tenor_months} bulan`} />
        <Stat label="Total ke leasing" value={formatRupiah(loan.total_payable)} />
        {summary && !isSubmitted && (
          <>
            <Stat label="Terbayar" value={`${summary.paid_count} / ${loan.tenor_months}`} />
            <Stat label="Sisa pokok" value={formatRupiah(summary.outstanding_principal)} />
            <Stat label="Jatuh tempo berikut" value={summary.next_due_date ? formatDay(summary.next_due_date) : "—"} />
            <Stat
              label="Denda tertunggak"
              value={formatRupiah(summary.late_fees_due)}
              tone={summary.late_fees_due > 0 ? "danger" : undefined}
            />
          </>
        )}
      </dl>

      {loan.lender_disbursed_at && (
        <p className="mt-3 text-xs text-on-surface-muted">
          {perspective === "seller" ? "Leasing telah melunasi " : "Leasing melunasi "}
          <strong className="text-on-surface">{formatRupiah(loan.vehicle_financed)}</strong> ke penjual pada{" "}
          {formatDate(loan.lender_disbursed_at)}.
          {perspective === "seller" && " Cicilan menjadi urusan pembeli dengan leasing — Anda tidak menanggung risiko kredit."}
        </p>
      )}

      {loan.clock_offset_months > 0 && (
        <p className="mt-2 text-xs text-warning">
          Jam simulasi dimajukan {loan.clock_offset_months} bulan · tanggal simulasi {formatDay(loan.simulated_today)}.
        </p>
      )}

      {perspective !== "seller" && installments.length > 0 && (
        <div className="mt-4 overflow-x-auto">
          <table className="w-full min-w-[640px] text-sm">
            <thead>
              <tr className="border-b border-primary/40 text-right text-[11px] uppercase tracking-wider text-on-surface-muted">
                <th className="py-2 pr-3 text-left font-medium">Ke-</th>
                <th className="py-2 pr-3 text-left font-medium">Jatuh tempo</th>
                <th className="py-2 pr-3 font-medium">Pokok</th>
                <th className="py-2 pr-3 font-medium">Bunga</th>
                <th className="py-2 pr-3 font-medium">Denda</th>
                <th className="py-2 pr-3 font-medium">Tagihan</th>
                <th className="py-2 text-left font-medium">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border text-right tabular-nums">
              {installments.map((item) => {
                const status = INSTALLMENT_STATUS[item.status];
                const canPay = perspective === "buyer" && loan.status === "approved" && item.id === nextPayable?.id && paymentReference;
                return (
                  <tr key={item.id}>
                    <td className="py-2 pr-3 text-left text-on-surface">{item.sequence}</td>
                    <td className="py-2 pr-3 text-left text-on-surface-muted">{formatDay(item.due_date)}</td>
                    <td className="py-2 pr-3 text-on-surface-muted">{formatRupiah(item.principal_amount)}</td>
                    <td className="py-2 pr-3 text-on-surface-muted">{formatRupiah(item.interest_amount)}</td>
                    <td className={`py-2 pr-3 ${item.late_fee > 0 ? "text-error" : "text-on-surface-muted"}`}>
                      {item.late_fee > 0 ? formatRupiah(item.late_fee) : "—"}
                    </td>
                    <td className="py-2 pr-3 text-on-surface">
                      {formatRupiah(item.paid_at ? item.paid_amount ?? item.amount : item.amount_due)}
                    </td>
                    <td className="py-2 text-left">
                      <div className="flex flex-wrap items-center gap-2">
                        <Badge tone={status.tone}>{status.label}</Badge>
                        {canPay && (
                          <Link
                            href={`/bank-portal/${paymentReference}?installment=${item.id}`}
                            className="btn-gold rounded-md px-2.5 py-1 text-xs font-medium"
                          >
                            Bayar
                          </Link>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
          <p className="mt-2 text-xs text-on-surface-muted">
            Angsuran dibayar berurutan ke leasing. Keterlambatan dikenai denda 0,2% per hari dari nilai angsuran. BPKB ditahan
            leasing sampai kredit lunas.
          </p>
        </div>
      )}

      {perspective === "admin" && (
        <Link href="/admin/loans" className="mt-3 inline-block text-sm text-on-surface underline">
          Kelola di halaman Kredit &amp; Cicilan
        </Link>
      )}
    </section>
  );
}

function Stat({ label, value, hint, tone }: { label: string; value: string; hint?: string; tone?: "danger" }) {
  return (
    <div>
      <dt className="text-xs uppercase text-on-surface-muted">{label}</dt>
      <dd className={`tabular-nums ${tone === "danger" ? "text-error" : "text-on-surface"}`}>{value}</dd>
      {hint && <p className="text-[11px] text-on-surface-muted">{hint}</p>}
    </div>
  );
}
