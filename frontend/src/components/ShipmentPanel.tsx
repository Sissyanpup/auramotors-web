"use client";

import { useMemo, useState } from "react";
import { ApiError, apiFetch, toFormData } from "@/lib/api";
import type {
  Shipment,
  ShipmentDocument,
  ShipmentDocumentType,
  ShipmentStatus,
} from "@/lib/types";
import FileUpload from "@/components/FileUpload";
import ConfirmModal from "@/components/ConfirmModal";

type UserRole = "buyer" | "seller" | "admin";

type Props = {
  transactionId: number | string;
  shipment: Shipment;
  userRole: UserRole;
  onUpdate: () => void | Promise<void>;
};

type UploadSlot = {
  type: ShipmentDocumentType;
  label: string;
  category: string;
  helpText?: string;
};

const SLOTS_BY_ROLE: Record<UserRole, UploadSlot[]> = {
  seller: [
    { type: "export_declaration", label: "Export Declaration", category: "Ekspor & Pengiriman" },
    { type: "bill_of_lading", label: "Bill of Lading (Laut)", category: "Ekspor & Pengiriman" },
    { type: "air_waybill", label: "Air Waybill (Udara)", category: "Ekspor & Pengiriman" },
  ],
  buyer: [
    { type: "proof_of_delivery", label: "Proof of Delivery", category: "Penerimaan" },
    { type: "letter_of_acceptance", label: "Letter of Acceptance", category: "Penerimaan" },
  ],
  admin: [
    { type: "export_declaration", label: "Export Declaration", category: "Ekspor & Pengiriman" },
    { type: "bill_of_lading", label: "Bill of Lading", category: "Ekspor & Pengiriman" },
    { type: "air_waybill", label: "Air Waybill", category: "Ekspor & Pengiriman" },
    { type: "import_declaration", label: "Import Declaration", category: "Bea Cukai" },
    { type: "customs_duty_receipt", label: "Customs Duty Receipt", category: "Bea Cukai" },
    { type: "certificate_of_conformity", label: "Certificate of Conformity", category: "Bea Cukai" },
    { type: "proof_of_delivery", label: "Proof of Delivery", category: "Penerimaan" },
    { type: "letter_of_acceptance", label: "Letter of Acceptance", category: "Penerimaan" },
  ],
};

const STATUS_COLORS: Record<ShipmentStatus, { badge: string; dot: string }> = {
  draft: { badge: "bg-surface-container-high text-on-surface-muted border-border", dot: "bg-on-surface-muted" },
  logistics_prep: { badge: "bg-amber-500/10 text-amber-500 border-amber-500/40", dot: "bg-amber-500" },
  in_transit: { badge: "bg-blue-500/10 text-blue-500 border-blue-500/40", dot: "bg-blue-500" },
  customs_clearance: { badge: "bg-purple-500/10 text-purple-500 border-purple-500/40", dot: "bg-purple-500" },
  delivered: { badge: "bg-emerald-500/10 text-emerald-500 border-emerald-500/40", dot: "bg-emerald-500" },
  delayed: { badge: "bg-red-500/10 text-red-500 border-red-500/40", dot: "bg-red-500" },
};

const STEPPER: { key: ShipmentStatus; label: string }[] = [
  { key: "draft", label: "Draft" },
  { key: "logistics_prep", label: "Logistics Prep" },
  { key: "in_transit", label: "In Transit" },
  { key: "customs_clearance", label: "Customs Clearance" },
  { key: "delivered", label: "Delivered" },
];

const CATEGORY_ORDER = ["Ekspor & Pengiriman", "Bea Cukai", "Penerimaan"];

type AdminTransition = {
  status: ShipmentStatus;
  action: "mark-logistics-prep" | "mark-in-transit" | "mark-customs-clearance" | "mark-delivered";
  label: string;
  confirmMsg: string;
};

const ADMIN_TRANSITIONS: AdminTransition[] = [
  {
    status: "draft",
    action: "mark-logistics-prep",
    label: "Mark as Logistics Prep",
    confirmMsg: "Setujui transisi shipment ke tahap Logistics Prep?",
  },
  {
    status: "logistics_prep",
    action: "mark-in-transit",
    label: "Mark as In Transit",
    confirmMsg: "Setujui transisi shipment ke tahap In Transit? Dokumen prasyarat harus lengkap.",
  },
  {
    status: "in_transit",
    action: "mark-customs-clearance",
    label: "Mark as Customs Clearance",
    confirmMsg: "Setujui transisi shipment ke tahap Customs Clearance? Dokumen prasyarat harus lengkap.",
  },
  {
    status: "customs_clearance",
    action: "mark-delivered",
    label: "Mark as Delivered",
    confirmMsg: "Setujui transisi shipment ke tahap Delivered? Dokumen prasyarat harus lengkap.",
  },
];

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function formatDateShort(iso: string | null): string {
  if (!iso) return "-";
  return new Date(iso).toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}

function formatRupiah(v: string | null): string {
  if (v === null || v === "") return "-";
  const n = Number(v);
  if (!Number.isFinite(n)) return v;
  return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(n);
}

export default function ShipmentPanel({ transactionId, shipment, userRole, onUpdate }: Props) {
  const [uploadFiles, setUploadFiles] = useState<Record<string, File | null>>({});
  const [uploadingType, setUploadingType] = useState<ShipmentDocumentType | null>(null);
  const [uploadError, setUploadError] = useState<string | null>(null);
  const [pendingTransition, setPendingTransition] = useState<AdminTransition | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  const activeStepIndex = useMemo(() => {
    const idx = STEPPER.findIndex((s) => s.key === shipment.status);
    // delayed → treat as index of last-known progression: keep between in_transit(2)/customs(3)
    if (shipment.status === "delayed") return 2;
    return idx;
  }, [shipment.status]);

  const uploadedTypes = useMemo(
    () => new Set(shipment.documents.map((d) => d.type)),
    [shipment.documents]
  );

  const grouped = useMemo(() => {
    const g: Record<string, ShipmentDocument[]> = {};
    for (const doc of shipment.documents) {
      (g[doc.category] ??= []).push(doc);
    }
    return g;
  }, [shipment.documents]);

  const slots = SLOTS_BY_ROLE[userRole];
  const availableSlots = slots.filter((s) => !uploadedTypes.has(s.type));

  const currentTransition = ADMIN_TRANSITIONS.find((t) => t.status === shipment.status);

  async function handleUpload(slotType: ShipmentDocumentType) {
    const file = uploadFiles[slotType];
    if (!file) return;
    setUploadError(null);
    setUploadingType(slotType);
    try {
      const body = toFormData({ type: slotType, file });
      await apiFetch(`/api/transactions/${transactionId}/shipment/documents`, {
        method: "POST",
        body,
      });
      setUploadFiles((prev) => ({ ...prev, [slotType]: null }));
      await onUpdate();
    } catch (err) {
      if (err instanceof ApiError) {
        setUploadError(err.errors?.file?.[0] ?? err.errors?.type?.[0] ?? err.message);
      } else {
        setUploadError("Gagal mengunggah dokumen.");
      }
    } finally {
      setUploadingType(null);
    }
  }

  async function handleTransition() {
    if (!pendingTransition) return;
    setActionError(null);
    setIsSubmitting(true);
    try {
      await apiFetch(`/api/admin/transactions/${transactionId}/shipment/${pendingTransition.action}`, {
        method: "POST",
      });
      setPendingTransition(null);
      await onUpdate();
    } catch (err) {
      if (err instanceof ApiError) setActionError(err.message);
      else setActionError("Gagal memperbarui status shipment.");
    } finally {
      setIsSubmitting(false);
    }
  }

  const statusColor = STATUS_COLORS[shipment.status];
  const insurance = shipment.cargo_insurance;

  return (
    <section className="rounded-lg border border-border bg-surface-container p-5">
      {/* Header */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0">
          <h2 className="font-display text-lg text-on-surface">Logistik & Pengiriman</h2>
          <p className="mt-0.5 text-xs text-on-surface-muted">
            Cross-border shipment tracking, dokumen ekspor-impor, dan status bea cukai.
          </p>
        </div>
        <span
          className={`inline-flex items-center gap-1.5 self-start rounded-full border px-3 py-1 text-xs font-medium ${statusColor.badge}`}
        >
          <span className={`h-1.5 w-1.5 rounded-full ${statusColor.dot}`} aria-hidden />
          {shipment.status_label}
        </span>
      </div>

      {/* Stepper */}
      <ol className="mt-5 flex items-center gap-1 overflow-x-auto pb-2" aria-label="Progres shipment">
        {STEPPER.map((step, idx) => {
          const isActive = idx === activeStepIndex;
          const isDone = idx < activeStepIndex;
          const isDelayed = shipment.status === "delayed" && isActive;
          return (
            <li key={step.key} className="flex min-w-0 flex-1 items-center gap-1">
              <div className="flex min-w-0 flex-1 flex-col items-center gap-1">
                <div
                  className={`flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full border text-xs font-semibold ${
                    isDelayed
                      ? "border-red-500 bg-red-500/10 text-red-500"
                      : isDone
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
              {idx < STEPPER.length - 1 && (
                <div className={`h-0.5 min-w-4 flex-1 ${isDone ? "bg-emerald-500/60" : "bg-border"}`} aria-hidden />
              )}
            </li>
          );
        })}
      </ol>

      {/* Shipment Info */}
      <dl className="mt-5 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-xs uppercase text-on-surface-muted">Rute</dt>
          <dd className="text-on-surface">
            {shipment.origin_country} &rarr; {shipment.destination_country}
          </dd>
        </div>
        <div>
          <dt className="text-xs uppercase text-on-surface-muted">Moda Pengiriman</dt>
          <dd className="text-on-surface">{shipment.shipping_mode_label}</dd>
        </div>
        <div>
          <dt className="text-xs uppercase text-on-surface-muted">Carrier</dt>
          <dd className="text-on-surface">{shipment.carrier_name}</dd>
        </div>
        <div>
          <dt className="text-xs uppercase text-on-surface-muted">Nomor Tracking</dt>
          <dd className="text-on-surface">{shipment.tracking_number ?? "-"}</dd>
        </div>
        <div>
          <dt className="text-xs uppercase text-on-surface-muted">Estimasi Tiba</dt>
          <dd className="text-on-surface">{formatDateShort(shipment.estimated_arrival)}</dd>
        </div>
        <div>
          <dt className="text-xs uppercase text-on-surface-muted">Aktual Tiba</dt>
          <dd className="text-on-surface">{formatDateShort(shipment.actual_arrival)}</dd>
        </div>
      </dl>

      {/* Cargo Insurance */}
      <div className="mt-5 rounded-md border border-border bg-surface p-4">
        <div className="flex items-baseline justify-between gap-2">
          <h3 className="text-sm font-semibold text-on-surface">Asuransi Kargo</h3>
          <span
            className={`rounded-full border px-2 py-0.5 text-[10px] font-medium ${
              insurance.is_complete
                ? "border-emerald-500/40 bg-emerald-500/10 text-emerald-500"
                : "border-amber-500/40 bg-amber-500/10 text-amber-500"
            }`}
          >
            {insurance.is_complete ? "Lengkap" : "Belum lengkap"}
          </span>
        </div>
        <dl className="mt-3 grid grid-cols-1 gap-2 text-xs sm:grid-cols-3">
          <div>
            <dt className="text-on-surface-muted">Insurer</dt>
            <dd className="text-on-surface">{insurance.insurer_name ?? "-"}</dd>
          </div>
          <div>
            <dt className="text-on-surface-muted">No. Polis</dt>
            <dd className="text-on-surface">{insurance.policy_number ?? "-"}</dd>
          </div>
          <div>
            <dt className="text-on-surface-muted">Coverage</dt>
            <dd className="text-on-surface">{formatRupiah(insurance.coverage_amount)}</dd>
          </div>
        </dl>
        {insurance.certificate_url && (
          <a
            href={insurance.certificate_url}
            target="_blank"
            rel="noreferrer"
            className="mt-3 inline-flex items-center gap-1 text-xs text-primary hover:underline"
          >
            <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden>
              <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z" />
              <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z" />
            </svg>
            Sertifikat Asuransi
          </a>
        )}
      </div>

      {/* Documents */}
      <div className="mt-5">
        <div className="mb-2 flex items-baseline justify-between gap-2">
          <h3 className="text-sm font-semibold text-on-surface">Dokumen Shipment</h3>
          <span className="text-xs text-on-surface-muted">{shipment.documents.length} dokumen</span>
        </div>
        {shipment.documents.length === 0 ? (
          <p className="rounded-md border border-dashed border-border bg-surface px-4 py-6 text-center text-xs text-on-surface-muted">
            Belum ada dokumen shipment.
          </p>
        ) : (
          <div className="space-y-3">
            {CATEGORY_ORDER.map((cat) => {
              const items = grouped[cat];
              if (!items || items.length === 0) return null;
              return (
                <div key={cat}>
                  <h4 className="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-on-surface-muted">
                    {cat}
                  </h4>
                  <ul className="divide-y divide-border rounded-md border border-border">
                    {items.map((doc) => (
                      <li key={doc.id} className="flex items-center justify-between gap-3 px-3 py-2.5">
                        <div className="min-w-0">
                          <p className="truncate text-sm font-medium text-on-surface">{doc.label}</p>
                          <p className="mt-0.5 text-[11px] text-on-surface-muted">
                            {doc.uploaded_by ? `oleh ${doc.uploaded_by}` : "Sistem"} &middot;{" "}
                            {formatDate(doc.created_at)}
                          </p>
                        </div>
                        <a
                          href={doc.download_url}
                          target="_blank"
                          rel="noreferrer"
                          className="flex-shrink-0 rounded-md border border-primary/40 bg-primary-container/10 px-3 py-1.5 text-xs font-medium text-primary transition-colors hover:bg-primary-container/20"
                        >
                          Unduh
                        </a>
                      </li>
                    ))}
                  </ul>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Upload Slots */}
      {availableSlots.length > 0 && (
        <div className="mt-5 rounded-md border border-dashed border-border bg-surface p-4">
          <h3 className="text-sm font-semibold text-on-surface">Unggah Dokumen</h3>
          <p className="mt-0.5 text-xs text-on-surface-muted">
            {userRole === "seller" && "Sebagai seller, kamu dapat mengunggah dokumen ekspor & pengiriman."}
            {userRole === "buyer" && "Sebagai buyer, kamu dapat mengunggah dokumen konfirmasi penerimaan."}
            {userRole === "admin" && "Sebagai admin, kamu dapat mengunggah semua tipe dokumen (override)."}
          </p>
          {uploadError && <p className="mt-2 text-xs text-error">{uploadError}</p>}
          <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            {availableSlots.map((slot) => {
              const file = uploadFiles[slot.type] ?? null;
              const isUploading = uploadingType === slot.type;
              return (
                <div key={slot.type} className="space-y-2">
                  <FileUpload
                    id={`shipment-doc-${slot.type}`}
                    label={slot.label}
                    helpText={`Kategori: ${slot.category}`}
                    file={file}
                    onFile={(f) => setUploadFiles((prev) => ({ ...prev, [slot.type]: f }))}
                  />
                  {file && (
                    <button
                      type="button"
                      onClick={() => handleUpload(slot.type)}
                      disabled={isUploading}
                      className="w-full rounded-md bg-primary-container px-3 py-1.5 text-xs font-medium text-on-primary hover:bg-primary disabled:opacity-60"
                    >
                      {isUploading ? "Mengunggah..." : "Unggah"}
                    </button>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Admin Transitions */}
      {userRole === "admin" && currentTransition && (
        <div className="mt-5 rounded-md border border-primary/30 bg-primary-container/5 p-4">
          <h3 className="text-sm font-semibold text-on-surface">Aksi Admin</h3>
          <p className="mt-0.5 text-xs text-on-surface-muted">
            Setiap transisi divalidasi dokumen prasyarat. Jika belum lengkap, sistem akan menolak (422).
          </p>
          {actionError && <p className="mt-2 text-xs text-error">{actionError}</p>}
          <div className="mt-3 flex flex-wrap gap-2">
            <button
              type="button"
              onClick={() => setPendingTransition(currentTransition)}
              disabled={isSubmitting}
              className="rounded-md bg-primary-container px-3 py-1.5 text-sm font-medium text-on-primary hover:bg-primary disabled:opacity-60"
            >
              {currentTransition.label}
            </button>
          </div>
        </div>
      )}

      {pendingTransition && (
        <ConfirmModal
          title={pendingTransition.label}
          message={pendingTransition.confirmMsg}
          isSubmitting={isSubmitting}
          onConfirm={handleTransition}
          onCancel={() => setPendingTransition(null)}
        />
      )}
    </section>
  );
}
