"use client";

import { use, useCallback, useEffect, useState } from "react";
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
import DeliveryTracker from "@/components/DeliveryTracker";
import WarrantyClaimModal from "@/components/WarrantyClaimModal";

export default function BuyerTransactionDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  const [transaction, setTransaction] = useState<Transaction | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [disputeReason, setDisputeReason] = useState("");
  const [showDisputeForm, setShowDisputeForm] = useState(false);
  const [showConfirmHandover, setShowConfirmHandover] = useState(false);
  const [showCancelModal, setShowCancelModal] = useState(false);
  const [showWarrantyModal, setShowWarrantyModal] = useState(false);
  const [showSignaturePad, setShowSignaturePad] = useState(false);
  const [isSigning, setIsSigning] = useState(false);
  const [signatureError, setSignatureError] = useState<string | null>(null);
  // Progress pengantaran GPS (0-1). Default 1 supaya kalau tracker belum
  // aktif (mis. transaksi belum masuk fase escrow_hold), tombol tidak
  // ke-block. Tracker akan meng-emit 0 → 1 selama DELIVERY_SECONDS berjalan.
  const [deliveryProgress, setDeliveryProgress] = useState(1);
  const handleDeliveryProgress = useCallback((p: number) => setDeliveryProgress(p), []);
  const DELIVERY_SECONDS = 180;

  async function loadTransaction() {
    try {
      const { data } = await apiFetch<{ data: Transaction }>(`/api/buyer/transactions/${id}`);
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

  async function handleRefreshStatus() {
    setIsRefreshing(true);
    try {
      const { data } = await apiFetch<{ data: Transaction }>(
        `/api/buyer/transactions/${id}/refresh-status`,
        { method: "POST" }
      );
      setTransaction(data);
    } catch (err) {
      if (err instanceof ApiError) setError(err.message);
    } finally {
      setIsRefreshing(false);
    }
  }

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

  async function handleCancelTransaction(values: Record<string, string>) {
    setIsSubmitting(true);
    setError(null);
    try {
      const { data } = await apiFetch<{ data: Transaction }>(
        `/api/buyer/transactions/${id}/cancel`,
        { method: "POST", body: { reason: values.reason } }
      );
      setTransaction(data);
      setShowCancelModal(false);
    } catch (err) {
      if (err instanceof ApiError) setError(err.errors?.reason?.[0] ?? err.message);
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleConfirmHandover() {
    setIsSubmitting(true);
    setError(null);
    try {
      const { data } = await apiFetch<{ data: Transaction }>(
        `/api/buyer/transactions/${id}/confirm-handover`,
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
      const { data } = await apiFetch<{ data: Transaction }>(`/api/buyer/transactions/${id}/dispute`, {
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

  const canConfirmHandover = transaction.escrow_status === "escrow_hold" && !transaction.buyer_confirmed_at;
  // Dispute hanya boleh diajukan selama buyer belum menerima kendaraan
  // (analog ke olshop: "laporkan pesanan" hilang setelah "pesanan diterima").
  const canDispute =
    !transaction.buyer_confirmed_at &&
    (transaction.escrow_status === "escrow_hold" || transaction.escrow_status === "serah_terima");
  const canClaimWarranty =
    !!transaction.buyer_confirmed_at &&
    transaction.escrow_status !== "dispute" &&
    transaction.escrow_status !== "refunded";

  // Tampilkan tracker GPS hanya saat pengantaran benar-benar berjalan
  // (fase escrow_hold, buyer belum konfirmasi terima).
  const showDeliveryTracker =
    transaction.escrow_status === "escrow_hold" && !transaction.buyer_confirmed_at;

  // Gate tombol Terima/Laporkan saat fase pengantaran berlangsung sampai
  // armada dummy "tiba" di lokasi (progress >= 1). Setelah buyer konfirmasi
  // atau tracker tidak aktif, gate dilepas.
  const deliveryArrived = deliveryProgress >= 1;
  const gateActionsUntilArrive = showDeliveryTracker && !deliveryArrived;
  const etaSecondsRemaining = Math.max(0, Math.round((1 - deliveryProgress) * DELIVERY_SECONDS));
  const etaLabel =
    etaSecondsRemaining >= 60
      ? `± ${Math.ceil(etaSecondsRemaining / 60)} menit lagi`
      : `± ${etaSecondsRemaining} detik lagi`;

  // Ringkasan pelunasan setelah buyer konfirmasi terima kendaraan.
  const vehiclePrice = Number(transaction.vehicle_price ?? transaction.vehicle.price ?? 0);
  const paidNow = Number(transaction.amount ?? 0);
  const insurancePremium = Number(transaction.insurance_premium ?? 0);
  const dpPortion = Math.max(0, paidNow - insurancePremium);
  const remainingToSeller = Math.max(0, vehiclePrice - dpPortion);
  const isFullPayment = transaction.payment_scheme === "full";

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <Link href="/buyer/transactions" className="text-sm text-on-surface-muted hover:text-on-surface">
          &larr; Kembali ke Transaksi Saya
        </Link>
        {transaction.invoice_number && (
          <Link
            href={`/buyer/transactions/${transaction.id}/nota`}
            className="rounded-md border border-border px-3 py-1.5 text-xs font-medium text-on-surface hover:bg-surface-container-high"
          >
            Lihat / Cetak Nota
          </Link>
        )}
      </div>

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
            <dt className="text-xs uppercase text-on-surface-muted">Metode Pembayaran</dt>
            <dd className="text-on-surface">{transaction.payment_gateway === "mock" ? "Simulasi (Offline)" : "Xendit"}</dd>
          </div>
          <div>
            <dt className="text-xs uppercase text-on-surface-muted">Dibuat</dt>
            <dd className="text-on-surface">{formatDate(transaction.created_at)}</dd>
          </div>
          {transaction.paid_at && (
            <div>
              <dt className="text-xs uppercase text-on-surface-muted">Dibayar</dt>
              <dd className="text-on-surface">{formatDate(transaction.paid_at)}</dd>
            </div>
          )}
          {transaction.buyer_confirmed_at && (
            <div>
              <dt className="text-xs uppercase text-on-surface-muted">Anda Konfirmasi Terima</dt>
              <dd className="text-on-surface">{formatDate(transaction.buyer_confirmed_at)}</dd>
            </div>
          )}
          {transaction.seller_confirmed_at && (
            <div>
              <dt className="text-xs uppercase text-on-surface-muted">Penjual Konfirmasi Serahkan</dt>
              <dd className="text-on-surface">{formatDate(transaction.seller_confirmed_at)}</dd>
            </div>
          )}
        </dl>

        {error && <p className="mt-3 text-sm text-error">{error}</p>}

        {transaction.payment_status === "pending" && (
          <div className="mt-6 flex flex-wrap gap-3">
            {transaction.gateway_invoice_url && (
              <a
                href={transaction.gateway_invoice_url}
                className="rounded-md bg-primary-container px-4 py-2 text-sm font-medium text-on-primary hover:bg-primary"
              >
                Lanjutkan Pembayaran
              </a>
            )}
            <button
              onClick={handleRefreshStatus}
              disabled={isRefreshing}
              className="rounded-md border border-border px-4 py-2 text-sm font-medium text-on-surface hover:bg-surface-container-high disabled:opacity-60"
            >
              {isRefreshing ? "Mengecek..." : "Cek Status Pembayaran"}
            </button>
            <button
              onClick={() => setShowCancelModal(true)}
              disabled={isSubmitting}
              className="rounded-md border border-error/50 px-4 py-2 text-sm font-medium text-error hover:bg-error/10 disabled:opacity-60"
            >
              Batalkan Pesanan
            </button>
          </div>
        )}

        {transaction.payment_status === "cancelled" && transaction.cancellation_reason && (
          <div className="mt-6 rounded-md border border-border bg-surface-container-high p-4 text-sm">
            <p className="font-medium text-on-surface">Pesanan Dibatalkan</p>
            {transaction.cancelled_at && (
              <p className="mt-0.5 text-xs text-on-surface-muted">
                Dibatalkan pada {formatDate(transaction.cancelled_at)}
              </p>
            )}
            <p className="mt-2 whitespace-pre-line text-on-surface-muted">
              <span className="text-xs uppercase tracking-wider">Alasan: </span>
              {transaction.cancellation_reason}
            </p>
          </div>
        )}

        {(canConfirmHandover || canDispute) && (
          <div className="mt-6 border-t border-border pt-4">
            {gateActionsUntilArrive && (
              <p className="mb-3 rounded-md border border-warning/40 bg-warning/5 px-3 py-2 text-xs text-on-surface-muted">
                <span className="font-semibold text-warning">Menunggu armada tiba di lokasi.</span>{" "}
                Tombol konfirmasi &amp; laporkan aktif otomatis setelah kurir sampai
                {etaSecondsRemaining > 0 ? ` (${etaLabel})` : ""}.
              </p>
            )}
            <div className="flex flex-wrap gap-3">
              {canConfirmHandover && (
                <button
                  onClick={() => setShowConfirmHandover(true)}
                  disabled={isSubmitting || gateActionsUntilArrive}
                  aria-disabled={gateActionsUntilArrive}
                  title={gateActionsUntilArrive ? "Aktif setelah armada tiba di lokasi" : undefined}
                  className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-40"
                >
                  Konfirmasi Sudah Terima Kendaraan
                </button>
              )}
              {canDispute && !showDisputeForm && (
                <button
                  onClick={() => setShowDisputeForm(true)}
                  disabled={gateActionsUntilArrive}
                  aria-disabled={gateActionsUntilArrive}
                  title={gateActionsUntilArrive ? "Aktif setelah armada tiba di lokasi" : undefined}
                  className="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                >
                  Laporkan Ketidaksesuaian
                </button>
              )}
            </div>
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
              placeholder="Contoh: kondisi kendaraan tidak sesuai deskripsi listing."
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

        {transaction.escrow_status === "refunded" && transaction.dispute_resolution_note && (
          <div className="mt-4 rounded-md border border-border bg-surface-container-high p-4 text-sm text-on-surface">
            <p className="font-medium">Dana dikembalikan</p>
            <p className="mt-1">{transaction.dispute_resolution_note}</p>
          </div>
        )}
      </div>

      {showDeliveryTracker && (
        <div className="mt-6">
          <DeliveryTracker
            transactionId={transaction.id}
            sellerLocation={transaction.vehicle.location}
            buyerAddress={transaction.buyer_address}
            startedAt={transaction.paid_at ?? transaction.created_at}
            totalSeconds={DELIVERY_SECONDS}
            onProgressChange={handleDeliveryProgress}
          />
        </div>
      )}

      {transaction.buyer_confirmed_at && (
        <div className="mt-6 rounded-lg border border-success/40 bg-success/5 p-5">
          <div className="flex items-start gap-3">
            <div
              aria-hidden
              className="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-success/15 text-success"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" className="h-5 w-5">
                <path strokeLinecap="round" strokeLinejoin="round" d="m4.5 12.75 6 6 9-13.5" />
              </svg>
            </div>
            <div className="flex-1">
              <h2 className="font-display text-lg text-on-surface">
                {isFullPayment ? "Transaksi Lunas Sepenuhnya" : "Serah Terima Terkonfirmasi"}
              </h2>
              <p className="mt-0.5 text-sm text-on-surface-muted">
                Kamu telah mengkonfirmasi penerimaan kendaraan pada {formatDate(transaction.buyer_confirmed_at)}.
                {isFullPayment
                  ? " Tidak ada tagihan lanjutan — dana escrow menunggu approval admin untuk dilepas ke seller."
                  : " Berikut ringkasan tagihan tersisa untuk kamu selesaikan langsung dengan seller."}
              </p>

              <dl className="mt-4 divide-y divide-border rounded-md border border-border bg-surface text-sm">
                <div className="flex items-center justify-between px-3 py-2">
                  <dt className="text-on-surface-muted">Harga kendaraan</dt>
                  <dd className="text-on-surface">{formatRupiah(vehiclePrice)}</dd>
                </div>
                <div className="flex items-center justify-between px-3 py-2">
                  <dt className="text-on-surface-muted">
                    Sudah dibayar via escrow
                    {isFullPayment ? " (Lunas)" : ` (DP${transaction.dp_percent ? ` ${Number(transaction.dp_percent)}%` : ""})`}
                  </dt>
                  <dd className="text-on-surface">{formatRupiah(dpPortion)}</dd>
                </div>
                {insurancePremium > 0 && (
                  <div className="flex items-center justify-between px-3 py-2">
                    <dt className="text-on-surface-muted">Premi asuransi (termasuk di atas)</dt>
                    <dd className="text-on-surface">{formatRupiah(insurancePremium)}</dd>
                  </div>
                )}
                <div className="flex items-center justify-between px-3 py-2 text-base font-semibold">
                  <dt className={isFullPayment ? "text-success" : "text-on-surface"}>
                    {isFullPayment ? "Status" : "Sisa pelunasan"}
                  </dt>
                  <dd className={isFullPayment ? "text-success" : "text-primary"}>
                    {isFullPayment ? "LUNAS" : formatRupiah(remainingToSeller)}
                  </dd>
                </div>
              </dl>

              {!isFullPayment && remainingToSeller > 0 && (
                <div className="mt-3 rounded-md border border-warning/40 bg-warning/5 p-3 text-xs text-on-surface-muted">
                  <p className="font-semibold text-warning">Bayar selanjutnya langsung ke seller</p>
                  <p className="mt-1">
                    Sisa pelunasan diselesaikan di luar sistem escrow — koordinasikan metode transfer,
                    kwitansi, dan penyerahan dokumen (STNK/BPKB) langsung dengan seller. Simpan bukti
                    transfer untuk keperluan balik nama.
                  </p>
                </div>
              )}

              <div className="mt-4 flex flex-wrap gap-2">
                {transaction.invoice_number && (
                  <Link
                    href={`/buyer/transactions/${transaction.id}/nota`}
                    className="btn-gold rounded-md px-4 py-2 text-sm font-medium"
                  >
                    Lihat / Cetak Nota Resmi
                  </Link>
                )}
                {canClaimWarranty && (
                  <button
                    type="button"
                    onClick={() => setShowWarrantyModal(true)}
                    className="rounded-md border border-border px-4 py-2 text-sm font-medium text-on-surface hover:bg-surface-container-high"
                  >
                    Klaim Garansi
                  </button>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      <div className="mt-6">
        <VdrPanel documents={transaction.documents} />
      </div>

      <div className="mt-6">
        {transaction.shipment ? (
          <ShipmentPanel
            transactionId={transaction.id}
            shipment={transaction.shipment}
            userRole="buyer"
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
              Tanda tangan Anda akan muncul otomatis di PDF setelah disimpan.
            </p>
          </div>
          {transaction.buyer_signed_at ? (
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
            <dt className="text-on-surface-muted">Pembeli</dt>
            <dd className="mt-0.5 text-on-surface">
              {transaction.buyer_signed_at
                ? `Ditandatangani ${new Date(transaction.buyer_signed_at).toLocaleString("id-ID")}`
                : "Belum ditandatangani"}
            </dd>
          </div>
          <div>
            <dt className="text-on-surface-muted">Penjual</dt>
            <dd className="mt-0.5 text-on-surface">
              {transaction.seller_signed_at
                ? `Ditandatangani ${new Date(transaction.seller_signed_at).toLocaleString("id-ID")}`
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
          title="Tanda Tangan sebagai Pembeli"
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

      {showWarrantyModal && (
        <WarrantyClaimModal
          invoiceNumber={transaction.invoice_number}
          sellerName={transaction.seller?.name ?? null}
          onClose={() => setShowWarrantyModal(false)}
        />
      )}

      {showCancelModal && (
        <DangerConfirmModal
          title="Batalkan Pesanan"
          intro={
            <>
              Kamu akan membatalkan transaksi pembelian{" "}
              <strong className="text-on-surface">{transaction.vehicle.brand} {transaction.vehicle.model}</strong>.
              Invoice yang sudah diterbitkan akan ditandai batal dan kendaraan kembali tersedia untuk buyer lain.
            </>
          }
          consequences={[
            "Invoice pembayaran tidak lagi valid — link Lanjutkan Pembayaran akan dinonaktifkan.",
            "Nomor invoice tetap tersimpan di riwayat sebagai bukti audit.",
            "Kamu perlu membuat pengajuan baru dari halaman kendaraan jika ingin membeli ulang.",
            "Aksi ini hanya bisa dilakukan selagi status masih menunggu pembayaran.",
          ]}
          fields={[
            {
              name: "reason",
              label: "Alasan pembatalan",
              required: true,
              type: "textarea",
              placeholder: "Contoh: berubah pikiran, menemukan unit lain, keliru pilih paket DP, dll.",
            },
          ]}
          confirmWord="BATAL"
          confirmLabel="Ya, Batalkan Pesanan"
          isSubmitting={isSubmitting}
          onConfirm={handleCancelTransaction}
          onCancel={() => setShowCancelModal(false)}
        />
      )}

      {showConfirmHandover && (
        <DangerConfirmModal
          title="Konfirmasi Terima Kendaraan"
          intro={
            <>
              Aksi ini akan memberitahu admin & seller bahwa kendaraan{" "}
              <strong className="text-on-surface">{transaction.vehicle.brand} {transaction.vehicle.model}</strong>{" "}
              sudah kamu terima secara fisik dan dalam kondisi sesuai.
            </>
          }
          consequences={[
            "Dana escrow akan dilepas ke seller setelah admin verifikasi.",
            "Kamu tidak dapat lagi mengajukan sengketa (dispute) untuk transaksi ini.",
            "Status transaksi berpindah ke tahap payout (pencairan).",
          ]}
          confirmWord="TERIMA"
          confirmLabel="Ya, Kendaraan Sudah Diterima"
          isSubmitting={isSubmitting}
          onConfirm={handleConfirmHandover}
          onCancel={() => setShowConfirmHandover(false)}
        />
      )}
    </div>
  );
}
