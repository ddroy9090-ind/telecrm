<?php

declare(strict_types=1);

use HouzzHunt\Container\AppContainer;
use HouzzHunt\Services\PerformanceService;
use HouzzHunt\Support\DateRange;
use PHPUnit\Framework\TestCase;

final class PerformanceServiceTest extends TestCase
{
    private static ?AppContainer $container = null;

    protected function setUp(): void
    {
        if (self::$container === null) {
            self::$container = new AppContainer(hh_db(), hh_datamap());
        }
    }

    private function service(): PerformanceService
    {
        return self::$container->performanceService();
    }

    public function testPerformanceMetricsHaveExpectedKeys(): void
    {
        $range = DateRange::fromPreset('last_30_days');
        $context = [
            'role' => 'admin',
            'user_id' => 0,
            'user_name' => 'Test Runner',
        ];

        $metrics = $this->service()->performance($range, $context);

        foreach (['target_achievement', 'avg_response_time_hours', 'lead_engagement_pct', 'deal_velocity_days'] as $metric) {
            $this->assertArrayHasKey($metric, $metrics);
            $this->assertArrayHasKey('value', $metrics[$metric]);
            $this->assertArrayHasKey('unit', $metrics[$metric]);
        }
    }
}
