import { formatDate } from "@/lib/format";
import type { TransactionStatusHistoryEntry } from "@/lib/types";

const STATUS_LABELS: Record<string, string> = {
  escrow_hold: "Dana Ditahan (Escrow)",
  serah_terima: "Serah Terima Dikonfirmasi",
  payout_release: "Payout Disetujui",
  selesai: "Selesai",
  dispute: "Sengketa",
  refunded: "Dana Dikembalikan",
};

function label(status: string): string {
  return STATUS_LABELS[status] ?? status;
}

export default function TransactionStatusHistory({ entries }: { entries: TransactionStatusHistoryEntry[] }) {
  if (entries.length === 0) {
    return <p className="text-sm text-on-surface-muted">Belum ada riwayat status.</p>;
  }

  return (
    <ol className="space-y-3">
      {entries.map((entry) => (
        <li key={entry.id} className="rounded-md border border-border bg-surface-container-high p-3 text-sm">
          <p className="font-medium text-on-surface">
            {entry.from_status ? `${label(entry.from_status)} → ` : ""}
            {label(entry.to_status)}
          </p>
          <p className="mt-0.5 text-xs text-on-surface-muted">
            {entry.actor ?? "Sistem"} &middot; {formatDate(entry.created_at)}
          </p>
          {entry.note && <p className="mt-1 text-on-surface-muted">{entry.note}</p>}
        </li>
      ))}
    </ol>
  );
}
