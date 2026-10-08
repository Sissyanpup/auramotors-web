"use client";

import { use, useEffect, useState } from "react";
import Link from "next/link";
import { apiFetch } from "@/lib/api";
import { formatDate, formatRupiah } from "@/lib/format";
import type { Transaction } from "@/lib/types";

const INSURANCE_LABELS: Record<string, string> = {
  none: "Tanpa Asuransi",
  tlo: "TLO (Total Loss Only)",
  all_risk: "All Risk",
};

export default function NotaTransaksiPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [transaction, setTransaction] = useState<Transaction | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    apiFetch<{ data: Transaction }>(`/api/buyer/transactions/${id}`)
      .then(({ data }) => {
        if (!cancelled) setTransaction(data);
      })
      .catch(() => {
        if (!cancelled) setError("Nota tidak ditemukan.");
      });
    return () => {
      cancelled = true;
    };
  }, [id]);

  function handlePrint() {
    window.print();
  }

  if (error) return <p className="mx-auto max-w-2xl p-8 text-sm text-error">{error}</p>;
  if (!transaction) return <p className="mx-auto max-w-2xl p-8 text-sm text-on-surface-muted">Memuat nota...</p>;

  const price = Number(transaction.vehicle_price ?? transaction.vehicle.price);
  const dpAmount =
    transaction.payment_scheme === "full"
      ? price
      : Math.round((price * Number(transaction.dp_percent ?? 0)) / 100);
  const premium = Number(transaction.insurance_premium ?? 0);
  const totalPaid = Number(transaction.amount);
  const remaining = Math.max(price - dpAmount, 0);

  return (
    <>
      <style>{`
        @media print {
          body { background: white !important; color: black !important; }
          .no-print { display: none !important; }
          .nota-page { background: white !important; color: black !important; box-shadow: none !important; }
          .nota-page * { color: black !important; border-color: #999 !important; }
        }
      `}</style>

      <div className="mx-auto max-w-3xl px-4 py-6">
        <div className="no-print mb-4 flex flex-wrap items-center justify-between gap-3">
          <Link
            href={`/buyer/transactions/${transaction.id}`}
            className="text-sm text-on-surface-muted hover:text-on-surface"
          >
            &larr; Kembali ke detail transaksi
          </Link>
          <div className="flex gap-2">
            <button
              onClick={handlePrint}
              className="btn-gold rounded-md px-4 py-2 text-sm font-medium"
            >
              Cetak / Simpan PDF
            </button>
          </div>
        </div>

        <article className="nota-page rounded-lg border border-border bg-surface-container p-8 shadow-[0_8px_24px_rgba(0,0,0,0.4)]">
          {/* Header */}
          <header className="flex items-start justify-between gap-4 border-b border-border pb-4">
            <div>
              <p className="font-display text-2xl text-on-surface">AuraMotors</p>
              <p className="mt-1 text-xs text-on-surface-muted">
                Marketplace Kendaraan Bermotor Terverifikasi
              </p>
              <p className="mt-0.5 text-xs text-on-surface-muted">
                PT AuraMotors Escrow Indonesia &middot; support@auramotors.id
              </p>
            </div>
            <div className="text-right">
              <p className="text-xs font-semibold uppercase tracking-widest text-on-surface-muted">
                Nota Transaksi
              </p>
              <p className="mt-1 font-mono text-sm text-on-surface">
                {transaction.invoice_number ?? "—"}
              </p>
              <p className="mt-0.5 text-xs text-on-surface-muted">
                Tgl. Terbit: {formatDate(transaction.created_at)}
              </p>
            </div>
          </header>

          {/* Buyer / Seller */}
          <section className="mt-6 grid gap-6 sm:grid-cols-2">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wider text-on-surface-muted">
                Pembeli
              </p>
              <p className="mt-2 text-sm font-medium text-on-surface">
                {transaction.buyer?.name ?? "—"}
              </p>
              <p className="mt-0.5 whitespace-pre-line text-xs text-on-surface-muted">
                {transaction.buyer_address ?? "—"}
              </p>
              <p className="mt-1 text-xs text-on-surface-muted">
                Telp: {transaction.buyer_phone ?? "—"}
              </p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase tracking-wider text-on-surface-muted">
                Penjual
              </p>
              <p className="mt-2 text-sm font-medium text-on-surface">
                {transaction.seller?.name ?? "—"}
              </p>
              <p className="mt-0.5 text-xs text-on-surface-muted">
                Lokasi kendaraan: {transaction.vehicle.location}
              </p>
            </div>
          </section>

          {/* Item */}
          <section className="mt-6">
            <p className="text-xs font-semibold uppercase tracking-wider text-on-surface-muted">
              Rincian Kendaraan
            </p>
            <table className="mt-2 w-full border-collapse text-sm">
              <thead>
                <tr className="border-y border-border text-left text-xs uppercase tracking-wider text-on-surface-muted">
                  <th className="py-2">Deskripsi</th>
                  <th className="py-2 text-right">Jumlah (IDR)</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                <tr>
                  <td className="py-2 text-on-surface">
                    {transaction.vehicle.brand} {transaction.vehicle.model} {transaction.vehicle.year}
                    <span className="ml-2 text-xs text-on-surface-muted">
                      ({transaction.vehicle.mileage.toLocaleString("id-ID")} km)
                    </span>
                  </td>
                  <td className="py-2 text-right text-on-surface">{formatRupiah(price)}</td>
                </tr>
                <tr>
                  <td className="py-2 text-on-surface">
                    {transaction.payment_scheme === "full"
                      ? "Pelunasan Penuh (Full Payment)"
                      : `Down Payment ${transaction.dp_percent}%`}
                  </td>
                  <td className="py-2 text-right text-on-surface">{formatRupiah(dpAmount)}</td>
                </tr>
                {premium > 0 && (
                  <tr>
                    <td className="py-2 text-on-surface">
                      Premi Asuransi &mdash; {INSURANCE_LABELS[transaction.insurance_type ?? "none"]}
                    </td>
                    <td className="py-2 text-right text-on-surface">{formatRupiah(premium)}</td>
                  </tr>
                )}
                <tr className="font-semibold">
                  <td className="py-2 text-on-surface">TOTAL DITAGIH SEKARANG</td>
                  <td className="py-2 text-right text-primary">{formatRupiah(totalPaid)}</td>
                </tr>
                {transaction.payment_scheme !== "full" && (
                  <tr>
                    <td className="py-2 text-xs text-on-surface-muted">
                      Sisa Pelunasan (di luar sistem escrow)
                    </td>
                    <td className="py-2 text-right text-xs text-on-surface-muted">
                      {formatRupiah(remaining)}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </section>

          {/* Bank tujuan */}
          <section className="mt-6 rounded-md border border-border bg-surface p-4">
            <p className="text-xs font-semibold uppercase tracking-wider text-on-surface-muted">
              Rekening Escrow Tujuan Pembayaran
            </p>
            <dl className="mt-2 grid grid-cols-1 gap-1 text-sm sm:grid-cols-3">
              <div>
                <dt className="text-xs text-on-surface-muted">Bank</dt>
                <dd className="text-on-surface">{transaction.bank_transfer_bank ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs text-on-surface-muted">No. Rekening</dt>
                <dd className="font-mono text-on-surface">{transaction.bank_transfer_account_number ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs text-on-surface-muted">Atas Nama</dt>
                <dd className="text-on-surface">{transaction.bank_transfer_account_holder ?? "—"}</dd>
              </div>
            </dl>
            <p className="mt-2 text-xs text-on-surface-muted">
              Referensi transfer: <span className="font-mono">{transaction.gateway_reference ?? transaction.invoice_number}</span>.
              Cantumkan nomor invoice di berita transfer.
            </p>
          </section>

          {/* Status */}
          <section className="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
            <div>
              <dt className="text-xs text-on-surface-muted">Status Bayar</dt>
              <dd className="text-on-surface capitalize">{transaction.payment_status}</dd>
            </div>
            <div>
              <dt className="text-xs text-on-surface-muted">Status Escrow</dt>
              <dd className="text-on-surface">{transaction.escrow_status ?? "Menunggu"}</dd>
            </div>
            <div>
              <dt className="text-xs text-on-surface-muted">Dibayar Pada</dt>
              <dd className="text-on-surface">
                {transaction.paid_at ? formatDate(transaction.paid_at) : "Belum"}
              </dd>
            </div>
            <div>
              <dt className="text-xs text-on-surface-muted">Kadaluarsa</dt>
              <dd className="text-on-surface">
                {transaction.expires_at ? formatDate(transaction.expires_at) : "—"}
              </dd>
            </div>
          </section>

          {/* Catatan */}
          {transaction.buyer_notes && (
            <section className="mt-6 rounded-md border border-border bg-surface p-4">
              <p className="text-xs font-semibold uppercase tracking-wider text-on-surface-muted">
                Catatan Pembeli
              </p>
              <p className="mt-1 text-sm text-on-surface">{transaction.buyer_notes}</p>
            </section>
          )}

          {/* Footer */}
          <footer className="mt-8 border-t border-border pt-4 text-xs text-on-surface-muted">
            <p>
              Nota ini diterbitkan secara otomatis oleh sistem AuraMotors dan sah tanpa
              tanda tangan basah. Simpan sebagai bukti transaksi.
            </p>
            <p className="mt-2">
              Sisa pelunasan (jika ada) diselesaikan langsung antara pembeli dan penjual di
              luar sistem escrow. AuraMotors hanya menjamin dana DP + asuransi yang tercatat di nota ini.
            </p>
          </footer>
        </article>
      </div>
    </>
  );
}
