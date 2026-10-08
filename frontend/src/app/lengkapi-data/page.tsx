"use client";

import { useEffect, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { ApiError, apiFetch } from "@/lib/api";
import { useAuth } from "@/contexts/auth-context";
import ConfirmModal from "@/components/ConfirmModal";
import type { User } from "@/lib/types";

export default function LengkapiDataPage() {
  const { user, isLoading, refresh } = useAuth();
  const router = useRouter();

  const [ktpNumber, setKtpNumber] = useState("");
  const [ktpName, setKtpName] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    if (!isLoading && !user) {
      router.replace("/login");
    }
  }, [isLoading, user, router]);

  useEffect(() => {
    if (user) {
      setKtpNumber(user.ktp_number ?? "");
      setKtpName(user.ktp_name ?? user.name ?? "");
    }
  }, [user]);

  function handleSubmit(e: FormEvent) {
    e.preventDefault();

    const nextErrors: Record<string, string[]> = {};
    if (!/^\d{16}$/.test(ktpNumber)) {
      nextErrors.ktp_number = ["NIK harus terdiri dari 16 digit angka."];
    }
    if (ktpName.trim().length < 3) {
      nextErrors.ktp_name = ["Nama sesuai KTP wajib diisi."];
    }
    if (Object.keys(nextErrors).length > 0) {
      setErrors(nextErrors);
      return;
    }

    setErrors({});
    setShowConfirm(true);
  }

  async function executeSubmit() {
    setShowConfirm(false);
    setFormError(null);
    setSuccess(false);
    setIsSubmitting(true);

    try {
      await apiFetch<{ data: User }>("/api/auth/profile/ktp", {
        method: "PUT",
        body: { ktp_number: ktpNumber, ktp_name: ktpName.trim() },
      });
      await refresh();
      setSuccess(true);
      setTimeout(() => router.push("/"), 900);
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

  if (isLoading || !user) {
    return <p className="p-8 text-sm text-on-surface-muted">Memuat...</p>;
  }

  const alreadyCompleted = user.has_completed_ktp;

  return (
    <div className="mx-auto max-w-xl px-4 py-10">
      <h1 className="font-display text-2xl text-on-surface">Lengkapi Data KTP</h1>
      <p className="mt-1 text-sm text-on-surface-muted">
        Data ini dipakai untuk verifikasi identitas dan pembuatan nota resmi transaksi.
      </p>

      <div className="mt-6 rounded-lg border border-warning/40 bg-warning/5 p-4 text-sm text-on-surface-muted">
        <p>
          <strong className="text-on-surface">Pastikan NIK & Nama sesuai KTP fisik.</strong>{" "}
          Data yang salah dapat menghambat pencairan dana escrow saat serah-terima kendaraan.
        </p>
      </div>

      <form onSubmit={handleSubmit} className="mt-6 space-y-5 rounded-lg border border-border bg-surface-container p-6">
        <div>
          <label htmlFor="ktp-number" className="block text-sm font-medium text-on-surface">
            NIK (Nomor Induk Kependudukan)
          </label>
          <input
            id="ktp-number"
            type="text"
            inputMode="numeric"
            pattern="\d{16}"
            maxLength={16}
            value={ktpNumber}
            onChange={(e) => setKtpNumber(e.target.value.replace(/\D/g, ""))}
            placeholder="16 digit angka"
            className="input-field mt-1.5 font-mono tracking-wider"
            required
          />
          <p className="mt-1 text-xs text-on-surface-muted">
            {ktpNumber.length}/16 digit
          </p>
          {errors.ktp_number && (
            <p className="mt-1 text-xs text-error">{errors.ktp_number[0]}</p>
          )}
        </div>

        <div>
          <label htmlFor="ktp-name" className="block text-sm font-medium text-on-surface">
            Nama Lengkap Sesuai KTP
          </label>
          <input
            id="ktp-name"
            type="text"
            value={ktpName}
            onChange={(e) => setKtpName(e.target.value.toUpperCase())}
            placeholder="MISAL: MUHAMMAD ZAIRUL ILMI"
            className="input-field mt-1.5 uppercase"
            required
          />
          {errors.ktp_name && (
            <p className="mt-1 text-xs text-error">{errors.ktp_name[0]}</p>
          )}
        </div>

        {formError && (
          <p className="text-sm text-error" role="alert">{formError}</p>
        )}
        {success && (
          <p className="animate-fade-in text-sm text-success">
            Data KTP tersimpan. Mengarahkan ke katalog...
          </p>
        )}

        <div className="flex items-center gap-3 border-t border-border pt-4">
          <button
            type="submit"
            disabled={isSubmitting}
            className="btn-gold rounded-md px-4 py-2 text-sm font-medium disabled:opacity-50"
          >
            {isSubmitting ? "Menyimpan..." : alreadyCompleted ? "Perbarui Data KTP" : "Simpan & Lanjut ke Katalog"}
          </button>
          {alreadyCompleted && (
            <span className="text-xs text-success">Data KTP sudah terverifikasi</span>
          )}
        </div>
      </form>

      {showConfirm && (
        <ConfirmModal
          title="Konfirmasi Data KTP"
          message={
            <div className="space-y-1.5">
              <p>Periksa kembali data berikut. Setelah tersimpan, ini menjadi identitas resmi transaksi kamu:</p>
              <div className="mt-2 rounded-md border border-border bg-surface p-3 font-mono text-xs">
                <p><span className="text-on-surface-muted">NIK:</span> <strong className="text-on-surface">{ktpNumber}</strong></p>
                <p className="mt-1"><span className="text-on-surface-muted">Nama:</span> <strong className="text-on-surface">{ktpName.toUpperCase()}</strong></p>
              </div>
            </div>
          }
          confirmLabel="Ya, Data Sudah Benar"
          isSubmitting={isSubmitting}
          onConfirm={executeSubmit}
          onCancel={() => setShowConfirm(false)}
        />
      )}
    </div>
  );
}
