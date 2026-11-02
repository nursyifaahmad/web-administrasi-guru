<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class IndonesianDate
{
    public const MONTHS = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public const DAYS = [
        0 => 'Minggu',
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
    ];

    public static function monthName(int $month): string
    {
        return self::MONTHS[$month] ?? 'Januari';
    }

    public static function dayName(CarbonInterface|string $date): string
    {
        $c = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return self::DAYS[$c->dayOfWeek] ?? '';
    }

    public static function formatLong(CarbonInterface|string|null $date): string
    {
        $c = $date ? ($date instanceof CarbonInterface ? $date : Carbon::parse($date)) : now();
        $month = self::monthName((int) $c->month);

        return sprintf('%02d %s %d', $c->day, $month, $c->year);
    }

    public static function formatWithDay(CarbonInterface|string $date): string
    {
        $c = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return self::dayName($c) . ', ' . self::formatLong($c);
    }

    public static function titimangsa(string $city, CarbonInterface|string|null $date = null): string
    {
        $cleanCity = trim($city) !== '' ? trim($city) : 'Lemahabang';

        return $cleanCity . ', ' . self::formatLong($date);
    }
}
