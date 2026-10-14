<?php

namespace App\Support;

/**
 * Mengubah angka menjadi kalimat Bahasa Indonesia ("terbilang") untuk kuitansi
 * dan surat resmi, mis. 1.250.000 → "satu juta dua ratus lima puluh ribu rupiah".
 */
class Terbilang
{
    private const UNITS = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    public static function rupiah(float|int|string $amount): string
    {
        $value = (int) round((float) $amount);

        if ($value === 0) {
            return 'nol rupiah';
        }

        return trim(self::convert($value)).' rupiah';
    }

    private static function convert(int $n): string
    {
        return match (true) {
            $n === 0 => '',
            $n < 12 => ' '.self::UNITS[$n],
            $n < 20 => self::convert($n - 10).' belas',
            $n < 100 => self::convert(intdiv($n, 10)).' puluh'.self::convert($n % 10),
            $n < 200 => ' seratus'.self::convert($n - 100),
            $n < 1_000 => self::convert(intdiv($n, 100)).' ratus'.self::convert($n % 100),
            $n < 2_000 => ' seribu'.self::convert($n - 1_000),
            $n < 1_000_000 => self::convert(intdiv($n, 1_000)).' ribu'.self::convert($n % 1_000),
            $n < 1_000_000_000 => self::convert(intdiv($n, 1_000_000)).' juta'.self::convert($n % 1_000_000),
            $n < 1_000_000_000_000 => self::convert(intdiv($n, 1_000_000_000)).' miliar'.self::convert($n % 1_000_000_000),
            default => self::convert(intdiv($n, 1_000_000_000_000)).' triliun'.self::convert($n % 1_000_000_000_000),
        };
    }
}
