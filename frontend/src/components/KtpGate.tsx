"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useAuth } from "@/contexts/auth-context";

const GATED_PATH_PREFIXES = ["/", "/kendaraan"];

function isGatedPath(pathname: string): boolean {
  if (pathname === "/") return true;
  return GATED_PATH_PREFIXES.some((prefix) => prefix !== "/" && pathname.startsWith(prefix));
}

export default function KtpGate({ children }: { children: React.ReactNode }) {
  const { user, isLoading } = useAuth();
  const pathname = usePathname();

  const needsGate =
    !isLoading &&
    user !== null &&
    (user.role === "buyer" || user.role === "seller") &&
    !user.has_completed_ktp &&
    isGatedPath(pathname);

  if (!needsGate) {
    return <>{children}</>;
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-16 animate-fade-up">
      <div className="rounded-lg border border-warning/40 bg-warning/5 p-6">
        <div className="flex items-start gap-3">
          <div
            aria-hidden
            className="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-warning/15 text-warning"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-5 w-5">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            </svg>
          </div>
          <div className="flex-1">
            <h2 className="font-display text-lg text-on-surface">
              Lengkapi data KTP terlebih dahulu
            </h2>
            <p className="mt-1 text-sm text-on-surface-muted">
              Sesuai regulasi jual-beli kendaraan, kamu wajib mengisi <strong className="text-on-surface">NIK</strong> dan <strong className="text-on-surface">Nama sesuai KTP</strong> sebelum dapat melihat katalog atau melakukan transaksi.
            </p>
            <ul className="mt-3 space-y-1.5 text-sm text-on-surface-muted">
              <li className="flex items-start gap-2">
                <span aria-hidden className="mt-1 h-1 w-1 flex-shrink-0 rounded-full bg-warning" />
                Data hanya digunakan untuk verifikasi identitas & pembuatan nota resmi.
              </li>
              <li className="flex items-start gap-2">
                <span aria-hidden className="mt-1 h-1 w-1 flex-shrink-0 rounded-full bg-warning" />
                Tidak dibagikan ke pihak ketiga di luar transaksi kamu.
              </li>
              <li className="flex items-start gap-2">
                <span aria-hidden className="mt-1 h-1 w-1 flex-shrink-0 rounded-full bg-warning" />
                Cukup diisi satu kali &mdash; berlaku untuk semua transaksi selanjutnya.
              </li>
            </ul>

            <div className="mt-5 flex flex-wrap gap-2">
              <Link
                href="/lengkapi-data"
                className="btn-gold rounded-md px-4 py-2 text-sm font-medium"
              >
                Lengkapi Data KTP
              </Link>
              <Link
                href="/profil"
                className="rounded-md border border-border px-4 py-2 text-sm font-medium text-on-surface-muted transition-colors hover:bg-surface-container-high hover:text-on-surface"
              >
                Ke Profil
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
