"use client";

import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ApiError, apiFetch } from "@/lib/api";
import { formatDate, formatRupiah } from "@/lib/format";
import type { Transaction } from "@/lib/types";
import PaymentStatusBadge from "@/components/PaymentStatusBadge";
import EscrowStatusBadge from "@/components/EscrowStatusBadge";
import TransactionStatusHistory from "@/components/TransactionStatusHistory";
import VdrPanel from "@/components/VdrPanel";
import ShipmentPanel from "@/components/ShipmentPanel";
import ShipmentInitForm from "@/components/ShipmentInitForm";
import SignaturePad from "@/components/SignaturePad";
import DangerConfirmModal from "@/components/DangerConfirmModal";

export default function SellerTransactionDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  const [transaction, setTransaction] = useState<Transaction | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [disputeReason, setDisputeReason] = useState("");
  const [showDisputeForm, setShowDisputeForm] = useState(false);
  const [showConfirmHandover, setShowConfirmHandover] = useState(false);
  const [showSignaturePad, setShowSignaturePad] = useState(false);
  const [isSigning, setIsSigning] = useState(false);
  const [signatureError, setSignatureError] = useState<string | null>(null);

  async function handleSubmitSignature(dataUrl: string) {
    setIsSigning(true);
    setSignatureError(null);
    try {
      const { data } = await apiFetch<{ data: Transaction }>(
        `/api/transactions/${id}/sign`,
        { method: "POST", body: { signature: dataUrl } }
      );
      setTransaction(data);
      setShowSignaturePad(false);
    } catch (err) {
      if (err instanceof ApiError) setSignatureError(err.message);
    } finally {
      setIsSigning(false);
    }
  }

  async function loadTransaction() {
    try {
      const { data } = await apiFetch<{ data: Transaction }>(`/api/seller/transactions/${id}`);
      setTransaction(data);
    } catch {
      setError("Transaksi tidak ditemukan.");
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- initial data fetch on mount
    loadTransaction();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  async function handleConfirmHandover() {
    setIsSubmitting(true);
    setError(null);
    try {
      const { data } = await apiFetch<{ data: Transaction }>(
        `/api/seller/transactions/${id}/confirm-handover`,
        { method: "POST" }
      );
      setTransaction(data);
      setShowConfirmHandover(false);
    } catch (err) {
      if (err instanceof ApiError) setError(err.message);
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleSubmitDispute(e: React.FormEvent) {
    e.preventDefault();
    setIsSubmitting(true);
    setError(null);
    try {
      const { data } = await apiFetch<{ data: Transaction }>(`/api/seller/transactions/${id}/dispute`, {
        method: "POST",
        body: { reason: disputeReason },
      });
      setTransaction(data);
      setShowDisputeForm(false);
      setDisputeReason("");
    } catch (err) {
      if (err instanceof ApiError) setError(err.message);
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoading) {
    return <p className="text-sm text-on-surface-muted">Memuat...</p>;
  }

  if (error && !transaction) {
    return <p className="text-sm text-error">{error}</p>;
  }

  if (!transaction) {
    return <p className="text-sm text-error">Transaksi tidak ditemukan.</p>;
  }

  const canConfirmHandover = transaction.escrow_status === "escrow_hold" && !transaction.seller_confirmed_at;
  const canDispute = transaction.escrow_status === "escrow_hold" || transaction.escrow_status === "serah_terima";

  return (
    <div>
      <Link href="/seller/transactions" className="text-sm text-on-surface-muted hover:text-on-surface">
        &larr; Kembali ke Transaksi Penjualan
      </Link>

      <div className="mt-4 rounded-lg border border-border bg-surface-container p-5">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
          <h1 className="text-lg font-semibold text-on-surface">
            {transaction.vehicle.brand} {transaction.vehicle.model} {transaction.vehicle.year}
          </h1>
          <div className="flex flex-wrap items-center gap-1.5 sm:flex-col sm:items-end">
            <PaymentStatusBadge status={transaction.payment_status} />
            {transaction.escrow_status && <EscrowStatusBadge status={transaction.escrow_status} />}
          </div>
        </div>

        <dl className="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-xs uppercase text-on-surface-muted">Jumlah DP</dt>
            <dd className="text-on-surface">{formatRupiah(transaction.amount)}</dd>
          </div>
          <div>
            <dt className="text-xs uppercase text-on-surface-muted">Dibuat</dt>
            <dd className="text-on-surface">{formatDate(transaction.created_at)}</dd>
          </div>
          {transaction.paid_at && (
            <div>
              <dt className="text-xs uppercase text-on-surface-muted">Dibayar Buyer</dt>
              <dd className="text-on-surface">{formatDate(transaction.paid_at)}</dd>
            </div>
          )}
          {transaction.buyer_confirmed_at && (
            <div>
              <dt className="text-xs uppercase text-on-surface-muted">Buyer Konfirmasi Terima</dt>
              <dd className="text-on-surface">{formatDate(transaction.buyer_confirmed_at)}</dd>
            </div>
          )}
          {transaction.seller_confirmed_at && (
            <div>
              <dt className="text-xs uppercase text-on-surface-muted">Anda Konfirmasi Serahkan</dt>
              <dd className="text-on-surface">{formatDate(transaction.seller_confirmed_at)}</dd>
            </div>
          )}
        </dl>

        {error && <p className="mt-3 text-sm text-error">{error}</p>}

        {(canConfirmHandover || canDispute) && (
          <div className="mt-6 flex flex-wrap gap-3 border-t border-border pt-4">
            {canConfirmHandover && (
              <button
                onClick={() => setShowConfirmHandover(true)}
                disabled={isSubmitting}
                className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-60"
              >
                Konfirmasi Sudah Menyerahkan Kendaraan
              </button>
            )}
            {canDispute && !showDisputeForm && (
              <button
                onClick={() => setShowDisputeForm(true)}
                className="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
              >
                Laporkan Ketidaksesuaian
              </button>
            )}
          </div>
        )}

        {showDisputeForm && (
          <form onSubmit={handleSubmitDispute} className="mt-4 rounded-md border border-red-200 bg-red-50 p-4">
            <label className="block text-sm font-medium text-on-surface">Jelaskan ketidaksesuaian</label>
            <textarea
              required
              maxLength={1000}
              value={disputeReason}
              onChange={(e) => setDisputeReason(e.target.value)}
              rows={3}
              className="mt-2 w-full rounded-md border border-border p-2 text-sm bg-surface text-on-surface placeholder:text-on-surface-muted focus:border-primary focus:outline-none"
              placeholder="Contoh: buyer belum melunasi sisa pembayaran di luar sistem."
            />
            <div className="mt-3 flex gap-3">
              <button
                type="submit"
                disabled={isSubmitting}
                className="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500 disabled:opacity-60"
              >
                Kirim Laporan
              </button>
              <button
                type="button"
                onClick={() => setShowDisputeForm(false)}
                className="rounded-md border border-border px-4 py-2 text-sm font-medium text-on-surface hover:bg-surface-container-high"
              >
                Batal
              </button>
            </div>
          </form>
        )}

        {transaction.escrow_status === "dispute" && transaction.dispute_reason && (
          <div className="mt-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p className="font-medium">Sengketa sedang ditinjau admin</p>
            <p className="mt-1">{transaction.dispute_reason}</p>
          </div>
        )}
      </div>

      <div className="mt-6">
        <VdrPanel documents={transaction.documents} />
      </div>

      <div className="mt-6">
        {transaction.shipment ? (
          <ShipmentPanel
            transactionId={transaction.id}
            shipment={transaction.shipment}
            userRole="seller"
            onUpdate={loadTransaction}
          />
        ) : (
          <ShipmentInitForm transactionId={transaction.id} onUpdate={loadTransaction} />
        )}
      </div>

      <div className="mt-6 rounded-lg border border-border bg-surface-container p-5">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="font-display text-lg text-on-surface">Tanda Tangan Digital</h2>
            <p className="mt-0.5 text-xs text-on-surface-muted">
              Tanda tangani perjanjian (SPA) & bukti pemindahan hak (Bill of Sale) secara digital.
            </p>
          </div>
          {transaction.seller_signed_at ? (
            <span className="rounded-md border border-success/40 bg-success/5 px-3 py-1 text-xs text-success">
              &#10003; Sudah ditandatangani
            </span>
          ) : (
            <button
              type="button"
              onClick={() => setShowSignaturePad(true)}
              className="btn-gold rounded-md px-4 py-2 text-sm"
            >
              Tanda Tangan Sekarang
            </button>
          )}
        </div>
        <dl className="mt-4 grid grid-cols-2 gap-4 text-xs">
          <div>
            <dt className="text-on-surface-muted">Penjual</dt>
            <dd className="mt-0.5 text-on-surface">
              {transaction.seller_signed_at
                ? `Ditandatangani ${new Date(transaction.seller_signed_at).toLocaleString("id-ID")}`
                : "Belum ditandatangani"}
            </dd>
          </div>
          <div>
            <dt className="text-on-surface-muted">Pembeli</dt>
            <dd className="mt-0.5 text-on-surface">
              {transaction.buyer_signed_at
                ? `Ditandatangani ${new Date(transaction.buyer_signed_at).toLocaleString("id-ID")}`
                : "Belum ditandatangani"}
            </dd>
          </div>
        </dl>
      </div>

      {showSignaturePad && (
        <SignaturePad
          onSubmit={handleSubmitSignature}
          onClose={() => {
            setShowSignaturePad(false);
            setSignatureError(null);
          }}
          isSubmitting={isSigning}
          errorMessage={signatureError}
          title="Tanda Tangan sebagai Penjual"
          intro="Gambar tanda tangan Anda pada kotak di bawah. Tanda tangan hanya bisa disimpan sekali dan tidak dapat diubah."
        />
      )}

      {transaction.status_history && transaction.status_history.length > 0 && (
        <div className="mt-6">
          <h2 className="text-sm font-semibold text-on-surface">Riwayat Status Escrow</h2>
          <div className="mt-3">
            <TransactionStatusHistory entries={transaction.status_history} />
          </div>
        </div>
      )}

      {showConfirmHandover && (
        <DangerConfirmModal
          title="Konfirmasi Serah Terima Kendaraan"
          intro={
            <>
              Aksi ini menyatakan bahwa kendaraan{" "}
              <strong className="text-on-surface">{transaction.vehicle.brand} {transaction.vehicle.model}</strong>{" "}
              telah kamu serahkan secara fisik kepada buyer, lengkap dengan dokumen (STNK/BPKB) dan kunci.
            </>
          }
          consequences={[
            "Buyer akan diminta mengkonfirmasi penerimaan kendaraan.",
            "Setelah dikonfirmasi buyer & admin, dana escrow dilepas ke rekening bank kamu.",
            "Klaim serah-terima palsu dapat menyebabkan akun kamu di-suspend.",
          ]}
          confirmWord="SERAHKAN"
          confirmLabel="Ya, Sudah Diserahkan"
          isSubmitting={isSubmitting}
          onConfirm={handleConfirmHandover}
          onCancel={() => setShowConfirmHandover(false)}
        />
      )}
    </div>
  );
}
