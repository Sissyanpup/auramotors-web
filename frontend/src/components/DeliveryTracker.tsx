"use client";

import dynamic from "next/dynamic";
import { useEffect, useMemo, useState } from "react";

const DeliveryMap = dynamic(() => import("./DeliveryMap"), {
  ssr: false,
  loading: () => (
    <div className="flex h-72 items-center justify-center rounded-md border border-border bg-surface-container text-sm text-on-surface-muted">
      Memuat peta...
    </div>
  ),
});

type LatLng = { lat: number; lng: number };

/**
 * Centroid perkiraan sejumlah kota Indonesia. Cukup untuk demo lokal;
 * bukan pengganti geocoder sungguhan.
 */
const CITY_COORDS: Record<string, LatLng> = {
  jakarta: { lat: -6.2, lng: 106.816 },
  bandung: { lat: -6.9147, lng: 107.6098 },
  surabaya: { lat: -7.2575, lng: 112.7521 },
  semarang: { lat: -6.9667, lng: 110.4167 },
  yogyakarta: { lat: -7.7956, lng: 110.3695 },
  medan: { lat: 3.5952, lng: 98.6722 },
  makassar: { lat: -5.1477, lng: 119.4327 },
  denpasar: { lat: -8.65, lng: 115.2167 },
  banjarmasin: { lat: -3.3194, lng: 114.5906 },
  balikpapan: { lat: -1.2379, lng: 116.8529 },
  palembang: { lat: -2.9761, lng: 104.7754 },
  pekanbaru: { lat: 0.5333, lng: 101.45 },
  malang: { lat: -7.9797, lng: 112.6304 },
  bogor: { lat: -6.5971, lng: 106.806 },
  bekasi: { lat: -6.2383, lng: 106.9756 },
  tangerang: { lat: -6.1783, lng: 106.63 },
  depok: { lat: -6.4025, lng: 106.7942 },
};

function extractCity(text: string | null | undefined): LatLng {
  if (!text) return CITY_COORDS.jakarta;
  const lowered = text.toLowerCase();
  for (const [city, coords] of Object.entries(CITY_COORDS)) {
    if (lowered.includes(city)) return coords;
  }
  return CITY_COORDS.jakarta;
}

/**
 * Deterministic offset supaya beberapa transaksi tidak tumpang tindih persis.
 */
function jitter(seed: number): LatLng {
  const angle = (seed * 137.508) % 360;
  const rad = (angle * Math.PI) / 180;
  const magnitude = 0.02;
  return { lat: Math.sin(rad) * magnitude, lng: Math.cos(rad) * magnitude };
}

type DeliveryTrackerProps = {
  transactionId: number;
  sellerLocation: string | null;
  buyerAddress: string | null;
  startedAt: string | null;
  /** Durasi simulasi total (detik). Progress = elapsed / totalSeconds. */
  totalSeconds?: number;
  /** Dipanggil tiap tick supaya parent tahu progress (0-1) & bisa gate tombol. */
  onProgressChange?: (progress: number) => void;
};

export default function DeliveryTracker({
  transactionId,
  sellerLocation,
  buyerAddress,
  startedAt,
  totalSeconds = 600,
  onProgressChange,
}: DeliveryTrackerProps) {
  const origin = useMemo(() => {
    const base = extractCity(sellerLocation);
    const j = jitter(transactionId);
    return { lat: base.lat + j.lat, lng: base.lng + j.lng };
  }, [sellerLocation, transactionId]);

  const destination = useMemo(() => {
    const base = extractCity(buyerAddress);
    const j = jitter(transactionId + 7);
    return { lat: base.lat + j.lat, lng: base.lng + j.lng };
  }, [buyerAddress, transactionId]);

  const [progress, setProgress] = useState(0);

  useEffect(() => {
    if (!startedAt) return;
    const start = new Date(startedAt).getTime();
    function tick() {
      const elapsed = (Date.now() - start) / 1000;
      const next = Math.min(1, Math.max(0, elapsed / totalSeconds));
      setProgress(next);
      onProgressChange?.(next);
    }
    tick();
    const timer = setInterval(tick, 5000);
    return () => clearInterval(timer);
  }, [startedAt, totalSeconds, onProgressChange]);

  const current: LatLng = useMemo(
    () => ({
      lat: origin.lat + (destination.lat - origin.lat) * progress,
      lng: origin.lng + (destination.lng - origin.lng) * progress,
    }),
    [origin, destination, progress]
  );

  const distanceKm = useMemo(() => haversine(origin, destination), [origin, destination]);
  const remainingKm = distanceKm * (1 - progress);
  const etaMinutes = Math.max(0, Math.round(((1 - progress) * totalSeconds) / 60));

  return (
    <div className="rounded-lg border border-border bg-surface-container p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h3 className="font-display text-base text-on-surface">Pelacakan Pengantaran (Live)</h3>
          <p className="mt-0.5 text-xs text-on-surface-muted">
            Simulasi dummy — pada produksi terhubung ke GPS armada.
          </p>
        </div>
        <div className="text-right">
          <p className="text-xs text-on-surface-muted">Estimasi tiba</p>
          <p className="font-mono text-sm text-on-surface">
            {progress >= 1 ? "Tiba di lokasi" : `± ${etaMinutes} menit`}
          </p>
        </div>
      </div>

      <div className="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
        <div className="rounded-md bg-surface p-2">
          <p className="text-on-surface-muted">Jarak Total</p>
          <p className="mt-0.5 font-mono text-on-surface">{distanceKm.toFixed(1)} km</p>
        </div>
        <div className="rounded-md bg-surface p-2">
          <p className="text-on-surface-muted">Sisa Jarak</p>
          <p className="mt-0.5 font-mono text-on-surface">{remainingKm.toFixed(1)} km</p>
        </div>
        <div className="rounded-md bg-surface p-2">
          <p className="text-on-surface-muted">Progress</p>
          <p className="mt-0.5 font-mono text-on-surface">{Math.round(progress * 100)}%</p>
        </div>
      </div>

      <div className="mt-3 h-2 overflow-hidden rounded-full bg-surface">
        <div
          className="h-full rounded-full bg-primary transition-all duration-500"
          style={{ width: `${Math.round(progress * 100)}%` }}
        />
      </div>

      <div className="mt-4 overflow-hidden rounded-md">
        <DeliveryMap origin={origin} destination={destination} current={current} />
      </div>

      <div className="mt-3 grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
        <div className="rounded-md border border-border p-2">
          <p className="font-semibold text-emerald-500">● Titik Jemput (Seller)</p>
          <p className="mt-0.5 text-on-surface-muted">{sellerLocation ?? "—"}</p>
        </div>
        <div className="rounded-md border border-border p-2">
          <p className="font-semibold text-primary">● Tujuan (Buyer)</p>
          <p className="mt-0.5 whitespace-pre-line text-on-surface-muted line-clamp-2">
            {buyerAddress ?? "—"}
          </p>
        </div>
      </div>
    </div>
  );
}

function haversine(a: LatLng, b: LatLng): number {
  const R = 6371;
  const dLat = ((b.lat - a.lat) * Math.PI) / 180;
  const dLng = ((b.lng - a.lng) * Math.PI) / 180;
  const lat1 = (a.lat * Math.PI) / 180;
  const lat2 = (b.lat * Math.PI) / 180;
  const h =
    Math.sin(dLat / 2) ** 2 +
    Math.sin(dLng / 2) ** 2 * Math.cos(lat1) * Math.cos(lat2);
  return 2 * R * Math.asin(Math.sqrt(h));
}
