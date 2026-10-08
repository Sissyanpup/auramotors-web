"use client";

import { use, useEffect, useMemo, useState, type FormEvent } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ApiError, apiFetch } from "@/lib/api";
import { formatRupiah } from "@/lib/format";
import { useAuth } from "@/contexts/auth-context";
import DangerConfirmModal from "@/components/DangerConfirmModal";
import Badge from "@/components/Badge";
import type { InsuranceOption, InsuranceKind, PaymentOption, PaymentScheme, Transaction, VehicleDetail } from "@/lib/types";

type FormState = {
  scheme: PaymentScheme;
  percent: number;
  insuranceType: InsuranceKind;
  address: string;
  phone: string;
  notes: string;
};

const INITIAL_FORM: FormState = {
  scheme: "down_payment",
  percent: 10,
  insuranceType: "none",
  address: "",
  phone: "",
  notes: "",
};

export default function CheckoutFormPage({ params }: { params: Promise<{ vehicleId: string }> }) {
  const { vehicleId } = use(params);
  const { user, isLoading: authLoading } = useAuth();
  const router = useRouter();

  const [vehicle, setVehicle] = useState<VehicleDetail | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [form, setForm] = useState<FormState>(INITIAL_FORM);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);

  useEffect(() => {
    let cancelled = false;
    apiFetch<{ data: VehicleDetail }>(`/api/vehicles/${vehicleId}`)
      .then(({ data }) => {
        if (cancelled) return;
        setVehicle(data);
        const defaultPercent = data.payment_options?.[0]?.percent ?? 10;
        setForm((prev) => ({ ...prev, percent: defaultPercent }));
      })
      .catch(() => {
        if (!cancelled) setLoadError("Kendaraan tidak ditemukan atau belum disetujui.");
      });
    return () => {
      cancelled = true;
    };
  }, [vehicleId]);

  useEffect(() => {
    if (!authLoading && !user) router.replace("/login");
    if (!authLoading && user && user.role !== "buyer") router.replace("/");
    if (!authLoading && user && !user.has_completed_ktp) router.replace("/lengkapi-data");
  }, [authLoading, user, router]);

  const paymentOptions: PaymentOption[] = vehicle?.payment_options ?? [];
  const insuranceOptions: InsuranceOption[] = vehicle?.insurance_options ?? [];

  const selectedInsurance = useMemo(
    () => insuranceOptions.find((opt) => opt.type === form.insuranceType) ?? null,
    [insuranceOptions, form.insuranceType]
  );

  const price = vehicle ? Number(vehicle.price) : 0;
  const isFullPayment = form.scheme === "full";
  const dpAmount = isFullPayment ? price : Math.round((price * form.percent) / 100);
  const insurancePremium = selectedInsurance?.premium ?? 0;
  const totalDueNow = dpAmount + insurancePremium;
  const remainingAfterDp = Math.max(price - dpAmount, 0);

  function selectScheme(percent: number) {
    if (percent >= 100) {
      setForm((prev) => ({ ...prev, scheme: "full", percent: 100 }));
    } else {
      setForm((prev) => ({ ...prev, scheme: "down_payment", percent }));
    }
  }

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    const nextErrors: Record<string, string[]> = {};
    if (form.address.trim().length < 10) nextErrors.buyer_address = ["Alamat pengantaran wajib diisi lengkap."];
    if (form.phone.trim().length < 8) nextErrors.buyer_phone = ["Nomor telepon wajib diisi."];
    if (Object.keys(nextErrors).length > 0) {
      setErrors(nextErrors);
      return;
    }
    setErrors({});
    setShowConfirm(true);
  }

  async function executeCheckout() {
    if (!vehicle) return;
    setShowConfirm(false);
    setIsSubmitting(true);
    setFormError(null);
    try {
      const { data } = await apiFetch<{ data: Transaction }>(`/api/buyer/vehicles/${vehicle.id}/checkout`, {
        method: "POST",
        body: {
          payment_scheme: form.scheme,
          dp_percent: form.percent,
          insurance_type: form.insuranceType,
          buyer_address: form.address.trim(),
          buyer_phone: form.phone.trim(),
          buyer_notes: form.notes.trim() || null,
        },
      });
      if (data.gateway_invoice_url) {
        window.location.href = data.gateway_invoice_url;
      } else {
        router.push(`/buyer/transactions/${data.id}`);
      }
    } catch (err) {
      if (err instanceof ApiError && err.errors) {
        setErrors(err.errors);
      } else if (err instanceof ApiError) {
        setFormError(err.message);
      }
      setIsSubmitting(false);
    }
  }

  if (loadError) return <p className="mx-auto max-w-2xl p-8 text-sm text-error">{loadError}</p>;
  if (!vehicle || authLoading || !user) return <p className="mx-auto max-w-2xl p-8 text-sm text-on-surface-muted">Memuat...</p>;

  return (
    <div className="mx-auto max-w-4xl px-4 py-8">
      <Link href={`/kendaraan/${vehicle.id}`} className="text-sm text-on-surface-muted hover:text-on-surface">
        &larr; Kembali ke detail kendaraan
      </Link>

      <div className="mt-3">
        <Badge tone="primary">Form Pembelian &amp; Escrow</Badge>
        <h1 className="mt-2 font-display text-2xl text-on-surface">
          Beli {vehicle.brand} {vehicle.model} {vehicle.year}
        </h1>
        <p className="mt-1 text-sm text-on-surface-muted">
          Isi form dengan teliti — data ini akan tercetak pada nota resmi transaksi kamu.
        </p>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-[1fr,320px]">
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Data buyer (readonly dari KTP) */}
          <section className="rounded-lg border border-border bg-surface-container p-5">
            <h2 className="text-sm font-semibold uppercase tracking-wider text-on-surface-muted">Data Pembeli (KTP)</h2>
            <dl className="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-xs text-on-surface-muted">Nama sesuai KTP</dt>
                <dd className="text-on-surface">{user.ktp_name}</dd>
              </div>
              <div>
                <dt className="text-xs text-on-surface-muted">NIK</dt>
                <dd className="font-mono text-on-surface">{user.ktp_number}</dd>
              </div>
              <div className="sm:col-span-2">
                <dt className="text-xs text-on-surface-muted">Email</dt>
                <dd className="text-on-surface">{user.email}</dd>
              </div>
            </dl>
          </section>

          {/* Skema pembayaran */}
          <section className="rounded-lg border border-border bg-surface-container p-5">
            <h2 className="text-sm font-semibold uppercase tracking-wider text-on-surface-muted">Skema Pembayaran</h2>
            <p className="mt-1 text-xs text-on-surface-muted">
              Pilih besaran DP atau bayar penuh. Dana masuk ke escrow sampai serah-terima terverifikasi.
            </p>
            <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
              {paymentOptions.map((opt) => {
                const active = form.percent === opt.percent;
                return (
                  <button
                    key={opt.percent}
                    type="button"
                    onClick={() => selectScheme(opt.percent)}
                    className={`rounded-md border p-3 text-left transition-colors ${
                      active
                        ? "border-primary bg-primary/10 text-on-surface"
                        : "border-border bg-surface hover:border-primary/40 text-on-surface-muted"
                    }`}
                  >
                    <p className="text-sm font-semibold text-on-surface">{opt.label}</p>
                    <p className="mt-1 text-xs">
                      {opt.percent >= 100
                        ? formatRupiah(price)
                        : `${formatRupiah(Math.round((price * opt.percent) / 100))}`}
                    </p>
                  </button>
                );
              })}
            </div>
          </section>

          {/* Asuransi */}
          <section className="rounded-lg border border-border bg-surface-container p-5">
            <h2 className="text-sm font-semibold uppercase tracking-wider text-on-surface-muted">Asuransi Kendaraan</h2>
            <p className="mt-1 text-xs text-on-surface-muted">
              TLO menanggung kehilangan/kerusakan total, All Risk menanggung kerusakan sebagian juga.
            </p>
            <div className="mt-3 space-y-2">
              {insuranceOptions.map((opt) => {
                const active = form.insuranceType === opt.type;
                return (
                  <label
                    key={opt.type}
                    className={`flex cursor-pointer items-center justify-between gap-3 rounded-md border p-3 transition-colors ${
                      active ? "border-primary bg-primary/10" : "border-border bg-surface hover:border-primary/40"
                    }`}
                  >
                    <div className="flex items-center gap-3">
                      <input
                        type="radio"
                        name="insurance"
                        value={opt.type}
                        checked={active}
                        onChange={() => setForm((prev) => ({ ...prev, insuranceType: opt.type }))}
                        className="h-4 w-4 text-primary focus:ring-primary"
                      />
                      <div>
                        <p className="text-sm font-medium text-on-surface">{opt.label}</p>
                        {opt.type === "tlo" && (
                          <p className="text-xs text-on-surface-muted">Menanggung total loss (hilang / rusak berat &gt;75%).</p>
                        )}
                        {opt.type === "all_risk" && (
                          <p className="text-xs text-on-surface-muted">Menanggung kerusakan sebagian &amp; total, termasuk pihak ketiga.</p>
                        )}
                      </div>
                    </div>
                    <p className="text-sm font-semibold text-on-surface">
                      {opt.premium > 0 ? `+ ${formatRupiah(opt.premium)}` : "Gratis"}
                    </p>
                  </label>
                );
              })}
            </div>
          </section>

          {/* Alamat pengantaran */}
          <section className="rounded-lg border border-border bg-surface-container p-5">
            <h2 className="text-sm font-semibold uppercase tracking-wider text-on-surface-muted">Alamat Pengantaran &amp; Kontak</h2>
            <p className="mt-1 text-xs text-on-surface-muted">
              Kendaraan akan diantar oleh seller ke alamat ini setelah pembayaran DP terverifikasi.
            </p>

            <div className="mt-4 space-y-4">
              <div>
                <label htmlFor="buyer-address" className="block text-sm font-medium text-on-surface">
                  Alamat lengkap pengantaran
                </label>
                <textarea
                  id="buyer-address"
                  required
                  rows={3}
                  maxLength={1000}
                  value={form.address}
                  onChange={(e) => setForm((prev) => ({ ...prev, address: e.target.value }))}
                  placeholder="Jl. Contoh No. 12, RT 01 RW 02, Kelurahan Foo, Kecamatan Bar, Kota Baz, Provinsi Qux, 12345"
                  className="mt-1.5 block w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-on-surface placeholder:text-on-surface-muted focus:border-primary focus:outline-none resize-none"
                />
                {errors.buyer_address && <p className="mt-1 text-xs text-error">{errors.buyer_address[0]}</p>}
              </div>

              <div>
                <label htmlFor="buyer-phone" className="block text-sm font-medium text-on-surface">
                  Nomor telepon aktif (WA)
                </label>
                <input
                  id="buyer-phone"
                  type="tel"
                  required
                  maxLength={32}
                  value={form.phone}
                  onChange={(e) => setForm((prev) => ({ ...prev, phone: e.target.value }))}
                  placeholder="mis. 08123456789"
                  className="input-field mt-1.5"
                />
                {errors.buyer_phone && <p className="mt-1 text-xs text-error">{errors.buyer_phone[0]}</p>}
              </div>

              <div>
                <label htmlFor="buyer-notes" className="block text-sm font-medium text-on-surface">
                  Catatan untuk seller <span className="font-normal text-on-surface-muted">(opsional)</span>
                </label>
                <textarea
                  id="buyer-notes"
                  rows={2}
                  maxLength={1000}
                  value={form.notes}
                  onChange={(e) => setForm((prev) => ({ ...prev, notes: e.target.value }))}
                  placeholder="mis. mohon konfirmasi 1 hari sebelum pengantaran."
                  className="mt-1.5 block w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-on-surface placeholder:text-on-surface-muted focus:border-primary focus:outline-none resize-none"
                />
              </div>
            </div>
          </section>

          {formError && <p className="text-sm text-error" role="alert">{formError}</p>}

          <button
            type="submit"
            disabled={isSubmitting}
            className="btn-gold w-full rounded-md px-4 py-3 text-sm font-medium disabled:opacity-50"
          >
            {isSubmitting ? "Memproses..." : "Tinjau & Lanjut ke Konfirmasi"}
          </button>
        </form>

        {/* Ringkasan biaya (sticky) */}
        <aside className="lg:sticky lg:top-24 lg:self-start">
          <div className="rounded-lg border border-border bg-surface-container p-5">
            <h2 className="text-sm font-semibold uppercase tracking-wider text-on-surface-muted">Ringkasan Biaya</h2>
            <dl className="mt-3 space-y-2 text-sm">
              <div className="flex items-center justify-between">
                <dt className="text-on-surface-muted">Harga unit</dt>
                <dd className="text-on-surface">{formatRupiah(price)}</dd>
              </div>
              <div className="flex items-center justify-between">
                <dt className="text-on-surface-muted">
                  {isFullPayment ? "Bayar penuh" : `DP ${form.percent}%`}
                </dt>
                <dd className="text-on-surface">{formatRupiah(dpAmount)}</dd>
              </div>
              {insurancePremium > 0 && (
                <div className="flex items-center justify-between">
                  <dt className="text-on-surface-muted">Premi asuransi</dt>
                  <dd className="text-on-surface">{formatRupiah(insurancePremium)}</dd>
                </div>
              )}
              {!isFullPayment && (
                <div className="flex items-center justify-between text-xs text-on-surface-muted">
                  <dt>Sisa pelunasan (di luar sistem)</dt>
                  <dd>{formatRupiah(remainingAfterDp)}</dd>
                </div>
              )}
              <div className="flex items-center justify-between border-t border-border pt-2 text-base font-semibold">
                <dt className="text-on-surface">Bayar sekarang</dt>
                <dd className="text-primary">{formatRupiah(totalDueNow)}</dd>
              </div>
            </dl>

            <div className="mt-4 rounded-md border border-border bg-surface p-3 text-xs text-on-surface-muted">
              Dana ditahan di rekening escrow platform sampai kamu mengkonfirmasi terima kendaraan.
            </div>
          </div>
        </aside>
      </div>

      {showConfirm && (
        <DangerConfirmModal
          title="Konfirmasi Pengajuan Pembelian"
          intro={
            <>
              Aksi ini membuat invoice resmi & memblokir kendaraan{" "}
              <strong className="text-on-surface">{vehicle.brand} {vehicle.model}</strong> untuk buyer lain hingga pembayaran/expire.
            </>
          }
          consequences={[
            `Total ${formatRupiah(totalDueNow)} akan ditagihkan (${isFullPayment ? "bayar penuh" : `DP ${form.percent}%`}${insurancePremium > 0 ? " + premi asuransi" : ""}).`,
            "Nomor invoice resmi diterbitkan otomatis dan tidak dapat diubah.",
            "Data KTP, alamat, dan nomor telepon akan tercantum pada nota transaksi.",
            !isFullPayment ? `Sisa pelunasan ${formatRupiah(remainingAfterDp)} diselesaikan di luar sistem antara kamu dan seller.` : "Transaksi ini bersifat lunas.",
          ]}
          confirmWord="BAYAR"
          confirmLabel="Ya, Terbitkan Invoice"
          isSubmitting={isSubmitting}
          onConfirm={executeCheckout}
          onCancel={() => setShowConfirm(false)}
        />
      )}
    </div>
  );
}
