"use client";

import { useEffect, useState } from "react";
import { apiFetch } from "@/lib/api";
import { formatRupiah } from "@/lib/format";
import type { Paginated, Vehicle, VehicleDetail } from "@/lib/types";
import StatusBadge from "@/components/StatusBadge";
import ConfirmModal from "@/components/ConfirmModal";

type PendingAction = { type: "approve" | "reject"; id: number };

export default function AdminVehiclesPage() {
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [actionError, setActionError] = useState<string | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<VehicleDetail | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);

  async function loadVehicles() {
    setIsLoading(true);
    const { data } = await apiFetch<Paginated<Vehicle>>("/api/admin/vehicles");
    setVehicles(data);
    setIsLoading(false);
  }

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- initial data fetch on mount
    loadVehicles();
  }, []);

  async function toggleDetail(id: number) {
    if (expandedId === id) {
      setExpandedId(null);
      setDetail(null);
      return;
    }

    setExpandedId(id);
    setDetail(null);
    const { data } = await apiFetch<{ data: VehicleDetail }>(`/api/admin/vehicles/${id}`);
    setDetail(data);
  }

  async function runAction(action: PendingAction, values: Record<string, string>) {
    setActionError(null);
    setIsSubmitting(true);
    try {
      if (action.type === "approve") {
        await apiFetch(`/api/admin/vehicles/${action.id}`, { method: "PATCH", body: { status: "approved" } });
      } else {
        await apiFetch(`/api/admin/vehicles/${action.id}`, {
          method: "PATCH",
          body: { status: "rejected", rejection_reason: values.reason },
        });
      }
      setPendingAction(null);
      setExpandedId(null);
      await loadVehicles();
    } catch {
      setActionError(action.type === "approve" ? "Gagal menyetujui listing." : "Gagal menolak listing.");
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div>
      <h1 className="text-xl font-semibold text-on-surface">Review Listing Kendaraan</h1>

      {actionError && <p className="mt-3 text-sm text-error">{actionError}</p>}

      {isLoading ? (
        <p className="mt-6 text-sm text-on-surface-muted">Memuat...</p>
      ) : vehicles.length === 0 ? (
        <p className="mt-6 text-sm text-on-surface-muted">Tidak ada listing pending review.</p>
      ) : (
        <div className="mt-6 space-y-4">
          {vehicles.map((vehicle) => (
            <div key={vehicle.id} className="rounded-lg border border-border bg-surface-container p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium text-on-surface">
                    {vehicle.brand} {vehicle.model} {vehicle.year}
                  </p>
                  <p className="text-sm text-on-surface-muted">
                    {formatRupiah(vehicle.price)} &middot; {vehicle.seller_name}
                  </p>
                </div>
                <StatusBadge status={vehicle.status} />
              </div>

              <button
                onClick={() => toggleDetail(vehicle.id)}
                className="mt-3 text-sm text-on-surface underline"
              >
                {expandedId === vehicle.id ? "Sembunyikan detail" : "Lihat detail & dokumen"}
              </button>

              {expandedId === vehicle.id && (
                <div className="mt-3 rounded-md bg-surface-container-high p-3 text-sm">
                  {!detail ? (
                    <p className="text-on-surface-muted">Memuat detail...</p>
                  ) : (
                    <div className="flex flex-col gap-2">
                      <p className="text-on-surface-muted">{detail.description}</p>
                      <div className="flex flex-wrap gap-3">
                        {detail.documents?.map((doc) => (
                          <a
                            key={doc.id}
                            href={doc.download_url}
                            target="_blank"
                            rel="noreferrer"
                            className="text-on-surface underline"
                          >
                            Lihat {doc.type.toUpperCase()}
                          </a>
                        ))}
                        {(!detail.documents || detail.documents.length === 0) && (
                          <span className="text-on-surface-muted">Belum ada dokumen diunggah.</span>
                        )}
                      </div>
                    </div>
                  )}
                </div>
              )}

              <div className="mt-4 flex gap-3">
                <button
                  onClick={() => setPendingAction({ type: "approve", id: vehicle.id })}
                  className="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500"
                >
                  Setujui
                </button>
                <button
                  onClick={() => setPendingAction({ type: "reject", id: vehicle.id })}
                  className="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-500"
                >
                  Tolak
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {pendingAction?.type === "approve" && (
        <ConfirmModal
          title="Setujui Listing"
          message="Setujui listing ini agar tayang di katalog publik?"
          confirmLabel="Setujui"
          isSubmitting={isSubmitting}
          onConfirm={(values) => runAction(pendingAction, values)}
          onCancel={() => setPendingAction(null)}
        />
      )}

      {pendingAction?.type === "reject" && (
        <ConfirmModal
          title="Tolak Listing"
          message="Jelaskan alasan penolakan listing ini."
          fields={[{ name: "reason", label: "Alasan Penolakan", required: true }]}
          confirmLabel="Tolak"
          isSubmitting={isSubmitting}
          onConfirm={(values) => runAction(pendingAction, values)}
          onCancel={() => setPendingAction(null)}
        />
      )}
    </div>
  );
}
