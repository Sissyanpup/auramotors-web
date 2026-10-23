"use client";

import { Fragment, useState } from "react";
import Link from "next/link";
import Badge, { type BadgeTone } from "@/components/Badge";
import { formatDate } from "@/lib/format";
import type { ActivityCategory, ActivityLog } from "@/lib/types";

export const CATEGORY_TONE: Record<ActivityCategory, BadgeTone> = {
  account: "neutral",
  verification: "info",
  transaction: "primary",
  payment: "success",
  handover: "warning",
  document: "neutral",
  shipment: "info",
  credit: "warning",
  payout: "success",
  bonus: "primary",
  promo: "primary",
};

const ROLE_LABEL: Record<string, string> = { admin: "Admin", seller: "Penjual", buyer: "Pembeli" };

/**
 * Tabel jejak aktivitas. Dipakai di halaman Log Aktivitas dan (mode ringkas)
 * di detail transaksi admin sebagai timeline per transaksi.
 */
export default function ActivityLogTable({ logs, compact = false }: { logs: ActivityLog[]; compact?: boolean }) {
  const [openId, setOpenId] = useState<number | null>(null);

  if (logs.length === 0) {
    return <p className="text-sm text-on-surface-muted">Belum ada aktivitas yang cocok dengan filter.</p>;
  }

  return (
    <div className="overflow-x-auto">
      <table className={`w-full text-sm ${compact ? "min-w-[560px]" : "min-w-[860px]"}`}>
        <thead>
          <tr className="border-b border-border text-left text-[11px] uppercase tracking-wider text-on-surface-muted">
            <th className="py-2 pr-3 font-medium">Waktu</th>
            <th className="py-2 pr-3 font-medium">Aktor</th>
            <th className="py-2 pr-3 font-medium">Kategori</th>
            <th className="py-2 pr-3 font-medium">Aktivitas</th>
            {!compact && <th className="py-2 pr-3 font-medium">Terkait</th>}
          </tr>
        </thead>
        <tbody className="divide-y divide-border align-top">
          {logs.map((log) => {
            const hasDetail = !!log.properties || !!log.ip_address;
            const isOpen = openId === log.id;
            return (
              <Fragment key={log.id}>
                <tr>
                  <td className="whitespace-nowrap py-2 pr-3 text-xs text-on-surface-muted tabular-nums">{formatDate(log.created_at)}</td>
                  <td className="py-2 pr-3">
                    {log.actor ? (
                      <>
                        <p className="text-on-surface">{log.actor.name}</p>
                        <p className="text-[11px] text-on-surface-muted">{ROLE_LABEL[log.actor.role ?? ""] ?? "Pengguna"}</p>
                      </>
                    ) : (
                      <p className="text-on-surface-muted italic">Sistem</p>
                    )}
                  </td>
                  <td className="py-2 pr-3">
                    <Badge tone={CATEGORY_TONE[log.category]}>{log.category_label}</Badge>
                  </td>
                  <td className="py-2 pr-3">
                    <p className="text-on-surface">{log.description}</p>
                    <p className="font-mono text-[10px] text-on-surface-muted">{log.action}</p>
                    {hasDetail && (
                      <button
                        type="button"
                        onClick={() => setOpenId(isOpen ? null : log.id)}
                        className="mt-1 text-[11px] text-primary underline"
                        aria-expanded={isOpen}
                      >
                        {isOpen ? "Tutup detail" : "Detail"}
                      </button>
                    )}
                  </td>
                  {!compact && (
                    <td className="py-2 pr-3 text-xs text-on-surface-muted">
                      {log.subject_type && (
                        <p>
                          {log.subject_type} #{log.subject_id}
                        </p>
                      )}
                      {log.transaction_id && (
                        <Link href={`/admin/activity?transaction_id=${log.transaction_id}`} className="text-on-surface underline">
                          Transaksi #{log.transaction_id}
                        </Link>
                      )}
                    </td>
                  )}
                </tr>
                {isOpen && (
                  <tr>
                    <td colSpan={compact ? 4 : 5} className="pb-3">
                      <dl className="grid grid-cols-1 gap-1 rounded-md bg-surface-container-high p-3 text-xs sm:grid-cols-2">
                        {Object.entries(log.properties ?? {}).map(([key, value]) => (
                          <div key={key} className="flex gap-2">
                            <dt className="text-on-surface-muted">{key}</dt>
                            <dd className="break-all text-on-surface">{Array.isArray(value) ? value.join(", ") : String(value ?? "—")}</dd>
                          </div>
                        ))}
                        {log.ip_address && (
                          <div className="flex gap-2">
                            <dt className="text-on-surface-muted">IP</dt>
                            <dd className="font-mono text-on-surface">{log.ip_address}</dd>
                          </div>
                        )}
                      </dl>
                    </td>
                  </tr>
                )}
              </Fragment>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
