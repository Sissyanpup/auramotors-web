import Link from "next/link";
import Badge from "@/components/Badge";

export const metadata = {
  title: "Pesan — AuraMotors",
};

export default function PesanPage() {
  return (
    <div className="mx-auto max-w-2xl px-4 py-12 animate-fade-up">
      <Badge tone="warning">Segera Hadir</Badge>
      <h1 className="mt-2 font-display text-2xl text-on-surface">Pesan Masuk</h1>
      <p className="mt-1 text-sm text-on-surface-muted">
        Fitur inbox pesan antar-pengguna sedang dalam pengembangan. Untuk sementara, notifikasi
        transaksi tetap dikirim ke email kamu.
      </p>

      <div className="mt-6 rounded-lg border border-dashed border-border bg-surface-container/60 p-6 text-center text-sm text-on-surface-muted">
        Belum ada pesan.
      </div>

      <div className="mt-6 flex gap-2">
        <Link href="/" className="rounded-md border border-border px-4 py-2 text-sm text-on-surface-muted hover:bg-surface-container-high hover:text-on-surface">
          Kembali ke Katalog
        </Link>
      </div>
    </div>
  );
}
