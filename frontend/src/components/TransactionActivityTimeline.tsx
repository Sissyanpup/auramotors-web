"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { apiFetch } from "@/lib/api";
import type { ActivityLog } from "@/lib/types";
import ActivityLogTable from "@/components/ActivityLogTable";

/**
 * Semua jejak aktivitas satu transaksi (checkout → bayar → serah terima →
 * kredit → payout) untuk admin. `refreshKey` berubah setiap ada aksi admin
 * supaya timeline ikut diperbarui.
 */
export default function TransactionActivityTimeline({ transactionId, refreshKey }: { transactionId: number; refreshKey?: unknown }) {
  const [logs, setLogs] = useState<ActivityLog[] | null>(null);

  useEffect(() => {
    let cancelled = false;
    apiFetch<{ data: ActivityLog[] }>(`/api/admin/activity-logs?transaction_id=${transactionId}&per_page=100`).then(({ data }) => {
      if (!cancelled) setLogs(data);
    });
    return () => {
      cancelled = true;
    };
  }, [transactionId, refreshKey]);

  return (
    <div>
      <div className="mb-2 flex items-center justify-between">
        <p className="text-xs font-semibold uppercase text-on-surface-muted">Jejak Aktivitas Transaksi</p>
        <Link href={`/admin/activity?transaction_id=${transactionId}`} className="text-xs text-on-surface underline">
          Buka di Log Aktivitas
        </Link>
      </div>
      {logs === null ? <p className="text-sm text-on-surface-muted">Memuat jejak...</p> : <ActivityLogTable logs={logs} compact />}
    </div>
  );
}
