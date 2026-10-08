"use client";

import { useEffect, useState, type FormEvent } from "react";
import { ApiError, apiFetch, toFormData } from "@/lib/api";
import type { SellerEntityType, SellerProfile } from "@/lib/types";
import StatusBadge from "@/components/StatusBadge";
import FileUpload from "@/components/FileUpload";
import { useAuth } from "@/contexts/auth-context";

export default function SellerKycPage() {
  const { refresh } = useAuth();
  const [profile, setProfile] = useState<SellerProfile | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [entityType, setEntityType] = useState<SellerEntityType>("individu");
  const [ktp, setKtp] = useState<File | null>(null);
  const [npwp, setNpwp] = useState<File | null>(null);
  const [companyRegistration, setCompanyRegistration] = useState<File | null>(null);
  const [articlesOfAssociation, setArticlesOfAssociation] = useState<File | null>(null);
  const [uboDeclaration, setUboDeclaration] = useState<File | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [bankName, setBankName] = useState("");
  const [bankAccountNumber, setBankAccountNumber] = useState("");
  const [bankAccountHolderName, setBankAccountHolderName] = useState("");
  const [bankErrors, setBankErrors] = useState<Record<string, string[]>>({});
  const [bankFormError, setBankFormError] = useState<string | null>(null);
  const [bankSuccess, setBankSuccess] = useState(false);
  const [isSavingBank, setIsSavingBank] = useState(false);

  async function loadProfile() {
    try {
      const { data } = await apiFetch<{ data: SellerProfile }>("/api/seller/kyc");
      setProfile(data);
      setEntityType(data.entity_type ?? "individu");
      setBankName(data.bank_name ?? "");
      setBankAccountNumber(data.bank_account_number ?? "");
      setBankAccountHolderName(data.bank_account_holder_name ?? "");
    } catch (error) {
      if (!(error instanceof ApiError && error.status === 404)) {
        setFormError("Gagal memuat status KYC.");
      }
    } finally {
      setIsLoading(false);
    }
  }

  async function handleBankSubmit(event: FormEvent) {
    event.preventDefault();
    setBankErrors({});
    setBankFormError(null);
    setBankSuccess(false);

    setIsSavingBank(true);
    try {
      const { data } = await apiFetch<{ data: SellerProfile }>("/api/seller/bank-account", {
        method: "PUT",
        body: {
          bank_name: bankName,
          bank_account_number: bankAccountNumber,
          bank_account_holder_name: bankAccountHolderName,
        },
      });
      setProfile(data);
      setBankSuccess(true);
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        setBankErrors(error.errors);
      } else if (error instanceof ApiError) {
        setBankFormError(error.message);
      }
    } finally {
      setIsSavingBank(false);
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
    if (!profile && !ktp) {
      localErrors.ktp = ["Foto KTP wajib diunggah."];
    }
    if (entityType === "perusahaan" && !profile) {
      if (!companyRegistration) localErrors.company_registration = ["Dokumen SIUP/NIB wajib diunggah."];
      if (!articlesOfAssociation) localErrors.articles_of_association = ["Akta pendirian wajib diunggah."];
      if (!uboDeclaration) localErrors.ubo_declaration = ["Deklarasi UBO wajib diunggah."];
    }
    if (Object.keys(localErrors).length > 0) {
      setErrors(localErrors);
      return;
    }

    setIsSubmitting(true);
    try {
      const formData = toFormData({
        entity_type: entityType,
        ktp,
        npwp,
        company_registration: entityType === "perusahaan" ? companyRegistration : null,
        articles_of_association: entityType === "perusahaan" ? articlesOfAssociation : null,
        ubo_declaration: entityType === "perusahaan" ? uboDeclaration : null,
      });
      const { data } = await apiFetch<{ data: SellerProfile }>("/api/seller/kyc", {
        method: profile ? "PUT" : "POST",
        body: formData,
      });
      setProfile(data);
      setKtp(null);
      setNpwp(null);
      setCompanyRegistration(null);
      setArticlesOfAssociation(null);
      setUboDeclaration(null);
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
        <h1 className="font-display text-2xl text-on-surface">Verifikasi KYC Seller</h1>
        <p className="mt-1.5 text-sm text-on-surface-muted">
          Wajib disetujui admin sebelum kamu bisa membuat listing kendaraan.
        </p>
      </div>

      <div className="mb-6 rounded-lg border border-warning/40 bg-warning/5 p-4 text-sm text-on-surface-muted">
        <p>
          <strong className="text-on-surface">Data disimpan terenkripsi.</strong>{" "}
          Untuk seller <em>perusahaan</em>, tambahan dokumen legal entitas (SIUP/NIB, Akta, UBO) wajib
          diunggah sesuai standar KYC/AML.
        </p>
      </div>

      {/* Status card */}
      {profile && (
        <div className="mb-6 rounded-lg border border-border bg-surface-container p-5">
          <div className="flex items-center gap-2">
            <span className="text-sm text-on-surface-muted">Status pengajuan:</span>
            <StatusBadge status={profile.status} />
          </div>
          <p className="mt-3 text-sm text-on-surface-muted">
            Tipe entitas:{" "}
            <span className="text-on-surface">
              {profile.entity_type === "perusahaan" ? "Perusahaan" : "Individu"}
            </span>
          </p>
          {profile.reviewed_at && (
            <p className="mt-1 text-xs text-on-surface-muted">
              Direview {new Date(profile.reviewed_at).toLocaleString("id-ID")}
              {profile.reviewed_by && ` oleh ${profile.reviewed_by}`}
            </p>
          )}

          {profile.status === "rejected" && profile.rejection_reason && (
            <div className="mt-4 rounded-md border border-error/40 bg-error/5 p-3 text-sm text-on-surface">
              <p className="font-medium text-error">Alasan penolakan:</p>
              <p className="mt-1">{profile.rejection_reason}</p>
            </div>
          )}

          {profile.status === "approved" && (
            <p className="mt-3 text-xs text-success">
              Verifikasi lolos &mdash; kamu sudah bisa membuat listing kendaraan.
            </p>
          )}
        </div>
      )}

      {/* Form KYC */}
      {canSubmit && (
        <form
          onSubmit={handleSubmit}
          className="space-y-6 rounded-lg border border-border bg-surface-container p-6"
        >
          <div>
            <h2 className="font-display text-lg text-on-surface">
              {isResubmit ? "Ajukan Ulang Dokumen" : "Data Identitas & Legalitas"}
            </h2>
            <p className="mt-0.5 text-xs text-on-surface-muted">
              File maksimum 5 MB (JPG/PNG/PDF).
            </p>
          </div>

          {/* Section 1: Tipe entitas */}
          <fieldset className="space-y-3 border-t border-border pt-4">
            <legend className="-mt-6 bg-surface-container px-2 text-xs font-medium uppercase tracking-wider text-on-surface-muted">
              1. Tipe Entitas
            </legend>
            <div className="grid grid-cols-2 gap-3">
              {(["individu", "perusahaan"] as const).map((value) => {
                const active = entityType === value;
                return (
                  <label
                    key={value}
                    className={`flex cursor-pointer flex-col items-start gap-1 rounded-md border px-4 py-3 transition-colors ${
                      active
                        ? "border-primary bg-primary-container/10"
                        : "border-border bg-surface hover:border-primary/40"
                    }`}
                  >
                    <input
                      type="radio"
                      name="entity_type"
                      value={value}
                      checked={active}
                      onChange={() => setEntityType(value)}
                      className="sr-only"
                    />
                    <span className={`text-sm font-medium ${active ? "text-primary" : "text-on-surface"}`}>
                      {value === "individu" ? "Individu" : "Perusahaan"}
                    </span>
                    <span className="text-xs text-on-surface-muted">
                      {value === "individu"
                        ? "Perorangan / pengguna pribadi"
                        : "PT, CV, atau dealer resmi"}
                    </span>
                  </label>
                );
              })}
            </div>
          </fieldset>

          {/* Section 2: Dokumen Identitas */}
          <fieldset className="space-y-4 border-t border-border pt-4">
            <legend className="-mt-6 bg-surface-container px-2 text-xs font-medium uppercase tracking-wider text-on-surface-muted">
              2. Dokumen Identitas
            </legend>

            <FileUpload
              id="seller-ktp"
              label="Foto KTP"
              helpText={
                entityType === "perusahaan"
                  ? "KTP penanggung jawab / direktur perusahaan."
                  : "KTP pemilik akun. Pastikan seluruh area kartu terlihat."
              }
              file={ktp}
              onFile={setKtp}
              required={!profile}
              error={errors.ktp?.[0]}
              existingUrl={profile?.ktp_url}
              existingLabel="Lihat KTP tersimpan"
            />

            <FileUpload
              id="seller-npwp"
              label={`NPWP ${entityType === "perusahaan" ? "(disarankan untuk perusahaan)" : "(opsional)"}`}
              helpText="Untuk keperluan pelaporan pajak transaksi."
              file={npwp}
              onFile={setNpwp}
              error={errors.npwp?.[0]}
              existingUrl={profile?.npwp_url}
              existingLabel="Lihat NPWP tersimpan"
            />
          </fieldset>

          {/* Section 3: Dokumen Perusahaan — conditional */}
          {entityType === "perusahaan" && (
            <fieldset className="space-y-4 border-t border-border pt-4">
              <legend className="-mt-6 bg-surface-container px-2 text-xs font-medium uppercase tracking-wider text-on-surface-muted">
                3. Dokumen Legal Perusahaan
              </legend>

              <FileUpload
                id="company-registration"
                label="SIUP / NIB (Company Registration)"
                helpText="Nomor Induk Berusaha atau Surat Izin Usaha Perdagangan yang masih berlaku."
                file={companyRegistration}
                onFile={setCompanyRegistration}
                required={!profile}
                error={errors.company_registration?.[0]}
                existingUrl={profile?.company_registration_url}
                existingLabel="Lihat SIUP/NIB tersimpan"
              />

              <FileUpload
                id="articles-of-association"
                label="Akta Pendirian (Articles of Association)"
                helpText="Akta notaris pendirian PT/CV beserta perubahan terakhir."
                file={articlesOfAssociation}
                onFile={setArticlesOfAssociation}
                required={!profile}
                error={errors.articles_of_association?.[0]}
                existingUrl={profile?.articles_of_association_url}
                existingLabel="Lihat Akta tersimpan"
              />

              <FileUpload
                id="ubo-declaration"
                label="Deklarasi UBO (Ultimate Beneficial Owner)"
                helpText="Surat pernyataan Pemilik Manfaat Akhir sesuai Perpres 13/2018."
                file={uboDeclaration}
                onFile={setUboDeclaration}
                required={!profile}
                error={errors.ubo_declaration?.[0]}
                existingUrl={profile?.ubo_declaration_url}
                existingLabel="Lihat UBO tersimpan"
              />
            </fieldset>
          )}

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
            <p className="text-xs text-on-surface-muted">Review admin: 1&ndash;2 hari kerja.</p>
          </div>
        </form>
      )}

      {/* Form Rekening Bank */}
      {profile && (
        <div className="mt-8">
          <h2 className="font-display text-xl text-on-surface">Rekening Bank untuk Payout</h2>
          <p className="mt-1 text-sm text-on-surface-muted">
            Dana hasil penjualan akan ditransfer admin ke rekening ini setelah serah-terima. Bisa diperbarui
            kapan pun tanpa perlu review ulang KYC.
          </p>

          <form
            onSubmit={handleBankSubmit}
            className="mt-4 space-y-4 rounded-lg border border-border bg-surface-container p-6"
          >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-[180px_1fr]">
              <div>
                <label htmlFor="bank-name" className="block text-sm font-medium text-on-surface">
                  Nama Bank
                </label>
                <input
                  id="bank-name"
                  type="text"
                  value={bankName}
                  onChange={(e) => setBankName(e.target.value)}
                  placeholder="mis. BCA, Mandiri, BNI"
                  className="input-field mt-1.5"
                />
                {bankErrors.bank_name && (
                  <p className="mt-1 text-xs text-error">{bankErrors.bank_name[0]}</p>
                )}
              </div>

              <div>
                <label htmlFor="bank-account-number" className="block text-sm font-medium text-on-surface">
                  Nomor Rekening
                </label>
                <input
                  id="bank-account-number"
                  type="text"
                  inputMode="numeric"
                  value={bankAccountNumber}
                  onChange={(e) => setBankAccountNumber(e.target.value.replace(/\D/g, ""))}
                  placeholder="Nomor rekening (angka saja)"
                  className="input-field mt-1.5 font-mono tracking-wider"
                />
                {bankErrors.bank_account_number && (
                  <p className="mt-1 text-xs text-error">{bankErrors.bank_account_number[0]}</p>
                )}
              </div>
            </div>

            <div>
              <label htmlFor="bank-holder" className="block text-sm font-medium text-on-surface">
                Nama Pemilik Rekening
              </label>
              <input
                id="bank-holder"
                type="text"
                value={bankAccountHolderName}
                onChange={(e) => setBankAccountHolderName(e.target.value.toUpperCase())}
                placeholder="Nama sesuai buku tabungan"
                className="input-field mt-1.5 uppercase"
              />
              <p className="mt-1 text-xs text-on-surface-muted">
                Harus persis sama dengan nama di buku tabungan/statement bank.
              </p>
              {bankErrors.bank_account_holder_name && (
                <p className="mt-1 text-xs text-error">{bankErrors.bank_account_holder_name[0]}</p>
              )}
            </div>

            {bankFormError && (
              <div className="rounded-md border border-error/40 bg-error/5 p-3 text-sm text-error">
                {bankFormError}
              </div>
            )}
            {bankSuccess && (
              <div className="animate-fade-in rounded-md border border-success/40 bg-success/5 p-3 text-sm text-success">
                Rekening bank tersimpan.
              </div>
            )}

            <div className="border-t border-border pt-4">
              <button
                type="submit"
                disabled={isSavingBank}
                className="btn-gold rounded-md px-5 py-2 text-sm"
              >
                {isSavingBank ? "Menyimpan..." : "Simpan Rekening"}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
