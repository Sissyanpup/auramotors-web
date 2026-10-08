"use client";

import { useState, type FormEvent } from "react";
import { ApiError, apiFetch, toFormData } from "@/lib/api";
import type { ShippingMode } from "@/lib/types";
import FileUpload from "@/components/FileUpload";

type Props = {
  transactionId: number | string;
  onUpdate: () => void | Promise<void>;
};

const COUNTRIES: { code: string; label: string }[] = [
  { code: "ID", label: "ID - Indonesia" },
  { code: "SG", label: "SG - Singapore" },
  { code: "MY", label: "MY - Malaysia" },
  { code: "US", label: "US - United States" },
  { code: "AU", label: "AU - Australia" },
  { code: "JP", label: "JP - Japan" },
];

const MODES: { value: ShippingMode; label: string; hint: string }[] = [
  { value: "sea", label: "Laut", hint: "Kontainer / RoRo, biaya rendah, waktu lebih lama" },
  { value: "air", label: "Udara", hint: "Charter kargo pesawat, cepat, biaya premium" },
  { value: "land", label: "Darat", hint: "Trailer lintas negara (Asia Tenggara)" },
];

export default function ShipmentInitForm({ transactionId, onUpdate }: Props) {
  const [originCountry, setOriginCountry] = useState("ID");
  const [destinationCountry, setDestinationCountry] = useState("SG");
  const [shippingMode, setShippingMode] = useState<ShippingMode>("sea");
  const [carrierName, setCarrierName] = useState("");
  const [trackingNumber, setTrackingNumber] = useState("");
  const [estimatedArrival, setEstimatedArrival] = useState("");
  const [cargoInsurerName, setCargoInsurerName] = useState("");
  const [cargoPolicyNumber, setCargoPolicyNumber] = useState("");
  const [cargoCoverageAmount, setCargoCoverageAmount] = useState("");
  const [cargoCertificate, setCargoCertificate] = useState<File | null>(null);

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setIsSubmitting(true);
    setError(null);
    setFieldErrors({});
    try {
      const body = toFormData({
        origin_country: originCountry,
        destination_country: destinationCountry,
        shipping_mode: shippingMode,
        carrier_name: carrierName,
        tracking_number: trackingNumber || undefined,
        estimated_arrival: estimatedArrival || undefined,
        cargo_insurer_name: cargoInsurerName || undefined,
        cargo_policy_number: cargoPolicyNumber || undefined,
        cargo_coverage_amount: cargoCoverageAmount || undefined,
        cargo_certificate: cargoCertificate ?? undefined,
      });
      await apiFetch(`/api/transactions/${transactionId}/shipment`, { method: "POST", body });
      await onUpdate();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
        if (err.errors) {
          const flat: Record<string, string> = {};
          for (const [key, msgs] of Object.entries(err.errors)) {
            flat[key] = Array.isArray(msgs) ? msgs[0] : String(msgs);
          }
          setFieldErrors(flat);
        }
      } else {
        setError("Gagal menginisiasi shipment.");
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <section className="rounded-lg border border-border bg-surface-container p-5">
      <div className="mb-4">
        <h2 className="font-display text-lg text-on-surface">Logistik & Pengiriman</h2>
        <p className="mt-0.5 text-xs text-on-surface-muted">
          Inisiasi shipment untuk transaksi cross-border. Setelah dibuat, dokumen ekspor, bea cukai,
          dan penerimaan bisa diunggah bertahap.
        </p>
      </div>

      {error && (
        <div className="mb-4 rounded-md border border-error/40 bg-error/5 p-3 text-sm text-error">{error}</div>
      )}

      <form onSubmit={handleSubmit} className="space-y-5">
        {/* Countries */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label htmlFor="origin_country" className="block text-sm font-medium text-on-surface">
              Negara Asal <span className="text-error">*</span>
            </label>
            <select
              id="origin_country"
              value={originCountry}
              onChange={(e) => setOriginCountry(e.target.value)}
              required
              className="input-field mt-1.5"
            >
              {COUNTRIES.map((c) => (
                <option key={c.code} value={c.code}>
                  {c.label}
                </option>
              ))}
            </select>
            {fieldErrors.origin_country && (
              <p className="mt-1 text-xs text-error">{fieldErrors.origin_country}</p>
            )}
          </div>
          <div>
            <label htmlFor="destination_country" className="block text-sm font-medium text-on-surface">
              Negara Tujuan <span className="text-error">*</span>
            </label>
            <select
              id="destination_country"
              value={destinationCountry}
              onChange={(e) => setDestinationCountry(e.target.value)}
              required
              className="input-field mt-1.5"
            >
              {COUNTRIES.map((c) => (
                <option key={c.code} value={c.code}>
                  {c.label}
                </option>
              ))}
            </select>
            {fieldErrors.destination_country && (
              <p className="mt-1 text-xs text-error">{fieldErrors.destination_country}</p>
            )}
          </div>
        </div>

        {/* Shipping mode radio cards */}
        <div>
          <span className="block text-sm font-medium text-on-surface">
            Moda Pengiriman <span className="text-error">*</span>
          </span>
          <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
            {MODES.map((m) => {
              const active = shippingMode === m.value;
              return (
                <label
                  key={m.value}
                  className={`flex cursor-pointer flex-col gap-1 rounded-md border p-3 transition-colors ${
                    active
                      ? "border-primary bg-primary-container/10"
                      : "border-border bg-surface hover:border-primary/60"
                  }`}
                >
                  <div className="flex items-center gap-2">
                    <input
                      type="radio"
                      name="shipping_mode"
                      value={m.value}
                      checked={active}
                      onChange={() => setShippingMode(m.value)}
                      className="h-3.5 w-3.5"
                    />
                    <span className="text-sm font-medium text-on-surface">{m.label}</span>
                  </div>
                  <span className="pl-5 text-[11px] text-on-surface-muted">{m.hint}</span>
                </label>
              );
            })}
          </div>
          {fieldErrors.shipping_mode && (
            <p className="mt-1 text-xs text-error">{fieldErrors.shipping_mode}</p>
          )}
        </div>

        {/* Carrier + tracking + ETA */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label htmlFor="carrier_name" className="block text-sm font-medium text-on-surface">
              Nama Carrier <span className="text-error">*</span>
            </label>
            <input
              id="carrier_name"
              type="text"
              required
              value={carrierName}
              onChange={(e) => setCarrierName(e.target.value)}
              placeholder="mis. Global Auto Lines"
              className="input-field mt-1.5"
            />
            {fieldErrors.carrier_name && (
              <p className="mt-1 text-xs text-error">{fieldErrors.carrier_name}</p>
            )}
          </div>
          <div>
            <label htmlFor="tracking_number" className="block text-sm font-medium text-on-surface">
              Nomor Tracking (opsional)
            </label>
            <input
              id="tracking_number"
              type="text"
              value={trackingNumber}
              onChange={(e) => setTrackingNumber(e.target.value)}
              placeholder="mis. MSCU1234567"
              className="input-field mt-1.5"
            />
            {fieldErrors.tracking_number && (
              <p className="mt-1 text-xs text-error">{fieldErrors.tracking_number}</p>
            )}
          </div>
          <div>
            <label htmlFor="estimated_arrival" className="block text-sm font-medium text-on-surface">
              Estimasi Tiba (opsional)
            </label>
            <input
              id="estimated_arrival"
              type="date"
              value={estimatedArrival}
              onChange={(e) => setEstimatedArrival(e.target.value)}
              className="input-field mt-1.5"
            />
            {fieldErrors.estimated_arrival && (
              <p className="mt-1 text-xs text-error">{fieldErrors.estimated_arrival}</p>
            )}
          </div>
        </div>

        {/* Cargo Insurance */}
        <div className="rounded-md border border-border bg-surface p-4">
          <h3 className="text-sm font-semibold text-on-surface">Asuransi Kargo (opsional)</h3>
          <p className="mt-0.5 text-xs text-on-surface-muted">
            Isi semua kolom untuk mengaktifkan status &quot;Lengkap&quot;.
          </p>
          <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
              <label htmlFor="cargo_insurer_name" className="block text-sm font-medium text-on-surface">
                Insurer
              </label>
              <input
                id="cargo_insurer_name"
                type="text"
                value={cargoInsurerName}
                onChange={(e) => setCargoInsurerName(e.target.value)}
                placeholder="Marine Insure Co"
                className="input-field mt-1.5"
              />
              {fieldErrors.cargo_insurer_name && (
                <p className="mt-1 text-xs text-error">{fieldErrors.cargo_insurer_name}</p>
              )}
            </div>
            <div>
              <label htmlFor="cargo_policy_number" className="block text-sm font-medium text-on-surface">
                No. Polis
              </label>
              <input
                id="cargo_policy_number"
                type="text"
                value={cargoPolicyNumber}
                onChange={(e) => setCargoPolicyNumber(e.target.value)}
                placeholder="MI-2026-001"
                className="input-field mt-1.5"
              />
              {fieldErrors.cargo_policy_number && (
                <p className="mt-1 text-xs text-error">{fieldErrors.cargo_policy_number}</p>
              )}
            </div>
            <div>
              <label htmlFor="cargo_coverage_amount" className="block text-sm font-medium text-on-surface">
                Coverage (IDR)
              </label>
              <input
                id="cargo_coverage_amount"
                type="number"
                min="0"
                step="1"
                value={cargoCoverageAmount}
                onChange={(e) => setCargoCoverageAmount(e.target.value)}
                placeholder="100000000"
                className="input-field mt-1.5"
              />
              {fieldErrors.cargo_coverage_amount && (
                <p className="mt-1 text-xs text-error">{fieldErrors.cargo_coverage_amount}</p>
              )}
            </div>
          </div>
          <div className="mt-4">
            <FileUpload
              id="cargo_certificate"
              label="Sertifikat Asuransi (opsional)"
              helpText="Unggah sertifikat polis dalam format PDF atau gambar."
              file={cargoCertificate}
              onFile={setCargoCertificate}
              error={fieldErrors.cargo_certificate}
            />
          </div>
        </div>

        <div className="flex justify-end">
          <button
            type="submit"
            disabled={isSubmitting}
            className="btn-gold rounded-md px-4 py-2 text-sm font-medium disabled:opacity-60"
          >
            {isSubmitting ? "Menginisiasi..." : "Inisiasi Shipment untuk Cross-Border"}
          </button>
        </div>
      </form>
    </section>
  );
}
