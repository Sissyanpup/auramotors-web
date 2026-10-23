"use client";

import Link from "next/link";
import StatusBadge from "@/components/StatusBadge";
import { formatDate } from "@/lib/format";

export type VerificationFilter = { key: string; label: string };

/**
 * Chip filter status untuk tabel verifikasi. Default-nya "Semua": data yang
 * sudah disetujui/ditolak tetap terlihat supaya jejak verifikasi lengkap.
 */
export function VerificationFilterBar({
  filters,
  active,
  onChange,
}: {
  filters: VerificationFilter[];
  active: string;
  onChange: (key: string) => void;
}) {
  return (
    <div className="mt-4 flex flex-wrap gap-2">
      {filters.map((filter) => (
        <button
          key={filter.key}
          onClick={() => onChange(filter.key)}
          className={`rounded-full px-3 py-1 text-xs font-medium ${
            active === filter.key ? "bg-primary-container text-on-primary" : "bg-surface-container-high text-on-surface-muted hover:bg-border"
          }`}
        >
          {filter.label}
        </button>
      ))}
    </div>
  );
}

/** Status + siapa/kapan meninjau + alasan + tautan ke jejak lengkapnya. */
export function ReviewCell({
  status,
  reviewedBy,
  reviewedAt,
  rejectionReason,
  historyHref,
}: {
  status: string;
  reviewedBy?: string | null;
  reviewedAt?: string | null;
  rejectionReason?: string | null;
  historyHref: string;
}) {
  return (
    <div className="space-y-1">
      <StatusBadge status={status} />
      {reviewedAt ? (
        <p className="text-[11px] text-on-surface-muted">
          oleh {reviewedBy ?? "admin"} · {formatDate(reviewedAt)}
        </p>
      ) : (
        <p className="text-[11px] text-on-surface-muted">Belum ditinjau</p>
      )}
      {rejectionReason && <p className="text-[11px] text-error">Alasan: {rejectionReason}</p>}
      <Link href={historyHref} className="text-[11px] text-on-surface underline">
        Lihat jejak
      </Link>
    </div>
  );
}

export function Pager({ page, lastPage, total, onPage }: { page: number; lastPage: number; total: number; onPage: (page: number) => void }) {
  if (lastPage <= 1) {
    return <p className="mt-3 text-xs text-on-surface-muted">{total} data</p>;
  }

  return (
    <div className="mt-4 flex items-center justify-between text-sm">
      <p className="text-on-surface-muted">
        Halaman {page} dari {lastPage} · {total} data
      </p>
      <div className="flex gap-2">
        <button disabled={page <= 1} onClick={() => onPage(page - 1)} className="rounded-md border border-border px-3 py-1 text-on-surface disabled:opacity-40">
          Sebelumnya
        </button>
        <button disabled={page >= lastPage} onClick={() => onPage(page + 1)} className="rounded-md border border-border px-3 py-1 text-on-surface disabled:opacity-40">
          Berikutnya
        </button>
      </div>
    </div>
  );
}
