"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { apiFetch } from "@/lib/api";
import { useAuth } from "@/contexts/auth-context";

type NotificationItem = {
  id: string;
  title: string;
  description: string;
  href: string;
  created_at: string | null;
};

type Summary = {
  cart: number;
  notifications: number;
  notification_items: NotificationItem[];
  messages: number;
  chats: number;
};

const INITIAL_SUMMARY: Summary = {
  cart: 0,
  notifications: 0,
  notification_items: [],
  messages: 0,
  chats: 0,
};

function Badge({ count }: { count: number }) {
  if (count <= 0) return null;
  const display = count > 99 ? "99+" : String(count);
  return (
    <span
      aria-hidden
      className="absolute -right-1 -top-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-error px-1 font-mono text-[10px] font-bold leading-none text-white shadow-[0_0_0_2px_var(--color-surface)]"
    >
      {display}
    </span>
  );
}

function IconButton({
  href,
  label,
  count,
  children,
}: {
  href: string;
  label: string;
  count: number;
  children: React.ReactNode;
}) {
  return (
    <Link
      href={href}
      aria-label={`${label}${count > 0 ? ` (${count} baru)` : ""}`}
      className="relative flex h-9 w-9 items-center justify-center rounded-md text-on-surface-muted transition-colors duration-150 hover:bg-surface-container hover:text-on-surface"
    >
      {children}
      <Badge count={count} />
    </Link>
  );
}

function NotificationMenu({
  count,
  items,
  allHref,
  align,
}: {
  count: number;
  items: NotificationItem[];
  allHref: string;
  align: "right" | "center";
}) {
  const [open, setOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const pathname = usePathname();

  useEffect(() => {
    setOpen(false);
  }, [pathname]);

  useEffect(() => {
    if (!open) return;
    function handlePointerDown(event: PointerEvent) {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setOpen(false);
      }
    }
    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") setOpen(false);
    }
    document.addEventListener("pointerdown", handlePointerDown);
    document.addEventListener("keydown", handleKeyDown);
    return () => {
      document.removeEventListener("pointerdown", handlePointerDown);
      document.removeEventListener("keydown", handleKeyDown);
    };
  }, [open]);

  const positionClass = align === "right" ? "right-0" : "left-1/2 -translate-x-1/2";

  return (
    <div ref={containerRef} className="relative">
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-label={`Notifikasi transaksi${count > 0 ? ` (${count} baru)` : ""}`}
        aria-haspopup="true"
        aria-expanded={open}
        className="relative flex h-9 w-9 items-center justify-center rounded-md text-on-surface-muted transition-colors duration-150 hover:bg-surface-container hover:text-on-surface"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" className="h-5 w-5">
          <path strokeLinecap="round" strokeLinejoin="round" d="M15 17h5l-1.4-1.5a2 2 0 0 1-.6-1.4V11a6 6 0 1 0-12 0v3.1a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0m6 0H9" />
        </svg>
        <Badge count={count} />
      </button>

      {open && (
        <div
          className={`absolute ${positionClass} top-full z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-border bg-surface shadow-lg`}
        >
          <div className="flex items-center justify-between border-b border-border/50 px-4 py-3">
            <span className="text-sm font-semibold text-on-surface">Notifikasi</span>
            {count > 0 && <span className="font-mono text-xs text-on-surface-muted">{count} baru</span>}
          </div>

          {items.length === 0 ? (
            <p className="px-4 py-6 text-center text-sm text-on-surface-muted">Belum ada notifikasi.</p>
          ) : (
            <ul className="max-h-80 overflow-y-auto">
              {items.map((item) => (
                <li key={item.id}>
                  <Link
                    href={item.href}
                    onClick={() => setOpen(false)}
                    className="block border-b border-border/30 px-4 py-3 transition-colors hover:bg-surface-container"
                  >
                    <p className="text-sm font-medium text-on-surface">{item.title}</p>
                    <p className="mt-0.5 truncate text-xs text-on-surface-muted">{item.description}</p>
                  </Link>
                </li>
              ))}
            </ul>
          )}

          <Link
            href={allHref}
            onClick={() => setOpen(false)}
            className="block px-4 py-2.5 text-center text-xs font-medium text-on-surface-muted hover:bg-surface-container hover:text-on-surface"
          >
            Lihat semua transaksi
          </Link>
        </div>
      )}
    </div>
  );
}

export default function NavbarActions({ variant = "desktop" }: { variant?: "desktop" | "mobile" }) {
  const { user, isLoading } = useAuth();
  const [summary, setSummary] = useState<Summary>(INITIAL_SUMMARY);
  const pathname = usePathname();

  useEffect(() => {
    if (isLoading || !user) {
      setSummary(INITIAL_SUMMARY);
      return;
    }
    let cancelled = false;
    apiFetch<{ data: Summary }>("/api/navbar-summary")
      .then(({ data }) => {
        if (!cancelled) setSummary(data);
      })
      .catch(() => {
        if (!cancelled) setSummary(INITIAL_SUMMARY);
      });
    return () => {
      cancelled = true;
    };
  }, [isLoading, user, pathname]);

  if (isLoading || !user) return null;

  const cartHref = user.role === "buyer" ? "/buyer/transactions?filter=pending" : "#";
  const notifHref =
    user.role === "admin"
      ? "/admin/transactions"
      : user.role === "seller"
        ? "/seller/transactions"
        : "/buyer/transactions";

  const containerClass =
    variant === "desktop"
      ? "hidden lg:flex items-center gap-1"
      : "flex items-center justify-around gap-1 border-b border-border/50 py-3";

  return (
    <div className={containerClass}>
      {user.role === "buyer" && (
        <IconButton href={cartHref} label="Keranjang / transaksi belum bayar" count={summary.cart}>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 3h1.5l2.4 12.6a2 2 0 0 0 2 1.65h9.7a2 2 0 0 0 2-1.65L21.75 7.5H5.4" />
            <circle cx="9" cy="20" r="1.25" />
            <circle cx="17" cy="20" r="1.25" />
          </svg>
        </IconButton>
      )}

      <NotificationMenu
        count={summary.notifications}
        items={summary.notification_items ?? []}
        allHref={notifHref}
        align={variant === "desktop" ? "right" : "center"}
      />

      <IconButton href="/pesan" label="Pesan" count={summary.messages}>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" className="h-5 w-5">
          <path strokeLinecap="round" strokeLinejoin="round" d="M3 6.75A2.25 2.25 0 0 1 5.25 4.5h13.5A2.25 2.25 0 0 1 21 6.75v10.5A2.25 2.25 0 0 1 18.75 19.5H5.25A2.25 2.25 0 0 1 3 17.25V6.75z" />
          <path strokeLinecap="round" strokeLinejoin="round" d="m3.75 7.5 8.25 6 8.25-6" />
        </svg>
      </IconButton>

      <IconButton href="/chat" label="Chat" count={summary.chats}>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" className="h-5 w-5">
          <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 5.25A2.25 2.25 0 0 1 6.75 3h10.5a2.25 2.25 0 0 1 2.25 2.25v8.25a2.25 2.25 0 0 1-2.25 2.25H12l-4.5 3.75V15.75H6.75a2.25 2.25 0 0 1-2.25-2.25V5.25z" />
        </svg>
      </IconButton>
    </div>
  );
}
