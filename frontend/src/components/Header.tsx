"use client";

import { useState, useEffect, useRef } from "react";
import Image from "next/image";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useAuth } from "@/contexts/auth-context";
import ConfirmModal from "@/components/ConfirmModal";
import NavbarActions from "@/components/NavbarActions";

const ROLE_LABEL: Record<string, string> = {
  admin: "Admin",
  seller: "Seller",
  buyer: "Buyer",
};

export default function Header() {
  const { user, isLoading, logout } = useAuth();
  const router = useRouter();
  const pathname = usePathname();
  const [menuOpen, setMenuOpen] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const [showLogoutConfirm, setShowLogoutConfirm] = useState(false);
  const profileRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setMenuOpen(false);
    setProfileOpen(false);
  }, [pathname]);

  useEffect(() => {
    if (!menuOpen && !profileOpen) return;
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") {
        setMenuOpen(false);
        setProfileOpen(false);
      }
    }
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [menuOpen, profileOpen]);

  useEffect(() => {
    if (!profileOpen) return;
    function onClick(e: MouseEvent) {
      if (profileRef.current && !profileRef.current.contains(e.target as Node)) {
        setProfileOpen(false);
      }
    }
    document.addEventListener("mousedown", onClick);
    return () => document.removeEventListener("mousedown", onClick);
  }, [profileOpen]);

  function handleLogout() {
    setMenuOpen(false);
    setShowLogoutConfirm(true);
  }

  async function confirmLogout() {
    setShowLogoutConfirm(false);
    await logout();
    router.push("/");
    router.refresh();
  }

  return (
    <>
    <header className="sticky top-0 z-40 border-b border-border/60 bg-surface/90 backdrop-blur-md">
      <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
        <Link
          href="/"
          className="font-display text-xl tracking-wide text-primary transition-[letter-spacing,opacity] duration-300 hover:tracking-widest hover:opacity-90"
        >
          AuraMotors
        </Link>

        {/* ── Desktop navigation (≥1024px) ── */}
        <nav className="hidden lg:flex font-label items-center gap-3 text-[13px] xl:gap-5 xl:text-sm" aria-label="Navigasi utama">
          {!isLoading && !user && (
            <>
              <Link href="/login" className="nav-link">Masuk</Link>
              <Link href="/register" className="btn-gold rounded-md px-3 py-1.5 text-sm font-medium">
                Daftar
              </Link>
            </>
          )}
          {!isLoading && user?.role === "seller" && (
            <>
              <Link href="/seller/kyc" className="nav-link">KYC</Link>
              <Link href="/seller/vehicles" className="nav-link">Listing Saya</Link>
              <Link href="/seller/transactions" className="nav-link">Transaksi Penjualan</Link>
            </>
          )}
          {!isLoading && user?.role === "buyer" && (
            <>
              <Link href="/buyer/kyc" className="nav-link">KYC</Link>
              <Link href="/buyer/transactions" className="nav-link">Transaksi Saya</Link>
            </>
          )}
          {!isLoading && user?.role === "admin" && (
            <>
              <Link href="/admin/kyc" className="nav-link">Review KYC Seller</Link>
              <Link href="/admin/buyer-kyc" className="nav-link">Review KYC Buyer</Link>
              <Link href="/admin/vehicles" className="nav-link">Review Listing</Link>
              <Link href="/admin/transactions" className="nav-link">Escrow & Transaksi</Link>
              <Link href="/admin/payouts" className="nav-link">Rekonsiliasi Payout</Link>
            </>
          )}
          {!isLoading && user && (
            <>
              {/* Separator tipis pemisah nav role vs user */}
              <span className="h-4 w-px bg-border/60" aria-hidden />
              <NavbarActions variant="desktop" />
              <span className="h-4 w-px bg-border/60" aria-hidden />

              {/* Profile dropdown trigger + panel */}
              <div className="relative" ref={profileRef}>
                <button
                  type="button"
                  onClick={() => setProfileOpen((v) => !v)}
                  aria-haspopup="menu"
                  aria-expanded={profileOpen}
                  aria-label={`Menu profil ${user.name}`}
                  className="flex items-center gap-2 rounded-full border border-border/60 bg-surface-container/40 py-1 pl-1 pr-2.5 text-sm text-on-surface transition-colors duration-150 hover:border-primary/40 hover:bg-surface-container"
                >
                  <span className="relative flex h-7 w-7 flex-shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-container/40 text-xs font-semibold uppercase text-on-surface">
                    {user.avatar_url ? (
                      <Image
                        src={user.avatar_url}
                        alt=""
                        fill
                        sizes="28px"
                        className="object-cover"
                      />
                    ) : (
                      user.name.charAt(0)
                    )}
                  </span>
                  <svg
                    className={`h-3.5 w-3.5 text-on-surface-muted transition-transform duration-150 ${profileOpen ? "rotate-180" : ""}`}
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden
                  >
                    <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clipRule="evenodd" />
                  </svg>
                </button>

                {profileOpen && (
                  <div
                    role="menu"
                    aria-label="Menu profil"
                    className="animate-fade-in absolute right-0 top-[calc(100%+0.5rem)] z-50 w-64 overflow-hidden rounded-lg border border-border/60 bg-surface shadow-lg shadow-black/20"
                  >
                    <div className="border-b border-border/50 px-4 py-3">
                      <p className="truncate text-sm font-medium text-tertiary" title={user.name}>
                        {user.name}
                      </p>
                      <p className="mt-0.5 truncate text-xs text-on-surface-muted" title={user.email}>
                        {user.email}
                      </p>
                      <span className="mt-2 inline-flex items-center rounded-full border border-border/60 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wider text-on-surface-muted">
                        {ROLE_LABEL[user.role] ?? user.role}
                      </span>
                    </div>
                    <Link
                      href="/profil"
                      role="menuitem"
                      onClick={() => setProfileOpen(false)}
                      className="flex items-center gap-2 px-4 py-2.5 text-sm text-on-surface transition-colors hover:bg-surface-container"
                    >
                      <svg className="h-4 w-4 text-on-surface-muted" viewBox="0 0 20 20" fill="currentColor" aria-hidden>
                        <path fillRule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clipRule="evenodd" />
                      </svg>
                      Profil
                    </Link>
                    <button
                      type="button"
                      role="menuitem"
                      onClick={handleLogout}
                      className="flex w-full items-center gap-2 border-t border-border/50 px-4 py-2.5 text-left text-sm text-on-surface transition-colors hover:bg-error/10 hover:text-error"
                    >
                      <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden>
                        <path fillRule="evenodd" d="M3 4.75A2.75 2.75 0 015.75 2h5.5A2.75 2.75 0 0114 4.75V6a.75.75 0 01-1.5 0V4.75c0-.69-.56-1.25-1.25-1.25h-5.5c-.69 0-1.25.56-1.25 1.25v10.5c0 .69.56 1.25 1.25 1.25h5.5c.69 0 1.25-.56 1.25-1.25V14a.75.75 0 011.5 0v1.25A2.75 2.75 0 0111.25 18h-5.5A2.75 2.75 0 013 15.25V4.75zm12.47 2.72a.75.75 0 011.06 0l2 2a.75.75 0 010 1.06l-2 2a.75.75 0 11-1.06-1.06l.72-.72H8.75a.75.75 0 010-1.5h7.44l-.72-.72a.75.75 0 010-1.06z" clipRule="evenodd" />
                      </svg>
                      Keluar
                    </button>
                  </div>
                )}
              </div>
            </>
          )}
        </nav>

        {/* ── Hamburger (mobile + tablet, <1024px) ── */}
        <button
          className="lg:hidden flex h-9 w-9 items-center justify-center rounded-md text-on-surface-muted
                     transition-colors duration-150 hover:bg-surface-container hover:text-on-surface"
          onClick={() => setMenuOpen((v) => !v)}
          aria-label={menuOpen ? "Tutup menu" : "Buka menu navigasi"}
          aria-expanded={menuOpen}
          aria-controls="mobile-nav"
        >
          {/* 3 lines → X animation */}
          <div className="relative h-[18px] w-[18px]" aria-hidden>
            <span
              className={`absolute inset-x-0 h-px origin-center bg-current transition-all duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] ${
                menuOpen ? "top-[9px] rotate-45" : "top-0"
              }`}
            />
            <span
              className={`absolute inset-x-0 top-[9px] h-px bg-current transition-all duration-200 ${
                menuOpen ? "scale-x-0 opacity-0" : "scale-x-100 opacity-100"
              }`}
            />
            <span
              className={`absolute inset-x-0 h-px origin-center bg-current transition-all duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] ${
                menuOpen ? "top-[9px] -rotate-45" : "bottom-0"
              }`}
            />
          </div>
        </button>
      </div>

      {/* ── Mobile + tablet nav dropdown (<1024px) ── */}
      {menuOpen && (
        <div
          id="mobile-nav"
          className="lg:hidden border-t border-border/60 bg-surface animate-fade-in"
        >
          <nav className="mx-auto max-w-6xl px-4 py-1" aria-label="Navigasi mobile">
            {/* User info */}
            {!isLoading && user && (
              <div className="border-b border-border/50 py-3">
                <p className="text-xs uppercase tracking-wider text-on-surface-muted">Masuk sebagai</p>
                <p className="mt-0.5 text-sm font-medium text-tertiary">{user.name}</p>
              </div>
            )}

            {/* Icons (cart, notif, pesan, chat) — mobile */}
            {!isLoading && user && <NavbarActions variant="mobile" />}

            {/* Nav links (role-based + Profil untuk user yang login) */}
            <div className="flex flex-col divide-y divide-border/30">
              {!isLoading && !user && (
                <>
                  <Link href="/login" className="py-3.5 text-sm font-medium text-on-surface-muted transition-colors hover:text-on-surface">
                    Masuk
                  </Link>
                  <Link href="/register" className="py-3.5 text-sm font-medium text-primary transition-colors hover:text-primary/80">
                    Daftar Akun
                  </Link>
                </>
              )}
              {!isLoading && user?.role === "seller" && (
                <>
                  <Link href="/seller/kyc" className="py-3.5 text-sm text-on-surface-muted transition-colors hover:text-on-surface">KYC</Link>
                  <Link href="/seller/vehicles" className="py-3.5 text-sm text-on-surface-muted transition-colors hover:text-on-surface">Listing Saya</Link>
                  <Link href="/seller/transactions" className="py-3.5 text-sm text-on-surface-muted transition-colors hover:text-on-surface">Transaksi Penjualan</Link>
                </>
              )}
              {!isLoading && user?.role === "buyer" && (
                <>
                  <Link href="/buyer/kyc" className="py-3.5 text-sm text-on-surface-muted transition-colors hover:text-on-surface">KYC</Link>
                  <Link href="/buyer/transactions" className="py-3.5 text-sm text-on-surface-muted transition-colors hover:text-on-surface">
                    Transaksi Saya
                  </Link>
                </>
              )}
              {!isLoading && user?.role === "admin" && (
                <>
                  <Link href="/admin/kyc" className="py-3.5 text-sm text-on-surface-muted transition-colors hover:text-on-surface">Review KYC Seller</Link>
                  <Link href="/admin/buyer-kyc" className="py-3.5 text-sm text-on-surface-muted transition-colors hover:text-on-surface">Review KYC Buyer</Link>
                  <Link href="/admin/vehicles" className="py-3.5 text-sm text-on-surface-muted transition-colors hover:text-on-surface">Review Listing</Link>
                  <Link href="/admin/transactions" className="py-3.5 text-sm text-on-surface-muted transition-colors hover:text-on-surface">Escrow & Transaksi</Link>
                  <Link href="/admin/payouts" className="py-3.5 text-sm text-on-surface-muted transition-colors hover:text-on-surface">Rekonsiliasi Payout</Link>
                </>
              )}
              {/* Profil — setara dengan menu navigasi lainnya */}
              {!isLoading && user && (
                <Link href="/profil" className="py-3.5 text-sm text-on-surface-muted transition-colors hover:text-on-surface">
                  Profil
                </Link>
              )}
            </div>

            {/* Keluar — dipisah sendiri di bawah, jelas sebagai aksi keluar */}
            {!isLoading && user && (
              <div className="border-t border-border/50 py-3">
                <button
                  onClick={handleLogout}
                  className="text-sm text-on-surface-muted transition-colors hover:text-error"
                >
                  Keluar
                </button>
              </div>
            )}
          </nav>
        </div>
      )}
    </header>

    {showLogoutConfirm && (
      <ConfirmModal
        title="Keluar dari AuraMotors?"
        message="Kamu akan keluar dari sesi ini. Pastikan tidak ada proses yang sedang berjalan."
        confirmLabel="Ya, Keluar"
        onConfirm={confirmLogout}
        onCancel={() => setShowLogoutConfirm(false)}
      />
    )}
    </>
  );
}
