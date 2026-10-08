"use client";

import { useEffect, useState } from "react";
import { apiFetch } from "@/lib/api";
import { formatDate, formatRupiah } from "@/lib/format";
import type { Paginated, TransactionPayout } from "@/lib/types";
import PayoutStatusBadge from "@/components/PayoutStatusBadge";

type ReconciliationSummary = {
  paid_count: number;
  total_commission: string;
  total_payout: string;
  total_disbursed: string;
};

type ReconciliationResponse = Paginated<TransactionPayout> & { summary: ReconciliationSummary };

export default function AdminPayoutReconciliationPage() {
  const [response, setResponse] = useState<ReconciliationResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    async function load() {
      setIsLoading(true);
      const data = await apiFetch<ReconciliationResponse>("/api/admin/payouts/reconciliation");
      setResponse(data);
      setIsLoading(false);
    }

    load();
  }, []);

  return (
    <div>
      <h1 className="text-xl font-semibold text-on-surface">Rekonsiliasi Komisi & Payout</h1>
      <p className="mt-1 text-sm text-on-surface-muted">
        Ringkasan komisi platform vs dana yang sudah dicairkan ke penjual, dari setiap percobaan payout yang tercatat.
      </p>

      {isLoading || !response ? (
        <p className="mt-6 text-sm text-on-surface-muted">Memuat...</p>
      ) : (
        <>
          <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div className="rounded-lg border border-border bg-surface-container p-4">
              <p className="text-xs uppercase text-on-surface-muted">Total Komisi Platform</p>
              <p className="mt-1 text-lg font-semibold text-on-surface">{formatRupiah(response.summary.total_commission)}</p>
            </div>
            <div className="rounded-lg border border-border bg-surface-container p-4">
              <p className="text-xs uppercase text-on-surface-muted">Total Payout ke Penjual</p>
              <p className="mt-1 text-lg font-semibold text-on-surface">{formatRupiah(response.summary.total_payout)}</p>
            </div>
            <div className="rounded-lg border border-border bg-surface-container p-4">
              <p className="text-xs uppercase text-on-surface-muted">Jumlah Payout Berhasil</p>
              <p className="mt-1 text-lg font-semibold text-on-surface">{response.summary.paid_count}</p>
            </div>
          </div>

          <div className="mt-6 overflow-x-auto rounded-lg border border-border bg-surface-container">
            <table className="min-w-full divide-y divide-border text-sm">
              <thead className="bg-surface-container-high">
                <tr>
                  <th className="px-4 py-2 text-left font-medium text-on-surface-muted">Tanggal</th>
                  <th className="px-4 py-2 text-left font-medium text-on-surface-muted">Kendaraan / Penjual</th>
                  <th className="px-4 py-2 text-left font-medium text-on-surface-muted">Metode</th>
                  <th className="px-4 py-2 text-left font-medium text-on-surface-muted">Status</th>
                  <th className="px-4 py-2 text-right font-medium text-on-surface-muted">Komisi</th>
                  <th className="px-4 py-2 text-right font-medium text-on-surface-muted">Payout</th>
                  <th className="px-4 py-2 text-left font-medium text-on-surface-muted">Referensi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {response.data.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-4 py-6 text-center text-on-surface-muted">
                      Belum ada payout tercatat.
                    </td>
                  </tr>
                ) : (
                  response.data.map((payout) => (
                    <tr key={payout.id}>
                      <td className="px-4 py-2 text-on-surface">{formatDate(payout.created_at)}</td>
                      <td className="px-4 py-2 text-on-surface">
                        {payout.transaction?.vehicle} &middot; {payout.transaction?.seller}
                      </td>
                      <td className="px-4 py-2 text-on-surface">{payout.method}</td>
                      <td className="px-4 py-2">
                        <PayoutStatusBadge status={payout.status} />
                      </td>
                      <td className="px-4 py-2 text-right text-on-surface">{formatRupiah(payout.commission_amount)}</td>
                      <td className="px-4 py-2 text-right text-on-surface">{formatRupiah(payout.payout_amount)}</td>
                      <td className="px-4 py-2 text-on-surface">{payout.reference ?? "-"}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}
