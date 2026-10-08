import type { EscrowStatus } from "@/lib/types";
import Badge, { type BadgeTone } from "./Badge";

const LABELS: Record<EscrowStatus, string> = {
  escrow_hold: "Dana Ditahan (Escrow)",
  serah_terima: "Serah Terima Dikonfirmasi",
  payout_release: "Payout Disetujui",
  selesai: "Selesai",
  dispute: "Sengketa",
  refunded: "Dana Dikembalikan",
};

const TONES: Record<EscrowStatus, BadgeTone> = {
  escrow_hold: "info",
  serah_terima: "info",
  payout_release: "primary",
  selesai: "success",
  dispute: "danger",
  refunded: "neutral",
};

export default function EscrowStatusBadge({ status }: { status: EscrowStatus }) {
  return <Badge tone={TONES[status]}>{LABELS[status]}</Badge>;
}
