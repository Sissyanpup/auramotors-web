"use client";

import { use, useEffect, useState } from "react";
import { apiFetch } from "@/lib/api";
import type { VehicleDetail } from "@/lib/types";
import VehicleForm from "@/components/VehicleForm";
import VehicleVinCheckSection from "@/components/VehicleVinCheckSection";
import VehicleExtraDocumentsSection from "@/components/VehicleExtraDocumentsSection";
import VehicleInsuranceSection from "@/components/VehicleInsuranceSection";

export default function EditVehiclePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [vehicle, setVehicle] = useState<VehicleDetail | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    apiFetch<{ data: VehicleDetail }>(`/api/seller/vehicles/${id}`)
      .then(({ data }) => setVehicle(data))
      .finally(() => setIsLoading(false));
  }, [id]);

  if (isLoading) {
    return <p className="text-sm text-on-surface-muted">Memuat...</p>;
  }

  if (!vehicle) {
    return <p className="text-sm text-error">Listing tidak ditemukan.</p>;
  }

  return (
    <div>
      <h1 className="text-xl font-semibold text-on-surface">Edit Listing Kendaraan</h1>

      <div className="mt-6">
        <VehicleForm vehicleId={vehicle.id} initial={vehicle} />
      </div>

      <div className="mt-8 space-y-8 rounded-lg border border-border bg-surface-container p-6">
        <div>
          <h2 className="font-display text-lg text-on-surface">Data Verifikasi &amp; Dokumen Ekstra</h2>
          <p className="mt-0.5 text-xs text-on-surface-muted">
            Data VIN, riwayat inspeksi, dan polis asuransi meningkatkan kepercayaan calon pembeli.
          </p>
        </div>

        <VehicleVinCheckSection vehicle={vehicle} onVehicleUpdated={setVehicle} />
        <VehicleExtraDocumentsSection vehicle={vehicle} onVehicleUpdated={setVehicle} />
        <VehicleInsuranceSection vehicle={vehicle} onVehicleUpdated={setVehicle} />
      </div>
    </div>
  );
}
