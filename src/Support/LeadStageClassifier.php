<?php

declare(strict_types=1);

namespace HouzzHunt\Support;

final class LeadStageClassifier
{
    private const ACTIVE_STAGES = [
        'new',
        'contacted',
        'follow up - in progress',
        'follow up – in progress',
        'qualified',
        'meeting scheduled',
        'meeting done',
        'offer made',
        'negotiation',
        'site visit',
    ];

    private const CLOSED_STAGES = [
        'won',
        'booking confirmed',
        'closed won',
        'closed',
    ];

    public static function normalizeStage(?string $rawStage): string
    {
        if ($rawStage === null) {
            return '';
        }

        $decoded = json_decode($rawStage, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_array($decoded)) {
                $first = reset($decoded);
                if (is_array($first)) {
                    $first = reset($first);
                }
                if (is_string($first)) {
                    $rawStage = $first;
                }
            } elseif (is_string($decoded)) {
                $rawStage = $decoded;
            }
        }

        $rawStage = (string) $rawStage;
        $rawStage = trim($rawStage, " \t\n\r\0\x0B\"[]");
        $rawStage = preg_replace('/\s+/u', ' ', $rawStage ?? '');
        $rawStage = strtr($rawStage ?? '', [
            "\xE2\x80\x93" => '-',
            "\xE2\x80\x94" => '-',
            "\xE2\x88\x92" => '-',
        ]);

        return strtolower(trim((string) $rawStage));
    }

    public static function isActive(?string $rawStage, ?string $rating = null): bool
    {
        $normalizedStage = self::normalizeStage($rawStage);
        $normalizedRating = strtolower(trim((string) $rating));

        if ($normalizedRating === 'hot') {
            return true;
        }

        foreach (self::ACTIVE_STAGES as $stage) {
            if ($normalizedStage === $stage) {
                return true;
            }
        }

        return false;
    }

    public static function isClosed(?string $rawStage): bool
    {
        $normalizedStage = self::normalizeStage($rawStage);

        foreach (self::CLOSED_STAGES as $stage) {
            if ($normalizedStage === $stage) {
                return true;
            }
        }

        return false;
    }
}
