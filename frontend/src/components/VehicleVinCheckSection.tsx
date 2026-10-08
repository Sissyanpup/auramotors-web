"use client";

import { useState } from "react";
import { ApiError, apiFetch } from "@/lib/api";
import Badge, { type BadgeTone } from "@/components/Badge";
import { formatDate } from "@/lib/format";
import type { VehicleDetail, VehicleVinCheck, VinCheckStatus } from "@/lib/types";

type Props = {
  vehicle: VehicleDetail;
  onVehicleUpdated: (v: VehicleDetail) => void;
};

const STATUS_TONE: Record<VinCheckStatus, BadgeTone> = {
  clean: "success",
  warning: "warning",
  blocked: "danger",
};

const FLAG_LABELS: Record<string, string> = {
  reported_stolen: "Dilaporkan Curian",
  odometer_rollback: "Terindikasi Rollback Odometer",
  salvage_title: "Bekas Kecelakaan Total (Salvage)",
  outstanding_lien: "Ada Kredit/Leasing Aktif",
  duplicate_vin: "VIN Duplikat",
  flood_damage: "Bekas Banjir",
  export_only: "Kendaraan untuk Ekspor",
  invalid_check_digit: "Digit Kontrol VIN Tidak Valid",
};

function humanizeFlag(flag: string): string {
  return (
    FLAG_LABELS[flag] ??
    flag
      .split("_")
      .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
      .join(" ")
  );
}

export default function VehicleVinCheckSection({ vehicle, onVehicleUpdated }: Props) {
  const [vin, setVin] = useState(vehicle.vin ?? "");
  const [savingVin, setSavingVin] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [saveSuccess, setSaveSuccess] = useState(false);

  const [checking, setChecking] = useState(false);
  const [checkError, setCheckError] = useState<string | null>(null);
  const [latest, setLatest] = useState<VehicleVinCheck | null>(vehicle.latest_vin_check ?? null);

  const vinPattern = /^[A-HJ-NPR-Z0-9]{17}$/;
  const isValidVin = vinPattern.test(vin);

  async function handleSaveVin() {
    setSaveError(null);
    setSaveSuccess(false);
    if (!isValidVin) {
      setSaveError("VIN harus 17 karakter alfanumerik (tanpa I/O/Q).");
      return;
    }
    setSavingVin(true);
    try {
      const formData = new FormData();
      formData.append("vin", vin);
      formData.append("_method", "PUT");
      const { data } = await apiFetch<{ data: VehicleDetail }>(
        `/api/seller/vehicles/${vehicle.id}`,
        {
          method: "POST",
          body: formData,
        }
      );
      onVehicleUpdated(data);
      setSaveSuccess(true);
    } catch (error) {
      if (error instanceof ApiError) {
        setSaveError(error.errors?.vin?.[0] ?? error.message);
      } else {
        setSaveError("Gagal menyimpan VIN.");
      }
    } finally {
      setSavingVin(false);
    }
  }

  async function handleRunCheck() {
    setCheckError(null);
    if (!isValidVin) {
      setCheckError("Simpan VIN valid terlebih dulu sebelum menjalankan cek.");
      return;
    }
    setChecking(true);
    try {
      const { data } = await apiFetch<{ data: VehicleVinCheck }>(
        `/api/seller/vehicles/${vehicle.id}/vin-checks`,
        {
          method: "POST",
          body: { vin },
        }
      );
      setLatest(data);
    } catch (error) {
      if (error instanceof ApiError) {
        setCheckError(error.errors?.vin?.[0] ?? error.message);
      } else {
        setCheckError("Gagal menjalankan cek VIN.");
      }
    } finally {
      setChecking(false);
    }
  }

  return (
    <fieldset className="space-y-4 border-t border-border pt-4">
      <legend className="-mt-6 bg-surface-container px-2 text-xs font-medium uppercase tracking-wider text-on-surface-muted">
        Nomor VIN &amp; Riwayat
      </legend>

      <div>
        <label htmlFor="vehicle-vin" className="block text-sm font-medium text-on-surface">
          VIN (Vehicle Identification Number)
        </label>
        <p className="mt-0.5 text-xs text-on-surface-muted">
          17 karakter alfanumerik. Huruf <span className="font-mono">I</span>, <span className="font-mono">O</span>, dan{" "}
          <span className="font-mono">Q</span> tidak dipakai untuk menghindari kekeliruan dengan angka.
        </p>
        <div className="mt-2 flex flex-col gap-2 sm:flex-row">
          <input
            id="vehicle-vin"
            value={vin}
            onChange={(e) => setVin(e.target.value.toUpperCase().replace(/\s/g, ""))}
            maxLength={17}
            spellCheck={false}
            autoComplete="off"
            placeholder="1HGBH41JXMN109186"
            className="input-field flex-1 font-mono uppercase tracking-wider"
          />
          <button
            type="button"
            onClick={handleSaveVin}
            disabled={savingVin || !isValidVin || vin === (vehicle.vin ?? "")}
            className="rounded-md border border-border bg-surface px-4 py-2 text-sm text-on-surface transition-colors hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-50"
          >
            {savingVin ? "Menyimpan..." : "Simpan VIN"}
          </button>
          <button
            type="button"
            onClick={handleRunCheck}
            disabled={checking || !isValidVin}
            className="btn-gold rounded-md px-4 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
          >
            {checking ? "Memeriksa..." : "Cek Riwayat VIN"}
          </button>
        </div>
        {saveError && <p className="mt-1.5 text-xs text-error">{saveError}</p>}
        {saveSuccess && !saveError && (
          <p className="mt-1.5 text-xs text-success">VIN tersimpan.</p>
        )}
        {checkError && <p className="mt-1.5 text-xs text-error">{checkError}</p>}
      </div>

      {latest ? (
        <div className="rounded-md border border-border bg-surface p-4">
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-sm text-on-surface-muted">Status pemeriksaan terakhir:</span>
            <Badge tone={STATUS_TONE[latest.status]}>{latest.status_label}</Badge>
            <span className="text-xs text-on-surface-muted">
              &middot; {formatDate(latest.checked_at)}
            </span>
          </div>
          <p className="mt-2 text-xs text-on-surface-muted">
            VIN diperiksa: <span className="font-mono text-on-surface">{latest.vin}</span>
          </p>

          {latest.report.flags.length > 0 && (
            <div className="mt-3">
              <p className="text-xs font-semibold uppercase tracking-wider text-on-surface-muted">
                Temuan:
              </p>
              <ul className="mt-2 space-y-1.5 text-sm">
                {latest.report.flags.map((flag) => (
                  <li key={flag} className="flex items-start gap-2">
                    <span
                      aria-hidden
                      className={`mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full ${
                        latest.status === "blocked" ? "bg-error" : "bg-warning"
                      }`}
                    />
                    <span className="text-on-surface">{humanizeFlag(flag)}</span>
                  </li>
                ))}
              </ul>
            </div>
          )}

          {latest.report.notes && (
            <p className="mt-3 rounded-md border border-border bg-surface-container p-3 text-xs text-on-surface-muted">
              {latest.report.notes}
            </p>
          )}
        </div>
      ) : (
        <p className="text-xs text-on-surface-muted">
          Belum ada riwayat pemeriksaan VIN untuk kendaraan ini.
        </p>
      )}
    </fieldset>
  );
}
