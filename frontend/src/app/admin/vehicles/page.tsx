"use client";

import { Fragment, useEffect, useState } from "react";
import { apiFetch } from "@/lib/api";
import { formatDate, formatRupiah } from "@/lib/format";
import type { Paginated, Vehicle, VehicleDetail } from "@/lib/types";
import ConfirmModal from "@/components/ConfirmModal";
import { Pager, ReviewCell, VerificationFilterBar } from "@/components/VerificationParts";

type PendingAction = { type: "approve" | "reject"; id: number };

const FILTERS = [
  { key: "all", label: "Semua" },
  { key: "pending_review", label: "Menunggu Review" },
  { key: "approved", label: "Disetujui" },
  { key: "rejected", label: "Ditolak" },
  { key: "sold", label: "Terjual" },
  { key: "draft", label: "Draf" },
];

export default function AdminVehiclesPage() {
  const [result, setResult] = useState<Paginated<Vehicle> | null>(null);
  const [status, setStatus] = useState("all");
  const [page, setPage] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [actionError, setActionError] = useState<string | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<VehicleDetail | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);

  async function loadVehicles() {
    setIsLoading(true);
    setResult(await apiFetch<Paginated<Vehicle>>(`/api/admin/vehicles?status=${status}&page=${page}`));
    setIsLoading(false);
  }

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- refetch when filter/page changes
    loadVehicles();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [status, page]);

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

  const vehicles = result?.data ?? [];

  return (
    <div>
      <h1 className="text-xl font-semibold text-on-surface">Verifikasi Listing Kendaraan</h1>
      <p className="mt-1 text-sm text-on-surface-muted">Semua listing ditampilkan, termasuk yang sudah disetujui, ditolak, atau terjual.</p>

      <VerificationFilterBar
        filters={FILTERS}
        active={status}
        onChange={(key) => {
          setStatus(key);
          setPage(1);
        }}
      />

      {actionError && <p className="mt-3 text-sm text-error">{actionError}</p>}

      <div className="mt-5 rounded-lg border border-border bg-surface-container p-4">
        {isLoading && !result ? (
          <p className="text-sm text-on-surface-muted">Memuat...</p>
        ) : vehicles.length === 0 ? (
          <p className="text-sm text-on-surface-muted">Tidak ada listing pada filter ini.</p>
        ) : (
          <div className={`overflow-x-auto ${isLoading ? "opacity-60" : ""}`}>
            <table className="w-full min-w-[820px] text-sm">
              <thead>
                <tr className="border-b border-border text-left text-[11px] uppercase tracking-wider text-on-surface-muted">
                  <th className="py-2 pr-3 font-medium">Kendaraan</th>
                  <th className="py-2 pr-3 font-medium">Penjual</th>
                  <th className="py-2 pr-3 text-right font-medium">Harga</th>
                  <th className="py-2 pr-3 font-medium">Dibuat</th>
                  <th className="py-2 pr-3 font-medium">Status &amp; peninjauan</th>
                  <th className="py-2 font-medium">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border align-top">
                {vehicles.map((vehicle) => (
                  <Fragment key={vehicle.id}>
                    <tr>
                      <td className="py-2 pr-3">
                        <p className="text-on-surface">
                          {vehicle.brand} {vehicle.model} {vehicle.year}
                        </p>
                        <button onClick={() => toggleDetail(vehicle.id)} className="text-xs text-on-surface-muted underline">
                          {expandedId === vehicle.id ? "Sembunyikan detail" : "Detail & dokumen"}
                        </button>
                      </td>
                      <td className="py-2 pr-3 text-on-surface-muted">{vehicle.seller_name}</td>
                      <td className="py-2 pr-3 text-right tabular-nums text-on-surface">{formatRupiah(vehicle.price)}</td>
                      <td className="whitespace-nowrap py-2 pr-3 text-xs text-on-surface-muted">
                        {vehicle.created_at ? formatDate(vehicle.created_at) : "—"}
                      </td>
                      <td className="py-2 pr-3">
                        <ReviewCell
                          status={vehicle.status}
                          reviewedBy={vehicle.reviewed_by}
                          reviewedAt={vehicle.reviewed_at}
                          rejectionReason={vehicle.rejection_reason}
                          historyHref={`/admin/activity?subject_type=Vehicle&subject_id=${vehicle.id}`}
                        />
                      </td>
                      <td className="py-2">
                        {vehicle.status === "pending_review" ? (
                          <div className="flex gap-2">
                            <button
                              onClick={() => setPendingAction({ type: "approve", id: vehicle.id })}
                              className="rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-emerald-500"
                            >
                              Setujui
                            </button>
                            <button
                              onClick={() => setPendingAction({ type: "reject", id: vehicle.id })}
                              className="rounded-md bg-red-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-red-500"
                            >
                              Tolak
                            </button>
                          </div>
                        ) : (
                          <span className="text-xs text-on-surface-muted">—</span>
                        )}
                      </td>
                    </tr>
                    {expandedId === vehicle.id && (
                      <tr>
                        <td colSpan={6} className="pb-3">
                          <div className="rounded-md bg-surface-container-high p-3 text-sm">
                            {!detail ? (
                              <p className="text-on-surface-muted">Memuat detail...</p>
                            ) : (
                              <div className="flex flex-col gap-2">
                                <p className="text-on-surface-muted">{detail.description}</p>
                                <div className="flex flex-wrap gap-3">
                                  {detail.documents?.map((doc) => (
                                    <a key={doc.id} href={doc.download_url} target="_blank" rel="noreferrer" className="text-on-surface underline">
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
                        </td>
                      </tr>
                    )}
                  </Fragment>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {result && <Pager page={result.meta.current_page} lastPage={result.meta.last_page} total={result.meta.total} onPage={setPage} />}
      </div>

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
