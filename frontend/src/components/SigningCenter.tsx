"use client";

import { useState } from "react";
import { ApiError, apiFetch } from "@/lib/api";
import type { SignerRole, SigningChecklistItem, Transaction, TransactionDocumentType } from "@/lib/types";
import SignaturePad from "@/components/SignaturePad";

type Props = {
  transactionId: number;
  checklist: SigningChecklistItem[] | undefined;
  /** Pihak yang sedang login. Admin hanya memantau (read-only). */
  role: SignerRole | "admin";
  onSigned?: (transaction: Transaction) => void;
};

const ROLE_LABEL: Record<SignerRole, string> = { buyer: "Pembeli", seller: "Penjual" };

function formatSignedAt(iso: string): string {
  return new Date(iso).toLocaleString("id-ID", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
}

/**
 * Pusat tanda tangan elektronik: daftar surat yang wajib diteken buyer/seller
 * (SPA, SPPA, Surat Kuasa Balik Nama, BAST, Kuitansi, Surat Pernyataan).
 * Satu gambar tanda tangan bisa dipakai untuk beberapa surat sekaligus.
 * Surat baru muncul saat PDF-nya terbit — mis. BAST setelah pembayaran lunas.
 */
export default function SigningCenter({ transactionId, checklist, role, onSigned }: Props) {
  const items = checklist ?? [];
  const [selected, setSelected] = useState<TransactionDocumentType[]>([]);
  const [padTypes, setPadTypes] = useState<TransactionDocumentType[] | null>(null);
  const [isSigning, setIsSigning] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const isParty = role !== "admin";
  const pendingForMe = isParty
    ? items.filter((item) => item.signers.some((s) => s.role === role && !s.signed_at))
    : [];
  const completeCount = items.filter((item) => item.is_complete).length;

  function toggle(type: TransactionDocumentType) {
    setSelected((prev) => (prev.includes(type) ? prev.filter((t) => t !== type) : [...prev, type]));
  }

  async function handleSubmit(dataUrl: string) {
    if (!padTypes) return;
    setIsSigning(true);
    setError(null);
    try {
      const { data } = await apiFetch<{ data: Transaction }>(`/api/transactions/${transactionId}/document-signatures`, {
        method: "POST",
        body: { document_types: padTypes, signature: dataUrl },
      });
      setPadTypes(null);
      setSelected([]);
      onSigned?.(data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Gagal menyimpan tanda tangan.");
    } finally {
      setIsSigning(false);
    }
  }

  const padLabels = items.filter((item) => padTypes?.includes(item.type)).map((item) => item.label);

  return (
    <section className="rounded-lg border border-border bg-surface-container p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="font-display text-lg text-on-surface">Pusat Tanda Tangan Dokumen</h2>
          <p className="mt-0.5 max-w-xl text-xs text-on-surface-muted">
            Surat jual beli yang wajib ditandatangani pembeli &amp; penjual. Kontrak juga ditandatangani badan hukum
            platform (PT AuraMotors Escrow Indonesia) sebagai agen escrow. Tanda tangan langsung tercetak di PDF.
          </p>
        </div>
        <span className="rounded-md border border-border px-2.5 py-1 text-xs text-on-surface-muted">
          {completeCount}/{items.length} lengkap
        </span>
      </div>

      {items.length === 0 && (
        <p className="mt-4 rounded-md border border-dashed border-border bg-surface px-4 py-6 text-center text-sm text-on-surface-muted">
          Belum ada dokumen yang perlu ditandatangani.
        </p>
      )}

      {pendingForMe.length > 0 && (
        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-primary/40 bg-primary/5 px-4 py-3">
          <p className="text-sm text-on-surface">
            <strong className="text-primary">{pendingForMe.length} dokumen</strong> menunggu tanda tangan Anda sebagai{" "}
            {ROLE_LABEL[role as SignerRole].toLowerCase()}.
          </p>
          <div className="flex gap-2">
            {selected.length > 0 && (
              <button
                type="button"
                onClick={() => setPadTypes(selected)}
                className="rounded-md border border-primary/50 px-3 py-1.5 text-xs font-medium text-primary hover:bg-primary/10"
              >
                Tanda tangani terpilih ({selected.length})
              </button>
            )}
            <button
              type="button"
              onClick={() => setPadTypes(pendingForMe.map((item) => item.type))}
              className="btn-gold rounded-md px-3 py-1.5 text-xs"
            >
              Tanda tangani semua ({pendingForMe.length})
            </button>
          </div>
        </div>
      )}

      {items.length > 0 && (
        <ul className="mt-4 divide-y divide-border rounded-md border border-border">
          {items.map((item) => {
            const mine = isParty ? item.signers.find((s) => s.role === role) : undefined;
            const canSign = mine !== undefined && !mine.signed_at;
            return (
              <li key={item.type} className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex min-w-0 items-start gap-3">
                  {canSign ? (
                    <input
                      type="checkbox"
                      aria-label={`Pilih ${item.label}`}
                      checked={selected.includes(item.type)}
                      onChange={() => toggle(item.type)}
                      className="mt-1 h-4 w-4 flex-shrink-0 accent-[var(--color-primary)]"
                    />
                  ) : (
                    <span
                      aria-hidden
                      className={`mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full text-[11px] ${
                        item.is_complete ? "bg-success/15 text-success" : "bg-warning/15 text-warning"
                      }`}
                    >
                      {item.is_complete ? "✓" : "…"}
                    </span>
                  )}
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-on-surface">{item.label}</p>
                    <div className="mt-1 flex flex-wrap gap-1.5">
                      {item.signers.map((signer) => (
                        <span
                          key={signer.role}
                          className={`rounded border px-2 py-0.5 text-[11px] ${
                            signer.signed_at
                              ? "border-success/40 bg-success/5 text-success"
                              : "border-border text-on-surface-muted"
                          }`}
                        >
                          {ROLE_LABEL[signer.role]}
                          {signer.signed_at ? ` ✓ ${formatSignedAt(signer.signed_at)}` : " · belum"}
                        </span>
                      ))}
                    </div>
                  </div>
                </div>
                <div className="flex flex-shrink-0 gap-2">
                  <a
                    href={item.download_url}
                    target="_blank"
                    rel="noreferrer"
                    className="rounded-md border border-border px-3 py-1.5 text-xs text-on-surface hover:border-primary/40"
                  >
                    Baca PDF
                  </a>
                  {canSign && (
                    <button
                      type="button"
                      onClick={() => setPadTypes([item.type])}
                      className="rounded-md border border-primary/40 bg-primary-container/10 px-3 py-1.5 text-xs font-medium text-primary hover:bg-primary-container/20"
                    >
                      Tanda tangani
                    </button>
                  )}
                </div>
              </li>
            );
          })}
        </ul>
      )}

      {padTypes && (
        <SignaturePad
          onSubmit={handleSubmit}
          onClose={() => {
            setPadTypes(null);
            setError(null);
          }}
          isSubmitting={isSigning}
          errorMessage={error}
          title={`Tanda Tangan sebagai ${ROLE_LABEL[role as SignerRole]}`}
          intro={`Dengan menandatangani, Anda menyatakan telah membaca dan menyetujui: ${padLabels.join(", ")}. Tanda tangan elektronik sah menurut UU ITE dan tidak dapat diubah setelah disimpan.`}
          submitLabel={padTypes.length > 1 ? `Tanda Tangani ${padTypes.length} Dokumen` : "Simpan Tanda Tangan"}
        />
      )}
    </section>
  );
}
