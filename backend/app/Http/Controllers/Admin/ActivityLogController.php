<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Log aktivitas global untuk admin: semua jejak proses ditampilkan apa
 * adanya (termasuk verifikasi yang sudah selesai), bisa disaring per
 * kategori, peran aktor, transaksi, rentang tanggal, dan kata kunci.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage', Transaction::class);

        $validated = $request->validate([
            'category' => ['nullable', 'string'],
            'actor_role' => ['nullable', 'in:admin,seller,buyer,system'],
            'transaction_id' => ['nullable', 'integer'],
            'subject_type' => ['nullable', 'string', 'max:60'],
            'subject_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);

        $base = ActivityLog::query()
            ->when($validated['actor_role'] ?? null, fn (Builder $q, string $role) => $role === 'system' ? $q->whereNull('actor_id') : $q->where('actor_role', $role))
            ->when($validated['transaction_id'] ?? null, fn (Builder $q, int $id) => $q->where('transaction_id', $id))
            ->when($validated['subject_type'] ?? null, fn (Builder $q, string $type) => $q->where('subject_type', 'like', '%'.$type))
            ->when($validated['subject_id'] ?? null, fn (Builder $q, int $id) => $q->where('subject_id', $id))
            ->when($validated['search'] ?? null, fn (Builder $q, string $search) => $q->where('description', 'like', "%{$search}%"))
            ->when($validated['from'] ?? null, fn (Builder $q, string $from) => $q->where('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $q, string $to) => $q->where('created_at', '<', now()->parse($to)->addDay()->startOfDay()));

        // Jumlah per kategori dihitung sebelum filter kategori, supaya chip filter tetap informatif.
        $counts = (clone $base)->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category');

        $query = $base
            ->with('actor:id,name')
            ->when($validated['category'] ?? null, fn (Builder $q, string $category) => $q->where('category', $category))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return ActivityLogResource::collection($query->paginate($validated['per_page'] ?? 50))
            ->additional([
                'categories' => collect(ActivityCategory::cases())->map(fn (ActivityCategory $category) => [
                    'value' => $category->value,
                    'label' => $category->label(),
                    'count' => (int) ($counts[$category->value] ?? 0),
                ]),
            ]);
    }
}
