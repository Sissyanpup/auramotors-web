"use client";

import Image from "next/image";
import { useAuth } from "@/contexts/auth-context";

type Developer = {
  name: string;
  nim: string;
  photo: string;
};

const DEVELOPERS: Developer[] = [
  { name: "Muhammad Zirlda Prairi", nim: "231011402293", photo: "/credits/zirlda.jpg" },
  { name: "Unknown1", nim: "0", photo: "/credits/placeholder.png" },
  { name: "Unknown2", nim: "0", photo: "/credits/placeholder.png" },
];

export default function DeveloperCredits() {
  const { user, isLoading } = useAuth();

  if (isLoading || user) {
    return null;
  }

  return (
    <div className="mb-6 rounded-lg border border-border bg-surface-container p-4">
      <p className="font-label text-center text-xs uppercase tracking-wide text-on-surface-muted">
        Dikembangkan oleh
      </p>
      <div className="mt-3 flex flex-wrap justify-center gap-6">
        {DEVELOPERS.map((dev) => (
          <div key={dev.name} className="flex items-center gap-3">
            <div className="relative h-12 w-12 shrink-0 overflow-hidden rounded-full border border-border">
              <Image src={dev.photo} alt={dev.name} fill className="object-cover" />
            </div>
            <div>
              <p className="text-sm font-medium text-on-surface">{dev.name}</p>
              <p className="text-xs text-on-surface-muted">NIM: {dev.nim}</p>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
