"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/contexts/auth-context";
import AdminSidebar from "@/components/AdminSidebar";

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  const { user, isLoading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!isLoading && user?.role !== "admin") {
      router.replace(user ? "/" : "/login");
    }
  }, [isLoading, user, router]);

  if (isLoading || user?.role !== "admin") {
    return <div className="mx-auto max-w-4xl px-4 py-10 text-sm text-on-surface-muted">Memuat...</div>;
  }

  return (
    <div data-fill-main className="flex flex-1 flex-col lg:flex-row">
      <AdminSidebar />
      <div className="min-w-0 flex-1">
        <div className="mx-auto max-w-5xl px-4 py-8">{children}</div>
      </div>
    </div>
  );
}
