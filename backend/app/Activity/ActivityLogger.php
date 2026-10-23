<?php

namespace App\Activity;

use App\Enums\ActivityCategory;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Menulis satu baris jejak audit. Aktor diambil dari user yang sedang
 * login; proses otomatis (webhook, persetujuan leasing setelah jeda,
 * seeder) tercatat sebagai "Sistem" (actor_id null).
 */
class ActivityLogger
{
    private static int $systemDepth = 0;

    /**
     * Jalankan callback dengan aktor "Sistem" — dipakai untuk proses otomatis
     * yang kebetulan terpicu saat seorang user membuka halaman (mis. kredit
     * disetujui otomatis), supaya tidak tercatat seolah user itu yang menyetujui.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function asSystem(callable $callback): mixed
    {
        self::$systemDepth++;

        try {
            return $callback();
        } finally {
            self::$systemDepth--;
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        ActivityCategory $category,
        string $action,
        string $description,
        ?Model $subject = null,
        ?int $transactionId = null,
        array $properties = [],
    ): ActivityLog {
        $actor = $this->currentActor();

        return ActivityLog::create([
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role?->value,
            'category' => $category,
            'action' => $action,
            'description' => mb_substr($description, 0, 500),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'transaction_id' => $transactionId,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    private function currentActor(): ?User
    {
        if (self::$systemDepth > 0 || app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        $user = request()->user();

        return $user instanceof User ? $user : null;
    }
}
