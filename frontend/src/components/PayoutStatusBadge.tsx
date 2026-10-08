import type { PayoutStatus } from "@/lib/types";
import Badge, { type BadgeTone } from "./Badge";

const LABELS: Record<PayoutStatus, string> = {
  pending: "Payout Diproses",
  paid: "Dana Dicairkan",
  failed: "Payout Gagal",
};

const TONES: Record<PayoutStatus, BadgeTone> = {
  pending: "warning",
  paid: "success",
  failed: "danger",
};

export default function PayoutStatusBadge({ status }: { status: PayoutStatus }) {
  return <Badge tone={TONES[status]}>{LABELS[status]}</Badge>;
}
