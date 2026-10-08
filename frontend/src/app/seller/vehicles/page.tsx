"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { apiFetch } from "@/lib/api";
import { formatRupiah } from "@/lib/format";
import type { Paginated, Vehicle } from "@/lib/types";
import StatusBadge from "@/components/StatusBadge";
import ConfirmModal from "@/components/ConfirmModal";

type PendingAction = { type: "delete" | "submit-for-review"; id: number };

export default function SellerVehiclesPage() {
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [actionError, setActionError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);

  async function loadVehicles() {
    setIsLoading(true);
    const { data } = await apiFetch<Paginated<Vehicle>>("/api/seller/vehicles");
    setVehicles(data);
    setIsLoading(false);
  }

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- initial data fetch on mount
    loadVehicles();
  }, []);

  async function runAction(action: PendingAction) {
    setActionError(null);
    setIsSubmitting(true);
    try {
      if (action.type === "delete") {
        await apiFetch(`/api/seller/vehicles/${action.id}`, { method: "DELETE" });
      } else {
        await apiFetch(`/api/seller/vehicles/${action.id}/submit-for-review`, { method: "POST" });
      }
      setPendingAction(null);
      await loadVehicles();
    } catch (error) {
      setActionError(
        error instanceof Error
          ? error.message
          : action.type === "delete"
            ? "Gagal menghapus listing."
            : "Gagal mengajukan listing untuk review."
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-xl font-semibold text-on-surface">Listing Saya</h1>
        <Link
          href="/seller/vehicles/new"
          className="btn-gold rounded-md px-4 py-2 text-sm font-medium"
        >
          + Tambah Listing
        </Link>
      </div>

      {actionError && <p className="mt-3 text-sm text-error">{actionError}</p>}

      {isLoading ? (
        <p className="mt-6 text-sm text-on-surface-muted">Memuat...</p>
      ) : vehicles.length === 0 ? (
        <p className="mt-6 text-sm text-on-surface-muted">
          Belum ada listing. Klik &ldquo;Tambah Listing&rdquo; untuk mulai.
        </p>
      ) : (
        <>
          {/* ── Card view (mobile) ── */}
          <div className="mt-4 space-y-3 md:hidden">
            {vehicles.map((vehicle) => (
              <div
                key={vehicle.id}
                className="rounded-lg border border-border bg-surface-container p-4"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="truncate text-sm font-medium text-on-surface">
                      {vehicle.brand} {vehicle.model} {vehicle.year}
                    </p>
                    <p className="mt-0.5 text-sm font-semibold text-primary">
                      {formatRupiah(vehicle.price)}
                    </p>
                  </div>
                  <StatusBadge status={vehicle.status} />
                </div>

                {(vehicle.status === "draft" || vehicle.status === "rejected") && (
                  <div className="mt-3 flex flex-wrap gap-4 border-t border-border/50 pt-3">
                    <Link
                      href={`/seller/vehicles/${vehicle.id}/edit`}
                      className="text-sm text-on-surface underline transition-colors hover:text-primary"
                    >
                      Edit
                    </Link>
                    <button
                      onClick={() => setPendingAction({ type: "submit-for-review", id: vehicle.id })}
                      className="text-sm text-success underline transition-colors"
                    >
                      Ajukan Review
                    </button>
                    <button
                      onClick={() => setPendingAction({ type: "delete", id: vehicle.id })}
                      className="text-sm text-error underline transition-colors"
                    >
                      Hapus
                    </button>
                  </div>
                )}
              </div>
            ))}
          </div>

          {/* ── Table view (desktop) ── */}
          <div className="mt-4 hidden overflow-x-auto rounded-lg border border-border bg-surface-container md:block">
            <table className="min-w-full divide-y divide-border text-sm">
              <thead>
                <tr className="text-left text-xs uppercase text-on-surface-muted">
                  <th className="px-4 py-3">Kendaraan</th>
                  <th className="px-4 py-3">Harga</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {vehicles.map((vehicle) => (
                  <tr key={vehicle.id}>
                    <td className="px-4 py-3">
                      {vehicle.brand} {vehicle.model} {vehicle.year}
                    </td>
                    <td className="px-4 py-3">{formatRupiah(vehicle.price)}</td>
                    <td className="px-4 py-3">
                      <StatusBadge status={vehicle.status} />
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap gap-3">
                        {(vehicle.status === "draft" || vehicle.status === "rejected") && (
                          <>
                            <Link
                              href={`/seller/vehicles/${vehicle.id}/edit`}
                              className="text-on-surface underline transition-colors hover:text-primary"
                            >
                              Edit
                            </Link>
                            <button
                              onClick={() => setPendingAction({ type: "submit-for-review", id: vehicle.id })}
                              className="text-success underline"
                            >
                              Ajukan Review
                            </button>
                            <button
                              onClick={() => setPendingAction({ type: "delete", id: vehicle.id })}
                              className="text-error underline"
                            >
                              Hapus
                            </button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}

      {pendingAction?.type === "submit-for-review" && (
        <ConfirmModal
          title="Ajukan Review"
          message="Ajukan listing ini untuk direview admin?"
          confirmLabel="Ajukan"
          isSubmitting={isSubmitting}
          onConfirm={() => runAction(pendingAction)}
          onCancel={() => setPendingAction(null)}
        />
      )}

      {pendingAction?.type === "delete" && (
        <ConfirmModal
          title="Hapus Listing"
          message="Hapus listing ini? Tindakan tidak bisa dibatalkan."
          confirmLabel="Hapus"
          isSubmitting={isSubmitting}
          onConfirm={() => runAction(pendingAction)}
          onCancel={() => setPendingAction(null)}
        />
      )}
    </div>
  );
}
