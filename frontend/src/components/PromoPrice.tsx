import { formatRupiah } from "@/lib/format";
import type { CatalogPromo } from "@/lib/types";

/** Sisa waktu promo dalam bahasa singkat untuk memicu urgensi ("2 hari lagi"). */
export function promoTimeLeft(endsAt: string | null): string | null {
  if (!endsAt) return null;
  const ms = new Date(endsAt).getTime() - Date.now();
  if (ms <= 0) return null;
  const hours = Math.floor(ms / 3_600_000);
  if (hours < 24) return `berakhir ${Math.max(hours, 1)} jam lagi`;
  return `berakhir ${Math.ceil(hours / 24)} hari lagi`;
}

/**
 * Harga dengan teknik harga coret: harga asli dicoret, harga setelah promo
 * menonjol, plus badge & hemat berapa. Tanpa promo tampil harga biasa.
 */
export default function PromoPrice({ price, promo, size = "md" }: { price: string | number; promo?: CatalogPromo | null; size?: "md" | "lg" }) {
  const main = size === "lg" ? "text-2xl font-bold" : "text-lg font-semibold";

  if (!promo) {
    return <p className={`mt-1 text-primary ${main}`}>{formatRupiah(price)}</p>;
  }

  const timeLeft = promoTimeLeft(promo.ends_at);

  return (
    <div className="mt-1">
      <div className="flex flex-wrap items-baseline gap-x-2">
        <p className={`text-primary ${main}`}>{formatRupiah(promo.final_price)}</p>
        <p className="text-sm text-on-surface-muted line-through">{formatRupiah(price)}</p>
      </div>
      <p className="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs">
        <span className="rounded bg-error px-1.5 py-0.5 font-semibold text-white">{promo.badge ?? "Promo"}</span>
        <span className="font-medium text-success">Hemat {formatRupiah(promo.discount_amount)}</span>
        {timeLeft && <span className="text-on-surface-muted">· {timeLeft}</span>}
      </p>
    </div>
  );
}
