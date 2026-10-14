<?php

namespace Tests\Unit;

use App\Support\Terbilang;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TerbilangTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function amounts(): array
    {
        return [
            'nol' => [0, 'nol rupiah'],
            'sebelas' => [11, 'sebelas rupiah'],
            'belasan' => [19, 'sembilan belas rupiah'],
            'seratus' => [100, 'seratus rupiah'],
            'seribu' => [1_000, 'seribu rupiah'],
            'harga mobil' => [1_250_500_000, 'satu miliar dua ratus lima puluh juta lima ratus ribu rupiah'],
            'DP' => [51_110_000, 'lima puluh satu juta seratus sepuluh ribu rupiah'],
        ];
    }

    #[DataProvider('amounts')]
    public function test_converts_amount_to_indonesian_words(int $amount, string $expected): void
    {
        $this->assertSame($expected, Terbilang::rupiah($amount));
    }
}
