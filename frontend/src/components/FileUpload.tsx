"use client";

import { useRef, type ChangeEvent } from "react";

type Props = {
  id: string;
  label: string;
  helpText?: string;
  file: File | null;
  onFile: (file: File | null) => void;
  required?: boolean;
  accept?: string;
  error?: string;
  existingUrl?: string | null;
  existingLabel?: string;
};

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

export default function FileUpload({
  id,
  label,
  helpText,
  file,
  onFile,
  required = false,
  accept = "image/jpeg,image/png,application/pdf",
  error,
  existingUrl,
  existingLabel = "Lihat dokumen tersimpan",
}: Props) {
  const inputRef = useRef<HTMLInputElement>(null);

  function handleChange(e: ChangeEvent<HTMLInputElement>) {
    onFile(e.target.files?.[0] ?? null);
  }

  function handleClear() {
    onFile(null);
    if (inputRef.current) inputRef.current.value = "";
  }

  return (
    <div className="min-w-0 max-w-full">
      <label htmlFor={id} className="block text-sm font-medium text-on-surface">
        {label}
        {required && <span className="ml-1 text-error">*</span>}
      </label>
      {helpText && <p className="mt-1 text-xs text-on-surface-muted">{helpText}</p>}

      <div className="mt-2">
        <input
          ref={inputRef}
          id={id}
          type="file"
          accept={accept}
          onChange={handleChange}
          className="sr-only"
          required={required && !file}
        />

        {file ? (
          <div className="flex w-full max-w-full items-center justify-between gap-3 overflow-hidden rounded-md border border-primary/40 bg-primary-container/10 px-3 py-2.5">
            <div className="flex min-w-0 flex-1 items-center gap-2">
              <svg className="h-5 w-5 flex-shrink-0 text-primary" viewBox="0 0 20 20" fill="currentColor" aria-hidden>
                <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clipRule="evenodd" />
              </svg>
              <div className="min-w-0 flex-1">
                <p className="min-w-0 truncate text-sm text-on-surface" title={file.name}>{file.name}</p>
                <p className="text-xs text-on-surface-muted">{formatSize(file.size)}</p>
              </div>
            </div>
            <button
              type="button"
              onClick={handleClear}
              className="flex-shrink-0 rounded-md border border-border px-2 py-1 text-xs text-on-surface-muted transition-colors hover:border-error hover:text-error"
            >
              Hapus
            </button>
          </div>
        ) : (
          <label
            htmlFor={id}
            className="flex cursor-pointer flex-col items-center justify-center rounded-md border border-dashed border-border bg-surface px-3 py-6 text-center transition-colors hover:border-primary hover:bg-primary-container/5"
          >
            <svg className="h-8 w-8 text-on-surface-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.5} aria-hidden>
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            <span className="mt-2 text-sm text-on-surface">Klik untuk memilih file</span>
            <span className="mt-0.5 text-xs text-on-surface-muted">JPG, PNG, atau PDF &middot; maks 5 MB</span>
          </label>
        )}

        {existingUrl && !file && (
          <a
            href={existingUrl}
            target="_blank"
            rel="noreferrer"
            className="mt-2 inline-flex items-center gap-1 text-xs text-primary hover:underline"
          >
            <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden>
              <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z" />
              <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z" />
            </svg>
            {existingLabel}
          </a>
        )}
      </div>

      {error && <p className="mt-1.5 text-xs text-error">{error}</p>}
    </div>
  );
}
