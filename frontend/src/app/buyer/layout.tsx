"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/contexts/auth-context";

export default function BuyerLayout({ children }: { children: React.ReactNode }) {
  const { user, isLoading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!isLoading && user?.role !== "buyer") {
      router.replace(user ? "/" : "/login");
    }
  }, [isLoading, user, router]);

  if (isLoading || user?.role !== "buyer") {
    return <div className="mx-auto max-w-4xl px-4 py-10 text-sm text-on-surface-muted">Memuat...</div>;
  }

  return <div className="mx-auto max-w-4xl px-4 py-8">{children}</div>;
}
