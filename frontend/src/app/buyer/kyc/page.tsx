"use client";

import { useEffect, useState, type FormEvent } from "react";
import { ApiError, apiFetch, toFormData } from "@/lib/api";
import type { BuyerIdType, BuyerProfile } from "@/lib/types";
import StatusBadge from "@/components/StatusBadge";
import FileUpload from "@/components/FileUpload";
import { useAuth } from "@/contexts/auth-context";

export default function BuyerKycPage() {
  const { refresh } = useAuth();
  const [profile, setProfile] = useState<BuyerProfile | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [idType, setIdType] = useState<BuyerIdType>("ktp");
  const [idNumber, setIdNumber] = useState("");
  const [idDocument, setIdDocument] = useState<File | null>(null);
  const [addressProof, setAddressProof] = useState<File | null>(null);
  const [proofOfFunds, setProofOfFunds] = useState<File | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function loadProfile() {
    try {
      const { data } = await apiFetch<{ data: BuyerProfile }>("/api/buyer/kyc");
      setProfile(data);
      setIdType(data.id_type ?? "ktp");
      setIdNumber(data.id_number ?? "");
    } catch (error) {
      if (!(error instanceof ApiError && error.status === 404)) {
        setFormError("Gagal memuat status KYC.");
      }
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- initial data fetch on mount
    loadProfile();
  }, []);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setErrors({});
    setFormError(null);
    setSuccess(false);

    const localErrors: Record<string, string[]> = {};
    if (!idNumber.trim()) {
      localErrors.id_number = ["Nomor identitas wajib diisi."];
    } else if (idType === "ktp" && !/^\d{16}$/.test(idNumber)) {
      localErrors.id_number = ["NIK harus terdiri dari 16 digit angka."];
    }
    if (!idDocument) localErrors.id_document = ["Dokumen identitas wajib diunggah."];
    if (!addressProof) localErrors.address_proof = ["Bukti alamat wajib diunggah."];
    if (!proofOfFunds) localErrors.proof_of_funds = ["Proof of Funds wajib diunggah."];
    if (Object.keys(localErrors).length > 0) {
      setErrors(localErrors);
      return;
    }

    setIsSubmitting(true);
    try {
      const formData = toFormData({
        id_type: idType,
        id_number: idNumber,
        id_document: idDocument,
        address_proof: addressProof,
        proof_of_funds: proofOfFunds,
      });
      const path = profile ? "/api/buyer/kyc/update" : "/api/buyer/kyc";
      const { data } = await apiFetch<{ data: BuyerProfile }>(path, {
        method: "POST",
        body: formData,
      });
      setProfile(data);
      setIdDocument(null);
      setAddressProof(null);
      setProofOfFunds(null);
      setSuccess(true);
      await refresh();
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        setErrors(error.errors);
      } else if (error instanceof ApiError) {
        setFormError(error.message);
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoading) {
    return <p className="p-8 text-sm text-on-surface-muted">Memuat...</p>;
  }

  const canSubmit = !profile || profile.status !== "approved";
  const isResubmit = profile && profile.status !== "approved";

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      <div className="mb-8">
        <h1 className="font-display text-2xl text-on-surface">Verifikasi KYC Buyer</h1>
        <p className="mt-1.5 text-sm text-on-surface-muted">
          Wajib disetujui admin sebelum kamu bisa checkout kendaraan bernilai tinggi (di atas Rp500 juta).
        </p>
      </div>

      {/* Info banner sesuai standar AML luxury vehicle */}
      <div className="mb-6 rounded-lg border border-warning/40 bg-warning/5 p-4 text-sm text-on-surface-muted">
        <p>
          <strong className="text-on-surface">Standar AML untuk kendaraan mewah.</strong>{" "}
          Data & dokumen ini disimpan terenkripsi dan hanya bisa diakses admin verifikator.
          Pastikan foto/scan jernih dan sesuai dokumen fisik.
        </p>
      </div>

      {/* Status card kalau sudah pernah submit */}
      {profile && (
        <div className="mb-6 rounded-lg border border-border bg-surface-container p-5">
          <div className="flex items-start justify-between gap-3">
            <div>
              <div className="flex items-center gap-2">
                <span className="text-sm text-on-surface-muted">Status pengajuan:</span>
                <StatusBadge status={profile.status} />
              </div>
              <dl className="mt-3 grid grid-cols-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <div className="flex gap-2">
                  <dt className="text-on-surface-muted">Tipe:</dt>
                  <dd className="text-on-surface">{profile.id_type === "passport" ? "Paspor" : "KTP"}</dd>
                </div>
                <div className="flex gap-2">
                  <dt className="text-on-surface-muted">Nomor:</dt>
                  <dd className="font-mono text-on-surface">{profile.id_number}</dd>
                </div>
              </dl>
              {profile.reviewed_at && (
                <p className="mt-2 text-xs text-on-surface-muted">
                  Direview {new Date(profile.reviewed_at).toLocaleString("id-ID")}
                  {profile.reviewed_by && ` oleh ${profile.reviewed_by}`}
                </p>
              )}
            </div>
          </div>

          {profile.status === "rejected" && profile.rejection_reason && (
            <div className="mt-4 rounded-md border border-error/40 bg-error/5 p-3 text-sm text-on-surface">
              <p className="font-medium text-error">Alasan penolakan:</p>
              <p className="mt-1">{profile.rejection_reason}</p>
              <p className="mt-2 text-xs text-on-surface-muted">
                Perbaiki dokumen di form bawah, lalu ajukan ulang.
              </p>
            </div>
          )}

          {profile.status === "approved" && (
            <p className="mt-3 text-xs text-success">
              Verifikasi telah lolos &mdash; kamu bisa checkout kendaraan nilai apa pun.
            </p>
          )}
        </div>
      )}

      {canSubmit && (
        <form
          onSubmit={handleSubmit}
          className="space-y-6 rounded-lg border border-border bg-surface-container p-6"
        >
          <div>
            <h2 className="font-display text-lg text-on-surface">
              {isResubmit ? "Ajukan Ulang Dokumen" : "Data Identitas & Verifikasi"}
            </h2>
            <p className="mt-0.5 text-xs text-on-surface-muted">
              Semua kolom wajib diisi. File maksimum 5 MB (JPG/PNG/PDF).
            </p>
          </div>

          {/* Section 1: Identitas */}
          <fieldset className="min-w-0 space-y-4 border-t border-border pt-4">
            <legend className="-mt-6 bg-surface-container px-2 text-xs font-medium uppercase tracking-wider text-on-surface-muted">
              1. Identitas
            </legend>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-[160px_minmax(0,1fr)]">
              <div>
                <label htmlFor="id-type" className="block text-sm font-medium text-on-surface">
                  Tipe Identitas
                </label>
                <select
                  id="id-type"
                  value={idType}
                  onChange={(e) => setIdType(e.target.value as BuyerIdType)}
                  className="input-field mt-1.5"
                >
                  <option value="ktp">KTP</option>
                  <option value="passport">Paspor</option>
                </select>
              </div>

              <div>
                <label htmlFor="id-number" className="block text-sm font-medium text-on-surface">
                  Nomor {idType === "ktp" ? "NIK" : "Paspor"}
                </label>
                <input
                  id="id-number"
                  type="text"
                  inputMode={idType === "ktp" ? "numeric" : "text"}
                  maxLength={idType === "ktp" ? 16 : 32}
                  value={idNumber}
                  onChange={(e) =>
                    setIdNumber(idType === "ktp" ? e.target.value.replace(/\D/g, "") : e.target.value)
                  }
                  placeholder={idType === "ktp" ? "16 digit angka NIK" : "mis. A1234567"}
                  className={`input-field mt-1.5 ${idType === "ktp" ? "font-mono tracking-wider" : ""}`}
                  required
                />
                {idType === "ktp" && (
                  <p className="mt-1 text-xs text-on-surface-muted">{idNumber.length}/16 digit</p>
                )}
                {errors.id_number && <p className="mt-1 text-xs text-error">{errors.id_number[0]}</p>}
              </div>
            </div>

            <FileUpload
              id="id-document"
              label={`Foto/Scan ${idType === "ktp" ? "KTP" : "Halaman Paspor"}`}
              helpText="Pastikan seluruh area kartu terlihat, tidak buram atau terpotong."
              file={idDocument}
              onFile={setIdDocument}
              required
              error={errors.id_document?.[0]}
              existingUrl={profile?.id_document_url}
              existingLabel="Lihat dokumen tersimpan"
            />
          </fieldset>

          {/* Section 2: Bukti Alamat */}
          <fieldset className="min-w-0 space-y-4 border-t border-border pt-4">
            <legend className="-mt-6 bg-surface-container px-2 text-xs font-medium uppercase tracking-wider text-on-surface-muted">
              2. Bukti Alamat
            </legend>
            <FileUpload
              id="address-proof"
              label="Bukti Alamat"
              helpText="Utility bill (PLN/PDAM/telepon) atau rekening koran yang menampilkan nama & alamat, tanggal terbit < 3 bulan."
              file={addressProof}
              onFile={setAddressProof}
              required
              error={errors.address_proof?.[0]}
              existingUrl={profile?.address_proof_url}
            />
          </fieldset>

          {/* Section 3: Proof of Funds */}
          <fieldset className="min-w-0 space-y-4 border-t border-border pt-4">
            <legend className="-mt-6 bg-surface-container px-2 text-xs font-medium uppercase tracking-wider text-on-surface-muted">
              3. Proof of Funds
            </legend>
            <FileUpload
              id="proof-of-funds"
              label="Bukti Kemampuan Finansial"
              helpText="Surat referensi bank, mutasi rekening 3 bulan terakhir, atau statement bank &mdash; sebagai bukti dana pembelian tersedia."
              file={proofOfFunds}
              onFile={setProofOfFunds}
              required
              error={errors.proof_of_funds?.[0]}
              existingUrl={profile?.proof_of_funds_url}
            />
          </fieldset>

          {formError && (
            <div className="rounded-md border border-error/40 bg-error/5 p-3 text-sm text-error">
              {formError}
            </div>
          )}
          {success && (
            <div className="animate-fade-in rounded-md border border-success/40 bg-success/5 p-3 text-sm text-success">
              Dokumen tersubmit. Menunggu review admin.
            </div>
          )}

          <div className="flex items-center gap-3 border-t border-border pt-4">
            <button
              type="submit"
              disabled={isSubmitting}
              className="btn-gold rounded-md px-5 py-2 text-sm"
            >
              {isSubmitting ? "Mengunggah..." : isResubmit ? "Kirim Ulang Dokumen" : "Kirim untuk Direview"}
            </button>
            <p className="text-xs text-on-surface-muted">
              Review admin: biasanya 1&ndash;2 hari kerja.
            </p>
          </div>
        </form>
      )}
    </div>
  );
}
