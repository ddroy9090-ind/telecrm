<?php

declare(strict_types=1);

namespace HouzzHunt\Support;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use InvalidArgumentException;

final class DateRange
{
    private DateTimeImmutable $start;
    private DateTimeImmutable $end;
    private DateTimeImmutable $previousStart;
    private DateTimeImmutable $previousEnd;
    private string $label;

    private function __construct(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        DateTimeImmutable $previousStart,
        DateTimeImmutable $previousEnd,
        string $label
    ) {
        if ($start > $end) {
            throw new InvalidArgumentException('Date range start must be before end.');
        }

        $this->start        = $start;
        $this->end          = $end;
        $this->previousStart = $previousStart;
        $this->previousEnd   = $previousEnd;
        $this->label        = $label;
    }

    public static function fromPreset(?string $preset): self
    {
        $normalized = $preset ? strtolower(trim($preset)) : '';
        $now        = new DateTimeImmutable('now');
        $today      = $now->setTime(0, 0, 0);

        switch ($normalized) {
            case 'last_7_days':
                $start = $today->sub(new DateInterval('P6D'));
                $end   = $now;
                $prevStart = $start->sub(new DateInterval('P7D'));
                $prevEnd   = $start->sub(new DateInterval('P1D'))->setTime(23, 59, 59);
                $label = 'last_7_days';
                break;
            case 'last_month':
                $start = $today->modify('first day of last month');
                $end   = $today->modify('last day of last month')->setTime(23, 59, 59);
                $prevStart = $start->modify('-1 month');
                $prevEnd   = $start->modify('-1 day')->setTime(23, 59, 59);
                $label = 'last_month';
                break;
            case 'last_quarter':
                $currentQuarter = (int) ceil((int) $today->format('n') / 3);
                $startQuarter   = $currentQuarter - 1;
                if ($startQuarter <= 0) {
                    $startQuarter += 4;
                    $yearOffset = -1;
                } else {
                    $yearOffset = 0;
                }
                $startMonth = (($startQuarter - 1) * 3) + 1;
                $start = $today
                    ->setDate((int) $today->format('Y') + $yearOffset, $startMonth, 1)
                    ->setTime(0, 0, 0);
                $end = $start->modify('+2 months')->modify('last day of this month')->setTime(23, 59, 59);
                $prevStart = $start->modify('-3 months');
                $prevEnd   = $start->modify('-1 day')->setTime(23, 59, 59);
                $label = 'last_quarter';
                break;
            case 'this_month':
                $start = $today->modify('first day of this month');
                $end   = $now;
                $prevStart = $today->modify('first day of last month');
                $prevEnd   = $today->modify('first day of this month')->modify('-1 day')->setTime(23, 59, 59);
                $label = 'this_month';
                break;
            case 'last_30_days':
            default:
                $start = $today->sub(new DateInterval('P29D'));
                $end   = $now;
                $prevStart = $start->sub(new DateInterval('P30D'));
                $prevEnd   = $start->sub(new DateInterval('P1D'))->setTime(23, 59, 59);
                $label = 'last_30_days';
                break;
        }

        return new self($start, $end, $prevStart, $prevEnd, $label);
    }

    public static function fromInput(?string $preset, ?string $startDate = null, ?string $endDate = null): self
    {
        $start = self::parseDateInput($startDate);
        $end = self::parseDateInput($endDate);

        if ($start !== null && $end !== null) {
            $rangeStart = $start->setTime(0, 0, 0);
            $rangeEnd = $end->setTime(23, 59, 59);

            if ($rangeStart > $rangeEnd) {
                throw new InvalidArgumentException('Start date must be on or before the end date.');
            }

            $length = (int) $rangeEnd->diff($rangeStart)->format('%a') + 1;
            $previousEnd = $rangeStart->modify('-1 day')->setTime(23, 59, 59);
            $daysToSubtract = max($length - 1, 0);
            $previousStart = $previousEnd->modify(sprintf('-%d days', $daysToSubtract))->setTime(0, 0, 0);

            return new self($rangeStart, $rangeEnd, $previousStart, $previousEnd, 'custom');
        }

        return self::fromPreset($preset);
    }

    public function getStart(): DateTimeImmutable
    {
        return $this->start;
    }

    public function getEnd(): DateTimeImmutable
    {
        return $this->end;
    }

    public function getPreviousStart(): DateTimeImmutable
    {
        return $this->previousStart;
    }

    public function getPreviousEnd(): DateTimeImmutable
    {
        return $this->previousEnd;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getLengthInDays(): int
    {
        $interval = $this->end->diff($this->start);

        return (int) $interval->format('%a') + 1;
    }

    /**
     * Provide human-readable labels for the current and previous periods.
     *
     * @return array{current:array{label:string,start:string,end:string},previous:array{label:string,start:string,end:string}}
     */
    public function getPeriodSummaries(): array
    {
        return [
            'current' => [
                'label' => self::formatPeriodLabel($this->start, $this->end),
                'start' => $this->start->format(DATE_ATOM),
                'end' => $this->end->format(DATE_ATOM),
            ],
            'previous' => [
                'label' => self::formatPeriodLabel($this->previousStart, $this->previousEnd),
                'start' => $this->previousStart->format(DATE_ATOM),
                'end' => $this->previousEnd->format(DATE_ATOM),
            ],
        ];
    }

    /**
     * Iterate each day in the range.
     *
     * @return DatePeriod
     */
    public function iterateDays(): DatePeriod
    {
        return new DatePeriod($this->start, new DateInterval('P1D'), $this->end->modify('+1 day'));
    }

    private static function formatPeriodLabel(DateTimeImmutable $start, DateTimeImmutable $end): string
    {
        if ($start->format('Y-m') === $end->format('Y-m')) {
            return $start->format('F Y');
        }

        return sprintf('%s - %s', $start->format('M j, Y'), $end->format('M j, Y'));
    }

    private static function parseDateInput(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $trimmed);
        if ($date === false) {
            return null;
        }

        return $date;
    }
}
