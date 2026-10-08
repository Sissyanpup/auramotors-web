"use client";

import { useEffect, useMemo, useState, type ReactNode } from "react";

type Field = {
  name: string;
  label: string;
  required?: boolean;
  placeholder?: string;
  type?: "text" | "textarea";
};

type DangerConfirmModalProps = {
  title: string;
  intro: ReactNode;
  consequences: ReactNode[];
  /** Field tambahan (mis. nomor referensi transfer, catatan). */
  fields?: Field[];
  /** Kata yang harus diketik user untuk memastikan bukan salah klik. Default: "KONFIRMASI". */
  confirmWord?: string;
  /** Detik minimum sebelum tombol confirm bisa ditekan. Default: 3. */
  minDelaySeconds?: number;
  confirmLabel?: string;
  isSubmitting?: boolean;
  onConfirm: (values: Record<string, string>) => void;
  onCancel: () => void;
};

/*
 * Modal konfirmasi berlapis untuk aksi tak-terbatalkan (checkout, serah-terima,
 * disburse, resolve-dispute). Tiga lapis biar tidak "instan":
 *  1. Warning card + rincian konsekuensi
 *  2. Checkbox "saya paham" wajib dicentang
 *  3. Ketik kata konfirmasi + jeda waktu minimum
 * Cegah salah klik pada tombol berisiko sesuai catatan dosen 25 Sept 2026.
 */
export default function DangerConfirmModal({
  title,
  intro,
  consequences,
  fields = [],
  confirmWord = "KONFIRMASI",
  minDelaySeconds = 3,
  confirmLabel = "Ya, Saya Konfirmasi",
  isSubmitting = false,
  onConfirm,
  onCancel,
}: DangerConfirmModalProps) {
  const [understood, setUnderstood] = useState(false);
  const [typedWord, setTypedWord] = useState("");
  const [values, setValues] = useState<Record<string, string>>({});
  const [remaining, setRemaining] = useState(minDelaySeconds);

  useEffect(() => {
    if (remaining <= 0) return;
    const timer = setTimeout(() => setRemaining((s) => s - 1), 1000);
    return () => clearTimeout(timer);
  }, [remaining]);

  const requiredFieldsFilled = useMemo(() => {
    return fields.every((f) => !f.required || (values[f.name] ?? "").trim().length > 0);
  }, [fields, values]);

  const canSubmit = useMemo(() => {
    return (
      understood &&
      typedWord.trim().toUpperCase() === confirmWord.toUpperCase() &&
      remaining <= 0 &&
      requiredFieldsFilled &&
      !isSubmitting
    );
  }, [understood, typedWord, confirmWord, remaining, requiredFieldsFilled, isSubmitting]);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!canSubmit) return;
    onConfirm(values);
  }

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="danger-modal-title"
      className="fixed inset-0 z-50 flex items-center justify-center p-4 animate-fade-in"
      style={{ background: "rgba(0,0,0,0.7)" }}
    >
      <div className="w-full max-w-lg rounded-lg border border-warning/50 bg-surface-container shadow-[0_24px_64px_rgba(0,0,0,0.6)] animate-scale-in">
        {/* Header — warning card */}
        <div className="flex items-start gap-3 border-b border-warning/30 bg-warning/10 p-5">
          <div
            aria-hidden
            className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-warning/20 text-warning"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-5 w-5">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            </svg>
          </div>
          <div className="flex-1">
            <h2 id="danger-modal-title" className="font-display text-lg text-on-surface">{title}</h2>
            <div className="mt-1 text-sm text-on-surface-muted">{intro}</div>
          </div>
        </div>

        {/* Body — bullets, checkbox, type-to-confirm */}
        <form onSubmit={handleSubmit} className="p-5">
          {consequences.length > 0 && (
            <>
              <p className="text-xs font-semibold uppercase tracking-wider text-on-surface-muted">
                Yang akan terjadi:
              </p>
              <ul className="mt-2 space-y-1.5 text-sm text-on-surface">
                {consequences.map((item, index) => (
                  <li key={index} className="flex items-start gap-2">
                    <span aria-hidden className="mt-1 h-1 w-1 flex-shrink-0 rounded-full bg-warning" />
                    <span>{item}</span>
                  </li>
                ))}
              </ul>
            </>
          )}

          {fields.length > 0 && (
            <div className="mt-4 space-y-3">
              {fields.map((field) => (
                <div key={field.name}>
                  <label htmlFor={`danger-modal-${field.name}`} className="block text-sm font-medium text-on-surface">
                    {field.label}
                    {field.required && <span className="ml-0.5 text-error">*</span>}
                  </label>
                  {field.type === "textarea" ? (
                    <textarea
                      id={`danger-modal-${field.name}`}
                      required={field.required}
                      placeholder={field.placeholder}
                      value={values[field.name] ?? ""}
                      onChange={(e) => setValues((prev) => ({ ...prev, [field.name]: e.target.value }))}
                      rows={3}
                      className="mt-1.5 block w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-on-surface placeholder:text-on-surface-muted focus:border-primary focus:outline-none resize-none"
                    />
                  ) : (
                    <input
                      id={`danger-modal-${field.name}`}
                      type="text"
                      required={field.required}
                      placeholder={field.placeholder}
                      value={values[field.name] ?? ""}
                      onChange={(e) => setValues((prev) => ({ ...prev, [field.name]: e.target.value }))}
                      className="input-field mt-1.5"
                    />
                  )}
                </div>
              ))}
            </div>
          )}

          <label className="mt-5 flex cursor-pointer select-none items-start gap-2.5 rounded-md border border-border bg-surface p-3 hover:border-warning/50">
            <input
              type="checkbox"
              checked={understood}
              onChange={(e) => setUnderstood(e.target.checked)}
              className="mt-0.5 h-4 w-4 rounded border-border text-warning focus:ring-warning"
            />
            <span className="text-sm text-on-surface">
              Saya telah membaca &amp; memahami konsekuensi aksi ini, dan bertanggung jawab atas keputusan yang saya buat.
            </span>
          </label>

          <div className="mt-4">
            <label htmlFor="confirm-word" className="block text-sm font-medium text-on-surface">
              Ketik <strong className="font-mono text-warning">{confirmWord}</strong> untuk melanjutkan:
            </label>
            <input
              id="confirm-word"
              type="text"
              value={typedWord}
              onChange={(e) => setTypedWord(e.target.value)}
              autoComplete="off"
              spellCheck={false}
              className="input-field mt-1.5 font-mono uppercase"
              placeholder={confirmWord}
              disabled={!understood}
            />
          </div>

          <div className="mt-5 flex items-center justify-between border-t border-border pt-4">
            <p className="text-xs text-on-surface-muted">
              {remaining > 0
                ? `Tombol aktif dalam ${remaining} detik...`
                : "Silakan konfirmasi jika sudah yakin."}
            </p>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={onCancel}
                disabled={isSubmitting}
                className="rounded-md px-3 py-1.5 text-sm font-medium text-on-surface-muted transition-colors hover:bg-surface-container-high hover:text-on-surface disabled:opacity-60"
              >
                Batal
              </button>
              <button
                type="submit"
                disabled={!canSubmit}
                className="rounded-md bg-warning px-4 py-1.5 text-sm font-medium text-on-warning transition-opacity disabled:cursor-not-allowed disabled:opacity-40"
              >
                {isSubmitting ? "Memproses..." : confirmLabel}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
}
