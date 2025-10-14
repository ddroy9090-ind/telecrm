<?php

declare(strict_types=1);

namespace HouzzHunt\Services;

use DateTimeImmutable;
use HouzzHunt\Repositories\ActivityRepository;
use HouzzHunt\Support\DateRange;

final class ActivityService
{
    private ActivityRepository $activityRepository;

    public function __construct(ActivityRepository $activityRepository)
    {
        $this->activityRepository = $activityRepository;
    }

    /**
     * @param array{role:string,user_id:int,user_name:?string} $context
     * @return array<int, array<string, mixed>>
     */
    public function recentActivities(array $context, int $limit = 20): array
    {
        $activities = $this->activityRepository->recent($limit, $context);
        $now = new DateTimeImmutable('now');

        return array_map(static function (array $activity) use ($now) {
            $createdAt = isset($activity['created_at']) ? new DateTimeImmutable((string) $activity['created_at']) : null;
            $relative = $createdAt ? self::formatRelativeTime($createdAt, $now) : null;

            $metadata = $activity['metadata'] ?? null;
            if (is_string($metadata) && $metadata !== '') {
                $decoded = json_decode($metadata, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $metadata = $decoded;
                }
            }

            return [
                'id' => (int) ($activity['id'] ?? 0),
                'lead_id' => (int) ($activity['lead_id'] ?? 0),
                'activity_type' => $activity['activity_type'] ?? null,
                'description' => $activity['description'] ?? null,
                'metadata' => $metadata,
                'created_by' => $activity['created_by_name'] ?? null,
                'created_at' => $activity['created_at'] ?? null,
                'relative_time' => $relative,
                'lead_name' => $activity['lead_name'] ?? null,
            ];
        }, $activities);
    }

    /**
     * Build an activity heatmap matrix keyed by weekday/hour.
     *
     * @param array{role:string,user_id:int,user_name:?string} $context
     */
    public function heatmap(DateRange $range, array $context): array
    {
        $rows = $this->activityRepository->heatmap($range, $context);
        $matrix = [];
        $max = 0;
        $total = 0;
        $cells = 0;

        for ($day = 0; $day < 7; $day++) {
            $matrix[$day] = array_fill(0, 24, 0);
        }

        foreach ($rows as $row) {
            $weekday = (int) ($row['weekday'] ?? 0);
            $hour = (int) ($row['hour_block'] ?? 0);
            $count = (int) ($row['total'] ?? 0);

            // MySQL DAYOFWEEK returns 1=Sunday ... 7=Saturday
            $dayIndex = ($weekday + 5) % 7; // convert to 0=Monday..6=Sunday

            if (isset($matrix[$dayIndex][$hour])) {
                $matrix[$dayIndex][$hour] = $count;
                $max = max($max, $count);
                $total += $count;
                $cells++;
            }
        }

        $average = $cells > 0 ? $total / $cells : 0;
        $relativeAvg = $max > 0 ? round(($average / $max) * 100, 2) : 0.0;

        return [
            'grid' => $matrix,
            'max' => $max,
            'average_fill_pct' => $relativeAvg,
        ];
    }

    private static function formatRelativeTime(DateTimeImmutable $time, DateTimeImmutable $now): string
    {
        $diff = $now->diff($time);

        if ($diff->y > 0) {
            return $diff->y . 'y ago';
        }
        if ($diff->m > 0) {
            return $diff->m . 'mo ago';
        }
        if ($diff->d > 0) {
            return $diff->d . 'd ago';
        }
        if ($diff->h > 0) {
            return $diff->h . 'h ago';
        }
        if ($diff->i > 0) {
            return $diff->i . 'm ago';
        }

        return 'Just now';
    }
}
