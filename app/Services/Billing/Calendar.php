<?php

declare(strict_types=1);

namespace App\Services\Billing;

use DateTimeImmutable;
use DateTimeZone;

/**
 * PD Section 5.0 — Calendar & Inclusive Block Days.
 *
 * Period boundaries and day counts: inclusive block days.
 * block_days = (end − start in days) + 1. SAST for all dates.
 */
final class Calendar
{
    private const SAST = 'Africa/Johannesburg';

    public function periodStart(string $date, int $billDay): string
    {
        $d = new DateTimeImmutable($date, new DateTimeZone(self::SAST));
        $day = (int) $d->format('j');
        if ($day >= $billDay) {
            $d = $d->setDate((int) $d->format('Y'), (int) $d->format('n'), $billDay);
        } else {
            $prev = $d->modify('first day of last month');
            $d = $prev->setDate((int) $prev->format('Y'), (int) $prev->format('n'), $billDay);
        }
        return $d->format('Y-m-d');
    }

    public function periodEnd(string $periodStart, int $billDay): string
    {
        $start = new DateTimeImmutable($periodStart, new DateTimeZone(self::SAST));
        $end = $start->modify('last day of this month');
        $lastDayOfMonth = (int) $end->format('j');
        if ($billDay > $lastDayOfMonth) {
            return $end->format('Y-m-d');
        }
        $end = $start->setDate((int) $start->format('Y'), (int) $start->format('n'), $billDay);
        $end = $end->modify('-1 day');
        return $end->format('Y-m-d');
    }

    /**
     * Inclusive block days from start to end (both dates included).
     */
    public function blockDays(string $start, string $end): int
    {
        $s = new DateTimeImmutable($start, new DateTimeZone(self::SAST));
        $e = new DateTimeImmutable($end, new DateTimeZone(self::SAST));
        $diff = $s->diff($e);
        return $diff->days + 1;
    }

    /**
     * Period-end dates strictly between start and end for the given billing day.
     * E.g. bill_day 15 => period ends 14th of each month (or last day of Feb).
     *
     * @return array<int, string> sorted Y-m-d dates
     */
    public function boundariesBetween(string $start, string $end, int $billDay): array
    {
        $boundaries = [];
        $s = new DateTimeImmutable($start, new DateTimeZone(self::SAST));
        $e = new DateTimeImmutable($end, new DateTimeZone(self::SAST));
        $cursor = $s->modify('first day of this month');
        $max = $e->modify('last day of this month');
        while ($cursor <= $max) {
            $y = (int) $cursor->format('Y');
            $m = (int) $cursor->format('n');
            $lastDay = (int) $cursor->format('t');
            $day = $billDay > 1 ? min($billDay - 1, $lastDay) : $lastDay;
            $bound = $cursor->setDate($y, $m, $day)->format('Y-m-d');
            if ($bound > $start && $bound < $end) {
                $boundaries[] = $bound;
            }
            $cursor = $cursor->modify('first day of next month');
        }
        sort($boundaries);
        return array_values(array_unique($boundaries));
    }

    /**
     * Next calendar day (Y-m-d).
     */
    public function nextDay(string $date): string
    {
        $d = new DateTimeImmutable($date, new DateTimeZone(self::SAST));
        return $d->modify('+1 day')->format('Y-m-d');
    }
}
