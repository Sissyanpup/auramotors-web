"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

type NavItem = { href: string; label: string; icon: string };

/** Path ikon outline 24×24 (gaya Heroicons). */
const NAV_GROUPS: { title: string; items: NavItem[] }[] = [
  {
    title: "Verifikasi",
    items: [
      {
        href: "/admin/kyc",
        label: "KYC Penjual",
        icon: "M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z",
      },
      {
        href: "/admin/buyer-kyc",
        label: "KYC Pembeli",
        icon: "M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z",
      },
      {
        href: "/admin/vehicles",
        label: "Listing Kendaraan",
        icon: "M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12",
      },
    ],
  },
  {
    title: "Transaksi",
    items: [
      {
        href: "/admin/transactions",
        label: "Escrow & Serah Terima",
        icon: "M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5",
      },
      {
        href: "/admin/loans",
        label: "Kredit & Cicilan",
        icon: "M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z",
      },
      {
        href: "/admin/payouts",
        label: "Rekonsiliasi Payout",
        icon: "M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z",
      },
    ],
  },
  {
    title: "Pemasaran",
    items: [
      {
        href: "/admin/promos",
        label: "Promo Pembeli",
        icon: "M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3ZM6 6h.008v.008H6V6Z",
      },
    ],
  },
  {
    title: "Analitik",
    items: [
      {
        href: "/admin/reports",
        label: "Laporan",
        icon: "M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z",
      },
      {
        href: "/admin/activity",
        label: "Log Aktivitas",
        icon: "M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z",
      },
    ],
  },
];

function NavIcon({ path }: { path: string }) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="h-5 w-5 flex-shrink-0" aria-hidden>
      <path strokeLinecap="round" strokeLinejoin="round" d={path} />
    </svg>
  );
}

/**
 * Navigasi panel admin. Desktop: kolom tetap di kiri (pola template admin
 * pada umumnya, catatan dosen 16 Okt). Layar sempit: baris menu geser
 * horizontal di atas konten supaya tidak memakan lebar layar.
 */
export default function AdminSidebar() {
  const pathname = usePathname();
  const isActive = (href: string) => pathname === href || pathname.startsWith(`${href}/`);

  return (
    <>
      <nav aria-label="Menu admin" className="hidden w-60 flex-shrink-0 border-r border-border bg-surface-container lg:block">
        <div className="sticky top-0 max-h-screen overflow-y-auto px-3 py-6">
          <p className="px-3 font-display text-lg text-on-surface">Panel Admin</p>
          {NAV_GROUPS.map((group) => (
            <div key={group.title} className="mt-6">
              <p className="px-3 text-[11px] font-semibold uppercase tracking-wider text-on-surface-muted">{group.title}</p>
              <ul className="mt-2 space-y-0.5">
                {group.items.map((item) => (
                  <li key={item.href}>
                    <Link
                      href={item.href}
                      aria-current={isActive(item.href) ? "page" : undefined}
                      className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors ${
                        isActive(item.href)
                          ? "bg-primary-container/20 font-medium text-primary"
                          : "text-on-surface-muted hover:bg-surface-container-high hover:text-on-surface"
                      }`}
                    >
                      <NavIcon path={item.icon} />
                      {item.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      </nav>

      <nav aria-label="Menu admin" className="border-b border-border bg-surface-container lg:hidden">
        <ul className="flex gap-1 overflow-x-auto px-4 py-2">
          {NAV_GROUPS.flatMap((group) => group.items).map((item) => (
            <li key={item.href} className="flex-shrink-0">
              <Link
                href={item.href}
                aria-current={isActive(item.href) ? "page" : undefined}
                className={`flex items-center gap-2 rounded-md px-3 py-1.5 text-xs whitespace-nowrap ${
                  isActive(item.href)
                    ? "bg-primary-container/20 font-medium text-primary"
                    : "text-on-surface-muted hover:bg-surface-container-high hover:text-on-surface"
                }`}
              >
                <NavIcon path={item.icon} />
                {item.label}
              </Link>
            </li>
          ))}
        </ul>
      </nav>
    </>
  );
}
