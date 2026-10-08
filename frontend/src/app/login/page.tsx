"use client";

import { useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { useAuth } from "@/contexts/auth-context";
import { ApiError } from "@/lib/api";

export default function LoginPage() {
  const { login } = useAuth();
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setIsSubmitting(true);

    try {
      await login(email, password);
      router.push("/");
      router.refresh();
    } catch (error) {
      setFormError(error instanceof ApiError ? error.message : "Terjadi kesalahan tak terduga.");
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="relative flex min-h-[calc(100dvh-3.25rem)] items-center justify-center px-4 py-12">
      {/* Ambient gold glow — dekoratif, tidak mengganggu konten */}
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
          <h1 className="font-display text-xl text-on-surface">Masuk</h1>
          <p className="mt-1 text-sm text-on-surface-muted">Selamat datang kembali.</p>

          <form onSubmit={handleSubmit} className="mt-7 space-y-5">
            <div>
              <label className="block text-sm font-medium text-on-surface" htmlFor="login-email">
                Email
              </label>
              <input
                id="login-email"
                required
                type="email"
                autoComplete="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="input-field mt-1.5"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-on-surface" htmlFor="login-password">
                Password
              </label>
              <input
                id="login-password"
                required
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="input-field mt-1.5"
              />
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
              {isSubmitting ? "Memproses..." : "Masuk"}
            </button>
          </form>

          <div className="mt-6 border-t border-border pt-5">
            <p className="text-center text-sm text-on-surface-muted">
              Belum punya akun?{" "}
              <Link
                href="/register"
                className="font-medium text-on-surface transition-colors duration-150 hover:text-primary"
              >
                Daftar sekarang
              </Link>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
