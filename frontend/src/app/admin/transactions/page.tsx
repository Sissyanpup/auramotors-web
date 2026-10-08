"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { ApiError, apiFetch } from "@/lib/api";
import { formatDate, formatRupiah } from "@/lib/format";
import type { Paginated, Transaction } from "@/lib/types";
import PaymentStatusBadge from "@/components/PaymentStatusBadge";
import EscrowStatusBadge from "@/components/EscrowStatusBadge";
import PayoutStatusBadge from "@/components/PayoutStatusBadge";
import TransactionStatusHistory from "@/components/TransactionStatusHistory";
import VdrPanel from "@/components/VdrPanel";
import ShipmentPanel from "@/components/ShipmentPanel";
import ConfirmModal from "@/components/ConfirmModal";
import DangerConfirmModal from "@/components/DangerConfirmModal";

const FILTERS: { value: string; label: string }[] = [
  { value: "", label: "Semua" },
  { value: "escrow_hold", label: "Dana Ditahan" },
  { value: "serah_terima", label: "Serah Terima" },
  { value: "payout_release", label: "Payout Disetujui" },
  { value: "selesai", label: "Selesai" },
  { value: "dispute", label: "Sengketa" },
  { value: "refunded", label: "Dikembalikan" },
];

type PendingAction =
  | { type: "approve-handover" | "approve-payout" | "mark-completed"; id: number }
  | { type: "disburse"; id: number }
  | { type: "resolve-dispute"; id: number; resolution: "refund" | "resume" };

export default function AdminTransactionsPage() {
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [filter, setFilter] = useState("");
  const [actionError, setActionError] = useState<string | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<Transaction | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);

  async function loadTransactions() {
    setIsLoading(true);
    const query = filter ? `?escrow_status=${filter}` : "";
    const { data } = await apiFetch<Paginated<Transaction>>(`/api/admin/transactions${query}`);
    setTransactions(data);
    setIsLoading(false);
  }

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- refetch when filter changes
    loadTransactions();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filter]);

  async function toggleDetail(id: number) {
    if (expandedId === id) {
      setExpandedId(null);
      setDetail(null);
      return;
    }

    setExpandedId(id);
    setDetail(null);
    const { data } = await apiFetch<{ data: Transaction }>(`/api/admin/transactions/${id}`);
    setDetail(data);
  }

  async function refreshDetail(id: number) {
    const { data } = await apiFetch<{ data: Transaction }>(`/api/admin/transactions/${id}`);
    setDetail(data);
    await loadTransactions();
  }

  async function runAction(action: PendingAction, values: Record<string, string>) {
    setActionError(null);
    setIsSubmitting(true);
    try {
      if (action.type === "resolve-dispute") {
        await apiFetch(`/api/admin/transactions/${action.id}/resolve-dispute`, {
          method: "POST",
          body: { resolution: action.resolution, note: values.note || undefined },
        });
      } else if (action.type === "disburse") {
        await apiFetch(`/api/admin/transactions/${action.id}/disburse`, {
          method: "POST",
          body: { reference: values.reference || undefined, note: values.note || undefined },
        });
      } else {
        await apiFetch(`/api/admin/transactions/${action.id}/${action.type}`, {
          method: "POST",
          body: values.note ? { note: values.note } : undefined,
        });
      }
      await refreshDetail(action.id);
      setPendingAction(null);
    } catch (err) {
      if (err instanceof ApiError) setActionError(err.message);
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div>
      <div className="flex flex-wrap items-start justify-between gap-2">
        <h1 className="text-xl font-semibold text-on-surface">Dashboard Escrow & Transaksi</h1>
        <Link href="/admin/payouts" className="text-sm text-on-surface-muted underline hover:text-on-surface">
          Rekonsiliasi Payout
        </Link>
      </div>

      <div className="mt-4 flex flex-wrap gap-2">
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
      ) : transactions.length === 0 ? (
        <p className="mt-6 text-sm text-on-surface-muted">Tidak ada transaksi pada kategori ini.</p>
      ) : (
        <div className="mt-6 space-y-4">
          {transactions.map((transaction) => (
            <div key={transaction.id} className="rounded-lg border border-border bg-surface-container p-4">
              <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                  <p className="font-medium text-on-surface">
                    {transaction.vehicle.brand} {transaction.vehicle.model} {transaction.vehicle.year}
                  </p>
                  <p className="mt-0.5 text-sm text-on-surface-muted">
                    {formatRupiah(transaction.amount)} &middot; {transaction.buyer?.name} → {transaction.seller?.name}
                  </p>
                </div>
                <div className="flex flex-wrap items-center gap-1.5 sm:flex-col sm:items-end">
                  <PaymentStatusBadge status={transaction.payment_status} />
                  {transaction.escrow_status && <EscrowStatusBadge status={transaction.escrow_status} />}
                  {transaction.payout_status && <PayoutStatusBadge status={transaction.payout_status} />}
                </div>
              </div>

              <button onClick={() => toggleDetail(transaction.id)} className="mt-3 text-sm text-on-surface underline">
                {expandedId === transaction.id ? "Sembunyikan detail" : "Lihat detail & aksi"}
              </button>

              {expandedId === transaction.id && (
                <div className="mt-3 rounded-md bg-surface-container-high p-3 text-sm">
                  {!detail ? (
                    <p className="text-on-surface-muted">Memuat detail...</p>
                  ) : (
                    <div className="flex flex-col gap-4">
                      <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                          <dt className="text-xs uppercase text-on-surface-muted">Buyer Konfirmasi Terima</dt>
                          <dd className="text-on-surface">
                            {detail.buyer_confirmed_at ? formatDate(detail.buyer_confirmed_at) : "Belum"}
                          </dd>
                        </div>
                        <div>
                          <dt className="text-xs uppercase text-on-surface-muted">Seller Konfirmasi Serahkan</dt>
                          <dd className="text-on-surface">
                            {detail.seller_confirmed_at ? formatDate(detail.seller_confirmed_at) : "Belum"}
                          </dd>
                        </div>
                        {detail.seller_bank_account && (
                          <div className="col-span-2">
                            <dt className="text-xs uppercase text-on-surface-muted">Rekening Bank Penjual</dt>
                            <dd className="text-on-surface">
                              {detail.seller_bank_account.bank_name} &middot;{" "}
                              {detail.seller_bank_account.bank_account_number} a.n.{" "}
                              {detail.seller_bank_account.bank_account_holder_name}
                            </dd>
                          </div>
                        )}
                        {detail.dispute_reason && (
                          <div className="col-span-2">
                            <dt className="text-xs uppercase text-on-surface-muted">Alasan Sengketa</dt>
                            <dd className="text-red-700">{detail.dispute_reason}</dd>
                          </div>
                        )}
                      </dl>

                      <div className="flex flex-wrap gap-2">
                        {detail.escrow_status === "escrow_hold" && (
                          <button
                            disabled={isSubmitting}
                            onClick={() => setPendingAction({ type: "approve-handover", id: detail.id })}
                            className="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
                          >
                            Setujui Serah Terima
                          </button>
                        )}
                        {detail.escrow_status === "serah_terima" && (
                          <button
                            disabled={isSubmitting}
                            onClick={() => setPendingAction({ type: "approve-payout", id: detail.id })}
                            className="rounded-md bg-purple-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-purple-500 disabled:opacity-60"
                          >
                            Setujui Payout Release
                          </button>
                        )}
                        {detail.escrow_status === "payout_release" && detail.payout_status !== "paid" && (
                          <button
                            disabled={isSubmitting}
                            onClick={() => setPendingAction({ type: "disburse", id: detail.id })}
                            className="rounded-md bg-teal-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-teal-500 disabled:opacity-60"
                          >
                            Cairkan Dana ke Penjual
                          </button>
                        )}
                        {detail.escrow_status === "payout_release" && detail.payout_status === "paid" && (
                          <button
                            disabled={isSubmitting}
                            onClick={() => setPendingAction({ type: "mark-completed", id: detail.id })}
                            className="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-60"
                          >
                            Tandai Selesai
                          </button>
                        )}
                        {detail.escrow_status === "dispute" && (
                          <>
                            <button
                              disabled={isSubmitting}
                              onClick={() => setPendingAction({ type: "resolve-dispute", id: detail.id, resolution: "resume" })}
                              className="rounded-md bg-surface-container-high px-3 py-1.5 text-sm font-medium text-on-surface hover:bg-border disabled:opacity-60"
                            >
                              Lanjutkan Transaksi
                            </button>
                            <button
                              disabled={isSubmitting}
                              onClick={() => setPendingAction({ type: "resolve-dispute", id: detail.id, resolution: "refund" })}
                              className="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-500 disabled:opacity-60"
                            >
                              Kembalikan Dana (Refund)
                            </button>
                          </>
                        )}
                      </div>

                      {detail.payouts && detail.payouts.length > 0 && (
                        <div>
                          <p className="mb-2 text-xs font-semibold uppercase text-on-surface-muted">Riwayat Payout</p>
                          <ul className="space-y-1 text-sm text-on-surface">
                            {detail.payouts.map((payout) => (
                              <li key={payout.id}>
                                {formatDate(payout.created_at)} &middot; {payout.method} &middot;{" "}
                                <PayoutStatusBadge status={payout.status} /> &middot; komisi{" "}
                                {formatRupiah(payout.commission_amount)} &middot; ke penjual{" "}
                                {formatRupiah(payout.payout_amount)}
                                {payout.reference ? ` (ref: ${payout.reference})` : ""}
                              </li>
                            ))}
                          </ul>
                        </div>
                      )}

                      <VdrPanel documents={detail.documents} />

                      {detail.shipment && (
                        <ShipmentPanel
                          transactionId={detail.id}
                          shipment={detail.shipment}
                          userRole="admin"
                          onUpdate={() => refreshDetail(detail.id)}
                        />
                      )}

                      {detail.status_history && (
                        <div>
                          <p className="mb-2 text-xs font-semibold uppercase text-on-surface-muted">Riwayat Status</p>
                          <TransactionStatusHistory entries={detail.status_history} />
                        </div>
                      )}
                    </div>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {pendingAction?.type === "approve-handover" && (
        <ConfirmModal
          title="Setujui Serah Terima"
          message="Setujui transisi ke status serah-terima?"
          fields={[{ name: "note", label: "Catatan (opsional)" }]}
          isSubmitting={isSubmitting}
          onConfirm={(values) => runAction(pendingAction, values)}
          onCancel={() => setPendingAction(null)}
        />
      )}

      {pendingAction?.type === "approve-payout" && (
        <ConfirmModal
          title="Setujui Payout Release"
          message="Setujui pelepasan dana ke penjual? Dana belum ditransfer sampai kamu mencairkannya di langkah berikutnya."
          fields={[{ name: "note", label: "Catatan (opsional)" }]}
          isSubmitting={isSubmitting}
          onConfirm={(values) => runAction(pendingAction, values)}
          onCancel={() => setPendingAction(null)}
        />
      )}

      {pendingAction?.type === "disburse" && (
        <DangerConfirmModal
          title="Cairkan Dana ke Penjual"
          intro="Aksi ini melepas dana escrow ke rekening penjual. Pastikan kamu sudah menyelesaikan transfer bank secara manual sebelum melanjutkan."
          consequences={[
            "Status payout berubah menjadi paid dan tidak bisa dibatalkan lewat sistem.",
            "Nomor referensi yang kamu masukkan tercatat permanen di audit trail.",
            "Refund harus dilakukan manual di luar sistem jika terjadi kesalahan.",
          ]}
          fields={[
            { name: "reference", label: "Nomor Referensi Transfer", required: true, placeholder: "mis. TRF-20260917-001" },
            { name: "note", label: "Catatan (opsional)", type: "textarea" },
          ]}
          confirmWord="CAIRKAN"
          confirmLabel="Cairkan Dana Sekarang"
          isSubmitting={isSubmitting}
          onConfirm={(values) => runAction(pendingAction, values)}
          onCancel={() => setPendingAction(null)}
        />
      )}

      {pendingAction?.type === "mark-completed" && (
        <ConfirmModal
          title="Tandai Selesai"
          message="Tandai transaksi ini selesai?"
          fields={[{ name: "note", label: "Catatan (opsional)" }]}
          isSubmitting={isSubmitting}
          onConfirm={(values) => runAction(pendingAction, values)}
          onCancel={() => setPendingAction(null)}
        />
      )}

      {pendingAction?.type === "resolve-dispute" && pendingAction.resolution === "refund" && (
        <DangerConfirmModal
          title="Kembalikan Dana (Refund) ke Buyer"
          intro="Sengketa akan diselesaikan dengan mengembalikan dana escrow ke buyer dan membuka kembali listing kendaraan."
          consequences={[
            "Dana escrow di-refund ke buyer dan tidak dapat ditarik kembali.",
            "Listing kendaraan kembali aktif dan bisa dibeli oleh buyer lain.",
            "Catatan penyelesaian tercatat di riwayat transaksi & tidak bisa diubah.",
          ]}
          fields={[{ name: "note", label: "Catatan penyelesaian sengketa", required: true, type: "textarea" }]}
          confirmWord="REFUND"
          confirmLabel="Ya, Kembalikan Dana"
          isSubmitting={isSubmitting}
          onConfirm={(values) => runAction(pendingAction, values)}
          onCancel={() => setPendingAction(null)}
        />
      )}

      {pendingAction?.type === "resolve-dispute" && pendingAction.resolution === "resume" && (
        <ConfirmModal
          title="Lanjutkan Transaksi"
          message="Lanjutkan transaksi ke status sebelum sengketa?"
          fields={[{ name: "note", label: "Catatan penyelesaian (opsional)" }]}
          isSubmitting={isSubmitting}
          onConfirm={(values) => runAction(pendingAction, values)}
          onCancel={() => setPendingAction(null)}
        />
      )}
    </div>
  );
}
