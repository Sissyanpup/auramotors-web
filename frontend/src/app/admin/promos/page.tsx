"use client";

import { useEffect, useState, type FormEvent } from "react";
import { ApiError, apiFetch } from "@/lib/api";
import { formatDate, formatRupiah } from "@/lib/format";
import type { Promo, PromoBenefit } from "@/lib/types";
import Badge from "@/components/Badge";
import ConfirmModal from "@/components/ConfirmModal";

/** Kode perluasan asuransi — sama dengan config/pricing.php → insurance.addons. */
const ADDON_OPTIONS = [
  { code: "flood", label: "Banjir & angin topan" },
  { code: "earthquake", label: "Gempa bumi, tsunami & letusan gunung" },
  { code: "riot", label: "Huru-hara & kerusuhan" },
  { code: "terrorism", label: "Terorisme & sabotase" },
  { code: "third_party_liability", label: "Tanggung jawab hukum pihak ketiga" },
  { code: "personal_accident", label: "Kecelakaan diri pengemudi & penumpang" },
  { code: "towing_replacement", label: "Derek, bengkel & mobil pengganti (All Risk)" },
];

const BENEFIT_LABEL: Record<PromoBenefit, string> = {
  discount: "Potongan harga",
  free_addon: "Gratis perluasan asuransi",
  credit_rate: "Bunga kredit spesial",
};

type FormState = {
  name: string;
  kind: "auto" | "voucher";
  code: string;
  benefit: PromoBenefit;
  discount_type: "percent" | "fixed";
  discount_value: string;
  max_discount: string;
  addon_code: string;
  rate_cut_percent: string;
  min_vehicle_price: string;
  brand: string;
  badge: string;
  description: string;
  starts_at: string;
  ends_at: string;
  quota: string;
  is_active: boolean;
};

const EMPTY_FORM: FormState = {
  name: "",
  kind: "auto",
  code: "",
  benefit: "discount",
  discount_type: "percent",
  discount_value: "",
  max_discount: "",
  addon_code: "flood",
  rate_cut_percent: "",
  min_vehicle_price: "",
  brand: "",
  badge: "",
  description: "",
  starts_at: "",
  ends_at: "",
  quota: "",
  is_active: true,
};

function toForm(promo: Promo): FormState {
  const day = (value: string | null) => (value ? value.slice(0, 10) : "");
  return {
    name: promo.name,
    kind: promo.kind,
    code: promo.code ?? "",
    benefit: promo.benefit,
    discount_type: promo.discount_type ?? "percent",
    discount_value: promo.discount_value ? String(Number(promo.discount_value)) : "",
    max_discount: promo.max_discount ? String(Number(promo.max_discount)) : "",
    addon_code: promo.addon_code ?? "flood",
    rate_cut_percent: promo.rate_cut ? String(Number(promo.rate_cut) * 100) : "",
    min_vehicle_price: promo.min_vehicle_price ? String(Number(promo.min_vehicle_price)) : "",
    brand: promo.brand ?? "",
    badge: promo.badge ?? "",
    description: promo.description ?? "",
    starts_at: day(promo.starts_at),
    ends_at: day(promo.ends_at),
    quota: promo.quota ? String(promo.quota) : "",
    is_active: promo.is_active ?? true,
  };
}

export default function AdminPromosPage() {
  const [promos, setPromos] = useState<Promo[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [deleting, setDeleting] = useState<Promo | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  async function loadPromos() {
    setIsLoading(true);
    const { data } = await apiFetch<{ data: Promo[] }>("/api/admin/promos");
    setPromos(data);
    setIsLoading(false);
  }

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- initial data fetch on mount
    loadPromos();
  }, []);

  function openCreate() {
    setForm(EMPTY_FORM);
    setEditingId(null);
    setErrors({});
    setFormError(null);
    setShowForm(true);
  }

  function openEdit(promo: Promo) {
    setForm(toForm(promo));
    setEditingId(promo.id);
    setErrors({});
    setFormError(null);
    setShowForm(true);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setIsSubmitting(true);
    setErrors({});
    setFormError(null);
    const nullable = (value: string) => (value.trim() === "" ? null : value.trim());
    try {
      await apiFetch(editingId ? `/api/admin/promos/${editingId}` : "/api/admin/promos", {
        method: editingId ? "PUT" : "POST",
        body: {
          name: form.name.trim(),
          kind: form.kind,
          code: form.kind === "voucher" ? nullable(form.code) : null,
          benefit: form.benefit,
          discount_type: form.benefit === "discount" ? form.discount_type : null,
          discount_value: form.benefit === "discount" ? nullable(form.discount_value) : null,
          max_discount: form.benefit === "discount" ? nullable(form.max_discount) : null,
          addon_code: form.benefit === "free_addon" ? form.addon_code : null,
          rate_cut_percent: form.benefit === "credit_rate" ? nullable(form.rate_cut_percent) : null,
          min_vehicle_price: nullable(form.min_vehicle_price),
          brand: nullable(form.brand),
          badge: nullable(form.badge),
          description: nullable(form.description),
          starts_at: nullable(form.starts_at),
          ends_at: nullable(form.ends_at),
          quota: nullable(form.quota),
          is_active: form.is_active,
        },
      });
      setShowForm(false);
      await loadPromos();
    } catch (err) {
      if (err instanceof ApiError && err.errors) setErrors(err.errors);
      else if (err instanceof ApiError) setFormError(err.message);
    } finally {
      setIsSubmitting(false);
    }
  }

  async function toggleActive(promo: Promo) {
    setActionError(null);
    try {
      await apiFetch(`/api/admin/promos/${promo.id}`, {
        method: "PUT",
        body: {
          ...toForm(promo),
          code: promo.kind === "voucher" ? promo.code : null,
          rate_cut_percent: promo.rate_cut ? Number(promo.rate_cut) * 100 : null,
          is_active: !promo.is_active,
        },
      });
      await loadPromos();
    } catch (err) {
      if (err instanceof ApiError) setActionError(err.message);
    }
  }

  async function confirmDelete() {
    if (!deleting) return;
    setIsSubmitting(true);
    setActionError(null);
    try {
      await apiFetch(`/api/admin/promos/${deleting.id}`, { method: "DELETE" });
      setDeleting(null);
      await loadPromos();
    } catch (err) {
      if (err instanceof ApiError) setActionError(err.message);
      setDeleting(null);
    } finally {
      setIsSubmitting(false);
    }
  }

  const field = (name: keyof FormState) => errors[name]?.[0] ?? (name === "rate_cut_percent" ? errors.rate_cut?.[0] : undefined);

  return (
    <div>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold text-on-surface">Promo Pembeli</h1>
          <p className="mt-1 text-sm text-on-surface-muted">
            Promo otomatis tampil sebagai harga coret di katalog; voucher butuh kode saat checkout. Potongan ditanggung platform,
            dana penjual tidak berkurang.
          </p>
        </div>
        <button onClick={openCreate} className="btn-gold rounded-md px-4 py-2 text-sm font-medium">
          + Promo Baru
        </button>
      </div>

      {actionError && <p className="mt-3 text-sm text-error">{actionError}</p>}

      {showForm && (
        <form onSubmit={handleSubmit} className="mt-5 space-y-4 rounded-lg border border-primary/40 bg-surface-container p-5">
          <h2 className="text-sm font-semibold uppercase tracking-wider text-on-surface-muted">{editingId ? "Ubah Promo" : "Promo Baru"}</h2>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Nama promo" error={field("name")}>
              <input className="input-field" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="mis. Flash Sale Akhir Bulan" required />
            </Field>
            <Field label="Label badge (opsional)" error={field("badge")}>
              <input className="input-field" value={form.badge} onChange={(e) => setForm({ ...form, badge: e.target.value })} placeholder="mis. Flash Sale" maxLength={30} />
            </Field>
            <Field label="Cara berlaku">
              <select className="input-field" value={form.kind} onChange={(e) => setForm({ ...form, kind: e.target.value as FormState["kind"] })}>
                <option value="auto">Otomatis (harga coret di katalog)</option>
                <option value="voucher">Voucher (pakai kode)</option>
              </select>
            </Field>
            {form.kind === "voucher" && (
              <Field label="Kode voucher" error={field("code")}>
                <input className="input-field font-mono uppercase" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} placeholder="mis. AURAHEMAT" maxLength={32} />
              </Field>
            )}
            <Field label="Manfaat">
              <select className="input-field" value={form.benefit} onChange={(e) => setForm({ ...form, benefit: e.target.value as PromoBenefit })}>
                <option value="discount">Potongan harga</option>
                <option value="free_addon">Gratis perluasan asuransi</option>
                <option value="credit_rate">Bunga kredit spesial</option>
              </select>
            </Field>

            {form.benefit === "discount" && (
              <>
                <Field label="Jenis potongan">
                  <select className="input-field" value={form.discount_type} onChange={(e) => setForm({ ...form, discount_type: e.target.value as FormState["discount_type"] })}>
                    <option value="percent">Persen dari harga</option>
                    <option value="fixed">Nominal tetap (Rp)</option>
                  </select>
                </Field>
                <Field label={form.discount_type === "percent" ? "Potongan (%)" : "Potongan (Rp)"} error={field("discount_value")}>
                  <input className="input-field" type="number" min={0} step="any" value={form.discount_value} onChange={(e) => setForm({ ...form, discount_value: e.target.value })} required />
                </Field>
                <Field label="Potongan maksimum (Rp, opsional)" error={field("max_discount")}>
                  <input className="input-field" type="number" min={0} value={form.max_discount} onChange={(e) => setForm({ ...form, max_discount: e.target.value })} />
                </Field>
              </>
            )}
            {form.benefit === "free_addon" && (
              <Field label="Perluasan yang digratiskan" error={field("addon_code")}>
                <select className="input-field" value={form.addon_code} onChange={(e) => setForm({ ...form, addon_code: e.target.value })}>
                  {ADDON_OPTIONS.map((addon) => (
                    <option key={addon.code} value={addon.code}>
                      {addon.label}
                    </option>
                  ))}
                </select>
              </Field>
            )}
            {form.benefit === "credit_rate" && (
              <Field label="Bunga turun (poin %/tahun)" error={field("rate_cut_percent")}>
                <input className="input-field" type="number" min={0.1} max={10} step="0.1" value={form.rate_cut_percent} onChange={(e) => setForm({ ...form, rate_cut_percent: e.target.value })} required />
              </Field>
            )}

            <Field label="Harga kendaraan minimal (Rp, opsional)" error={field("min_vehicle_price")}>
              <input className="input-field" type="number" min={0} value={form.min_vehicle_price} onChange={(e) => setForm({ ...form, min_vehicle_price: e.target.value })} />
            </Field>
            <Field label="Khusus merek (opsional)" error={field("brand")}>
              <input className="input-field" value={form.brand} onChange={(e) => setForm({ ...form, brand: e.target.value })} placeholder="mis. Toyota" />
            </Field>
            <Field label="Mulai" error={field("starts_at")}>
              <input className="input-field" type="date" value={form.starts_at} onChange={(e) => setForm({ ...form, starts_at: e.target.value })} />
            </Field>
            <Field label="Berakhir" error={field("ends_at")}>
              <input className="input-field" type="date" value={form.ends_at} onChange={(e) => setForm({ ...form, ends_at: e.target.value })} />
            </Field>
            <Field label="Kuota pemakaian (opsional)" error={field("quota")}>
              <input className="input-field" type="number" min={1} value={form.quota} onChange={(e) => setForm({ ...form, quota: e.target.value })} />
            </Field>
            <label className="flex items-center gap-2 self-end pb-2 text-sm text-on-surface">
              <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} className="h-4 w-4" />
              Aktif
            </label>
          </div>

          <Field label="Deskripsi untuk pembeli (opsional)" error={field("description")}>
            <textarea className="input-field" rows={2} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} maxLength={500} />
          </Field>

          {formError && <p className="text-sm text-error">{formError}</p>}

          <div className="flex gap-2">
            <button type="submit" disabled={isSubmitting} className="btn-gold rounded-md px-4 py-2 text-sm font-medium disabled:opacity-50">
              {isSubmitting ? "Menyimpan..." : "Simpan Promo"}
            </button>
            <button type="button" onClick={() => setShowForm(false)} className="rounded-md border border-border px-4 py-2 text-sm text-on-surface hover:bg-surface-container-high">
              Batal
            </button>
          </div>
        </form>
      )}

      <div className="mt-5 rounded-lg border border-border bg-surface-container p-4">
        {isLoading ? (
          <p className="text-sm text-on-surface-muted">Memuat...</p>
        ) : promos.length === 0 ? (
          <p className="text-sm text-on-surface-muted">Belum ada promo. Buat promo pertama untuk menarik pembeli.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[860px] text-sm">
              <thead>
                <tr className="border-b border-border text-left text-[11px] uppercase tracking-wider text-on-surface-muted">
                  <th className="py-2 pr-3 font-medium">Promo</th>
                  <th className="py-2 pr-3 font-medium">Manfaat</th>
                  <th className="py-2 pr-3 font-medium">Periode</th>
                  <th className="py-2 pr-3 text-right font-medium">Dipakai</th>
                  <th className="py-2 pr-3 text-right font-medium">Total manfaat</th>
                  <th className="py-2 pr-3 font-medium">Status</th>
                  <th className="py-2 font-medium">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border align-top">
                {promos.map((promo) => (
                  <tr key={promo.id}>
                    <td className="py-2 pr-3">
                      <p className="text-on-surface">{promo.name}</p>
                      <p className="text-xs text-on-surface-muted">
                        {promo.kind === "voucher" ? (
                          <>
                            Voucher <span className="font-mono text-on-surface">{promo.code}</span>
                          </>
                        ) : (
                          "Otomatis · harga coret"
                        )}
                        {promo.badge ? ` · badge "${promo.badge}"` : ""}
                      </p>
                    </td>
                    <td className="py-2 pr-3">
                      <p className="text-on-surface">{promo.benefit_summary}</p>
                      <p className="text-xs text-on-surface-muted">
                        {BENEFIT_LABEL[promo.benefit]}
                        {promo.brand ? ` · khusus ${promo.brand}` : ""}
                        {promo.min_vehicle_price ? ` · min. ${formatRupiah(promo.min_vehicle_price)}` : ""}
                      </p>
                    </td>
                    <td className="py-2 pr-3 text-xs text-on-surface-muted">
                      {promo.starts_at ? formatDate(promo.starts_at) : "Sekarang"} — {promo.ends_at ? formatDate(promo.ends_at) : "tanpa batas"}
                    </td>
                    <td className="py-2 pr-3 text-right tabular-nums text-on-surface">
                      {promo.used_count}
                      {promo.quota ? ` / ${promo.quota}` : ""}
                    </td>
                    <td className="py-2 pr-3 text-right tabular-nums text-on-surface">{formatRupiah(promo.redemptions_total ?? 0)}</td>
                    <td className="py-2 pr-3">
                      {promo.is_running ? <Badge tone="success">Berjalan</Badge> : promo.is_active ? <Badge tone="warning">Terjadwal/habis</Badge> : <Badge tone="neutral">Nonaktif</Badge>}
                    </td>
                    <td className="py-2">
                      <div className="flex flex-wrap gap-2 text-xs">
                        <button onClick={() => openEdit(promo)} className="text-on-surface underline">
                          Ubah
                        </button>
                        <button onClick={() => toggleActive(promo)} className="text-on-surface underline">
                          {promo.is_active ? "Nonaktifkan" : "Aktifkan"}
                        </button>
                        <button onClick={() => setDeleting(promo)} className="text-error underline">
                          Hapus
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {deleting && (
        <ConfirmModal
          title="Hapus Promo"
          message={`Hapus promo "${deleting.name}"? Promo yang sudah pernah dipakai tidak bisa dihapus — nonaktifkan saja supaya laporan tetap utuh.`}
          confirmLabel="Hapus"
          isSubmitting={isSubmitting}
          onConfirm={confirmDelete}
          onCancel={() => setDeleting(null)}
        />
      )}
    </div>
  );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
  return (
    <label className="block text-sm">
      <span className="mb-1 block text-xs font-medium text-on-surface-muted">{label}</span>
      {children}
      {error && <span className="mt-1 block text-xs text-error">{error}</span>}
    </label>
  );
}
