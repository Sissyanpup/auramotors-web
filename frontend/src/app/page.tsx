import Image from "next/image";
import Link from "next/link";
import { Suspense } from "react";
import { API_URL } from "@/lib/api";
import { formatRupiah } from "@/lib/format";
import type { Paginated, Vehicle } from "@/lib/types";
import Badge from "@/components/Badge";
import DeveloperCredits from "@/components/DeveloperCredits";

const FILTER_LABELS: Record<keyof SearchParams, string> = {
  brand: "Merek",
  location: "Lokasi",
  year_min: "Tahun min",
  year_max: "Tahun max",
  price_min: "Harga min",
  price_max: "Harga max",
  page: "Halaman",
};

type SearchParams = {
  brand?: string;
  year_min?: string;
  year_max?: string;
  price_min?: string;
  price_max?: string;
  location?: string;
  page?: string;
};

async function getCatalog(searchParams: SearchParams): Promise<Paginated<Vehicle>> {
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(searchParams)) {
    if (value) query.set(key, value);
  }

  const response = await fetch(`${API_URL}/api/vehicles?${query.toString()}`, {
    cache: "no-store",
  });

  if (!response.ok) {
    return { data: [], meta: { current_page: 1, last_page: 1, total: 0 } };
  }

  return response.json();
}

function CatalogGridSkeleton() {
  return (
    <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {Array.from({ length: 6 }).map((_, index) => (
        <div key={index} className="overflow-hidden rounded-lg border border-border bg-surface-container">
          <div className="aspect-video w-full skeleton" />
          <div className="space-y-2 p-4">
            <div className="h-4 w-3/4 skeleton rounded" />
            <div className="h-5 w-1/2 skeleton rounded" />
            <div className="h-3 w-2/3 skeleton rounded" />
          </div>
        </div>
      ))}
    </div>
  );
}

async function CatalogResults({ searchParams }: { searchParams: SearchParams }) {
  const catalog = await getCatalog(searchParams);

  if (catalog.data.length === 0) {
    return (
      <p className="mt-10 text-center text-sm text-on-surface-muted animate-fade-in">
        Belum ada listing yang cocok dengan filter ini.
      </p>
    );
  }

  return (
    <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 animate-fade-up">
      {catalog.data.map((vehicle) => (
        <Link
          key={vehicle.id}
          href={`/kendaraan/${vehicle.id}`}
          className="group overflow-hidden rounded-lg border border-border bg-surface-container
                     transition-all duration-300 ease-out
                     hover:-translate-y-1 hover:border-primary/40
                     hover:shadow-[0_8px_32px_rgba(0,0,0,0.5)]"
        >
          <div className="relative aspect-video w-full overflow-hidden bg-surface-container-high">
            {vehicle.cover_photo_url && (
              <Image
                src={vehicle.cover_photo_url}
                alt={`${vehicle.brand} ${vehicle.model}`}
                fill
                sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
                className="object-cover transition-transform duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:scale-[1.05]"
              />
            )}
            <div className="absolute left-2 top-2">
              <Badge tone="success">Terverifikasi Admin</Badge>
            </div>
          </div>
          <div className="p-4">
            <h2 className="font-display text-base font-medium text-on-surface">
              {vehicle.brand} {vehicle.model} {vehicle.year}
            </h2>
            <p className="mt-1 text-lg font-semibold text-primary">
              {formatRupiah(vehicle.price)}
            </p>
            <div className="mt-2 flex flex-wrap gap-1.5">
              <Badge tone="neutral">{vehicle.mileage.toLocaleString("id-ID")} km</Badge>
              <Badge tone="neutral">{vehicle.location}</Badge>
            </div>
            {vehicle.seller_name && (
              <p className="mt-2 text-xs text-on-surface-muted">Dijual oleh {vehicle.seller_name}</p>
            )}
          </div>
        </Link>
      ))}
    </div>
  );
}

export default async function CatalogPage({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  const params = await searchParams;

  const activeFilters = (Object.entries(params) as [keyof SearchParams, string | undefined][]).filter(
    ([key, value]) => key !== "page" && value,
  );

  return (
    <div className="mx-auto max-w-6xl px-4 py-8">
      <DeveloperCredits />

      <div className="animate-fade-up">
        <Badge tone="primary">Katalog Terverifikasi</Badge>
        <h1 className="mt-2 text-2xl font-semibold text-on-surface">Katalog Kendaraan</h1>
        <p className="mt-1 text-sm text-on-surface-muted">
          Semua listing sudah diverifikasi dokumen STNK/BPKB oleh admin.
        </p>
      </div>

      <form
        method="GET"
        className="mt-6 flex flex-wrap gap-2.5 rounded-lg border border-border bg-surface-container p-4
                   animate-fade-up animation-delay-100"
      >
        <input name="brand"     defaultValue={params.brand}     placeholder="Merek"     className="input-field min-w-[120px] flex-1" />
        <input name="location"  defaultValue={params.location}  placeholder="Lokasi"    className="input-field min-w-[120px] flex-1" />
        <input name="year_min"  defaultValue={params.year_min}  placeholder="Tahun min" type="number" className="input-field min-w-[100px] flex-1" />
        <input name="year_max"  defaultValue={params.year_max}  placeholder="Tahun max" type="number" className="input-field min-w-[100px] flex-1" />
        <input name="price_min" defaultValue={params.price_min} placeholder="Harga min" type="number" className="input-field min-w-[110px] flex-1" />
        <input name="price_max" defaultValue={params.price_max} placeholder="Harga max" type="number" className="input-field min-w-[110px] flex-1" />
        <button type="submit" className="btn-gold w-full rounded-md px-5 py-2 text-sm sm:w-auto">
          Filter
        </button>
      </form>

      {activeFilters.length > 0 && (
        <div className="mt-3 flex flex-wrap items-center gap-2 animate-fade-in">
          {activeFilters.map(([key, value]) => {
            const rest = new URLSearchParams();
            for (const [k, v] of Object.entries(params)) {
              if (v && k !== key) rest.set(k, v);
            }
            return (
              <Link
                key={key}
                href={`/?${rest.toString()}`}
                className="inline-flex items-center gap-1 rounded-lg bg-surface-container-high px-2.5 py-1
                           text-xs text-on-surface transition-colors duration-150 hover:text-primary"
              >
                {FILTER_LABELS[key]}: {value} <span aria-hidden>&times;</span>
              </Link>
            );
          })}
          <Link href="/" className="text-xs text-on-surface-muted underline transition-colors duration-150 hover:text-on-surface">
            Reset semua
          </Link>
        </div>
      )}

      <Suspense key={JSON.stringify(params)} fallback={<CatalogGridSkeleton />}>
        <CatalogResults searchParams={params} />
      </Suspense>
    </div>
  );
}
