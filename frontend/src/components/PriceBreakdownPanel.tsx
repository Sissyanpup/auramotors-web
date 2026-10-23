"use client";

import type { ReactNode } from "react";
import { formatRupiah } from "@/lib/format";
import type { BreakdownLine, PriceBreakdown } from "@/lib/types";

type Props = {
  breakdown: PriceBreakdown;
  /** Buyer melihat biaya kepemilikan; seller melihat hak bersihnya dibuka duluan. */
  perspective: "buyer" | "seller" | "admin";
  title?: string;
  intro?: string;
};

function percent(value: number): string {
  return `${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 3 }).format(value)}%`;
}

function LineTable({ lines, totalLabel, total, showPayee = false }: {
  lines: BreakdownLine[];
  totalLabel?: string;
  total?: number;
  showPayee?: boolean;
}) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[480px] text-sm">
        <thead>
          <tr className="border-b border-primary/40 text-left text-[11px] uppercase tracking-wider text-on-surface-muted">
            <th className="py-2 pr-3 font-medium">Komponen</th>
            <th className="py-2 pr-3 font-medium">Dasar perhitungan</th>
            {showPayee && <th className="py-2 pr-3 font-medium">Dibayar ke</th>}
            <th className="py-2 text-right font-medium">Jumlah</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          {lines.map((line, i) => (
            <tr key={`${line.label}-${i}`}>
              <td className="py-2 pr-3 text-on-surface">{line.label}</td>
              <td className="py-2 pr-3 text-xs text-on-surface-muted">{line.basis ?? "—"}</td>
              {showPayee && <td className="py-2 pr-3 text-xs text-on-surface-muted">{line.payee ?? "—"}</td>}
              <td className="whitespace-nowrap py-2 text-right tabular-nums text-on-surface">{formatRupiah(line.amount)}</td>
            </tr>
          ))}
          {totalLabel !== undefined && total !== undefined && (
            <tr className="font-semibold">
              <td className="py-2 pr-3 text-on-surface" colSpan={showPayee ? 3 : 2}>{totalLabel}</td>
              <td className="whitespace-nowrap py-2 text-right tabular-nums text-primary">{formatRupiah(total)}</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}

function Section({ title, hint, defaultOpen = false, children }: {
  title: string;
  hint?: string;
  defaultOpen?: boolean;
  children: ReactNode;
}) {
  return (
    <details open={defaultOpen} className="group rounded-md border border-border bg-surface">
      <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
        <span>
          <span className="text-sm font-medium text-on-surface">{title}</span>
          {hint && <span className="ml-2 text-xs text-on-surface-muted">{hint}</span>}
        </span>
        <span aria-hidden className="text-xs text-on-surface-muted transition-transform group-open:rotate-180">▼</span>
      </summary>
      <div className="border-t border-border px-4 pb-4 pt-2">{children}</div>
    </details>
  );
}

/**
 * Rincian harga lengkap — angka diambil apa adanya dari backend
 * (PriceBreakdownCalculator), tidak dihitung ulang di sini, supaya sama persis
 * dengan PDF "Rincian Harga, Pajak & Biaya".
 */
export default function PriceBreakdownPanel({ breakdown, perspective, title = "Rincian Harga, Pajak & Biaya", intro }: Props) {
  const { payment, insurance, seller_settlement: seller, credit_simulation: credit } = breakdown;
  const isFull = payment.scheme === "full";
  const plan = breakdown.credit ?? null;
  const remainingFromLender = seller.remaining_payer === "lender";
  const appliedPromos = (breakdown.promotions?.lines ?? []).filter((line) => line.applied);

  const summary: { label: string; value: string; strong?: boolean }[] = [
    { label: "Harga kendaraan (termasuk PPN & PPnBM)", value: formatRupiah(breakdown.vehicle_price) },
    {
      label: isFull ? "Skema: bayar penuh" : plan ? `Skema: kredit leasing, DP ${percent(payment.dp_percent)}` : `Skema: DP ${percent(payment.dp_percent)}`,
      value: formatRupiah(payment.dp_amount),
    },
    { label: "Asuransi tahun pertama", value: insurance.total > 0 ? formatRupiah(insurance.total) : "Tanpa asuransi" },
    ...((breakdown.promotions?.due_now_saving ?? 0) > 0
      ? [{ label: "Hemat dari promo (subsidi platform)", value: `− ${formatRupiah(breakdown.promotions?.due_now_saving ?? 0)}` }]
      : []),
    { label: "Dibayar sekarang ke escrow", value: formatRupiah(breakdown.due_now.total), strong: true },
  ];
  if (!isFull) {
    summary.push({
      label: remainingFromLender ? "Sisa harga dilunasi leasing ke penjual" : "Sisa pelunasan ke penjual saat serah terima",
      value: formatRupiah(payment.remaining_amount),
    });
  }
  if (plan && perspective !== "seller") {
    summary.push({ label: `Angsuran ke leasing × ${plan.tenor_months} bulan`, value: formatRupiah(plan.monthly_installment), strong: true });
  }
  if (perspective !== "seller") {
    summary.push({
      label: breakdown.registration.in_escrow ? "Biaya balik nama (termasuk tagihan escrow)" : "Estimasi balik nama (Samsat)",
      value: formatRupiah(breakdown.registration.total),
    });
    summary.push({
      label: plan ? "Total biaya kepemilikan sampai kredit lunas" : "Total biaya kepemilikan tahun pertama",
      value: formatRupiah(breakdown.total_cost_of_ownership.total),
      strong: true,
    });
  }
  if (perspective !== "buyer") {
    summary.push({ label: "Dana bersih diterima penjual", value: formatRupiah(seller.total_net), strong: true });
  }

  return (
    <section className="rounded-lg border border-border bg-surface-container p-5">
      <h2 className="font-display text-lg text-on-surface">{title}</h2>
      <p className="mt-0.5 text-xs text-on-surface-muted">
        {intro ?? "Setiap angka disertai rumusnya, supaya pembeli dan penjual paham kenapa nilainya segitu. Sama persis dengan lampiran PDF kontrak."}
      </p>

      <dl className="mt-4 divide-y divide-border rounded-md border border-border bg-surface text-sm">
        {summary.map((row) => (
          <div key={row.label} className={`flex items-center justify-between gap-3 px-3 py-2 ${row.strong ? "font-semibold" : ""}`}>
            <dt className={row.strong ? "text-on-surface" : "text-on-surface-muted"}>{row.label}</dt>
            <dd className={`whitespace-nowrap tabular-nums ${row.strong ? "text-primary" : "text-on-surface"}`}>{row.value}</dd>
          </div>
        ))}
      </dl>

      <div className="mt-4 space-y-2">
        <Section title="Komposisi pajak dalam harga" hint="DPP · PPN · PPnBM">
          <LineTable lines={breakdown.price_composition.lines} totalLabel="Harga kendaraan" total={breakdown.price_composition.total} />
          <p className="mt-2 text-xs text-on-surface-muted">{breakdown.price_composition.note}</p>
        </Section>

        <Section title="Tagihan yang dibayar sekarang" defaultOpen={perspective === "buyer"}>
          <LineTable lines={breakdown.due_now.lines} totalLabel="Total ke rekening escrow" total={breakdown.due_now.total} />
        </Section>

        {appliedPromos.length > 0 && (
          <Section title="Promo yang dipakai" hint={`hemat ${formatRupiah(breakdown.promotions?.total_saving ?? 0)}`} defaultOpen={perspective === "buyer"}>
            <LineTable
              lines={appliedPromos.map((line) => ({
                label: `${line.label}${line.code ? ` (kode ${line.code})` : ""}`,
                basis: line.in_due_now ? `${line.basis} — mengurangi tagihan sekarang` : `${line.basis} — mengurangi total cicilan`,
                amount: -line.saving,
              }))}
              totalLabel="Total hemat"
              total={-(breakdown.promotions?.total_saving ?? 0)}
            />
            <p className="mt-2 text-xs text-on-surface-muted">{breakdown.promotions?.note}</p>
          </Section>
        )}

        {insurance.type !== "none" && (
          <Section title={`Asuransi ${insurance.label}`} hint={insurance.insurer ?? undefined}>
            <p className="mb-2 text-xs text-on-surface-muted">Harga pertanggungan (TSI): {formatRupiah(insurance.sum_insured)}</p>
            <LineTable lines={insurance.lines} totalLabel="Total premi & biaya polis" total={insurance.total} />
            <ul className="mt-3 list-disc space-y-1 pl-5 text-xs text-on-surface-muted">
              {insurance.deductible_per_claim !== null && (
                <li>Risiko sendiri {formatRupiah(insurance.deductible_per_claim)} per kejadian untuk kerusakan sebagian.</li>
              )}
              <li>Kerusakan ≥ {insurance.total_loss_threshold_percent}% dianggap kerugian total (total loss).</li>
              <li>Kehilangan/pencurian diganti {insurance.theft_settlement_percent}% dari harga pertanggungan.</li>
              <li>Jaminan gugur bila mobil dipakai taksi online/rental atau dikemudikan tanpa SIM sah.</li>
            </ul>
          </Section>
        )}

        {perspective !== "seller" && (
          <Section
            title="Estimasi biaya balik nama"
            hint={breakdown.registration.in_escrow ? "dibayar bersama DP, diteruskan escrow ke Samsat" : "dibayar pembeli ke Samsat"}
          >
            <p className="mb-2 text-xs text-on-surface-muted">
              NJKB simulasi {formatRupiah(breakdown.registration.njkb)} ({breakdown.registration.njkb_basis}).
            </p>
            <LineTable lines={breakdown.registration.lines} totalLabel="Total estimasi" total={breakdown.registration.total} showPayee />
            <p className="mt-2 text-xs text-on-surface-muted">{breakdown.registration.note}</p>
          </Section>
        )}

        {perspective !== "buyer" && (
          <Section title="Hak bersih penjual" defaultOpen={perspective === "seller"}>
            <LineTable
              lines={[
                { label: "Porsi kendaraan yang ditahan escrow", basis: isFull ? "Pelunasan penuh" : `DP ${percent(payment.dp_percent)}`, amount: seller.escrow_vehicle_portion },
                { label: "Dikurangi biaya layanan escrow", basis: seller.commission_basis, amount: -seller.commission },
                ...(!isFull
                  ? [
                      remainingFromLender
                        ? { label: "Pelunasan sisa harga dari leasing", basis: `${plan?.lender ?? "Leasing"} — dibayar sekaligus setelah kredit disetujui`, amount: seller.direct_remaining }
                        : { label: "Sisa pelunasan langsung dari pembeli", basis: "Dibayar saat serah terima", amount: seller.direct_remaining },
                    ]
                  : []),
              ]}
              totalLabel="Total diterima penjual"
              total={seller.total_net}
            />
            <p className="mt-2 text-xs text-on-surface-muted">{seller.note}</p>
          </Section>
        )}

        {plan && (
          <Section title="Rencana kredit leasing" hint={plan.lender} defaultOpen={perspective === "buyer"}>
            <LineTable
              lines={[
                { label: "Sisa harga kendaraan dibiayai", basis: "Dilunasi leasing ke penjual", amount: plan.vehicle_financed },
                { label: "Premi asuransi tahun ke-2 dst.", basis: "Asuransi wajib selama tenor, ikut dicicil", amount: plan.insurance_financed },
                { label: "Bunga kredit", basis: `${plan.rate_basis} · efektif ±${percent(plan.effective_rate * 100)}/thn`, amount: plan.interest_total },
              ]}
              totalLabel={`Total dibayar ke leasing (${formatRupiah(plan.monthly_installment)} × ${plan.tenor_months})`}
              total={plan.total_payable}
            />
            <p className="mt-3 text-xs font-semibold text-on-surface">Biaya kredit di muka (sudah termasuk tagihan escrow)</p>
            <LineTable lines={plan.upfront.lines} totalLabel="Total biaya di muka" total={plan.upfront.total} />
            <p className="mt-2 text-xs text-on-surface-muted">{plan.note}</p>
          </Section>
        )}

        {perspective !== "seller" && (
          <Section title={plan ? "Perbandingan tenor kredit" : "Simulasi kredit sisa pelunasan"} hint="ilustrasi bunga flat">
            {!credit.applicable ? (
              <p className="text-sm text-on-surface-muted">{credit.note}</p>
            ) : (
              <>
                <p className="mb-2 text-xs text-on-surface-muted">
                  {credit.lender} · pokok {formatRupiah(credit.principal ?? 0)} · provisi {percent((credit.provision_rate ?? 0) * 100)} (
                  {formatRupiah(credit.provision_amount ?? 0)}) + admin {formatRupiah(credit.admin_fee ?? 0)}
                  {credit.fiducia_fee ? ` + fidusia ${formatRupiah(credit.fiducia_fee)}` : ""} di muka
                  {credit.age_surcharge_rate ? ` · tambahan bunga ${percent(credit.age_surcharge_rate * 100)} untuk unit berumur ${credit.vehicle_age_years} tahun` : ""}.
                </p>
                <div className="overflow-x-auto">
                  <table className="w-full min-w-[520px] text-sm">
                    <thead>
                      <tr className="border-b border-primary/40 text-right text-[11px] uppercase tracking-wider text-on-surface-muted">
                        <th className="py-2 pr-3 text-left font-medium">Tenor</th>
                        <th className="py-2 pr-3 font-medium">Bunga/thn</th>
                        <th className="py-2 pr-3 font-medium">Efektif</th>
                        <th className="py-2 pr-3 font-medium">Angsuran/bln</th>
                        <th className="py-2 pr-3 font-medium">+ Asuransi</th>
                        <th className="py-2 font-medium">Total bayar</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border text-right tabular-nums">
                      {credit.rows.map((row) => (
                        <tr key={row.tenor_months}>
                          <td className="py-2 pr-3 text-left text-on-surface">{row.tenor_months} bln</td>
                          <td className="py-2 pr-3 text-on-surface-muted">{percent(row.flat_rate * 100)}</td>
                          <td className="py-2 pr-3 text-on-surface-muted">{row.effective_rate !== undefined ? percent(row.effective_rate * 100) : "–"}</td>
                          <td className="py-2 pr-3 text-on-surface">{formatRupiah(row.monthly_installment)}</td>
                          <td className="py-2 pr-3 text-on-surface-muted">{formatRupiah(row.monthly_installment_with_insurance)}</td>
                          <td className="py-2 text-on-surface">{formatRupiah(row.total_payment)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <p className="mt-2 text-xs text-on-surface-muted">{credit.note}</p>
              </>
            )}
          </Section>
        )}
      </div>
    </section>
  );
}
