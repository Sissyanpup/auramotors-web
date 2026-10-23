"use client";

import { use, useEffect, useMemo, useState, type FormEvent, type ReactNode } from "react";
import Link from "next/link";
import { ApiError, apiFetch } from "@/lib/api";
import { formatRupiah } from "@/lib/format";
import type { LoanInstallment, Transaction, VehicleLoan } from "@/lib/types";

/*
 * Portal bank SIMULASI (proyek offline, tugas kuliah). Sengaja berdiri sendiri
 * dengan tema putih ala halaman 3-D Secure bank, terpisah dari tema gelap
 * AuraMotors (Header disembunyikan untuk rute /bank-portal).
 *
 * Semua angka acak diterima: nomor kartu 12–19 digit, MM/YY, CVV 3–4 digit,
 * OTP 6 digit apa saja. Nomor kartu & CVV tidak pernah disimpan backend —
 * hanya brand + 4 digit terakhir untuk Bukti Pembayaran.
 *
 * Mode angsuran (?installment={id}): portal yang sama dipakai pembeli untuk
 * membayar cicilan kredit ke leasing.
 */

type InstallmentInfo = { installment: LoanInstallment; loan: VehicleLoan; is_next_payable: boolean };

type Method = "card" | "paypal" | "virtual_account";
type Step = "method" | "details" | "otp" | "processing" | "success";

const VA_BANKS = [
  { code: "BCA", name: "BCA", prefix: "39358" },
  { code: "MANDIRI", name: "Mandiri", prefix: "89608" },
  { code: "BNI", name: "BNI", prefix: "8808" },
  { code: "BRI", name: "BRI", prefix: "26215" },
  { code: "PERMATA", name: "Permata", prefix: "8625" },
  { code: "AURA", name: "Bank Aura Nusantara", prefix: "7777" },
] as const;

const PROCESSING_STEPS = [
  "Menghubungi bank penerbit…",
  "Memverifikasi kode OTP…",
  "Menempatkan dana di rekening escrow…",
  "Menerbitkan bukti pembayaran & surat bank…",
];

const INSTALLMENT_PROCESSING_STEPS = [
  "Menghubungi bank penerbit…",
  "Memverifikasi kode OTP…",
  "Meneruskan pembayaran ke leasing…",
  "Mencatat angsuran & menerbitkan bukti bayar…",
];

function digitsOnly(value: string): string {
  return value.replace(/\D/g, "");
}

function detectBrand(cardDigits: string): string {
  if (cardDigits.startsWith("4")) return "VISA";
  if (/^(5[1-5]|2[2-7])/.test(cardDigits)) return "MASTERCARD";
  if (/^3[47]/.test(cardDigits)) return "AMEX";
  if (cardDigits.startsWith("35")) return "JCB";
  return cardDigits.length > 0 ? "DEBIT" : "";
}

function randomOtp(): string {
  return String(Math.floor(100000 + Math.random() * 900000));
}

function sleep(ms: number) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

export default function BankPortalPage({
  params,
  searchParams,
}: {
  params: Promise<{ reference: string }>;
  searchParams: Promise<{ installment?: string }>;
}) {
  const { reference } = use(params);
  const { installment: installmentParam } = use(searchParams);
  const installmentId = installmentParam ? Number(installmentParam) : null;
  const [installmentInfo, setInstallmentInfo] = useState<InstallmentInfo | null>(null);
  const processingSteps = installmentId ? INSTALLMENT_PROCESSING_STEPS : PROCESSING_STEPS;

  const [transaction, setTransaction] = useState<Transaction | null>(null);
  const [loadError, setLoadError] = useState<{ status: number; message: string } | null>(null);
  const [step, setStep] = useState<Step>("method");
  const [method, setMethod] = useState<Method>("card");
  const [error, setError] = useState<string | null>(null);

  const [card, setCard] = useState({ number: "", holder: "", expiry: "", cvv: "" });
  const [paypal, setPaypal] = useState({ email: "", password: "" });
  const [va, setVa] = useState({ bank: "BCA", userId: "", pin: "" });

  const [otp, setOtp] = useState("");
  const [sentOtp, setSentOtp] = useState("");
  const [otpSecondsLeft, setOtpSecondsLeft] = useState(0);
  const [showSms, setShowSms] = useState(false);
  const [processingIndex, setProcessingIndex] = useState(0);

  useEffect(() => {
    const loadInstallment = installmentId
      ? apiFetch<{ data: InstallmentInfo }>(`/api/buyer/installments/${installmentId}`).then(({ data }) => data)
      : Promise.resolve(null);

    Promise.all([apiFetch<{ data: Transaction }>(`/api/payments/mock/${reference}`), loadInstallment])
      .then(([{ data }, info]) => {
        setTransaction(data);
        setInstallmentInfo(info);
        const alreadyPaid = info ? info.installment.status === "paid" : data.payment_status === "paid";
        if (alreadyPaid) setStep("success");
      })
      .catch((err) => {
        setLoadError({
          status: err instanceof ApiError ? err.status : 500,
          message: err instanceof ApiError ? err.message : "Gagal memuat tagihan.",
        });
      });
  }, [reference, installmentId]);

  useEffect(() => {
    if (step !== "otp" || otpSecondsLeft <= 0) return;
    const timer = setInterval(() => setOtpSecondsLeft((s) => Math.max(0, s - 1)), 1000);
    return () => clearInterval(timer);
  }, [step, otpSecondsLeft]);

  useEffect(() => {
    if (step !== "processing") return;
    const timer = setInterval(() => setProcessingIndex((i) => Math.min(i + 1, processingSteps.length - 1)), 700);
    return () => clearInterval(timer);
  }, [step, processingSteps.length]);

  const cardDigits = digitsOnly(card.number);
  const brand = detectBrand(cardDigits);
  const vaBank = VA_BANKS.find((b) => b.code === va.bank) ?? VA_BANKS[0];
  const vaNumber = useMemo(
    () => `${vaBank.prefix}${digitsOnly(reference).padEnd(11, "7").slice(0, 11)}`,
    [vaBank.prefix, reference]
  );
  const maskedPhone = transaction?.buyer_phone ? `•••• •••• ${transaction.buyer_phone.slice(-4)}` : "nomor terdaftar";

  function sendOtp() {
    const code = randomOtp();
    setSentOtp(code);
    setOtp("");
    setOtpSecondsLeft(120);
    setShowSms(true);
  }

  function validateDetails(): string | null {
    if (method === "card") {
      if (cardDigits.length < 12 || cardDigits.length > 19) return "Nomor kartu harus 12–19 digit.";
      if (card.holder.trim().length < 2) return "Nama pemegang kartu wajib diisi.";
      if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(card.expiry)) return "Masa berlaku harus berformat MM/YY (bulan 01–12).";
      if (!/^\d{3,4}$/.test(card.cvv)) return "CVV harus 3 atau 4 digit.";
    }
    if (method === "paypal") {
      if (!/^\S+@\S+\.\S+$/.test(paypal.email)) return "Masukkan email yang valid.";
      if (paypal.password.length < 1) return "Kata sandi wajib diisi.";
    }
    if (method === "virtual_account") {
      if (va.userId.trim().length < 3) return "User ID internet banking minimal 3 karakter.";
      if (!/^\d{6}$/.test(va.pin)) return "PIN harus 6 digit angka.";
    }
    return null;
  }

  function handleDetailsSubmit(e: FormEvent) {
    e.preventDefault();
    const message = validateDetails();
    setError(message);
    if (message) return;
    sendOtp();
    setStep("otp");
  }

  async function handleOtpSubmit(e: FormEvent) {
    e.preventDefault();
    if (!/^\d{6}$/.test(otp)) {
      setError("Kode OTP harus 6 digit angka.");
      return;
    }
    setError(null);
    setShowSms(false);
    setProcessingIndex(0);
    setStep("processing");

    const payload: Record<string, unknown> = { method, otp };
    if (method === "card") {
      Object.assign(payload, { card_number: cardDigits, card_holder: card.holder, card_expiry: card.expiry, card_cvv: card.cvv });
    } else if (method === "paypal") {
      Object.assign(payload, { paypal_email: paypal.email, paypal_password: paypal.password });
    } else {
      Object.assign(payload, { va_bank: va.bank, va_user_id: va.userId, va_pin: va.pin });
    }

    try {
      // Jeda minimal supaya terasa seperti otorisasi bank sungguhan.
      if (installmentInfo) {
        const [{ data }] = await Promise.all([
          apiFetch<{ data: LoanInstallment }>(`/api/buyer/installments/${installmentInfo.installment.id}/pay`, { method: "POST", body: payload }),
          sleep(2800),
        ]);
        setInstallmentInfo({ ...installmentInfo, installment: data });
      } else {
        const [{ data }] = await Promise.all([
          apiFetch<{ data: Transaction }>(`/api/payments/mock/${reference}/pay`, { method: "POST", body: payload }),
          sleep(2800),
        ]);
        setTransaction(data);
      }
      setStep("success");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Transaksi ditolak oleh sistem bank simulasi.");
      setStep("otp");
    }
  }

  if (loadError) {
    return (
      <PortalShell>
        <div className="mx-auto max-w-md rounded-[14px] border border-slate-200 bg-white p-8 text-center shadow-sm">
          <p className="text-base font-semibold text-slate-900">
            {loadError.status === 401 ? "Sesi Anda berakhir" : "Tagihan tidak ditemukan"}
          </p>
          <p className="mt-2 text-sm text-slate-500">{loadError.message}</p>
          <Link href="/login" className="mt-6 inline-block rounded-[10px] bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
            Masuk ke AuraMotors
          </Link>
        </div>
      </PortalShell>
    );
  }

  if (!transaction || (installmentId && !installmentInfo)) {
    return (
      <PortalShell>
        <div className="flex items-center justify-center gap-3 py-24 text-sm text-slate-500">
          <Spinner /> Memuat tagihan…
        </div>
      </PortalShell>
    );
  }

  const breakdown = transaction.price_breakdown;
  const paidInstallment = installmentInfo?.installment.status === "paid";
  const amount = installmentInfo
    ? Number(paidInstallment ? installmentInfo.installment.paid_amount : installmentInfo.installment.amount_due)
    : Number(transaction.amount);
  const isClosed = installmentInfo
    ? !paidInstallment && (installmentInfo.loan.status !== "approved" || !installmentInfo.is_next_payable)
    : transaction.payment_status !== "pending" && transaction.payment_status !== "paid";
  const summaryLines = installmentInfo
    ? [
        { label: `Angsuran ke-${installmentInfo.installment.sequence} — pokok`, amount: Number(installmentInfo.installment.principal_amount) },
        { label: "Bunga", amount: Number(installmentInfo.installment.interest_amount) },
        ...(installmentInfo.installment.late_fee > 0
          ? [{ label: `Denda ${installmentInfo.installment.days_late} hari`, amount: installmentInfo.installment.late_fee }]
          : []),
      ]
    : breakdown?.due_now.lines ?? [];

  return (
    <PortalShell expiresAt={step !== "success" && !installmentInfo ? transaction.expires_at : null}>
      <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
        <div className="rounded-[14px] border border-slate-200 bg-white shadow-sm">
          {step !== "success" && step !== "processing" && !isClosed && (
            <StepIndicator step={step} />
          )}

          <div className="p-6 sm:p-8">
            {isClosed && (
              <div className="text-center">
                <p className="text-lg font-semibold text-slate-900">Tagihan tidak dapat dibayar</p>
                <p className="mt-2 text-sm text-slate-500">
                  {installmentInfo
                    ? installmentInfo.loan.status !== "approved"
                      ? "Kredit belum disetujui atau sudah lunas."
                      : "Angsuran harus dibayar berurutan — lunasi angsuran sebelumnya terlebih dahulu."
                    : `Status tagihan: ${transaction.payment_status}. Buat pesanan baru dari halaman kendaraan.`}
                </p>
                <Link href="/buyer/transactions" className="mt-6 inline-block text-sm font-semibold text-blue-700 hover:underline">
                  Kembali ke AuraMotors
                </Link>
              </div>
            )}

            {!isClosed && step === "method" && (
              <div>
                <h1 style={SANS} className="text-xl font-semibold text-slate-900">Pilih metode pembayaran</h1>
                <p className="mt-1 text-sm text-slate-500">
                  {installmentInfo
                    ? `Pembayaran angsuran diteruskan ke ${installmentInfo.loan.lender_name}.`
                    : "Dana Anda ditahan di rekening escrow sampai kendaraan diterima."}
                </p>
                <div className="mt-6 space-y-3">
                  <MethodOption
                    active={method === "card"}
                    onClick={() => setMethod("card")}
                    title="Kartu Kredit / Debit"
                    subtitle="Visa, Mastercard, JCB, American Express — dengan verifikasi 3-D Secure"
                    badges={["VISA", "MASTERCARD", "JCB", "AMEX"]}
                  />
                  <MethodOption
                    active={method === "paypal"}
                    onClick={() => setMethod("paypal")}
                    title="PayPal"
                    subtitle="Masuk dengan akun PayPal Anda lalu konfirmasi dengan OTP"
                    badges={["PAYPAL"]}
                  />
                  <MethodOption
                    active={method === "virtual_account"}
                    onClick={() => setMethod("virtual_account")}
                    title="Virtual Account / Internet Banking"
                    subtitle="Transfer dari BCA, Mandiri, BNI, BRI, Permata, atau Bank Aura Nusantara"
                    badges={["VA"]}
                  />
                </div>
                <button
                  type="button"
                  onClick={() => {
                    setError(null);
                    setStep("details");
                  }}
                  className="mt-8 w-full rounded-[10px] bg-blue-700 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-800"
                >
                  Lanjutkan
                </button>
              </div>
            )}

            {!isClosed && step === "details" && (
              <form onSubmit={handleDetailsSubmit} noValidate>
                <BackButton onClick={() => setStep("method")} />

                {method === "card" && (
                  <>
                    <h1 style={SANS} className="text-xl font-semibold text-slate-900">Detail kartu</h1>
                    <CardPreview number={card.number} holder={card.holder} expiry={card.expiry} brand={brand} />
                    <div className="mt-6 grid gap-4 sm:grid-cols-2">
                      <Field label="Nomor kartu" className="sm:col-span-2">
                        <input
                          inputMode="numeric"
                          autoComplete="off"
                          placeholder="1234 5678 9012 3456"
                          value={card.number}
                          onChange={(e) => setCard((c) => ({ ...c, number: digitsOnly(e.target.value).slice(0, 19).replace(/(\d{4})(?=\d)/g, "$1 ") }))}
                          className={INPUT}
                        />
                      </Field>
                      <Field label="Nama pada kartu" className="sm:col-span-2">
                        <input
                          autoComplete="off"
                          placeholder="NAMA SESUAI KARTU"
                          value={card.holder}
                          onChange={(e) => setCard((c) => ({ ...c, holder: e.target.value.toUpperCase().slice(0, 100) }))}
                          className={INPUT}
                        />
                      </Field>
                      <Field label="Berlaku hingga">
                        <input
                          inputMode="numeric"
                          autoComplete="off"
                          placeholder="MM/YY"
                          value={card.expiry}
                          onChange={(e) => {
                            const d = digitsOnly(e.target.value).slice(0, 4);
                            setCard((c) => ({ ...c, expiry: d.length > 2 ? `${d.slice(0, 2)}/${d.slice(2)}` : d }));
                          }}
                          className={INPUT}
                        />
                      </Field>
                      <Field label="CVV / CVC" hint="3 digit di belakang kartu">
                        <input
                          type="password"
                          inputMode="numeric"
                          autoComplete="off"
                          placeholder="•••"
                          value={card.cvv}
                          onChange={(e) => setCard((c) => ({ ...c, cvv: digitsOnly(e.target.value).slice(0, 4) }))}
                          className={INPUT}
                        />
                      </Field>
                    </div>
                  </>
                )}

                {method === "paypal" && (
                  <>
                    <h1 style={SANS} className="text-xl font-semibold text-slate-900">Masuk ke PayPal</h1>
                    <p className="mt-1 text-sm text-slate-500">Simulasi login — email & kata sandi apa pun diterima.</p>
                    <div className="mt-6 space-y-4">
                      <Field label="Email">
                        <input
                          type="email"
                          autoComplete="off"
                          placeholder="nama@email.com"
                          value={paypal.email}
                          onChange={(e) => setPaypal((p) => ({ ...p, email: e.target.value }))}
                          className={INPUT}
                        />
                      </Field>
                      <Field label="Kata sandi">
                        <input
                          type="password"
                          autoComplete="off"
                          placeholder="••••••••"
                          value={paypal.password}
                          onChange={(e) => setPaypal((p) => ({ ...p, password: e.target.value }))}
                          className={INPUT}
                        />
                      </Field>
                    </div>
                  </>
                )}

                {method === "virtual_account" && (
                  <>
                    <h1 style={SANS} className="text-xl font-semibold text-slate-900">Virtual Account</h1>
                    <div className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                      {VA_BANKS.map((bank) => (
                        <button
                          key={bank.code}
                          type="button"
                          onClick={() => setVa((v) => ({ ...v, bank: bank.code }))}
                          className={`rounded-[10px] border px-3 py-2.5 text-sm font-medium transition-colors ${
                            va.bank === bank.code ? "border-blue-600 bg-blue-50 text-blue-800" : "border-slate-200 text-slate-700 hover:border-slate-300"
                          }`}
                        >
                          {bank.name}
                        </button>
                      ))}
                    </div>
                    <div className="mt-4 rounded-[10px] bg-slate-50 px-4 py-3">
                      <p className="text-xs text-slate-500">Nomor Virtual Account {vaBank.name}</p>
                      <p className="mt-0.5 font-mono text-lg font-semibold tracking-wider text-slate-900">{vaNumber}</p>
                    </div>
                    <p className="mt-4 text-sm text-slate-500">Masuk internet banking simulasi untuk membayar VA di atas:</p>
                    <div className="mt-3 grid gap-4 sm:grid-cols-2">
                      <Field label="User ID">
                        <input
                          autoComplete="off"
                          placeholder="user id"
                          value={va.userId}
                          onChange={(e) => setVa((v) => ({ ...v, userId: e.target.value.slice(0, 100) }))}
                          className={INPUT}
                        />
                      </Field>
                      <Field label="PIN (6 digit)">
                        <input
                          type="password"
                          inputMode="numeric"
                          autoComplete="off"
                          placeholder="••••••"
                          value={va.pin}
                          onChange={(e) => setVa((v) => ({ ...v, pin: digitsOnly(e.target.value).slice(0, 6) }))}
                          className={INPUT}
                        />
                      </Field>
                    </div>
                  </>
                )}

                {error && <ErrorText>{error}</ErrorText>}

                <button type="submit" className="mt-8 w-full rounded-[10px] bg-blue-700 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-800">
                  Bayar {formatRupiah(amount)}
                </button>
                <p className="mt-3 text-center text-xs text-slate-400">Anda akan diminta memasukkan kode OTP pada langkah berikutnya.</p>
              </form>
            )}

            {!isClosed && step === "otp" && (
              <form onSubmit={handleOtpSubmit} noValidate>
                <BackButton onClick={() => setStep("details")} />
                <div className="flex items-center gap-3">
                  <span className="flex h-11 w-11 items-center justify-center rounded-full bg-blue-50 text-blue-700">
                    <LockIcon />
                  </span>
                  <div>
                    <h1 style={SANS} className="text-xl font-semibold text-slate-900">
                      {method === "card" ? "Verifikasi 3-D Secure" : "Verifikasi OTP"}
                    </h1>
                    <p className="text-sm text-slate-500">Kode 6 digit dikirim via SMS ke {maskedPhone}</p>
                  </div>
                </div>

                <dl className="mt-6 grid grid-cols-2 gap-3 rounded-[10px] bg-slate-50 p-4 text-sm">
                  <dt className="text-slate-500">Merchant</dt>
                  <dd className="text-right font-medium text-slate-900">AURAMOTORS ESCROW</dd>
                  <dt className="text-slate-500">Jumlah</dt>
                  <dd className="text-right font-semibold text-slate-900">{formatRupiah(amount)}</dd>
                  <dt className="text-slate-500">{method === "card" ? "Kartu" : method === "paypal" ? "Akun" : "Bank"}</dt>
                  <dd className="text-right font-medium text-slate-900">
                    {method === "card" && `${brand} •••• ${cardDigits.slice(-4)}`}
                    {method === "paypal" && paypal.email}
                    {method === "virtual_account" && `${vaBank.name} · VA ${vaNumber}`}
                  </dd>
                </dl>

                <label htmlFor="otp" className="mt-6 block text-sm font-medium text-slate-700">Kode OTP</label>
                <input
                  id="otp"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  autoFocus
                  placeholder="––––––"
                  value={otp}
                  onChange={(e) => setOtp(digitsOnly(e.target.value).slice(0, 6))}
                  className="mt-1.5 w-full rounded-[10px] border border-slate-300 bg-white px-4 py-3 text-center font-mono text-2xl tracking-[0.6em] text-slate-900 outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
                />
                <div className="mt-2 flex items-center justify-between text-xs">
                  <span className="text-slate-500">
                    {otpSecondsLeft > 0 ? `Kirim ulang dalam ${Math.floor(otpSecondsLeft / 60)}:${String(otpSecondsLeft % 60).padStart(2, "0")}` : "Tidak menerima kode?"}
                  </span>
                  <button type="button" onClick={sendOtp} disabled={otpSecondsLeft > 0} className="font-semibold text-blue-700 hover:underline disabled:cursor-not-allowed disabled:text-slate-300 disabled:no-underline">
                    Kirim ulang kode
                  </button>
                </div>

                {error && <ErrorText>{error}</ErrorText>}

                <button type="submit" className="mt-8 w-full rounded-[10px] bg-blue-700 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-800">
                  Konfirmasi Pembayaran
                </button>
                <p className="mt-3 text-center text-xs text-slate-400">Jangan berikan kode OTP kepada siapa pun, termasuk petugas bank.</p>
              </form>
            )}

            {step === "processing" && (
              <div className="flex flex-col items-center py-12 text-center">
                <Spinner large />
                <p className="mt-6 text-base font-semibold text-slate-900">Memproses pembayaran</p>
                <p className="mt-1 text-sm text-slate-500">{processingSteps[processingIndex]}</p>
                <p className="mt-6 text-xs text-slate-400">Jangan tutup atau muat ulang halaman ini.</p>
              </div>
            )}

            {step === "success" &&
              (installmentInfo ? (
                <InstallmentSuccessView transaction={transaction} info={installmentInfo} />
              ) : (
                <SuccessView transaction={transaction} />
              ))}
          </div>
        </div>

        <aside className="lg:sticky lg:top-6 lg:self-start">
          <div className="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">Ringkasan tagihan</p>
            <p className="mt-3 text-sm font-medium text-slate-900">
              {installmentInfo ? installmentInfo.loan.lender_name : "PT AuraMotors Escrow Indonesia"}
            </p>
            <p className="text-xs text-slate-500">
              {installmentInfo
                ? `Kontrak ${installmentInfo.loan.contract_number} · jatuh tempo ${new Date(installmentInfo.installment.due_date).toLocaleDateString("id-ID")}`
                : `Invoice ${transaction.invoice_number}`}
            </p>
            <div className="mt-4 rounded-[10px] bg-slate-50 p-3">
              <p className="text-sm font-medium text-slate-900">
                {transaction.vehicle.brand} {transaction.vehicle.model} {transaction.vehicle.year}
              </p>
              <p className="text-xs text-slate-500">Penjual: {transaction.seller?.name ?? "-"}</p>
            </div>
            <dl className="mt-4 space-y-2 text-sm">
              {summaryLines.map((line) => (
                <div key={line.label} className="flex justify-between gap-3">
                  <dt className="text-slate-500">{line.label}</dt>
                  <dd className="whitespace-nowrap text-slate-900">{formatRupiah(line.amount)}</dd>
                </div>
              ))}
              <div className="flex justify-between gap-3 border-t border-slate-200 pt-3 text-base font-semibold">
                <dt className="text-slate-900">Total</dt>
                <dd className="text-blue-800">{formatRupiah(amount)}</dd>
              </div>
            </dl>
            <p className="mt-4 text-xs leading-relaxed text-slate-400">
              {installmentInfo
                ? "Angsuran dibayar langsung ke leasing. Keterlambatan dikenai denda harian sesuai perjanjian pembiayaan."
                : `Dana ditahan di rekening escrow ${transaction.bank_transfer_bank} ${transaction.bank_transfer_account_number} dan baru diteruskan ke penjual setelah serah terima terkonfirmasi.`}
            </p>
          </div>
        </aside>
      </div>

      {showSms && step === "otp" && (
        <div role="status" className="fixed right-4 top-4 z-50 w-[min(92vw,360px)] rounded-[16px] bg-slate-900/95 p-4 text-white shadow-2xl">
          <div className="flex items-center justify-between text-xs text-slate-300">
            <span>💬 SMS · AURABANK</span>
            <button type="button" onClick={() => setShowSms(false)} aria-label="Tutup notifikasi" className="text-slate-400 hover:text-white">✕</button>
          </div>
          <p className="mt-2 text-sm leading-relaxed">
            JANGAN BERIKAN KODE INI KE SIAPA PUN. Kode OTP Anda <strong className="font-mono tracking-wider">{sentOtp}</strong> untuk
            transaksi {formatRupiah(amount)} di AURAMOTORS ESCROW.
          </p>
          <button type="button" onClick={() => setOtp(sentOtp)} className="mt-3 text-xs font-semibold text-sky-300 hover:underline">
            Isi otomatis
          </button>
          <p className="mt-1 text-[10px] text-slate-500">Simulasi — kode 6 digit apa pun juga diterima.</p>
        </div>
      )}
    </PortalShell>
  );
}

/** globals.css memberi h1 font serif AuraMotors (tanpa layer) — portal bank pakai sans. */
const SANS = { fontFamily: "var(--font-body)" };

const INPUT =
  "mt-1.5 w-full rounded-[10px] border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100";

function PortalShell({ children, expiresAt }: { children: ReactNode; expiresAt?: string | null }) {
  return (
    <div className="min-h-screen bg-slate-50 font-sans text-slate-900" style={{ colorScheme: "light" }}>
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4">
          <div className="flex items-center gap-2.5">
            <span className="flex h-9 w-9 items-center justify-center rounded-[10px] bg-blue-700 text-white">
              <LockIcon />
            </span>
            <div>
              <p className="text-sm font-bold tracking-tight text-slate-900">AuraPay Secure Gateway</p>
              <p className="text-[11px] text-slate-500">Gerbang pembayaran simulasi · Bank Aura Nusantara</p>
            </div>
          </div>
          <div className="text-right text-[11px] text-slate-500">
            <p className="font-medium text-emerald-700">🔒 Koneksi terenkripsi (simulasi)</p>
            {expiresAt && <p>Bayar sebelum {new Date(expiresAt).toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short" })}</p>}
          </div>
        </div>
      </header>
      <div className="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-xs text-amber-800">
        <strong>MODE SIMULASI</strong> — proyek tugas kuliah offline. Tidak ada uang sungguhan; isi angka kartu, CVV, dan OTP apa saja.
      </div>
      <div className="mx-auto max-w-5xl px-4 py-8">{children}</div>
      <footer className="pb-8 text-center text-[11px] text-slate-400">
        Dilindungi 3-D Secure · PCI-DSS (simulasi) · Nomor kartu & CVV tidak disimpan
      </footer>
    </div>
  );
}

function StepIndicator({ step }: { step: Step }) {
  const steps: { key: Step; label: string }[] = [
    { key: "method", label: "Metode" },
    { key: "details", label: "Detail" },
    { key: "otp", label: "Verifikasi" },
  ];
  const activeIndex = steps.findIndex((s) => s.key === step);
  return (
    <ol className="flex border-b border-slate-200 px-6 py-3 text-xs sm:px-8">
      {steps.map((s, i) => (
        <li key={s.key} className={`flex flex-1 items-center gap-2 ${i <= activeIndex ? "text-blue-700" : "text-slate-400"}`}>
          <span className={`flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold ${i <= activeIndex ? "bg-blue-700 text-white" : "bg-slate-200 text-slate-500"}`}>
            {i < activeIndex ? "✓" : i + 1}
          </span>
          <span className="font-medium">{s.label}</span>
        </li>
      ))}
    </ol>
  );
}

function MethodOption({ active, onClick, title, subtitle, badges }: {
  active: boolean;
  onClick: () => void;
  title: string;
  subtitle: string;
  badges: string[];
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={`flex w-full items-center gap-4 rounded-[12px] border p-4 text-left transition-colors ${
        active ? "border-blue-600 bg-blue-50/60 ring-2 ring-blue-100" : "border-slate-200 hover:border-slate-300"
      }`}
    >
      <span className={`flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border-2 ${active ? "border-blue-600" : "border-slate-300"}`}>
        {active && <span className="h-2.5 w-2.5 rounded-full bg-blue-600" />}
      </span>
      <span className="min-w-0 flex-1">
        <span className="block text-sm font-semibold text-slate-900">{title}</span>
        <span className="mt-0.5 block text-xs text-slate-500">{subtitle}</span>
      </span>
      <span className="hidden flex-shrink-0 gap-1 sm:flex">
        {badges.map((b) => (
          <span key={b} className="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-slate-600">{b}</span>
        ))}
      </span>
    </button>
  );
}

function CardPreview({ number, holder, expiry, brand }: { number: string; holder: string; expiry: string; brand: string }) {
  return (
    <div className="mt-4 aspect-[1.586] w-full max-w-sm rounded-[16px] bg-gradient-to-br from-slate-800 via-slate-900 to-blue-950 p-5 text-white shadow-lg">
      <div className="flex h-full flex-col justify-between">
        <div className="flex items-start justify-between">
          <span className="h-8 w-11 rounded-[6px] bg-gradient-to-br from-amber-200 to-amber-400" aria-hidden />
          <span className="text-sm font-bold italic tracking-wider">{brand || "BANK"}</span>
        </div>
        <p className="font-mono text-lg tracking-[0.15em] sm:text-xl">{number || "•••• •••• •••• ••••"}</p>
        <div className="flex items-end justify-between text-[10px] uppercase tracking-wider text-slate-300">
          <div className="min-w-0">
            <p>Pemegang kartu</p>
            <p className="truncate text-sm normal-case tracking-normal text-white">{holder || "NAMA ANDA"}</p>
          </div>
          <div className="text-right">
            <p>Berlaku</p>
            <p className="text-sm tracking-normal text-white">{expiry || "MM/YY"}</p>
          </div>
        </div>
      </div>
    </div>
  );
}

function SuccessView({ transaction }: { transaction: Transaction }) {
  const docs = transaction.documents ?? [];
  const advice = docs.find((d) => d.type === "bank_payment_advice");
  const highlighted = docs.filter((d) => ["escrow_fund_confirmation", "insurance_policy", "deposit_receipt", "tax_invoice"].includes(d.type));
  const methodLabel =
    transaction.payment_method === "card" ? "Kartu kredit/debit"
      : transaction.payment_method === "paypal" ? "PayPal"
        : transaction.payment_method === "virtual_account" ? "Virtual Account"
          : "Simulasi";

  return (
    <div className="text-center">
      <span className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-3xl text-emerald-600">✓</span>
      <h1 style={SANS} className="mt-4 text-2xl font-semibold text-slate-900">Pembayaran Berhasil</h1>
      <p className="mt-1 text-sm text-slate-500">Dana telah masuk ke rekening escrow dan menunggu serah terima kendaraan.</p>
      <p className="mt-4 text-3xl font-bold text-slate-900">{formatRupiah(transaction.amount)}</p>

      <dl className="mx-auto mt-6 max-w-md divide-y divide-slate-100 rounded-[12px] border border-slate-200 text-left text-sm">
        {[
          ["Kode otorisasi", transaction.payment_approval_code ?? "-"],
          ["Metode", `${methodLabel}${transaction.payment_channel ? ` · ${transaction.payment_channel}` : ""}`],
          ["Sumber dana", transaction.payment_account_masked ?? "-"],
          ["Waktu", transaction.paid_at ? new Date(transaction.paid_at).toLocaleString("id-ID") : "-"],
          ["Invoice", transaction.invoice_number ?? "-"],
        ].map(([label, value]) => (
          <div key={label} className="flex justify-between gap-4 px-4 py-2.5">
            <dt className="text-slate-500">{label}</dt>
            <dd className="text-right font-medium text-slate-900">{value}</dd>
          </div>
        ))}
      </dl>

      <div className="mx-auto mt-6 flex max-w-md flex-col gap-2 sm:flex-row">
        {advice && (
          <a href={advice.download_url} target="_blank" rel="noreferrer" className="flex-1 rounded-[10px] border border-blue-700 px-4 py-2.5 text-sm font-semibold text-blue-700 hover:bg-blue-50">
            Unduh Bukti Pembayaran (PDF)
          </a>
        )}
        <Link href={`/buyer/transactions/${transaction.id}`} className="flex-1 rounded-[10px] bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
          Kembali ke AuraMotors
        </Link>
      </div>

      {highlighted.length > 0 && (
        <div className="mx-auto mt-8 max-w-md text-left">
          <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">Dokumen yang diterbitkan</p>
          <ul className="mt-2 space-y-1.5">
            {highlighted.map((doc) => (
              <li key={doc.id}>
                <a href={doc.download_url} target="_blank" rel="noreferrer" className="text-sm text-blue-700 hover:underline">
                  📄 {doc.label}
                </a>
              </li>
            ))}
          </ul>
          <p className="mt-3 text-xs text-slate-500">
            Surat yang perlu ditandatangani (BAST, Surat Kuasa Balik Nama, dll.) tersedia di halaman detail transaksi.
          </p>
        </div>
      )}
    </div>
  );
}

function InstallmentSuccessView({ transaction, info }: { transaction: Transaction; info: InstallmentInfo }) {
  const { installment, loan } = info;
  const methodLabel =
    installment.payment_method === "card" ? "Kartu kredit/debit"
      : installment.payment_method === "paypal" ? "PayPal"
        : installment.payment_method === "virtual_account" ? "Virtual Account"
          : installment.payment_method === "manual" ? "Setoran manual"
            : "Simulasi";

  return (
    <div className="text-center">
      <span className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-3xl text-emerald-600">✓</span>
      <h1 style={SANS} className="mt-4 text-2xl font-semibold text-slate-900">Angsuran Berhasil Dibayar</h1>
      <p className="mt-1 text-sm text-slate-500">
        Angsuran ke-{installment.sequence} dari {loan.tenor_months} untuk kontrak {loan.contract_number} sudah tercatat di {loan.lender_name}.
      </p>
      <p className="mt-4 text-3xl font-bold text-slate-900">{formatRupiah(installment.paid_amount ?? installment.amount)}</p>

      <dl className="mx-auto mt-6 max-w-md divide-y divide-slate-100 rounded-[12px] border border-slate-200 text-left text-sm">
        {[
          ["Kode otorisasi", installment.payment_approval_code ?? "-"],
          ["Metode", `${methodLabel}${installment.payment_channel ? ` · ${installment.payment_channel}` : ""}`],
          ["Sumber dana", installment.payment_account_masked ?? "-"],
          ["Denda keterlambatan", installment.late_fee > 0 ? formatRupiah(installment.late_fee) : "Tidak ada"],
          ["Waktu", installment.paid_at ? new Date(installment.paid_at).toLocaleString("id-ID") : "-"],
        ].map(([label, value]) => (
          <div key={label} className="flex justify-between gap-4 px-4 py-2.5">
            <dt className="text-slate-500">{label}</dt>
            <dd className="text-right font-medium text-slate-900">{value}</dd>
          </div>
        ))}
      </dl>

      <div className="mx-auto mt-6 max-w-md">
        <Link href={`/buyer/transactions/${transaction.id}`} className="block rounded-[10px] bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
          Kembali ke jadwal angsuran
        </Link>
      </div>
    </div>
  );
}

function Field({ label, hint, className = "", children }: { label: string; hint?: string; className?: string; children: ReactNode }) {
  return (
    <label className={`block ${className}`}>
      <span className="text-sm font-medium text-slate-700">{label}</span>
      {hint && <span className="ml-1.5 text-xs text-slate-400">{hint}</span>}
      {children}
    </label>
  );
}

function BackButton({ onClick }: { onClick: () => void }) {
  return (
    <button type="button" onClick={onClick} className="mb-4 text-sm text-slate-500 hover:text-slate-800">
      ← Kembali
    </button>
  );
}

function ErrorText({ children }: { children: ReactNode }) {
  return <p role="alert" className="mt-4 rounded-[10px] border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{children}</p>;
}

function Spinner({ large = false }: { large?: boolean }) {
  return (
    <span
      aria-hidden
      className={`inline-block animate-spin rounded-full border-blue-700 border-t-transparent ${large ? "h-12 w-12 border-4" : "h-4 w-4 border-2"}`}
    />
  );
}

function LockIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} className="h-5 w-5" aria-hidden>
      <rect x="5" y="11" width="14" height="10" rx="2" />
      <path d="M8 11V7a4 4 0 0 1 8 0v4" />
    </svg>
  );
}
