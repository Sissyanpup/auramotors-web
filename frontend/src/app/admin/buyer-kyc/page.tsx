"use client";

import { useEffect, useState, useCallback } from "react";
import { apiFetch } from "@/lib/api";
import type { BuyerProfile, Paginated } from "@/lib/types";
import StatusBadge from "@/components/StatusBadge";
import ConfirmModal from "@/components/ConfirmModal";

type PendingAction = { type: "approve" | "reject"; id: number };
type StatusFilter = "pending" | "approved" | "rejected" | "all";

const FILTERS: { key: StatusFilter; label: string }[] = [
  { key: "pending", label: "Pending" },
  { key: "approved", label: "Disetujui" },
  { key: "rejected", label: "Ditolak" },
  { key: "all", label: "Semua" },
];

export default function AdminBuyerKycPage() {
  const [profiles, setProfiles] = useState<BuyerProfile[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState<StatusFilter>("pending");
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);

  const loadProfiles = useCallback(async () => {
    setIsLoading(true);
    try {
      const { data } = await apiFetch<Paginated<BuyerProfile>>(
        `/api/admin/buyer-kyc?status=${statusFilter}`
      );
      setProfiles(data);
    } catch {
      setActionError("Gagal memuat daftar KYC buyer.");
    } finally {
      setIsLoading(false);
    }
  }, [statusFilter]);

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

  return (
    <div>
      <h1 className="text-xl font-semibold text-on-surface">Review KYC Buyer</h1>

      <div className="mt-4 flex flex-wrap gap-2">
        {FILTERS.map((filter) => (
          <button
            key={filter.key}
            onClick={() => setStatusFilter(filter.key)}
            className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
              statusFilter === filter.key
                ? "bg-primary-container text-on-primary"
                : "border border-border bg-surface-container text-on-surface-muted hover:text-on-surface"
            }`}
          >
            {filter.label}
          </button>
        ))}
      </div>

      {actionError && <p className="mt-3 text-sm text-error">{actionError}</p>}

      {isLoading ? (
        <p className="mt-6 text-sm text-on-surface-muted">Memuat...</p>
      ) : profiles.length === 0 ? (
        <p className="mt-6 text-sm text-on-surface-muted">
          Tidak ada pengajuan KYC buyer pada filter ini.
        </p>
      ) : (
        <div className="mt-6 space-y-4">
          {profiles.map((profile) => {
            const isExpanded = expandedId === profile.id;
            return (
              <div key={profile.id} className="rounded-lg border border-border bg-surface-container p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-on-surface">{profile.user?.name}</p>
                    <p className="text-sm text-on-surface-muted">{profile.user?.email}</p>
                  </div>
                  <div className="flex items-center gap-3">
                    <StatusBadge status={profile.status} />
                    <button
                      onClick={() => setExpandedId(isExpanded ? null : profile.id)}
                      className="text-sm text-on-surface-muted underline hover:text-on-surface"
                    >
                      {isExpanded ? "Sembunyikan" : "Detail"}
                    </button>
                  </div>
                </div>

                {isExpanded && (
                  <div className="mt-4 border-t border-border pt-4">
                    <div className="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                      <p className="text-on-surface-muted">
                        Tipe identitas:{" "}
                        <span className="text-on-surface">
                          {profile.id_type === "passport" ? "Paspor" : "KTP"}
                        </span>
                      </p>
                      <p className="text-on-surface-muted">
                        Nomor identitas:{" "}
                        <span className="text-on-surface">{profile.id_number}</span>
                      </p>
                    </div>

                    {profile.status === "rejected" && profile.rejection_reason && (
                      <p className="mt-3 text-sm text-error">
                        Alasan ditolak: {profile.rejection_reason}
                      </p>
                    )}

                    <div className="mt-3 flex flex-wrap gap-4 text-sm">
                      {profile.id_document_url && (
                        <a
                          href={profile.id_document_url}
                          target="_blank"
                          rel="noreferrer"
                          className="text-on-surface underline"
                        >
                          Lihat Dokumen Identitas
                        </a>
                      )}
                      {profile.address_proof_url && (
                        <a
                          href={profile.address_proof_url}
                          target="_blank"
                          rel="noreferrer"
                          className="text-on-surface underline"
                        >
                          Lihat Bukti Alamat
                        </a>
                      )}
                      {profile.proof_of_funds_url && (
                        <a
                          href={profile.proof_of_funds_url}
                          target="_blank"
                          rel="noreferrer"
                          className="text-on-surface underline"
                        >
                          Lihat Proof of Funds
                        </a>
                      )}
                    </div>

                    {profile.status === "pending" && (
                      <div className="mt-4 flex gap-3">
                        <button
                          onClick={() => setPendingAction({ type: "approve", id: profile.id })}
                          className="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500"
                        >
                          Setujui
                        </button>
                        <button
                          onClick={() => setPendingAction({ type: "reject", id: profile.id })}
                          className="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-500"
                        >
                          Tolak
                        </button>
                      </div>
                    )}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}

      {pendingAction?.type === "approve" && (
        <ConfirmModal
          title="Setujui KYC Buyer"
          message="Setujui KYC buyer ini?"
          confirmLabel="Setujui"
          isSubmitting={isSubmitting}
          onConfirm={(values) => runAction(pendingAction, values)}
          onCancel={() => setPendingAction(null)}
        />
      )}

      {pendingAction?.type === "reject" && (
        <ConfirmModal
          title="Tolak KYC Buyer"
          message="Jelaskan alasan penolakan KYC buyer ini."
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
