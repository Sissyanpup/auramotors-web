import Badge, { type BadgeTone } from "./Badge";

const LABELS: Record<string, string> = {
  draft: "Draft",
  pending_review: "Menunggu Review",
  pending: "Menunggu Review",
  approved: "Disetujui",
  rejected: "Ditolak",
  sold: "Terjual",
};

const TONES: Record<string, BadgeTone> = {
  draft: "neutral",
  pending_review: "warning",
  pending: "warning",
  approved: "success",
  rejected: "danger",
  sold: "primary",
};

export default function StatusBadge({ status }: { status: string }) {
  return <Badge tone={TONES[status] ?? "neutral"}>{LABELS[status] ?? status}</Badge>;
}
