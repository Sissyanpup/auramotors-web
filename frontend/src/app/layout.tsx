import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import localFont from "next/font/local";
import "./globals.css";
import { AuthProvider } from "@/contexts/auth-context";
import Header from "@/components/Header";
import KtpGate from "@/components/KtpGate";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

// Font display/body tema AuraMotors di-self-host lewat next/font/local (file di
// ./fonts, sudah di-commit) alih-alih next/font/google — supaya build tetap jalan
// tanpa internet (kelas offline, lihat docs/03 §6), konsisten dengan pola Geist yang
// sudah dipakai sejak awal proyek.
const bodoniModa = localFont({
  src: "./fonts/BodoniModa-Variable.woff2",
  variable: "--font-bodoni",
  display: "swap",
});

const manrope = localFont({
  src: "./fonts/Manrope-Variable.woff2",
  variable: "--font-manrope",
  display: "swap",
});

export const metadata: Metadata = {
  title: "AuraMotors",
  description: "Marketplace jual-beli kendaraan dengan verifikasi dokumen & escrow.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html
      lang="id"
      className={`${geistSans.variable} ${geistMono.variable} ${bodoniModa.variable} ${manrope.variable} h-full antialiased`}
    >
      <body className="min-h-full flex flex-col bg-background text-on-surface">
        <AuthProvider>
          <Header />
          <main className="flex-1">
            <KtpGate>{children}</KtpGate>
          </main>
        </AuthProvider>
      </body>
    </html>
  );
}
