"use client";

import { useEffect, useState } from "react";
import { apiFetch } from "@/lib/api";
import type { Paginated, SellerProfile } from "@/lib/types";
import StatusBadge from "@/components/StatusBadge";
import ConfirmModal from "@/components/ConfirmModal";

type PendingAction = { type: "approve" | "reject"; id: number };

export default function AdminKycPage() {
  const [profiles, setProfiles] = useState<SellerProfile[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [actionError, setActionError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);

  async function loadProfiles() {
    setIsLoading(true);
    const { data } = await apiFetch<Paginated<SellerProfile>>("/api/admin/kyc");
    setProfiles(data);
    setIsLoading(false);
  }

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- initial data fetch on mount
    loadProfiles();
  }, []);

  async function runAction(action: PendingAction, values: Record<string, string>) {
    setActionError(null);
    setIsSubmitting(true);
    try {
      if (action.type === "approve") {
        await apiFetch(`/api/admin/kyc/${action.id}`, { method: "PATCH", body: { status: "approved" } });
      } else {
        await apiFetch(`/api/admin/kyc/${action.id}`, {
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
      <h1 className="text-xl font-semibold text-on-surface">Review KYC Seller</h1>

      {actionError && <p className="mt-3 text-sm text-error">{actionError}</p>}

      {isLoading ? (
        <p className="mt-6 text-sm text-on-surface-muted">Memuat...</p>
      ) : profiles.length === 0 ? (
        <p className="mt-6 text-sm text-on-surface-muted">Tidak ada pengajuan KYC pending.</p>
      ) : (
        <div className="mt-6 space-y-4">
          {profiles.map((profile) => (
            <div key={profile.id} className="rounded-lg border border-border bg-surface-container p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium text-on-surface">{profile.user?.name}</p>
                  <p className="text-sm text-on-surface-muted">{profile.user?.email}</p>
                </div>
                <StatusBadge status={profile.status} />
              </div>

              <div className="mt-3 flex gap-4 text-sm">
                <a href={profile.ktp_url} target="_blank" rel="noreferrer" className="text-on-surface underline">
                  Lihat KTP
                </a>
                {profile.npwp_url && (
                  <a href={profile.npwp_url} target="_blank" rel="noreferrer" className="text-on-surface underline">
                    Lihat NPWP
                  </a>
                )}
              </div>

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
            </div>
          ))}
        </div>
      )}

      {pendingAction?.type === "approve" && (
        <ConfirmModal
          title="Setujui KYC"
          message="Setujui KYC seller ini?"
          confirmLabel="Setujui"
          isSubmitting={isSubmitting}
          onConfirm={(values) => runAction(pendingAction, values)}
          onCancel={() => setPendingAction(null)}
        />
      )}

      {pendingAction?.type === "reject" && (
        <ConfirmModal
          title="Tolak KYC"
          message="Jelaskan alasan penolakan KYC ini."
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
