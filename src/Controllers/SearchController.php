<?php

declare(strict_types=1);

namespace HouzzHunt\Controllers;

use HouzzHunt\Services\SearchService;

final class SearchController
{
    private SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * @param array{role:string,user_id:int,user_name:?string} $context
     */
    public function search(string $term, array $context): array
    {
        $results = $this->searchService->search($term, $context);

        return [
            'data' => $results,
            'meta' => [
                'query' => $term,
                'generated_at' => gmdate(DATE_ATOM),
            ],
        ];
    }
}
