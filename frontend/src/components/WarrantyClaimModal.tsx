"use client";

import Link from "next/link";
import { useState } from "react";

type WarrantyCoverage = {
  label: string;
  period: string;
};

const COVERAGES: WarrantyCoverage[] = [
  { label: "Mesin & transmisi", period: "6 bulan / 10.000 km (yang tercapai lebih dulu)" },
  { label: "Sistem kelistrikan & AC", period: "3 bulan" },
  { label: "Kelengkapan dokumen (STNK/BPKB)", period: "30 hari sejak serah terima" },
];

type WarrantyClaimModalProps = {
  invoiceNumber: string | null;
  sellerName: string | null;
  onClose: () => void;
};

export default function WarrantyClaimModal({ invoiceNumber, sellerName, onClose }: WarrantyClaimModalProps) {
  const [issue, setIssue] = useState("");
  const [copied, setCopied] = useState(false);

  async function handleCopyInvoice() {
    if (!invoiceNumber) return;
    try {
      await navigator.clipboard.writeText(invoiceNumber);
      setCopied(true);
      setTimeout(() => setCopied(false), 1800);
    } catch {
      /* fallback: user tinggal select-copy manual */
    }
  }

  const chatSubject = issue.trim().length > 0
    ? `Klaim Garansi ${invoiceNumber ?? ""}: ${issue.trim().slice(0, 80)}`
    : `Klaim Garansi ${invoiceNumber ?? ""}`;

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="warranty-modal-title"
      className="fixed inset-0 z-50 flex items-center justify-center p-4 animate-fade-in"
      style={{ background: "rgba(0,0,0,0.65)" }}
    >
      <div className="w-full max-w-lg overflow-hidden rounded-lg border border-border bg-surface-container shadow-[0_24px_64px_rgba(0,0,0,0.6)] animate-scale-in">
        {/* Header */}
        <div className="flex items-start gap-3 border-b border-border bg-surface-container-high p-5">
          <div
            aria-hidden
            className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary-container/40 text-primary"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-5 w-5">
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
            </svg>
          </div>
          <div className="flex-1">
            <h2 id="warranty-modal-title" className="font-display text-lg text-on-surface">
              Klaim Garansi Kendaraan
            </h2>
            <p className="mt-0.5 text-sm text-on-surface-muted">
              Ajukan klaim ke seller <strong className="text-on-surface">{sellerName ?? "—"}</strong> selama masa garansi masih berlaku.
            </p>
          </div>
        </div>

        {/* Body */}
        <div className="p-5">
          <p className="text-xs font-semibold uppercase tracking-wider text-on-surface-muted">
            Cakupan Garansi Standar
          </p>
          <ul className="mt-2 divide-y divide-border rounded-md border border-border bg-surface text-sm">
            {COVERAGES.map((c) => (
              <li key={c.label} className="flex items-start justify-between gap-3 px-3 py-2">
                <span className="text-on-surface">{c.label}</span>
                <span className="text-right text-xs text-on-surface-muted">{c.period}</span>
              </li>
            ))}
          </ul>
          <p className="mt-2 text-xs text-on-surface-muted">
            Garansi standar berlaku sejak tanggal serah-terima. Tidak berlaku untuk kerusakan akibat
            pemakaian di luar spesifikasi pabrikan, kecelakaan, atau modifikasi pribadi.
          </p>

          <div className="mt-4">
            <label htmlFor="warranty-issue" className="block text-sm font-medium text-on-surface">
              Deskripsi kendala <span className="font-normal text-on-surface-muted">(opsional)</span>
            </label>
            <textarea
              id="warranty-issue"
              value={issue}
              onChange={(e) => setIssue(e.target.value)}
              rows={3}
              maxLength={500}
              placeholder="Contoh: AC tidak dingin setelah 2 minggu pemakaian, ada suara aneh dari transmisi saat kickdown."
              className="mt-1.5 block w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-on-surface placeholder:text-on-surface-muted focus:border-primary focus:outline-none resize-none"
            />
            <p className="mt-0.5 text-right text-xs text-on-surface-muted">{issue.length}/500</p>
          </div>

          {invoiceNumber && (
            <div className="mt-3 flex items-center justify-between gap-2 rounded-md border border-border bg-surface p-3 text-xs">
              <div>
                <p className="text-on-surface-muted">Nomor Invoice (referensi klaim)</p>
                <p className="mt-0.5 font-mono text-on-surface">{invoiceNumber}</p>
              </div>
              <button
                type="button"
                onClick={handleCopyInvoice}
                className="rounded-md border border-border px-2 py-1 text-xs font-medium text-on-surface-muted hover:bg-surface-container-high hover:text-on-surface"
              >
                {copied ? "Tersalin ✓" : "Salin"}
              </button>
            </div>
          )}

          <div className="mt-5 rounded-md border border-primary/30 bg-primary-container/10 p-3 text-xs text-on-surface-muted">
            <p className="font-semibold text-primary">Hubungi seller untuk penanganan</p>
            <p className="mt-1">
              Sistem akan menghubungkan kamu ke thread chat / pesan dengan seller. Sertakan nomor
              invoice &amp; deskripsi kendala saat mengirim.
            </p>
          </div>
        </div>

        {/* Footer */}
        <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-surface-container-high p-4">
          <button
            type="button"
            onClick={onClose}
            className="rounded-md px-3 py-1.5 text-sm font-medium text-on-surface-muted transition-colors hover:bg-surface-container hover:text-on-surface"
          >
            Tutup
          </button>
          <Link
            href={`/pesan?subject=${encodeURIComponent(chatSubject)}`}
            className="rounded-md border border-border px-3 py-1.5 text-sm font-medium text-on-surface hover:bg-surface-container"
          >
            Kirim Pesan
          </Link>
          <Link
            href={`/chat?subject=${encodeURIComponent(chatSubject)}`}
            className="btn-gold rounded-md px-4 py-1.5 text-sm font-medium"
          >
            Chat Seller Sekarang
          </Link>
        </div>
      </div>
    </div>
  );
}
