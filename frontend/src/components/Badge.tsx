export type BadgeTone = "neutral" | "warning" | "success" | "danger" | "info" | "primary";

const TONE_CLASSES: Record<BadgeTone, string> = {
  neutral: "bg-neutral-container text-neutral",
  warning: "bg-warning-container text-warning",
  success: "bg-success-container text-success",
  danger: "bg-error-container text-error",
  info: "bg-surface-container-high text-tertiary",
  primary: "bg-primary-container text-on-primary",
};

export default function Badge({ tone, children }: { tone: BadgeTone; children: React.ReactNode }) {
  return (
    <span
      className={`font-label inline-flex items-center rounded-lg px-2.5 py-0.5 text-xs font-medium tracking-wide ${TONE_CLASSES[tone]}`}
    >
      {children}
    </span>
  );
}
