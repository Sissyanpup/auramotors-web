<?php

namespace App\Http\Controllers;

use App\Enums\EscrowStatus;
use App\Enums\SellerProfileStatus;
use App\Enums\TransactionPaymentStatus;
use App\Enums\VehicleStatus;
use App\Models\SellerProfile;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class NavbarSummaryController extends Controller
{
    /**
     * Jumlah maksimum item notifikasi yang dikirim ke dropdown navbar.
     */
    private const NOTIFICATION_ITEM_LIMIT = 10;

    /**
     * Ringkasan angka untuk badge navbar (cart, notifikasi, pesan, chat).
     * Perhitungan diturunkan dari state transaksi yang sudah ada supaya angka
     * realistis tanpa harus menambah tabel notifikasi/chat baru. Daftar item
     * notifikasi diambil dari query yang sama dengan angkanya, sehingga badge
     * dan isi dropdown selalu konsisten.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $cart = 0;

        if ($user->isBuyer()) {
            $cart = Transaction::query()
                ->where('buyer_id', $user->id)
                ->where('payment_status', TransactionPaymentStatus::Pending)
                ->count();
        }

        [$notificationCount, $notificationItems] = $this->notificationsFor($user);

        return response()->json([
            'data' => [
                'cart' => $cart,
                'notifications' => $notificationCount,
                'notification_items' => $notificationItems->values(),
                'messages' => 0,
                'chats' => 0,
            ],
        ]);
    }

    /**
     * @return array{0: int, 1: Collection<int, array{id: string, title: string, description: string, href: string, created_at: string|null}>}
     */
    private function notificationsFor(User $user): array
    {
        if ($user->isBuyer()) {
            $query = Transaction::query()
                ->where('buyer_id', $user->id)
                ->whereIn('escrow_status', [EscrowStatus::SerahTerima, EscrowStatus::PayoutRelease])
                ->whereNull('buyer_confirmed_at');

            return [
                $query->count(),
                $this->transactionItems($query, 'Konfirmasi serah-terima', fn (Transaction $transaction): string => "/buyer/transactions/{$transaction->id}"),
            ];
        }

        if ($user->isSeller()) {
            $query = Transaction::query()
                ->where('seller_id', $user->id)
                ->whereIn('escrow_status', [EscrowStatus::EscrowHold, EscrowStatus::SerahTerima]);

            return [
                $query->count(),
                $this->transactionItems($query, 'Transaksi perlu ditindaklanjuti', fn (Transaction $transaction): string => "/seller/transactions/{$transaction->id}"),
            ];
        }

        if ($user->isAdmin()) {
            return $this->adminNotifications();
        }

        return [0, collect()];
    }

    /**
     * @param  Builder<Transaction>  $query
     * @param  callable(Transaction): string  $href
     * @return Collection<int, array{id: string, title: string, description: string, href: string, created_at: string|null}>
     */
    private function transactionItems(Builder $query, string $title, callable $href): Collection
    {
        return (clone $query)
            ->with('vehicle:id,brand,model,year')
            ->latest('updated_at')
            ->limit(self::NOTIFICATION_ITEM_LIMIT)
            ->get()
            ->map(fn (Transaction $transaction): array => [
                'id' => "transaction-{$transaction->id}",
                'title' => $title,
                'description' => $this->describeTransaction($transaction),
                'href' => $href($transaction),
                'created_at' => $transaction->updated_at?->toIso8601String(),
            ]);
    }

    /**
     * @return array{0: int, 1: Collection<int, array{id: string, title: string, description: string, href: string, created_at: string|null}>}
     */
    private function adminNotifications(): array
    {
        $kycQuery = SellerProfile::query()->where('status', SellerProfileStatus::Pending);
        $vehicleQuery = Vehicle::query()->where('status', VehicleStatus::PendingReview);
        $disputeQuery = Transaction::query()->where('escrow_status', EscrowStatus::Dispute);

        $count = $kycQuery->count() + $vehicleQuery->count() + $disputeQuery->count();

        $kycItems = (clone $kycQuery)
            ->with('user:id,name')
            ->latest('updated_at')
            ->limit(self::NOTIFICATION_ITEM_LIMIT)
            ->get()
            ->map(fn (SellerProfile $profile): array => [
                'id' => "kyc-{$profile->id}",
                'title' => 'KYC seller menunggu review',
                'description' => $profile->user?->name ?? "Seller #{$profile->user_id}",
                'href' => '/admin/kyc',
                'created_at' => $profile->updated_at?->toIso8601String(),
            ]);

        $vehicleItems = (clone $vehicleQuery)
            ->latest('updated_at')
            ->limit(self::NOTIFICATION_ITEM_LIMIT)
            ->get()
            ->map(fn (Vehicle $vehicle): array => [
                'id' => "vehicle-{$vehicle->id}",
                'title' => 'Listing kendaraan menunggu review',
                'description' => trim("{$vehicle->brand} {$vehicle->model} {$vehicle->year}"),
                'href' => '/admin/vehicles',
                'created_at' => $vehicle->updated_at?->toIso8601String(),
            ]);

        $disputeItems = $this->transactionItems($disputeQuery, 'Dispute terbuka', fn (): string => '/admin/transactions');

        $items = $kycItems
            ->concat($vehicleItems)
            ->concat($disputeItems)
            ->sortByDesc('created_at')
            ->take(self::NOTIFICATION_ITEM_LIMIT);

        return [$count, $items];
    }

    private function describeTransaction(Transaction $transaction): string
    {
        $vehicle = $transaction->vehicle;
        $vehicleName = $vehicle ? trim("{$vehicle->brand} {$vehicle->model} {$vehicle->year}") : 'Kendaraan';
        $reference = $transaction->invoice_number ?? "#{$transaction->id}";

        return "{$vehicleName} · {$reference}";
    }
}
