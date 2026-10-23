"use client";

import { use, useEffect, useState, type FormEvent } from "react";
import { apiFetch } from "@/lib/api";
import type { ActivityCategory, ActivityLog } from "@/lib/types";
import ActivityLogTable from "@/components/ActivityLogTable";

type CategoryOption = { value: ActivityCategory; label: string; count: number };

type ActivityResponse = {
  data: ActivityLog[];
  meta: { current_page: number; last_page: number; total: number };
  categories: CategoryOption[];
};

type Filters = {
  category: string;
  actor_role: string;
  search: string;
  from: string;
  to: string;
  transaction_id: string;
  subject_type: string;
  subject_id: string;
};

const ROLE_OPTIONS = [
  { value: "", label: "Semua aktor" },
  { value: "admin", label: "Admin" },
  { value: "seller", label: "Penjual" },
  { value: "buyer", label: "Pembeli" },
  { value: "system", label: "Sistem" },
];

/**
 * Log aktivitas global: semua jejak proses jual beli — termasuk verifikasi
 * yang sudah selesai — untuk dipantau admin (catatan dosen 16 Okt).
 */
export default function AdminActivityPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const initial = use(searchParams);
  const [filters, setFilters] = useState<Filters>({
    category: initial.category ?? "",
    actor_role: initial.actor_role ?? "",
    search: initial.search ?? "",
    from: initial.from ?? "",
    to: initial.to ?? "",
    transaction_id: initial.transaction_id ?? "",
    subject_type: initial.subject_type ?? "",
    subject_id: initial.subject_id ?? "",
  });
  const [draftSearch, setDraftSearch] = useState(filters.search);
  const [page, setPage] = useState(1);
  const [result, setResult] = useState<ActivityResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    const query = new URLSearchParams({ page: String(page) });
    Object.entries(filters).forEach(([key, value]) => {
      if (value) query.set(key, value);
    });
    // eslint-disable-next-line react-hooks/set-state-in-effect -- loading indicator for refetch
    setIsLoading(true);
    apiFetch<ActivityResponse>(`/api/admin/activity-logs?${query.toString()}`).then((data) => {
      if (cancelled) return;
      setResult(data);
      setIsLoading(false);
    });
    return () => {
      cancelled = true;
    };
  }, [filters, page]);

  function updateFilter(patch: Partial<Filters>) {
    setFilters((prev) => ({ ...prev, ...patch }));
    setPage(1);
  }

  function handleSearch(event: FormEvent) {
    event.preventDefault();
    updateFilter({ search: draftSearch.trim() });
  }

  const scoped = filters.transaction_id || filters.subject_type;
  const totalAll = result?.categories.reduce((sum, item) => sum + item.count, 0) ?? 0;

  return (
    <div>
      <h1 className="text-xl font-semibold text-on-surface">Log Aktivitas</h1>
      <p className="mt-1 text-sm text-on-surface-muted">
        Semua jejak proses — verifikasi, transaksi, serah terima, kredit, payout, bonus, dan promo — tercatat otomatis beserta
        pelakunya. Data yang sudah terverifikasi tetap ditampilkan.
      </p>

      {scoped && (
        <div className="mt-4 flex flex-wrap items-center gap-2 rounded-md border border-border bg-surface-container px-3 py-2 text-sm">
          <span className="text-on-surface-muted">Disaring untuk</span>
          {filters.transaction_id && <span className="font-medium text-on-surface">Transaksi #{filters.transaction_id}</span>}
          {filters.subject_type && (
            <span className="font-medium text-on-surface">
              {filters.subject_type} #{filters.subject_id}
            </span>
          )}
          <button
            type="button"
            onClick={() => updateFilter({ transaction_id: "", subject_type: "", subject_id: "" })}
            className="ml-auto text-xs text-primary underline"
          >
            Hapus saringan
          </button>
        </div>
      )}

      <div className="mt-4 flex flex-wrap gap-2">
        <button
          onClick={() => updateFilter({ category: "" })}
          className={`rounded-full px-3 py-1 text-xs font-medium ${
            filters.category === "" ? "bg-primary-container text-on-primary" : "bg-surface-container-high text-on-surface-muted hover:bg-border"
          }`}
        >
          Semua ({totalAll})
        </button>
        {result?.categories.map((item) => (
          <button
            key={item.value}
            onClick={() => updateFilter({ category: item.value })}
            className={`rounded-full px-3 py-1 text-xs font-medium ${
              filters.category === item.value ? "bg-primary-container text-on-primary" : "bg-surface-container-high text-on-surface-muted hover:bg-border"
            }`}
          >
            {item.label} ({item.count})
          </button>
        ))}
      </div>

      <div className="mt-3 flex flex-wrap items-end gap-3">
        <form onSubmit={handleSearch} className="flex min-w-[220px] flex-1 gap-2">
          <input
            type="search"
            value={draftSearch}
            onChange={(e) => setDraftSearch(e.target.value)}
            placeholder="Cari deskripsi, nama, atau kendaraan…"
            className="input-field"
            aria-label="Cari aktivitas"
          />
          <button type="submit" className="rounded-md border border-border px-3 text-sm text-on-surface hover:bg-surface-container-high">
            Cari
          </button>
        </form>
        <label className="text-xs text-on-surface-muted">
          Aktor
          <select
            value={filters.actor_role}
            onChange={(e) => updateFilter({ actor_role: e.target.value })}
            className="input-field mt-1 py-1.5"
          >
            {ROLE_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>
        <label className="text-xs text-on-surface-muted">
          Dari
          <input type="date" value={filters.from} onChange={(e) => updateFilter({ from: e.target.value })} className="input-field mt-1 py-1.5" />
        </label>
        <label className="text-xs text-on-surface-muted">
          Sampai
          <input type="date" value={filters.to} onChange={(e) => updateFilter({ to: e.target.value })} className="input-field mt-1 py-1.5" />
        </label>
      </div>

      <div className="mt-5 rounded-lg border border-border bg-surface-container p-4">
        {isLoading && !result ? (
          <p className="text-sm text-on-surface-muted">Memuat...</p>
        ) : (
          <div className={isLoading ? "opacity-60" : undefined}>
            <ActivityLogTable logs={result?.data ?? []} />
          </div>
        )}

        {result && result.meta.last_page > 1 && (
          <div className="mt-4 flex items-center justify-between text-sm">
            <p className="text-on-surface-muted">
              Halaman {result.meta.current_page} dari {result.meta.last_page} · {result.meta.total} aktivitas
            </p>
            <div className="flex gap-2">
              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => p - 1)}
                className="rounded-md border border-border px-3 py-1 text-on-surface disabled:opacity-40"
              >
                Sebelumnya
              </button>
              <button
                disabled={page >= result.meta.last_page}
                onClick={() => setPage((p) => p + 1)}
                className="rounded-md border border-border px-3 py-1 text-on-surface disabled:opacity-40"
              >
                Berikutnya
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
