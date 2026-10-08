"use client";

import { useState } from "react";
import { ApiError, apiFetch, toFormData } from "@/lib/api";
import FileUpload from "@/components/FileUpload";
import ConfirmModal from "@/components/ConfirmModal";
import type { VehicleDetail, VehicleDocument, VehicleDocumentType } from "@/lib/types";

type Props = {
  vehicle: VehicleDetail;
  onVehicleUpdated: (v: VehicleDetail) => void;
};

const EXTRA_TYPES: {
  type: VehicleDocumentType;
  label: string;
  helpText: string;
}[] = [
  {
    type: "service_history",
    label: "Riwayat Servis (Service History)",
    helpText: "Catatan servis berkala dari bengkel resmi atau independen (PDF/JPG/PNG).",
  },
  {
    type: "inspection_report",
    label: "Laporan Inspeksi (Inspection Report)",
    helpText: "Hasil pemeriksaan pra-jual dari inspektor pihak ketiga.",
  },
  {
    type: "certificate_of_authenticity",
    label: "Sertifikat Keaslian (Certificate of Authenticity)",
    helpText: "Sertifikat dari pabrikan/dealer resmi untuk unit rare atau kolektor.",
  },
];

export default function VehicleExtraDocumentsSection({ vehicle, onVehicleUpdated }: Props) {
  const [uploads, setUploads] = useState<Record<VehicleDocumentType, File | null>>({
    stnk: null,
    bpkb: null,
    service_history: null,
    inspection_report: null,
    certificate_of_authenticity: null,
  });
  const [busyType, setBusyType] = useState<VehicleDocumentType | null>(null);
  const [errorByType, setErrorByType] = useState<Partial<Record<VehicleDocumentType, string>>>({});
  const [deleteTarget, setDeleteTarget] = useState<VehicleDocument | null>(null);
  const [deleting, setDeleting] = useState(false);

  async function reload() {
    const { data } = await apiFetch<{ data: VehicleDetail }>(
      `/api/seller/vehicles/${vehicle.id}`
    );
    onVehicleUpdated(data);
  }

  async function handleUpload(type: VehicleDocumentType) {
    const file = uploads[type];
    if (!file) {
      setErrorByType((prev) => ({ ...prev, [type]: "Pilih file terlebih dulu." }));
      return;
    }
    setBusyType(type);
    setErrorByType((prev) => ({ ...prev, [type]: undefined }));
    try {
      await apiFetch(`/api/seller/vehicles/${vehicle.id}/documents`, {
        method: "POST",
        body: toFormData({ type, file }),
      });
      setUploads((prev) => ({ ...prev, [type]: null }));
      await reload();
    } catch (error) {
      const msg =
        error instanceof ApiError
          ? error.errors?.file?.[0] ?? error.errors?.type?.[0] ?? error.message
          : "Gagal mengunggah dokumen.";
      setErrorByType((prev) => ({ ...prev, [type]: msg }));
    } finally {
      setBusyType(null);
    }
  }

  async function handleDelete() {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      await apiFetch(`/api/seller/vehicles/${vehicle.id}/documents/${deleteTarget.id}`, {
        method: "DELETE",
      });
      setDeleteTarget(null);
      await reload();
    } catch {
      // fall-through; keep modal so user can retry
    } finally {
      setDeleting(false);
    }
  }

  const existingByType = (type: VehicleDocumentType) =>
    (vehicle.documents ?? []).filter((d) => d.type === type);

  return (
    <>
      <fieldset className="space-y-5 border-t border-border pt-4">
        <legend className="-mt-6 bg-surface-container px-2 text-xs font-medium uppercase tracking-wider text-on-surface-muted">
          Dokumen Kendaraan Tambahan
        </legend>
        <p className="text-xs text-on-surface-muted">
          Dokumen pendukung untuk meningkatkan kepercayaan calon pembeli. Semua file terenkripsi dan
          hanya dapat diakses oleh pemilik dan admin.
        </p>

        {EXTRA_TYPES.map(({ type, label, helpText }) => {
          const existing = existingByType(type);
          const busy = busyType === type;
          return (
            <div key={type} className="rounded-md border border-border bg-surface p-4">
              <FileUpload
                id={`upload-${type}`}
                label={label}
                helpText={helpText}
                file={uploads[type]}
                onFile={(f) => setUploads((prev) => ({ ...prev, [type]: f }))}
                error={errorByType[type]}
              />
              <div className="mt-3 flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => handleUpload(type)}
                  disabled={busy || !uploads[type]}
                  className="rounded-md border border-primary bg-primary-container/20 px-3 py-1.5 text-xs font-medium text-primary transition-colors hover:bg-primary-container/40 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {busy ? "Mengunggah..." : "Unggah"}
                </button>
                <span className="text-xs text-on-surface-muted">
                  {existing.length > 0
                    ? `${existing.length} file tersimpan`
                    : "Belum ada file"}
                </span>
              </div>

              {existing.length > 0 && (
                <ul className="mt-3 space-y-2">
                  {existing.map((doc) => (
                    <li
                      key={doc.id}
                      className="flex items-center justify-between gap-3 rounded-md border border-border bg-surface-container px-3 py-2"
                    >
                      <a
                        href={doc.download_url}
                        target="_blank"
                        rel="noreferrer"
                        className="flex items-center gap-2 text-xs text-primary hover:underline"
                      >
                        <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden>
                          <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clipRule="evenodd" />
                        </svg>
                        Unduh dokumen #{doc.id}
                      </a>
                      <button
                        type="button"
                        onClick={() => setDeleteTarget(doc)}
                        className="rounded-md border border-border px-2 py-1 text-xs text-on-surface-muted transition-colors hover:border-error hover:text-error"
                      >
                        Hapus
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          );
        })}
      </fieldset>

      {deleteTarget && (
        <ConfirmModal
          title="Hapus dokumen kendaraan?"
          message={
            <>
              Dokumen <span className="font-mono">#{deleteTarget.id}</span> akan dihapus permanen
              dan tidak bisa dikembalikan.
            </>
          }
          confirmLabel="Ya, Hapus"
          isSubmitting={deleting}
          onConfirm={handleDelete}
          onCancel={() => setDeleteTarget(null)}
        />
      )}
    </>
  );
}
