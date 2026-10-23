"use client";

import { useEffect, useState, useCallback } from "react";
import { apiFetch } from "@/lib/api";
import { formatDate } from "@/lib/format";
import type { BuyerProfile, Paginated } from "@/lib/types";
import ConfirmModal from "@/components/ConfirmModal";
import { Pager, ReviewCell, VerificationFilterBar } from "@/components/VerificationParts";

type PendingAction = { type: "approve" | "reject"; id: number };

const FILTERS = [
  { key: "all", label: "Semua" },
  { key: "pending", label: "Menunggu" },
  { key: "approved", label: "Disetujui" },
  { key: "rejected", label: "Ditolak" },
];

export default function AdminBuyerKycPage() {
  const [result, setResult] = useState<Paginated<BuyerProfile> | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState("all");
  const [page, setPage] = useState(1);
  const [actionError, setActionError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);

  const loadProfiles = useCallback(async () => {
    setIsLoading(true);
    try {
      setResult(await apiFetch<Paginated<BuyerProfile>>(`/api/admin/buyer-kyc?status=${statusFilter}&page=${page}`));
    } catch {
      setActionError("Gagal memuat daftar KYC pembeli.");
    } finally {
      setIsLoading(false);
    }
  }, [statusFilter, page]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- data fetch on filter change
    loadProfiles();
  }, [loadProfiles]);

  async function runAction(action: PendingAction, values: Record<string, string>) {
    setActionError(null);
    setIsSubmitting(true);
    try {
      if (action.type === "approve") {
        await apiFetch(`/api/admin/buyer-kyc/${action.id}`, {
          method: "PATCH",
          body: { status: "approved" },
        });
      } else {
        await apiFetch(`/api/admin/buyer-kyc/${action.id}`, {
          method: "PATCH",
          body: { status: "rejected", rejection_reason: values.reason },
        });
      }
      setPendingAction(null);
      await loadProfiles();
    } catch {
      setActionError(action.type === "approve" ? "Gagal menyetujui KYC." : "Gagal menolak KYC.");
    } finally {
      setIsSubmitting(false);
    }
  }

  const profiles = result?.data ?? [];

  return (
    <div>
      <h1 className="text-xl font-semibold text-on-surface">Verifikasi KYC Pembeli</h1>
      <p className="mt-1 text-sm text-on-surface-muted">Semua pengajuan ditampilkan, termasuk yang sudah disetujui atau ditolak.</p>

      <VerificationFilterBar
        filters={FILTERS}
        active={statusFilter}
        onChange={(key) => {
          setStatusFilter(key);
          setPage(1);
        }}
      />

      {actionError && <p className="mt-3 text-sm text-error">{actionError}</p>}

      <div className="mt-5 rounded-lg border border-border bg-surface-container p-4">
        {isLoading && !result ? (
          <p className="text-sm text-on-surface-muted">Memuat...</p>
        ) : profiles.length === 0 ? (
          <p className="text-sm text-on-surface-muted">Tidak ada pengajuan KYC pembeli pada filter ini.</p>
        ) : (
          <div className={`overflow-x-auto ${isLoading ? "opacity-60" : ""}`}>
            <table className="w-full min-w-[820px] text-sm">
              <thead>
                <tr className="border-b border-border text-left text-[11px] uppercase tracking-wider text-on-surface-muted">
                  <th className="py-2 pr-3 font-medium">Pembeli</th>
                  <th className="py-2 pr-3 font-medium">Identitas</th>
                  <th className="py-2 pr-3 font-medium">Dokumen</th>
                  <th className="py-2 pr-3 font-medium">Diajukan</th>
                  <th className="py-2 pr-3 font-medium">Status &amp; peninjauan</th>
                  <th className="py-2 font-medium">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border align-top">
                {profiles.map((profile) => (
                  <tr key={profile.id}>
                    <td className="py-2 pr-3">
                      <p className="text-on-surface">{profile.user?.name}</p>
                      <p className="text-xs text-on-surface-muted">{profile.user?.email}</p>
                    </td>
                    <td className="py-2 pr-3 text-xs">
                      <p className="text-on-surface-muted">{profile.id_type === "passport" ? "Paspor" : "KTP"}</p>
                      <p className="font-mono text-on-surface">{profile.id_number}</p>
                    </td>
                    <td className="py-2 pr-3">
                      <div className="flex flex-col gap-0.5 text-xs">
                        {profile.id_document_url && (
                          <a href={profile.id_document_url} target="_blank" rel="noreferrer" className="text-on-surface underline">
                            Dokumen identitas
                          </a>
                        )}
                        {profile.address_proof_url && (
                          <a href={profile.address_proof_url} target="_blank" rel="noreferrer" className="text-on-surface underline">
                            Bukti alamat
                          </a>
                        )}
                        {profile.proof_of_funds_url && (
                          <a href={profile.proof_of_funds_url} target="_blank" rel="noreferrer" className="text-on-surface underline">
                            Proof of funds
                          </a>
                        )}
                      </div>
                    </td>
                    <td className="whitespace-nowrap py-2 pr-3 text-xs text-on-surface-muted">{formatDate(profile.created_at)}</td>
                    <td className="py-2 pr-3">
                      <ReviewCell
                        status={profile.status}
                        reviewedBy={profile.reviewed_by}
                        reviewedAt={profile.reviewed_at}
                        rejectionReason={profile.rejection_reason}
                        historyHref={`/admin/activity?subject_type=BuyerProfile&subject_id=${profile.id}`}
                      />
                    </td>
                    <td className="py-2">
                      {profile.status === "pending" ? (
                        <div className="flex gap-2">
                          <button
                            onClick={() => setPendingAction({ type: "approve", id: profile.id })}
                            className="rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-emerald-500"
                          >
                            Setujui
                          </button>
                          <button
                            onClick={() => setPendingAction({ type: "reject", id: profile.id })}
                            className="rounded-md bg-red-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-red-500"
                          >
                            Tolak
                          </button>
                        </div>
                      ) : (
                        <span className="text-xs text-on-surface-muted">Selesai ditinjau</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {result && <Pager page={result.meta.current_page} lastPage={result.meta.last_page} total={result.meta.total} onPage={setPage} />}
      </div>

      {pendingAction?.type === "approve" && (
        <ConfirmModal
          title="Setujui KYC Pembeli"
          message="Setujui KYC pembeli ini?"
          confirmLabel="Setujui"
          isSubmitting={isSubmitting}
          onConfirm={(values) => runAction(pendingAction, values)}
          onCancel={() => setPendingAction(null)}
        />
      )}

      {pendingAction?.type === "reject" && (
        <ConfirmModal
          title="Tolak KYC Pembeli"
          message="Jelaskan alasan penolakan KYC pembeli ini."
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
