"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { ApiError, apiFetch } from "@/lib/api";
import { formatDate, formatRupiah } from "@/lib/format";
import type { VehicleLoan } from "@/lib/types";
import Badge from "@/components/Badge";
import ConfirmModal from "@/components/ConfirmModal";
import { INSTALLMENT_STATUS, LOAN_STATUS_TONE, formatDay, formatRate } from "@/components/CreditPanel";

type LoanStats = {
  total: number;
  submitted: number;
  active: number;
  paid_off: number;
  overdue_loans: number;
  outstanding_principal: number;
  late_fees_due: number;
  collected: number;
};

const FILTERS: { value: string; label: string }[] = [
  { value: "", label: "Semua" },
  { value: "submitted", label: "Menunggu Persetujuan" },
  { value: "active", label: "Berjalan" },
  { value: "overdue", label: "Terlambat" },
  { value: "paid_off", label: "Lunas" },
];

type PendingAction =
  | { type: "approve" | "advance-clock"; loan: VehicleLoan }
  | { type: "record-payment"; loan: VehicleLoan; installmentId: number; sequence: number; amountDue: number };

/**
 * Pemantauan & pengelolaan cicilan kredit leasing (revisi dosen 16 Okt).
 */
export default function AdminLoansPage() {
  const [loans, setLoans] = useState<VehicleLoan[]>([]);
  const [stats, setStats] = useState<LoanStats | null>(null);
  const [filter, setFilter] = useState("");
  const [isLoading, setIsLoading] = useState(true);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  async function loadLoans() {
    setIsLoading(true);
    const query = filter ? `?filter=${filter}` : "";
    const response = await apiFetch<{ data: VehicleLoan[]; stats: LoanStats }>(`/api/admin/loans${query}`);
    setLoans(response.data);
    setStats(response.stats);
    setIsLoading(false);
  }

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- refetch when filter changes
    loadLoans();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filter]);

  async function runAction(action: PendingAction, values: Record<string, string>) {
    setActionError(null);
    setIsSubmitting(true);
    try {
      if (action.type === "record-payment") {
        await apiFetch(`/api/admin/installments/${action.installmentId}/record-payment`, {
          method: "POST",
          body: { channel: values.channel, note: values.note || undefined },
        });
      } else {
        await apiFetch(`/api/admin/loans/${action.loan.id}/${action.type}`, {
          method: "POST",
          body: values.note ? { note: values.note } : undefined,
        });
      }
      setPendingAction(null);
      await loadLoans();
    } catch (err) {
      if (err instanceof ApiError) setActionError(err.message);
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div>
      <h1 className="text-xl font-semibold text-on-surface">Kredit &amp; Cicilan</h1>
      <p className="mt-1 text-sm text-on-surface-muted">
        Pantau semua kredit leasing, setujui pengajuan, catat setoran manual, dan majukan jam simulasi untuk demo jatuh tempo.
      </p>

      {stats && (
        <dl className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
          <StatCard label="Kredit berjalan" value={String(stats.active)} hint={`${stats.paid_off} lunas · ${stats.total} total`} />
          <StatCard label="Menunggu persetujuan" value={String(stats.submitted)} />
          <StatCard label="Kredit terlambat" value={String(stats.overdue_loans)} tone={stats.overdue_loans > 0 ? "danger" : undefined} />
          <StatCard label="Denda tertunggak" value={formatRupiah(stats.late_fees_due)} tone={stats.late_fees_due > 0 ? "danger" : undefined} />
          <StatCard label="Sisa pokok beredar" value={formatRupiah(stats.outstanding_principal)} wide />
          <StatCard label="Angsuran tertagih" value={formatRupiah(stats.collected)} wide />
        </dl>
      )}

      <div className="mt-5 flex flex-wrap gap-2">
        {FILTERS.map((item) => (
          <button
            key={item.value}
            onClick={() => setFilter(item.value)}
            className={`rounded-full px-3 py-1 text-xs font-medium ${
              filter === item.value ? "bg-primary-container text-on-primary" : "bg-surface-container-high text-on-surface-muted hover:bg-border"
            }`}
          >
            {item.label}
          </button>
        ))}
      </div>

      {actionError && <p className="mt-3 text-sm text-error">{actionError}</p>}

      {isLoading ? (
        <p className="mt-6 text-sm text-on-surface-muted">Memuat...</p>
      ) : loans.length === 0 ? (
        <p className="mt-6 text-sm text-on-surface-muted">Belum ada kredit pada kategori ini.</p>
      ) : (
        <div className="mt-6 space-y-4">
          {loans.map((loan) => {
            const summary = loan.summary;
            const installments = loan.installments ?? [];
            const next = installments.find((item) => !item.paid_at);
            const progress = summary ? Math.round((summary.paid_count / loan.tenor_months) * 100) : 0;
            const isExpanded = expandedId === loan.id;

            return (
              <div key={loan.id} className="rounded-lg border border-border bg-surface-container p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                  <div className="min-w-0">
                    <p className="font-medium text-on-surface">{loan.transaction?.vehicle ?? `Transaksi #${loan.transaction_id}`}</p>
                    <p className="mt-0.5 text-sm text-on-surface-muted">
                      {loan.transaction?.buyer} → {loan.transaction?.seller} · <span className="font-mono">{loan.contract_number}</span>
                    </p>
                    <p className="mt-0.5 text-xs text-on-surface-muted">
                      Pokok {formatRupiah(loan.principal)} · {formatRate(loan.flat_rate)} flat · {formatRupiah(loan.monthly_installment)} ×{" "}
                      {loan.tenor_months}
                    </p>
                  </div>
                  <div className="flex flex-wrap items-center gap-1.5 sm:flex-col sm:items-end">
                    <Badge tone={LOAN_STATUS_TONE[loan.status]}>{loan.status_label}</Badge>
                    {summary && summary.overdue_count > 0 && <Badge tone="danger">{summary.overdue_count} angsuran terlambat</Badge>}
                  </div>
                </div>

                {loan.status !== "submitted" && summary && (
                  <div className="mt-3">
                    <div className="flex justify-between text-xs text-on-surface-muted">
                      <span>
                        {summary.paid_count}/{loan.tenor_months} angsuran terbayar
                      </span>
                      <span>Sisa pokok {formatRupiah(summary.outstanding_principal)}</span>
                    </div>
                    <div className="mt-1 h-2 overflow-hidden rounded-full bg-surface-container-high" aria-hidden>
                      <div className="h-full rounded-full bg-emerald-500" style={{ width: `${progress}%` }} />
                    </div>
                  </div>
                )}

                {loan.status === "submitted" && (
                  <p className="mt-3 text-xs text-on-surface-muted">
                    Diajukan {formatDate(loan.submitted_at)} · disetujui otomatis {formatDate(loan.auto_approve_at)} bila tidak diproses lebih dulu.
                  </p>
                )}

                <div className="mt-3 flex flex-wrap gap-2">
                  {loan.status === "submitted" && (
                    <button
                      disabled={isSubmitting}
                      onClick={() => setPendingAction({ type: "approve", loan })}
                      className="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
                    >
                      Setujui Kredit
                    </button>
                  )}
                  {loan.status === "approved" && next && (
                    <button
                      disabled={isSubmitting}
                      onClick={() =>
                        setPendingAction({
                          type: "record-payment",
                          loan,
                          installmentId: next.id,
                          sequence: next.sequence,
                          amountDue: next.amount_due,
                        })
                      }
                      className="rounded-md bg-teal-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-teal-500 disabled:opacity-60"
                    >
                      Catat Setoran Angsuran ke-{next.sequence}
                    </button>
                  )}
                  {loan.status === "approved" && (
                    <button
                      disabled={isSubmitting}
                      onClick={() => setPendingAction({ type: "advance-clock", loan })}
                      className="rounded-md border border-border px-3 py-1.5 text-sm font-medium text-on-surface hover:bg-surface-container-high disabled:opacity-60"
                    >
                      Majukan 1 Bulan (simulasi)
                    </button>
                  )}
                  {installments.length > 0 && (
                    <button onClick={() => setExpandedId(isExpanded ? null : loan.id)} className="px-1 text-sm text-on-surface underline">
                      {isExpanded ? "Sembunyikan jadwal" : "Lihat jadwal angsuran"}
                    </button>
                  )}
                </div>

                {loan.clock_offset_months > 0 && (
                  <p className="mt-2 text-xs text-warning">
                    Jam simulasi +{loan.clock_offset_months} bulan · tanggal simulasi {formatDay(loan.simulated_today)}
                  </p>
                )}

                {isExpanded && (
                  <div className="mt-3 overflow-x-auto rounded-md bg-surface-container-high p-3">
                    <table className="w-full min-w-[680px] text-sm">
                      <thead>
                        <tr className="border-b border-border text-right text-[11px] uppercase tracking-wider text-on-surface-muted">
                          <th className="py-2 pr-3 text-left font-medium">Ke-</th>
                          <th className="py-2 pr-3 text-left font-medium">Jatuh tempo</th>
                          <th className="py-2 pr-3 font-medium">Angsuran</th>
                          <th className="py-2 pr-3 font-medium">Denda</th>
                          <th className="py-2 pr-3 text-left font-medium">Status</th>
                          <th className="py-2 text-left font-medium">Pembayaran</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-border text-right tabular-nums">
                        {installments.map((item) => (
                          <tr key={item.id}>
                            <td className="py-2 pr-3 text-left text-on-surface">{item.sequence}</td>
                            <td className="py-2 pr-3 text-left text-on-surface-muted">{formatDay(item.due_date)}</td>
                            <td className="py-2 pr-3 text-on-surface">{formatRupiah(item.amount)}</td>
                            <td className={`py-2 pr-3 ${item.late_fee > 0 ? "text-error" : "text-on-surface-muted"}`}>
                              {item.late_fee > 0 ? `${formatRupiah(item.late_fee)} (${item.days_late} hr)` : "—"}
                            </td>
                            <td className="py-2 pr-3 text-left">
                              <Badge tone={INSTALLMENT_STATUS[item.status].tone}>{INSTALLMENT_STATUS[item.status].label}</Badge>
                            </td>
                            <td className="py-2 text-left text-xs text-on-surface-muted">
                              {item.paid_at
                                ? `${formatDate(item.paid_at)} · ${item.payment_channel ?? item.payment_method ?? "-"}${item.recorded_by ? ` (dicatat ${item.recorded_by})` : ""}`
                                : "—"}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                    <Link href="/admin/transactions" className="mt-2 inline-block text-xs text-on-surface underline">
                      Buka transaksi terkait
                    </Link>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}

      {pendingAction?.type === "approve" && (
        <ConfirmModal
          title="Setujui Kredit"
          message={`Leasing akan melunasi ${formatRupiah(pendingAction.loan.vehicle_financed)} ke penjual dan jadwal ${pendingAction.loan.tenor_months} angsuran terbit untuk pembeli.`}
          fields={[{ name: "note", label: "Catatan (opsional)" }]}
          confirmLabel="Setujui"
          isSubmitting={isSubmitting}
          onConfirm={(values) => runAction(pendingAction, values)}
          onCancel={() => setPendingAction(null)}
        />
      )}

      {pendingAction?.type === "advance-clock" && (
        <ConfirmModal
          title="Majukan Jam Simulasi"
          message="Tanggal 'hari ini' untuk kredit ini dimajukan satu bulan. Angsuran yang belum dibayar bisa berubah menjadi jatuh tempo atau terlambat dan mulai dikenai denda."
          confirmLabel="Majukan 1 Bulan"
          isSubmitting={isSubmitting}
          onConfirm={(values) => runAction(pendingAction, values)}
          onCancel={() => setPendingAction(null)}
        />
      )}

      {pendingAction?.type === "record-payment" && (
        <ConfirmModal
          title={`Catat Setoran Angsuran ke-${pendingAction.sequence}`}
          message={`Tagihan saat ini ${formatRupiah(pendingAction.amountDue)} (termasuk denda bila ada). Gunakan hanya untuk setoran di luar portal bank, mis. tunai di kantor leasing.`}
          fields={[
            { name: "channel", label: "Saluran pembayaran", required: true, placeholder: "mis. Tunai — kantor leasing" },
            { name: "note", label: "Catatan (opsional)" },
          ]}
          confirmLabel="Catat Pembayaran"
          isSubmitting={isSubmitting}
          onConfirm={(values) => runAction(pendingAction, values)}
          onCancel={() => setPendingAction(null)}
        />
      )}
    </div>
  );
}

function StatCard({ label, value, hint, tone, wide }: { label: string; value: string; hint?: string; tone?: "danger"; wide?: boolean }) {
  return (
    <div className={`rounded-lg border border-border bg-surface-container p-3 ${wide ? "col-span-2" : ""}`}>
      <dt className="text-xs uppercase text-on-surface-muted">{label}</dt>
      <dd className={`mt-1 text-lg font-semibold tabular-nums ${tone === "danger" ? "text-error" : "text-on-surface"}`}>{value}</dd>
      {hint && <p className="text-[11px] text-on-surface-muted">{hint}</p>}
    </div>
  );
}
