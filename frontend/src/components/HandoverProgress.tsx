import { formatDate } from "@/lib/format";
import type { Transaction } from "@/lib/types";

type HandoverProgressProps = {
  transaction: Pick<
    Transaction,
    "paid_at" | "escrow_status" | "handover_requested_at" | "handover_approved_at" | "seller_confirmed_at" | "buyer_confirmed_at"
  >;
  perspective: "buyer" | "seller" | "admin";
};

/**
 * Serah terima dua tahap (catatan dosen 16 Okt): kendaraan baru boleh
 * diserahkan/diberangkatkan setelah admin menyetujui pengajuan penjual.
 */
export default function HandoverProgress({ transaction, perspective }: HandoverProgressProps) {
  if (!transaction.escrow_status || transaction.escrow_status === "refunded") return null;

  const steps = [
    { label: "Dana di escrow", at: transaction.paid_at },
    { label: "Penjual ajukan", at: transaction.handover_requested_at },
    { label: "Admin setujui", at: transaction.handover_approved_at },
    { label: "Diserahkan", at: transaction.seller_confirmed_at },
    { label: "Diterima", at: transaction.buyer_confirmed_at },
  ];
  const activeIndex = steps.findIndex((step) => !step.at);
  const allDone = activeIndex === -1;

  return (
    <div className="rounded-lg border border-border bg-surface-container p-5">
      <h2 className="text-sm font-semibold text-on-surface">Progres Serah Terima</h2>
      <ol className="mt-4 flex items-center gap-1 overflow-x-auto pb-2" aria-label="Progres serah terima">
        {steps.map((step, idx) => {
          const isDone = !!step.at;
          const isActive = idx === activeIndex;
          return (
            <li key={step.label} className="flex min-w-0 flex-1 items-center gap-1">
              <div className="flex min-w-0 flex-1 flex-col items-center gap-1" title={step.at ? formatDate(step.at) : undefined}>
                <div
                  className={`flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full border text-xs font-semibold ${
                    isDone
                      ? "border-emerald-500 bg-emerald-500/10 text-emerald-500"
                      : isActive
                        ? "border-primary bg-primary-container/20 text-primary"
                        : "border-border bg-surface text-on-surface-muted"
                  }`}
                >
                  {isDone ? "✓" : idx + 1}
                </div>
                <span
                  className={`truncate text-[10px] font-medium uppercase tracking-wide ${
                    isActive || isDone ? "text-on-surface" : "text-on-surface-muted"
                  }`}
                >
                  {step.label}
                </span>
              </div>
              {idx < steps.length - 1 && (
                <div className={`h-0.5 min-w-4 flex-1 ${isDone ? "bg-emerald-500/60" : "bg-border"}`} aria-hidden />
              )}
            </li>
          );
        })}
      </ol>
      <p className="mt-2 text-xs text-on-surface-muted">{allDone ? "Serah terima tuntas." : nextStepHint(activeIndex, perspective)}</p>
    </div>
  );
}

function nextStepHint(activeIndex: number, perspective: HandoverProgressProps["perspective"]): string {
  const hints: Record<HandoverProgressProps["perspective"], string[]> = {
    buyer: [
      "Selesaikan pembayaran supaya dana masuk escrow.",
      "Menunggu penjual mengajukan serah terima. Kendaraan belum dikirim.",
      "Pengajuan penjual sedang diperiksa admin. Kendaraan belum boleh diserahkan sebelum disetujui.",
      "Admin sudah menyetujui. Menunggu penjual menyerahkan atau memberangkatkan kendaraan.",
      "Kendaraan sudah diserahkan penjual. Periksa kondisinya, lalu konfirmasi penerimaan.",
    ],
    seller: [
      "Menunggu pembeli membayar.",
      "Ajukan serah terima ke admin. Jangan serahkan kendaraan sebelum disetujui.",
      "Pengajuan sedang diperiksa admin. Tunggu persetujuan sebelum menyerahkan kendaraan.",
      "Serah terima disetujui. Serahkan atau berangkatkan kendaraan, lalu konfirmasi penyerahan.",
      "Menunggu pembeli mengonfirmasi penerimaan kendaraan.",
    ],
    admin: [
      "Pembayaran belum masuk escrow.",
      "Menunggu penjual mengajukan serah terima.",
      "Periksa syarat serah terima, lalu setujui atau tahan pengajuan.",
      "Menunggu penjual menyerahkan kendaraan.",
      "Menunggu pembeli mengonfirmasi penerimaan.",
    ],
  };

  return hints[perspective][activeIndex] ?? "";
}
