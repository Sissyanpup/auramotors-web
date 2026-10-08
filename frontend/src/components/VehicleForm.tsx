"use client";

import { useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { ApiError, apiFetch, toFormData } from "@/lib/api";
import type { VehicleDetail } from "@/lib/types";

type Props = {
  vehicleId?: number;
  initial?: VehicleDetail;
};

export default function VehicleForm({ vehicleId, initial }: Props) {
  const router = useRouter();
  const [brand, setBrand] = useState(initial?.brand ?? "");
  const [model, setModel] = useState(initial?.model ?? "");
  const [year, setYear] = useState(initial?.year?.toString() ?? "");
  const [vin, setVin] = useState(initial?.vin ?? "");
  const [price, setPrice] = useState(initial?.price ?? "");
  const [mileage, setMileage] = useState(initial?.mileage?.toString() ?? "");
  const [location, setLocation] = useState(initial?.location ?? "");
  const [description, setDescription] = useState(initial?.description ?? "");
  const [photos, setPhotos] = useState<FileList | null>(null);
  const [stnk, setStnk] = useState<File | null>(null);
  const [bpkb, setBpkb] = useState<File | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setErrors({});
    setFormError(null);

    if (!vehicleId && (!photos || photos.length === 0)) {
      setErrors({ photos: ["Minimal 1 foto kendaraan diperlukan."] });
      return;
    }

    setIsSubmitting(true);
    try {
      const formData = toFormData({
        brand,
        model,
        year,
        vin: vin.trim() ? vin.trim().toUpperCase() : null,
        price,
        mileage,
        location,
        description,
        photos: photos ? Array.from(photos) : undefined,
        stnk,
        bpkb,
      });

      const path = vehicleId ? `/api/seller/vehicles/${vehicleId}` : "/api/seller/vehicles";
      const { data } = await apiFetch<{ data: VehicleDetail }>(path, {
        method: "POST",
        body: formData,
      });

      router.push("/seller/vehicles");
      router.refresh();
      return data;
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

  return (
    <form onSubmit={handleSubmit} className="space-y-4 rounded-lg border border-border bg-surface-container p-4">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="Merek" error={errors.brand?.[0]}>
          <input
            required
            value={brand}
            onChange={(e) => setBrand(e.target.value)}
            className="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm bg-surface text-on-surface placeholder:text-on-surface-muted focus:border-primary focus:outline-none"
          />
        </Field>

        <Field label="Model" error={errors.model?.[0]}>
          <input
            required
            value={model}
            onChange={(e) => setModel(e.target.value)}
            className="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm bg-surface text-on-surface placeholder:text-on-surface-muted focus:border-primary focus:outline-none"
          />
        </Field>

        <Field label="Tahun" error={errors.year?.[0]}>
          <input
            required
            type="number"
            value={year}
            onChange={(e) => setYear(e.target.value)}
            className="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm bg-surface text-on-surface placeholder:text-on-surface-muted focus:border-primary focus:outline-none"
          />
        </Field>

        <Field label="Harga (Rp)" error={errors.price?.[0]}>
          <input
            required
            type="number"
            value={price}
            onChange={(e) => setPrice(e.target.value)}
            className="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm bg-surface text-on-surface placeholder:text-on-surface-muted focus:border-primary focus:outline-none"
          />
        </Field>

        <Field label="Kilometer" error={errors.mileage?.[0]}>
          <input
            required
            type="number"
            value={mileage}
            onChange={(e) => setMileage(e.target.value)}
            className="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm bg-surface text-on-surface placeholder:text-on-surface-muted focus:border-primary focus:outline-none"
          />
        </Field>

        <Field label="Lokasi" error={errors.location?.[0]}>
          <input
            required
            value={location}
            onChange={(e) => setLocation(e.target.value)}
            className="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm bg-surface text-on-surface placeholder:text-on-surface-muted focus:border-primary focus:outline-none"
          />
        </Field>
      </div>

      <Field
        label="Nomor VIN (opsional, 17 karakter)"
        error={errors.vin?.[0]}
      >
        <input
          value={vin}
          onChange={(e) => setVin(e.target.value.toUpperCase())}
          maxLength={17}
          minLength={vin.length > 0 ? 17 : undefined}
          pattern="[A-HJ-NPR-Z0-9]{17}"
          placeholder="mis. 1HGBH41JXMN109186"
          title="17 karakter alfanumerik, tanpa huruf I/O/Q"
          className="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm bg-surface text-on-surface placeholder:text-on-surface-muted focus:border-primary focus:outline-none font-mono uppercase tracking-wider"
        />
      </Field>

      <Field label="Deskripsi" error={errors.description?.[0]}>
        <textarea
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          rows={4}
          className="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm bg-surface text-on-surface placeholder:text-on-surface-muted focus:border-primary focus:outline-none"
        />
      </Field>

      <Field
        label={vehicleId ? "Foto Kendaraan (kosongkan jika tidak ingin mengganti)" : "Foto Kendaraan"}
        error={errors.photos?.[0]}
      >
        <input
          type="file"
          multiple
          accept="image/jpeg,image/png,image/webp"
          onChange={(e) => setPhotos(e.target.files)}
          className="mt-1 block w-full text-sm"
        />
      </Field>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="STNK" error={errors.stnk?.[0]}>
          <input
            type="file"
            accept="image/jpeg,image/png,application/pdf"
            onChange={(e) => setStnk(e.target.files?.[0] ?? null)}
            className="mt-1 block w-full text-sm"
          />
        </Field>

        <Field label="BPKB" error={errors.bpkb?.[0]}>
          <input
            type="file"
            accept="image/jpeg,image/png,application/pdf"
            onChange={(e) => setBpkb(e.target.files?.[0] ?? null)}
            className="mt-1 block w-full text-sm"
          />
        </Field>
      </div>

      {formError && <p className="text-sm text-error">{formError}</p>}

      <button
        type="submit"
        disabled={isSubmitting}
        className="rounded-md bg-primary-container px-4 py-2 text-sm font-medium text-on-primary hover:bg-primary disabled:opacity-60"
      >
        {isSubmitting ? "Menyimpan..." : "Simpan"}
      </button>
    </form>
  );
}

function Field({
  label,
  error,
  children,
}: {
  label: string;
  error?: string;
  children: React.ReactNode;
}) {
  return (
    <div>
      <label className="block text-sm font-medium text-on-surface">{label}</label>
      {children}
      {error && <p className="mt-1 text-xs text-error">{error}</p>}
    </div>
  );
}
