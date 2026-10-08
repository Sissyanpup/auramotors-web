import Image from "next/image";
import { notFound } from "next/navigation";
import { API_URL } from "@/lib/api";
import { formatRupiah } from "@/lib/format";
import type { VehicleDetail } from "@/lib/types";
import CheckoutWidget from "@/components/CheckoutWidget";

const STAGGER_DELAYS = [
  "",
  "animation-delay-75",
  "animation-delay-150",
  "animation-delay-200",
  "animation-delay-300",
];

async function getVehicle(id: string): Promise<VehicleDetail | null> {
  const response = await fetch(`${API_URL}/api/vehicles/${id}`, { cache: "no-store" });
  if (!response.ok) return null;
  const { data } = await response.json();
  return data;
}

export default async function VehicleDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  const vehicle = await getVehicle(id);

  if (!vehicle) notFound();

  return (
    <div className="mx-auto max-w-4xl px-4 py-8">
      {/* Galeri foto — staggered entrance */}
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
        {vehicle.photos && vehicle.photos.length > 0 ? (
          vehicle.photos.map((photo, index) => (
            <div
              key={photo.id}
              className={`relative aspect-video w-full overflow-hidden rounded-lg bg-surface-container-high
                          animate-fade-up ${STAGGER_DELAYS[index] ?? "animation-delay-300"}`}
            >
              <Image
                src={photo.url}
                alt={`${vehicle.brand} ${vehicle.model} — foto ${index + 1}`}
                fill
                sizes="(min-width: 640px) 33vw, 50vw"
                priority={index === 0}
                className="object-cover transition-transform duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] hover:scale-[1.04]"
              />
            </div>
          ))
        ) : (
          <div className="col-span-full aspect-video w-full rounded-lg bg-surface-container-high animate-fade-in" />
        )}
      </div>

      {/* Info utama */}
      <div className="animate-fade-up animation-delay-200">
        <h1 className="mt-6 text-2xl font-semibold text-on-surface">
          {vehicle.brand} {vehicle.model} {vehicle.year}
        </h1>
        <p className="mt-1 text-2xl font-bold text-primary">{formatRupiah(vehicle.price)}</p>
        <p className="mt-1 text-sm text-on-surface-muted">
          {vehicle.mileage.toLocaleString("id-ID")} km &middot; {vehicle.location}
        </p>
      </div>

      {vehicle.description && (
        <p className="mt-4 whitespace-pre-line text-sm leading-relaxed text-on-surface animate-fade-up animation-delay-300">
          {vehicle.description}
        </p>
      )}

      {vehicle.specs && Object.keys(vehicle.specs).length > 0 && (
        <dl className="mt-6 grid grid-cols-2 gap-3 rounded-lg border border-border bg-surface-container p-4
                       sm:grid-cols-3 animate-fade-up animation-delay-300">
          {Object.entries(vehicle.specs).map(([key, value]) => (
            <div key={key} className="group">
              <dt className="text-xs uppercase tracking-wider text-on-surface-muted transition-colors duration-150 group-hover:text-primary/70">
                {key}
              </dt>
              <dd className="mt-0.5 text-sm text-on-surface">{value}</dd>
            </div>
          ))}
        </dl>
      )}

      {(() => {
        const vinClean = vehicle.latest_vin_check?.status === "clean";
        const activePolicy = vehicle.insurance_policies?.find((p) => p.is_active);
        if (!vinClean && !activePolicy) return null;
        return (
          <div className="mt-6 rounded-lg border border-primary/30 bg-primary-container/10 p-4 animate-fade-up animation-delay-400">
            <p className="text-xs font-semibold uppercase tracking-wider text-primary">
              Sinyal Kepercayaan
            </p>
            <ul className="mt-2 space-y-1.5 text-sm text-on-surface">
              {vinClean && (
                <li className="flex items-start gap-2">
                  <span aria-hidden className="text-success">&#10003;</span>
                  <span>VIN diverifikasi (bersih)</span>
                </li>
              )}
              {activePolicy && (
                <li className="flex items-start gap-2">
                  <span aria-hidden className="text-success">&#10003;</span>
                  <span>
                    Diasuransikan: {activePolicy.insurer_name} &mdash;{" "}
                    {activePolicy.policy_type_label}
                    {activePolicy.valid_until && (
                      <>
                        {" "}
                        sampai{" "}
                        {new Intl.DateTimeFormat("id-ID", { dateStyle: "medium" }).format(
                          new Date(activePolicy.valid_until)
                        )}
                      </>
                    )}
                  </span>
                </li>
              )}
            </ul>
          </div>
        );
      })()}

      {vehicle.seller && (
        <p className="mt-6 text-sm text-on-surface-muted animate-fade-up animation-delay-400">
          Dijual oleh{" "}
          <span className="font-medium text-on-surface">{vehicle.seller.name}</span>
        </p>
      )}

      {vehicle.status === "approved" && (
        <div className="animate-fade-up animation-delay-400">
          <CheckoutWidget vehicleId={vehicle.id} price={vehicle.price} />
        </div>
      )}
    </div>
  );
}
