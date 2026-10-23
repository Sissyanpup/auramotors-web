import { API_URL } from "@/lib/api";
import type { Promo } from "@/lib/types";
import { promoTimeLeft } from "@/components/PromoPrice";

async function getPromos(): Promise<Promo[]> {
  try {
    const response = await fetch(`${API_URL}/api/promos`, { cache: "no-store" });
    if (!response.ok) return [];
    const { data } = (await response.json()) as { data: Promo[] };
    return data;
  } catch {
    return [];
  }
}

/**
 * Strip promo berjalan di atas katalog — voucher menampilkan kodenya supaya
 * pembeli langsung bisa memakainya di checkout.
 */
export default async function PromoBanner() {
  const promos = await getPromos();
  if (promos.length === 0) return null;

  return (
    <section aria-label="Promo berlangsung" className="mt-6 animate-fade-up">
      <div className="flex gap-3 overflow-x-auto pb-1">
        {promos.map((promo) => {
          const timeLeft = promoTimeLeft(promo.ends_at);
          return (
            <div
              key={promo.id}
              className="min-w-[240px] flex-1 rounded-lg border border-primary/40 bg-gradient-to-br from-primary/15 to-transparent p-4"
            >
              <div className="flex items-center justify-between gap-2">
                <span className="rounded bg-error px-1.5 py-0.5 text-[11px] font-semibold text-white">{promo.badge ?? "Promo"}</span>
                {timeLeft && <span className="text-[11px] text-on-surface-muted">{timeLeft}</span>}
              </div>
              <p className="mt-2 font-display text-base text-on-surface">{promo.name}</p>
              <p className="text-sm font-medium text-primary">{promo.benefit_summary}</p>
              {promo.description && <p className="mt-1 text-xs text-on-surface-muted">{promo.description}</p>}
              {promo.kind === "voucher" && promo.code ? (
                <p className="mt-2 text-xs text-on-surface-muted">
                  Pakai kode <span className="rounded border border-dashed border-primary px-1.5 py-0.5 font-mono font-semibold text-on-surface">{promo.code}</span> saat checkout
                </p>
              ) : (
                <p className="mt-2 text-xs text-on-surface-muted">Otomatis berlaku — lihat harga coret di katalog</p>
              )}
            </div>
          );
        })}
      </div>
    </section>
  );
}
