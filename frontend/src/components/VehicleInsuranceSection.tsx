"use client";

import { useState, type FormEvent } from "react";
import { ApiError, apiFetch, toFormData } from "@/lib/api";
import Badge from "@/components/Badge";
import ConfirmModal from "@/components/ConfirmModal";
import FileUpload from "@/components/FileUpload";
import { formatRupiah } from "@/lib/format";
import type {
  VehicleDetail,
  VehicleInsurancePolicy,
  VehiclePolicyType,
} from "@/lib/types";

type Props = {
  vehicle: VehicleDetail;
  onVehicleUpdated: (v: VehicleDetail) => void;
};

const POLICY_TYPE_OPTIONS: { value: VehiclePolicyType; label: string; description: string }[] = [
  {
    value: "all_risk",
    label: "All Risk",
    description: "Melindungi hampir semua risiko kerusakan, dari lecet ringan sampai kehilangan.",
  },
  {
    value: "tlo",
    label: "TLO (Total Loss Only)",
    description: "Hanya menanggung kerusakan >75% atau kehilangan total.",
  },
  {
    value: "agreed_value",
    label: "Agreed Value",
    description: "Nilai pertanggungan disepakati di muka — cocok untuk kendaraan koleksi/langka.",
  },
];

function formatDateID(iso: string | null): string {
  if (!iso) return "-";
  return new Intl.DateTimeFormat("id-ID", { dateStyle: "medium" }).format(new Date(iso));
}

export default function VehicleInsuranceSection({ vehicle, onVehicleUpdated }: Props) {
  const [showForm, setShowForm] = useState(false);
  const [policyType, setPolicyType] = useState<VehiclePolicyType>("all_risk");
  const [insurerName, setInsurerName] = useState("");
  const [policyNumber, setPolicyNumber] = useState("");
  const [coverageAmount, setCoverageAmount] = useState("");
  const [agreedValueAmount, setAgreedValueAmount] = useState("");
  const [validFrom, setValidFrom] = useState("");
  const [validUntil, setValidUntil] = useState("");
  const [certificate, setCertificate] = useState<File | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const [deleteTarget, setDeleteTarget] = useState<VehicleInsurancePolicy | null>(null);
  const [deleting, setDeleting] = useState(false);

  const policies = vehicle.insurance_policies ?? [];

  async function reload() {
    const { data } = await apiFetch<{ data: VehicleDetail }>(
      `/api/seller/vehicles/${vehicle.id}`
    );
    onVehicleUpdated(data);
  }

  function resetForm() {
    setPolicyType("all_risk");
    setInsurerName("");
    setPolicyNumber("");
    setCoverageAmount("");
    setAgreedValueAmount("");
    setValidFrom("");
    setValidUntil("");
    setCertificate(null);
    setErrors({});
    setFormError(null);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setErrors({});
    setFormError(null);

    if (!certificate) {
      setErrors({ certificate: ["Sertifikat polis wajib diunggah."] });
      return;
    }

    setSubmitting(true);
    try {
      const formData = toFormData({
        policy_type: policyType,
        insurer_name: insurerName,
        policy_number: policyNumber,
        coverage_amount: coverageAmount,
        agreed_value_amount: policyType === "agreed_value" ? agreedValueAmount : null,
        valid_from: validFrom,
        valid_until: validUntil,
        certificate,
      });
      await apiFetch(`/api/seller/vehicles/${vehicle.id}/insurance`, {
        method: "POST",
        body: formData,
      });
      resetForm();
      setShowForm(false);
      await reload();
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        setErrors(error.errors);
      } else if (error instanceof ApiError) {
        setFormError(error.message);
      } else {
        setFormError("Gagal menyimpan polis.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete() {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      await apiFetch(
        `/api/seller/vehicles/${vehicle.id}/insurance/${deleteTarget.id}`,
        { method: "DELETE" }
      );
      setDeleteTarget(null);
      await reload();
    } catch {
      // keep modal for retry
    } finally {
      setDeleting(false);
    }
  }

  return (
    <>
      <fieldset className="space-y-4 border-t border-border pt-4">
        <legend className="-mt-6 bg-surface-container px-2 text-xs font-medium uppercase tracking-wider text-on-surface-muted">
          Polis Asuransi Kendaraan
        </legend>

        {policies.length === 0 ? (
          <p className="text-xs text-on-surface-muted">
            Belum ada polis asuransi terdaftar untuk kendaraan ini.
          </p>
        ) : (
          <ul className="space-y-3">
            {policies.map((policy) => (
              <li
                key={policy.id}
                className="rounded-md border border-border bg-surface p-4"
              >
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge tone="primary">{policy.policy_type_label}</Badge>
                      {policy.is_active ? (
                        <Badge tone="success">Aktif</Badge>
                      ) : (
                        <Badge tone="neutral">Kedaluwarsa</Badge>
                      )}
                    </div>
                    <p className="mt-2 text-sm font-medium text-on-surface">
                      {policy.insurer_name}
                    </p>
                    <p className="text-xs text-on-surface-muted">
                      No. Polis: <span className="font-mono">{policy.policy_number}</span>
                    </p>
                    <dl className="mt-3 grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                      <div>
                        <dt className="text-on-surface-muted">Nilai Pertanggungan</dt>
                        <dd className="font-medium text-on-surface">
                          {formatRupiah(policy.coverage_amount)}
                        </dd>
                      </div>
                      {policy.agreed_value_amount && (
                        <div>
                          <dt className="text-on-surface-muted">Nilai Disepakati</dt>
                          <dd className="font-medium text-on-surface">
                            {formatRupiah(policy.agreed_value_amount)}
                          </dd>
                        </div>
                      )}
                      <div>
                        <dt className="text-on-surface-muted">Berlaku</dt>
                        <dd className="text-on-surface">
                          {formatDateID(policy.valid_from)} &rarr; {formatDateID(policy.valid_until)}
                        </dd>
                      </div>
                    </dl>
                  </div>
                  <div className="flex flex-shrink-0 flex-col items-end gap-2">
                    <a
                      href={policy.certificate_url}
                      target="_blank"
                      rel="noreferrer"
                      className="rounded-md border border-primary/60 px-3 py-1.5 text-xs font-medium text-primary transition-colors hover:bg-primary-container/20"
                    >
                      Unduh Sertifikat
                    </a>
                    <button
                      type="button"
                      onClick={() => setDeleteTarget(policy)}
                      className="rounded-md border border-border px-3 py-1.5 text-xs text-on-surface-muted transition-colors hover:border-error hover:text-error"
                    >
                      Hapus
                    </button>
                  </div>
                </div>
              </li>
            ))}
          </ul>
        )}

        {!showForm ? (
          <button
            type="button"
            onClick={() => setShowForm(true)}
            className="btn-gold rounded-md px-4 py-2 text-sm"
          >
            + Tambah Polis Baru
          </button>
        ) : (
          <form
            onSubmit={handleSubmit}
            className="space-y-4 rounded-md border border-border bg-surface p-4"
          >
            <div>
              <label className="block text-sm font-medium text-on-surface">Jenis Polis</label>
              <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                {POLICY_TYPE_OPTIONS.map((opt) => {
                  const active = policyType === opt.value;
                  return (
                    <label
                      key={opt.value}
                      className={`flex cursor-pointer flex-col gap-1 rounded-md border px-3 py-2 transition-colors ${
                        active
                          ? "border-primary bg-primary-container/10"
                          : "border-border bg-surface hover:border-primary/40"
                      }`}
                    >
                      <input
                        type="radio"
                        name="policy_type"
                        value={opt.value}
                        checked={active}
                        onChange={() => setPolicyType(opt.value)}
                        className="sr-only"
                      />
                      <span
                        className={`text-sm font-medium ${
                          active ? "text-primary" : "text-on-surface"
                        }`}
                      >
                        {opt.label}
                      </span>
                      <span className="text-xs text-on-surface-muted">{opt.description}</span>
                    </label>
                  );
                })}
              </div>
              {errors.policy_type && (
                <p className="mt-1 text-xs text-error">{errors.policy_type[0]}</p>
              )}
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label htmlFor="insurer-name" className="block text-sm font-medium text-on-surface">
                  Nama Perusahaan Asuransi
                </label>
                <input
                  id="insurer-name"
                  type="text"
                  required
                  value={insurerName}
                  onChange={(e) => setInsurerName(e.target.value)}
                  placeholder="mis. Allianz, ACA, Zurich"
                  className="input-field mt-1.5"
                />
                {errors.insurer_name && (
                  <p className="mt-1 text-xs text-error">{errors.insurer_name[0]}</p>
                )}
              </div>
              <div>
                <label htmlFor="policy-number" className="block text-sm font-medium text-on-surface">
                  Nomor Polis
                </label>
                <input
                  id="policy-number"
                  type="text"
                  required
                  value={policyNumber}
                  onChange={(e) => setPolicyNumber(e.target.value)}
                  className="input-field mt-1.5 font-mono"
                />
                {errors.policy_number && (
                  <p className="mt-1 text-xs text-error">{errors.policy_number[0]}</p>
                )}
              </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label htmlFor="coverage-amount" className="block text-sm font-medium text-on-surface">
                  Nilai Pertanggungan (Rp)
                </label>
                <input
                  id="coverage-amount"
                  type="number"
                  required
                  min="0"
                  step="1"
                  value={coverageAmount}
                  onChange={(e) => setCoverageAmount(e.target.value)}
                  className="input-field mt-1.5"
                />
                {errors.coverage_amount && (
                  <p className="mt-1 text-xs text-error">{errors.coverage_amount[0]}</p>
                )}
              </div>
              {policyType === "agreed_value" && (
                <div>
                  <label htmlFor="agreed-value-amount" className="block text-sm font-medium text-on-surface">
                    Nilai Disepakati (Rp)
                  </label>
                  <input
                    id="agreed-value-amount"
                    type="number"
                    required
                    min="0"
                    step="1"
                    value={agreedValueAmount}
                    onChange={(e) => setAgreedValueAmount(e.target.value)}
                    className="input-field mt-1.5"
                  />
                  <p className="mt-1 text-xs text-on-surface-muted">
                    Wajib untuk polis Agreed Value.
                  </p>
                  {errors.agreed_value_amount && (
                    <p className="mt-1 text-xs text-error">{errors.agreed_value_amount[0]}</p>
                  )}
                </div>
              )}
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label htmlFor="valid-from" className="block text-sm font-medium text-on-surface">
                  Berlaku Sejak
                </label>
                <input
                  id="valid-from"
                  type="date"
                  required
                  value={validFrom}
                  onChange={(e) => setValidFrom(e.target.value)}
                  className="input-field mt-1.5"
                />
                {errors.valid_from && (
                  <p className="mt-1 text-xs text-error">{errors.valid_from[0]}</p>
                )}
              </div>
              <div>
                <label htmlFor="valid-until" className="block text-sm font-medium text-on-surface">
                  Berlaku Sampai
                </label>
                <input
                  id="valid-until"
                  type="date"
                  required
                  value={validUntil}
                  onChange={(e) => setValidUntil(e.target.value)}
                  className="input-field mt-1.5"
                />
                {errors.valid_until && (
                  <p className="mt-1 text-xs text-error">{errors.valid_until[0]}</p>
                )}
              </div>
            </div>

            <FileUpload
              id="policy-certificate"
              label="Sertifikat Polis (PDF/JPG/PNG)"
              helpText="Unggah salinan sertifikat polis untuk verifikasi."
              file={certificate}
              onFile={setCertificate}
              required
              error={errors.certificate?.[0]}
            />

            {formError && (
              <p className="rounded-md border border-error/40 bg-error/5 p-3 text-sm text-error">
                {formError}
              </p>
            )}

            <div className="flex items-center gap-2 border-t border-border pt-4">
              <button
                type="submit"
                disabled={submitting}
                className="btn-gold rounded-md px-4 py-2 text-sm"
              >
                {submitting ? "Menyimpan..." : "Simpan Polis"}
              </button>
              <button
                type="button"
                onClick={() => {
                  resetForm();
                  setShowForm(false);
                }}
                disabled={submitting}
                className="rounded-md px-3 py-2 text-sm text-on-surface-muted hover:text-on-surface disabled:opacity-60"
              >
                Batal
              </button>
            </div>
          </form>
        )}
      </fieldset>

      {deleteTarget && (
        <ConfirmModal
          title="Hapus polis asuransi?"
          message={
            <>
              Polis <span className="font-mono">{deleteTarget.policy_number}</span> ({" "}
              {deleteTarget.insurer_name}) akan dihapus permanen dari kendaraan ini.
            </>
          }
          confirmLabel="Ya, Hapus"
          isSubmitting={deleting}
          onConfirm={handleDelete}
          onCancel={() => setDeleteTarget(null)}
        />
      )}
    </>
  );
}
