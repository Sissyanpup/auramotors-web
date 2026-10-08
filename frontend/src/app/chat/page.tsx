import Link from "next/link";
import Badge from "@/components/Badge";

export const metadata = {
  title: "Chat — AuraMotors",
};

export default function ChatPage() {
  return (
    <div className="mx-auto max-w-2xl px-4 py-12 animate-fade-up">
      <Badge tone="warning">Segera Hadir</Badge>
      <h1 className="mt-2 font-display text-2xl text-on-surface">Chat</h1>
      <p className="mt-1 text-sm text-on-surface-muted">
        Live chat antara buyer, seller, dan admin akan hadir pada rilis berikutnya. Sementara ini
        koordinasi dilakukan lewat catatan pada transaksi.
      </p>

      <div className="mt-6 rounded-lg border border-dashed border-border bg-surface-container/60 p-6 text-center text-sm text-on-surface-muted">
        Belum ada percakapan.
      </div>

      <div className="mt-6 flex gap-2">
        <Link href="/" className="rounded-md border border-border px-4 py-2 text-sm text-on-surface-muted hover:bg-surface-container-high hover:text-on-surface">
          Kembali ke Katalog
        </Link>
      </div>
    </div>
  );
}
