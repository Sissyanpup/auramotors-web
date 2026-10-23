"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { apiFetch } from "@/lib/api";
import { formatDate, formatRupiah } from "@/lib/format";
import type { SellerBonusItem, SellerBonusSummary } from "@/lib/types";
import Badge from "@/components/Badge";

/**
 * Bonus keberhasilan menjual (catatan dosen 16 Okt): bonus per unit terjual +
 * bonus tier, dibayarkan bersama pencairan dana penjualan.
 */
export default function SellerBonusPage() {
  const [items, setItems] = useState<SellerBonusItem[]>([]);
  const [summary, setSummary] = useState<SellerBonusSummary | null>(null);

  useEffect(() => {
    apiFetch<{ data: SellerBonusItem[]; summary: SellerBonusSummary }>("/api/seller/bonuses").then((response) => {
      setItems(response.data);
      setSummary(response.summary);
    });
  }, []);

  if (!summary) {
    return <p className="text-sm text-on-surface-muted">Memuat...</p>;
  }

  const next = summary.next_tier;
  const lastThreshold = summary.tiers[summary.tiers.length - 1]?.threshold ?? 1;
  const progress = Math.min(100, Math.round((summary.completed_sales / lastThreshold) * 100));
  const ratePercent = (summary.sale_rate * 100).toLocaleString("id-ID", { maximumFractionDigits: 2 });

  return (
    <div>
      <h1 className="text-xl font-semibold text-on-surface">Bonus Penjual</h1>
      <p className="mt-1 text-sm text-on-surface-muted">
        Setiap mobil yang berhasil terjual memberi bonus {ratePercent}% dari harga, ditambah bonus pencapaian saat jumlah penjualan
        sukses Anda mencapai target. Bonus ikut ditransfer bersama pencairan dana penjualan.
      </p>

      <dl className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div className="rounded-lg border border-border bg-surface-container p-4">
          <dt className="text-xs text-on-surface-muted">Total bonus diterima</dt>
          <dd className="mt-1 text-2xl font-semibold tabular-nums text-primary">{formatRupiah(summary.total_bonus)}</dd>
        </div>
        <div className="rounded-lg border border-border bg-surface-container p-4">
          <dt className="text-xs text-on-surface-muted">Penjualan sukses</dt>
          <dd className="mt-1 text-2xl font-semibold tabular-nums text-on-surface">{summary.completed_sales} unit</dd>
        </div>
        <div className="rounded-lg border border-border bg-surface-container p-4">
          <dt className="text-xs text-on-surface-muted">Target berikutnya</dt>
          <dd className="mt-1 text-sm text-on-surface">
            {next ? (
              <>
                Jual <strong>{next.remaining_sales}</strong> unit lagi untuk bonus <strong className="text-primary">{formatRupiah(next.amount)}</strong>
              </>
            ) : (
              "Semua target tercapai — luar biasa!"
            )}
          </dd>
        </div>
      </dl>

      <section className="mt-5 rounded-lg border border-border bg-surface-container p-4">
        <h2 className="text-sm font-semibold text-on-surface">Target bonus pencapaian</h2>
        <div className="mt-3 h-2 overflow-hidden rounded-full bg-surface-container-high" aria-hidden>
          <div className="h-full rounded-full bg-primary" style={{ width: `${progress}%` }} />
        </div>
        <ul className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
          {summary.tiers.map((tier) => (
            <li key={tier.threshold} className="flex items-center justify-between rounded-md border border-border bg-surface px-3 py-2 text-sm">
              <span className="text-on-surface">{tier.threshold} penjualan</span>
              <span className="flex items-center gap-2">
                <span className="tabular-nums text-on-surface">{formatRupiah(tier.amount)}</span>
                {tier.achieved ? <Badge tone="success">Tercapai</Badge> : <Badge tone="neutral">Belum</Badge>}
              </span>
            </li>
          ))}
        </ul>
      </section>

      <section className="mt-5 rounded-lg border border-border bg-surface-container p-4">
        <h2 className="text-sm font-semibold text-on-surface">Riwayat bonus</h2>
        {items.length === 0 ? (
          <p className="mt-2 text-sm text-on-surface-muted">
            Belum ada bonus. Bonus pertama masuk saat dana penjualan pertama Anda dicairkan.{" "}
            <Link href="/seller/vehicles" className="text-on-surface underline">
              Kelola listing
            </Link>
          </p>
        ) : (
          <div className="mt-3 overflow-x-auto">
            <table className="w-full min-w-[560px] text-sm">
              <thead>
                <tr className="border-b border-border text-left text-[11px] uppercase tracking-wider text-on-surface-muted">
                  <th className="py-2 pr-3 font-medium">Tanggal</th>
                  <th className="py-2 pr-3 font-medium">Jenis</th>
                  <th className="py-2 pr-3 font-medium">Keterangan</th>
                  <th className="py-2 text-right font-medium">Jumlah</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {items.map((item) => (
                  <tr key={item.id}>
                    <td className="whitespace-nowrap py-2 pr-3 text-xs text-on-surface-muted">{item.paid_at ? formatDate(item.paid_at) : "—"}</td>
                    <td className="py-2 pr-3">
                      <Badge tone={item.type === "tier" ? "primary" : "info"}>{item.type === "tier" ? "Pencapaian" : "Per unit"}</Badge>
                    </td>
                    <td className="py-2 pr-3">
                      <p className="text-on-surface">{item.basis}</p>
                      {item.vehicle && item.transaction_id && (
                        <Link href={`/seller/transactions/${item.transaction_id}`} className="text-xs text-on-surface-muted underline">
                          {item.vehicle}
                        </Link>
                      )}
                    </td>
                    <td className="py-2 text-right tabular-nums text-on-surface">{formatRupiah(item.amount)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}
