"use client";

import { useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { useAuth } from "@/contexts/auth-context";
import { ApiError } from "@/lib/api";

export default function RegisterPage() {
  const { register } = useAuth();
  const router = useRouter();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [role, setRole] = useState<"buyer" | "seller">("buyer");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setErrors({});
    setFormError(null);

    if (password !== passwordConfirmation) {
      setErrors({ password_confirmation: ["Konfirmasi password tidak cocok."] });
      return;
    }

    setIsSubmitting(true);
    try {
      await register({ name, email, password, password_confirmation: passwordConfirmation, role });
      router.push(role === "seller" ? "/seller/kyc" : "/");
      router.refresh();
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        setErrors(error.errors);
      } else if (error instanceof ApiError) {
        setFormError(error.message);
      } else {
        setFormError("Terjadi kesalahan tak terduga.");
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="relative flex min-h-[calc(100dvh-3.25rem)] items-center justify-center px-4 py-12">
      {/* Ambient gold glow — dekoratif */}
      <div
        aria-hidden
        className="pointer-events-none fixed left-1/2 top-0 -translate-x-1/2"
        style={{
          width: "600px",
          height: "260px",
          background: "radial-gradient(ellipse at 50% 0%, color-mix(in srgb, #f2ca50 8%, transparent) 0%, transparent 70%)",
        }}
      />

      <div className="animate-fade-up relative w-full max-w-md">
        {/* Wordmark */}
        <div className="mb-8 text-center">
          <span className="font-display text-2xl tracking-widest text-primary">AuraMotors</span>
          <p className="mt-1.5 text-xs uppercase tracking-[0.2em] text-on-surface-muted">
            Marketplace Kendaraan Premium
          </p>
        </div>

        <div className="rounded-lg border border-border bg-surface-container p-8">
          <h1 className="font-display text-xl text-on-surface">Daftar Akun</h1>
          <p className="mt-1 text-sm text-on-surface-muted">Mulai perjalanan Anda bersama AuraMotors.</p>

          <form onSubmit={handleSubmit} className="mt-7 space-y-5">
            <div>
              <label className="block text-sm font-medium text-on-surface" htmlFor="reg-name">
                Nama Lengkap
              </label>
              <input
                id="reg-name"
                required
                autoComplete="name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="input-field mt-1.5"
              />
              {errors.name && (
                <p className="mt-1 text-xs text-error animate-fade-in">{errors.name[0]}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-on-surface" htmlFor="reg-email">
                Email
              </label>
              <input
                id="reg-email"
                required
                type="email"
                autoComplete="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="input-field mt-1.5"
              />
              {errors.email && (
                <p className="mt-1 text-xs text-error animate-fade-in">{errors.email[0]}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-on-surface" htmlFor="reg-password">
                Password
              </label>
              <input
                id="reg-password"
                required
                type="password"
                minLength={8}
                autoComplete="new-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="input-field mt-1.5"
              />
              {errors.password && (
                <p className="mt-1 text-xs text-error animate-fade-in">{errors.password[0]}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-on-surface" htmlFor="reg-confirm">
                Konfirmasi Password
              </label>
              <input
                id="reg-confirm"
                required
                type="password"
                minLength={8}
                autoComplete="new-password"
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                className="input-field mt-1.5"
              />
              {errors.password_confirmation && (
                <p className="mt-1 text-xs text-error animate-fade-in">
                  {errors.password_confirmation[0]}
                </p>
              )}
            </div>

            <div>
              <span className="block text-sm font-medium text-on-surface">Daftar sebagai</span>
              <div className="mt-2 flex gap-3">
                {(["buyer", "seller"] as const).map((r) => (
                  <label
                    key={r}
                    className={`flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-md border px-4 py-2.5 text-sm
                                transition-all duration-200
                                ${role === r
                                  ? "border-primary bg-primary/10 text-primary"
                                  : "border-border text-on-surface-muted hover:border-border/80 hover:text-on-surface"
                                }`}
                  >
                    <input
                      type="radio"
                      className="sr-only"
                      checked={role === r}
                      onChange={() => setRole(r)}
                    />
                    {r === "buyer" ? "Pembeli" : "Penjual"}
                  </label>
                ))}
              </div>
              {errors.role && (
                <p className="mt-1 text-xs text-error animate-fade-in">{errors.role[0]}</p>
              )}
            </div>

            {formError && (
              <p className="animate-fade-in text-sm text-error" role="alert">
                {formError}
              </p>
            )}

            <button
              type="submit"
              disabled={isSubmitting}
              className="btn-gold mt-2 w-full rounded-md px-4 py-2.5 text-sm"
            >
              {isSubmitting ? "Memproses..." : "Buat Akun"}
            </button>
          </form>

          <div className="mt-6 border-t border-border pt-5">
            <p className="text-center text-sm text-on-surface-muted">
              Sudah punya akun?{" "}
              <Link
                href="/login"
                className="font-medium text-on-surface transition-colors duration-150 hover:text-primary"
              >
                Masuk
              </Link>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
