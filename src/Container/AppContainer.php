<?php

declare(strict_types=1);

namespace HouzzHunt\Container;

use HouzzHunt\Repositories\ActivityRepository;
use HouzzHunt\Repositories\LeadRepository;
use HouzzHunt\Repositories\ProjectRepository;
use HouzzHunt\Repositories\SchemaInspector;
use HouzzHunt\Repositories\UserRepository;
use HouzzHunt\Services\ActivityService;
use HouzzHunt\Services\AgentPerformanceService;
use HouzzHunt\Services\InventoryService;
use HouzzHunt\Services\LeadStatsService;
use HouzzHunt\Services\PerformanceService;
use HouzzHunt\Services\SearchService;
use PDO;

final class AppContainer
{
    private PDO $pdo;
    private array $datamap;
    private SchemaInspector $schemaInspector;

    public function __construct(PDO $pdo, array $datamap)
    {
        $this->pdo = $pdo;
        $this->datamap = $datamap;
        $this->schemaInspector = new SchemaInspector($pdo);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function datamap(): array
    {
        return $this->datamap;
    }

    public function leadRepository(): LeadRepository
    {
        return new LeadRepository($this->pdo, $this->datamap);
    }

    public function userRepository(): UserRepository
    {
        return new UserRepository($this->pdo, $this->datamap);
    }

    public function activityRepository(): ActivityRepository
    {
        return new ActivityRepository($this->pdo, $this->datamap);
    }

    public function projectRepository(): ProjectRepository
    {
        return new ProjectRepository($this->pdo, $this->datamap, $this->schemaInspector);
    }

    public function leadStatsService(): LeadStatsService
    {
        return new LeadStatsService($this->leadRepository(), $this->userRepository());
    }

    public function agentPerformanceService(): AgentPerformanceService
    {
        return new AgentPerformanceService($this->leadRepository(), $this->userRepository());
    }

    public function activityService(): ActivityService
    {
        return new ActivityService($this->activityRepository());
    }

    public function performanceService(): PerformanceService
    {
        return new PerformanceService($this->leadRepository(), $this->activityRepository());
    }

    public function inventoryService(): InventoryService
    {
        return new InventoryService($this->projectRepository());
    }

    public function searchService(): SearchService
    {
        return new SearchService($this->pdo, $this->datamap);
    }
}
