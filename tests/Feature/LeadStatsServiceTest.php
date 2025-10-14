<?php

declare(strict_types=1);

use HouzzHunt\Container\AppContainer;
use HouzzHunt\Services\LeadStatsService;
use HouzzHunt\Support\DateRange;
use PHPUnit\Framework\TestCase;

final class LeadStatsServiceTest extends TestCase
{
    private static ?AppContainer $container = null;

    protected function setUp(): void
    {
        if (self::$container === null) {
            self::$container = new AppContainer(hh_db(), hh_datamap());
        }
    }

    private function service(): LeadStatsService
    {
        return self::$container->leadStatsService();
    }

    public function testLeadCountersStructure(): void
    {
        $range = DateRange::fromPreset('last_30_days');
        $context = [
            'role' => 'admin',
            'user_id' => 0,
            'user_name' => 'Test Runner',
        ];

        $counters = $this->service()->leadCounters($range, $context);

        $this->assertIsArray($counters);
        foreach (['total_leads', 'hot_active', 'closed_leads', 'channel_partners'] as $metric) {
            $this->assertArrayHasKey($metric, $counters);
            $this->assertArrayHasKey('value', $counters[$metric]);
            $this->assertArrayHasKey('change_pct', $counters[$metric]);
        }
    }

    public function testLeadSourcesReturnsArray(): void
    {
        $range = DateRange::fromPreset('last_30_days');
        $context = [
            'role' => 'admin',
            'user_id' => 0,
            'user_name' => 'Test Runner',
        ];

        $sources = $this->service()->leadSources($range, $context);

        $this->assertIsArray($sources);
        foreach ($sources as $row) {
            $this->assertArrayHasKey('source', $row);
            $this->assertArrayHasKey('count', $row);
            $this->assertArrayHasKey('percentage', $row);
        }
    }
}
