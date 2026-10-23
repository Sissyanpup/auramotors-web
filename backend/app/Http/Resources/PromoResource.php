<?php

namespace App\Http\Resources;

use App\Models\Promo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Promo */
class PromoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAdmin = $request->user()?->isAdmin() ?? false;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'kind' => $this->kind,
            'benefit' => $this->benefit,
            'benefit_summary' => $this->benefitSummary(),
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'max_discount' => $this->max_discount,
            'addon_code' => $this->addon_code,
            'rate_cut' => $this->rate_cut,
            'min_vehicle_price' => $this->min_vehicle_price,
            'brand' => $this->brand,
            'badge' => $this->badge,
            'description' => $this->description,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'quota' => $this->quota,
            'used_count' => $this->used_count,
            'is_active' => $this->when($isAdmin, $this->is_active),
            'is_running' => $this->isRunning(),
            'redemptions_count' => $this->when($isAdmin && isset($this->redemptions_count), fn () => (int) $this->redemptions_count),
            'redemptions_total' => $this->when($isAdmin && array_key_exists('redemptions_sum_benefit_amount', $this->getAttributes()), fn () => (float) $this->redemptions_sum_benefit_amount),
            'created_at' => $this->created_at,
        ];
    }

    private function benefitSummary(): string
    {
        $rupiah = fn (float $value) => 'Rp'.number_format($value, 0, ',', '.');

        return match ($this->benefit) {
            Promo::BENEFIT_DISCOUNT => ($this->discount_type === 'percent'
                ? 'Diskon '.rtrim(rtrim(number_format((float) $this->discount_value, 2, ',', '.'), '0'), ',').'%'
                : 'Potongan '.$rupiah((float) $this->discount_value))
                .($this->max_discount !== null ? ' (maks. '.$rupiah((float) $this->max_discount).')' : ''),
            Promo::BENEFIT_FREE_ADDON => 'Gratis '.mb_strtolower((string) config("pricing.insurance.addons.{$this->addon_code}.label", 'perluasan asuransi')).' tahun pertama',
            Promo::BENEFIT_CREDIT_RATE => 'Bunga kredit turun '.rtrim(rtrim(number_format((float) $this->rate_cut * 100, 2, ',', '.'), '0'), ',').'% per tahun',
            default => '',
        };
    }

    private function isRunning(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gte(now()))
            && ($this->quota === null || $this->used_count < $this->quota);
    }
}
