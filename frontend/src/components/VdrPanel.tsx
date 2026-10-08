"use client";

import type { TransactionDocument, TransactionDocumentFolder } from "@/lib/types";

type Props = {
  documents: TransactionDocument[] | undefined;
};

const FOLDER_ORDER: TransactionDocumentFolder[] = ["Financial", "Legal"];

const FOLDER_META: Record<TransactionDocumentFolder, { title: string; hint: string }> = {
  Financial: {
    title: "Dokumen Keuangan",
    hint: "Faktur & nota resmi transaksi.",
  },
  Legal: {
    title: "Dokumen Legal",
    hint: "Bukti pemindahan hak kepemilikan.",
  },
};

function formatDate(iso: string): string {
  const d = new Date(iso);
  return d.toLocaleString("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export default function VdrPanel({ documents }: Props) {
  const docs = documents ?? [];

  // Group by folder, preserving FOLDER_ORDER.
  const grouped: Record<string, TransactionDocument[]> = {};
  for (const doc of docs) {
    (grouped[doc.folder] ??= []).push(doc);
  }

  return (
    <section className="rounded-lg border border-border bg-surface-container p-5">
      <div className="mb-4 flex items-baseline justify-between gap-3">
        <div>
          <h2 className="font-display text-lg text-on-surface">Virtual Data Room</h2>
          <p className="mt-0.5 text-xs text-on-surface-muted">
            Dokumen resmi transaksi &mdash; auto-generated oleh sistem.
          </p>
        </div>
        <span className="text-xs text-on-surface-muted">{docs.length} dokumen</span>
      </div>

      {docs.length === 0 && (
        <p className="rounded-md border border-dashed border-border bg-surface px-4 py-6 text-center text-sm text-on-surface-muted">
          Belum ada dokumen. Faktur komersial akan otomatis dibuat setelah checkout.
        </p>
      )}

      <div className="space-y-4">
        {FOLDER_ORDER.map((folder) => {
          const items = grouped[folder];
          if (!items || items.length === 0) return null;
          const meta = FOLDER_META[folder];
          return (
            <div key={folder}>
              <div className="mb-2 flex items-baseline gap-2">
                <svg className="h-4 w-4 text-primary" viewBox="0 0 20 20" fill="currentColor" aria-hidden>
                  <path d="M2 6a2 2 0 012-2h4l2 2h6a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                </svg>
                <h3 className="text-xs font-medium uppercase tracking-wider text-on-surface">
                  {meta.title}
                </h3>
                <span className="text-xs text-on-surface-muted">&middot; {meta.hint}</span>
              </div>
              <ul className="divide-y divide-border rounded-md border border-border">
                {items.map((doc) => (
                  <li key={doc.id} className="flex items-center justify-between gap-3 px-4 py-3">
                    <div className="flex min-w-0 items-center gap-3">
                      <svg
                        className="h-8 w-8 flex-shrink-0 text-error/70"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={1.5}
                        aria-hidden
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
                        />
                      </svg>
                      <div className="min-w-0">
                        <p className="truncate text-sm font-medium text-on-surface">{doc.label}</p>
                        <p className="mt-0.5 text-xs text-on-surface-muted">
                          Dihasilkan {formatDate(doc.generated_at)}
                        </p>
                      </div>
                    </div>
                    <a
                      href={doc.download_url}
                      target="_blank"
                      rel="noreferrer"
                      className="flex-shrink-0 rounded-md border border-primary/40 bg-primary-container/10 px-3 py-1.5 text-xs font-medium text-primary transition-colors hover:bg-primary-container/20"
                    >
                      Unduh PDF
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          );
        })}
      </div>
    </section>
  );
}
