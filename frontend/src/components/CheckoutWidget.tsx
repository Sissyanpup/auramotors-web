"use client";

import Link from "next/link";
import { formatRupiah } from "@/lib/format";
import { useAuth } from "@/contexts/auth-context";
import Badge from "@/components/Badge";

const ESCROW_STEPS = [
  "Dana Anda ditahan di rekening escrow platform, belum diteruskan ke penjual.",
  "Serah terima kendaraan dikonfirmasi kedua pihak, lalu diverifikasi admin.",
  "Setelah verifikasi selesai, dana baru dicairkan (payout) ke penjual.",
];

export default function CheckoutWidget({ vehicleId, price }: { vehicleId: number; price: string }) {
  const { user, isLoading } = useAuth();

  if (isLoading) return null;

  if (!user) {
    return (
      <div className="mt-6 rounded-lg border border-border bg-surface-container p-4 text-sm text-on-surface-muted">
        <Link href="/login" className="font-medium text-on-surface transition-colors duration-150 hover:text-primary">
          Masuk
        </Link>{" "}
        sebagai pembeli untuk mengajukan pembelian kendaraan ini.
      </div>
    );
  }

  if (user.role !== "buyer") {
    return null;
  }

  if (!user.has_completed_ktp) {
    return (
      <div className="mt-6 rounded-lg border border-warning/40 bg-warning/5 p-4 text-sm text-on-surface-muted">
        Lengkapi data KTP kamu dulu sebelum bisa mengajukan pembelian.{" "}
        <Link href="/lengkapi-data" className="font-medium text-warning hover:underline">
          Isi sekarang
        </Link>
      </div>
    );
  }

  return (
    <div className="mt-6 rounded-lg border border-border bg-surface-container p-5">
      <div className="flex items-center justify-between gap-2">
        <h2 className="font-display text-base font-medium text-on-surface">
          Ajukan Pembelian &amp; Escrow
        </h2>
        <Badge tone="success">Dana Aman</Badge>
      </div>

      <dl className="mt-4 border-t border-border pt-4 text-sm">
        <div className="flex items-center justify-between">
          <dt className="text-on-surface-muted">Harga Unit</dt>
          <dd className="font-semibold text-primary">{formatRupiah(Number(price))}</dd>
        </div>
      </dl>

      <p className="mt-3 text-xs text-on-surface-muted">
        Pilih skema DP (mulai 5%) atau bayar penuh, tambahkan opsi asuransi, dan isi alamat pengantaran di form berikutnya.
      </p>

      <Link
        href={`/checkout/${vehicleId}`}
        className="btn-gold mt-4 block w-full rounded-md px-4 py-2.5 text-center text-sm"
      >
        Lanjut ke Form Pembelian
      </Link>

      <ol className="mt-5 space-y-2 border-t border-border pt-4">
        {ESCROW_STEPS.map((step, index) => (
          <li key={step} className="flex gap-2 text-xs text-on-surface-muted">
            <span className="font-label shrink-0 font-medium text-primary">{index + 1}.</span>
            <span>{step}</span>
          </li>
        ))}
      </ol>
    </div>
  );
}
