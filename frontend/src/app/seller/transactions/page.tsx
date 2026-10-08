"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { apiFetch } from "@/lib/api";
import { formatDate, formatRupiah } from "@/lib/format";
import type { Paginated, Transaction } from "@/lib/types";
import PaymentStatusBadge from "@/components/PaymentStatusBadge";
import EscrowStatusBadge from "@/components/EscrowStatusBadge";

export default function SellerTransactionsPage() {
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    apiFetch<Paginated<Transaction>>("/api/seller/transactions")
      .then(({ data }) => setTransactions(data))
      .finally(() => setIsLoading(false));
  }, []);

  if (isLoading) {
    return <p className="text-sm text-on-surface-muted">Memuat...</p>;
  }

  return (
    <div>
      <h1 className="text-xl font-semibold text-on-surface">Transaksi Penjualan Saya</h1>

      {transactions.length === 0 ? (
        <p className="mt-4 text-sm text-on-surface-muted">Belum ada transaksi.</p>
      ) : (
        <ul className="mt-6 space-y-3">
          {transactions.map((transaction) => (
            <li key={transaction.id}>
              <Link
                href={`/seller/transactions/${transaction.id}`}
                className="flex flex-col gap-2 rounded-lg border border-border bg-surface-container p-4
                           transition-colors duration-200 hover:border-primary/40
                           sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="min-w-0">
                  <p className="font-medium text-on-surface">
                    {transaction.vehicle.brand} {transaction.vehicle.model} {transaction.vehicle.year}
                  </p>
                  <p className="mt-1 text-sm text-on-surface-muted">
                    DP {formatRupiah(transaction.amount)} &middot; {formatDate(transaction.created_at)}
                  </p>
                </div>
                <div className="flex flex-wrap items-center gap-1.5 sm:flex-col sm:items-end">
                  <PaymentStatusBadge status={transaction.payment_status} />
                  {transaction.escrow_status && <EscrowStatusBadge status={transaction.escrow_status} />}
                </div>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
