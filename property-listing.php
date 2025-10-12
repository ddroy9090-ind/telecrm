<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$itemsPerPage = 6;
$currentPage = (int)filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'default' => 1,
            'min_range' => 1,
        ],
    ]
);

$sanitizeNumericString = static function ($value): string {
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);

    return $value !== '' && ctype_digit($value) ? $value : '';
};

$allowedSortOptions = [
    'price_desc',
    'price_asc',
    'name_asc',
    'newest',
    'oldest',
    'completion_date',
];

$filters = [
    'q' => trim((string)(filter_input(INPUT_GET, 'q', FILTER_UNSAFE_RAW) ?? '')),
    'project_name' => trim((string)(filter_input(INPUT_GET, 'project_name', FILTER_UNSAFE_RAW) ?? '')),
    'location' => trim((string)(filter_input(INPUT_GET, 'location', FILTER_UNSAFE_RAW) ?? '')),
    'property_type' => trim((string)(filter_input(INPUT_GET, 'property_type', FILTER_UNSAFE_RAW) ?? '')),
    'bedrooms' => trim((string)(filter_input(INPUT_GET, 'bedrooms', FILTER_UNSAFE_RAW) ?? '')),
    'location_query' => trim((string)(filter_input(INPUT_GET, 'location_query', FILTER_UNSAFE_RAW) ?? '')),
    'completion_year' => filter_input(INPUT_GET, 'completion_year', FILTER_VALIDATE_INT) ?: '',
    'min_price' => $sanitizeNumericString(filter_input(INPUT_GET, 'min_price', FILTER_UNSAFE_RAW)),
    'max_price' => $sanitizeNumericString(filter_input(INPUT_GET, 'max_price', FILTER_UNSAFE_RAW)),
    'sort' => trim((string)(filter_input(INPUT_GET, 'sort', FILTER_UNSAFE_RAW) ?? '')),
];

if ($filters['sort'] !== '' && !in_array($filters['sort'], $allowedSortOptions, true)) {
    $filters['sort'] = '';
}

$minPriceValue = $filters['min_price'] !== '' ? (float)$filters['min_price'] : null;
$maxPriceValue = $filters['max_price'] !== '' ? (float)$filters['max_price'] : null;
$effectiveMinPrice = $minPriceValue;
$effectiveMaxPrice = $maxPriceValue;
if ($effectiveMinPrice !== null && $effectiveMaxPrice !== null && $effectiveMinPrice > $effectiveMaxPrice) {
    $temp = $effectiveMinPrice;
    $effectiveMinPrice = $effectiveMaxPrice;
    $effectiveMaxPrice = $temp;
}

$parsePriceToNumber = static function ($price) {
    if (!is_string($price)) {
        return null;
    }

    $normalized = strtoupper($price);
    $normalized = str_replace(['AED', 'DHS'], '', $normalized);
    $normalized = str_replace([',', ' '], '', $normalized);
    $normalized = preg_replace('/[^0-9MK\.]/', '', $normalized);

    if (!is_string($normalized)) {
        return null;
    }

    if ($normalized === '') {
        return null;
    }

    $multiplier = 1;
    if (str_ends_with($normalized, 'M')) {
        $multiplier = 1000000;
        $normalized = substr($normalized, 0, -1);
    } elseif (str_ends_with($normalized, 'K')) {
        $multiplier = 1000;
        $normalized = substr($normalized, 0, -1);
    }

    if ($normalized === '' || !is_numeric($normalized)) {
        return null;
    }

    return (float)$normalized * $multiplier;
};

$filterOptions = [
    'locations' => [],
    'property_types' => [],
    'bedrooms' => [],
    'completion_years' => [],
];

$bedroomOptionMap = [];
$offplanProperties = [];
$propertyCount = 0;
$totalPages = 1;
$offset = 0;

try {
    $pdo = hh_db();

    $filterOptions['locations'] = array_values(array_filter(array_map(
        static fn($value) => is_string($value) ? trim($value) : '',
        $pdo->query('SELECT DISTINCT property_location FROM properties_list WHERE property_location IS NOT NULL AND TRIM(property_location) <> "" ORDER BY property_location ASC')->fetchAll(\PDO::FETCH_COLUMN)
    )));

    $filterOptions['property_types'] = array_values(array_filter(array_map(
        static fn($value) => is_string($value) ? trim($value) : '',
        $pdo->query('SELECT DISTINCT property_type FROM properties_list WHERE property_type IS NOT NULL AND TRIM(property_type) <> "" ORDER BY property_type ASC')->fetchAll(\PDO::FETCH_COLUMN)
    )));

    $rawBedrooms = $pdo->query('SELECT DISTINCT bedroom FROM properties_list WHERE bedroom IS NOT NULL AND TRIM(bedroom) <> ""')->fetchAll(\PDO::FETCH_COLUMN);
    if ($rawBedrooms) {
        foreach ($rawBedrooms as $value) {
            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value === '') {
                continue;
            }

            $label = $value;
            $lower = strtolower($value);
            if ($lower === 'studio') {
                $label = 'Studio';
            } elseif (is_numeric($value)) {
                $label = (int)$value === 1 ? '1 Bed' : $value . ' Beds';
            }

            $bedroomOptionMap[$value] = $label;
        }

        ksort($bedroomOptionMap, SORT_NATURAL);
        $filterOptions['bedrooms'] = $bedroomOptionMap;
    }

    $filterOptions['completion_years'] = array_values(array_filter(array_map(
        static fn($value) => $value !== null ? (int)$value : null,
        $pdo->query('SELECT DISTINCT YEAR(completion_date) AS completion_year FROM properties_list WHERE completion_date IS NOT NULL AND completion_date <> "0000-00-00" ORDER BY completion_year ASC')->fetchAll(\PDO::FETCH_COLUMN)
    ), static fn($value) => $value !== null && $value > 0));

    $filterClauses = [];
    $queryParams = [];

    if ($filters['q'] !== '') {
        $filterClauses[] = '(project_name LIKE :search OR property_title LIKE :search)';
        $queryParams[':search'] = '%' . $filters['q'] . '%';
    }

    if ($filters['project_name'] !== '') {
        $filterClauses[] = 'project_name LIKE :project_name_filter';
        $queryParams[':project_name_filter'] = '%' . $filters['project_name'] . '%';
    }

    if ($filters['location'] !== '') {
        $filterClauses[] = 'property_location = :location';
        $queryParams[':location'] = $filters['location'];
    }

    if ($filters['property_type'] !== '') {
        $filterClauses[] = 'property_type = :property_type';
        $queryParams[':property_type'] = $filters['property_type'];
    }

    if ($filters['bedrooms'] !== '') {
        $filterClauses[] = 'bedroom = :bedrooms';
        $queryParams[':bedrooms'] = $filters['bedrooms'];
    }

    if ($filters['location_query'] !== '') {
        $filterClauses[] = 'property_location LIKE :location_query';
        $queryParams[':location_query'] = '%' . $filters['location_query'] . '%';
    }

    if ($filters['completion_year'] !== '') {
        $filterClauses[] = 'completion_date IS NOT NULL AND completion_date <> "0000-00-00" AND YEAR(completion_date) = :completion_year';
        $queryParams[':completion_year'] = (int)$filters['completion_year'];
    }

    $whereClause = $filterClauses ? ' WHERE ' . implode(' AND ', $filterClauses) : '';

    $stmt = $pdo->prepare(
        'SELECT id, hero_banner, gallery_images, project_status, property_type, project_name, property_title, property_location, starting_price, bedroom, bathroom, total_area, completion_date, created_at
            FROM properties_list'
        . $whereClause .
        ' ORDER BY created_at DESC'
    );

    foreach ($queryParams as $param => $value) {
        $stmt->bindValue($param, $value);
    }

    $stmt->execute();
    $allProperties = $stmt->fetchAll();

    $filteredProperties = [];
    foreach ($allProperties as $property) {
        $priceNumeric = $parsePriceToNumber($property['starting_price'] ?? null);

        if ($effectiveMinPrice !== null && ($priceNumeric === null || $priceNumeric < $effectiveMinPrice)) {
            continue;
        }

        if ($effectiveMaxPrice !== null && ($priceNumeric === null || $priceNumeric > $effectiveMaxPrice)) {
            continue;
        }

        $filteredProperties[] = $property;
    }

    $filteredProperties = array_values($filteredProperties);

    $sortOption = $filters['sort'] ?: 'newest';

    $getComparableName = static function (array $property): string {
        $name = '';
        if (isset($property['project_name']) && is_string($property['project_name'])) {
            $name = trim($property['project_name']);
        }

        if ($name === '' && isset($property['property_title']) && is_string($property['property_title'])) {
            $name = trim($property['property_title']);
        }

        if ($name === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($name, 'UTF-8');
        }

        return strtolower($name);
    };

    $getCompletionDateTimestamp = static function (array $property): ?int {
        if (empty($property['completion_date']) || !is_string($property['completion_date'])) {
            return null;
        }

        $timestamp = strtotime($property['completion_date']);

        return $timestamp !== false ? $timestamp : null;
    };

    $getCreatedAtTimestamp = static function (array $property): ?int {
        if (empty($property['created_at']) || !is_string($property['created_at'])) {
            return null;
        }

        $timestamp = strtotime($property['created_at']);

        return $timestamp !== false ? $timestamp : null;
    };

    $getPriceValue = static function (array $property) use ($parsePriceToNumber): ?float {
        return $parsePriceToNumber($property['starting_price'] ?? null);
    };

    switch ($sortOption) {
        case 'price_desc':
            usort($filteredProperties, static function (array $a, array $b) use ($getPriceValue): int {
                $priceA = $getPriceValue($a);
                $priceB = $getPriceValue($b);

                if ($priceA === $priceB) {
                    return 0;
                }

                if ($priceA === null) {
                    return 1;
                }

                if ($priceB === null) {
                    return -1;
                }

                return $priceB <=> $priceA;
            });
            break;

        case 'price_asc':
            usort($filteredProperties, static function (array $a, array $b) use ($getPriceValue): int {
                $priceA = $getPriceValue($a);
                $priceB = $getPriceValue($b);

                if ($priceA === $priceB) {
                    return 0;
                }

                if ($priceA === null) {
                    return 1;
                }

                if ($priceB === null) {
                    return -1;
                }

                return $priceA <=> $priceB;
            });
            break;

        case 'name_asc':
            usort($filteredProperties, static function (array $a, array $b) use ($getComparableName): int {
                $nameA = $getComparableName($a);
                $nameB = $getComparableName($b);

                if ($nameA === $nameB) {
                    return 0;
                }

                if ($nameA === '') {
                    return 1;
                }

                if ($nameB === '') {
                    return -1;
                }

                return strcmp($nameA, $nameB);
            });
            break;

        case 'oldest':
            usort($filteredProperties, static function (array $a, array $b) use ($getCreatedAtTimestamp): int {
                $createdA = $getCreatedAtTimestamp($a);
                $createdB = $getCreatedAtTimestamp($b);

                if ($createdA === $createdB) {
                    return 0;
                }

                if ($createdA === null) {
                    return 1;
                }

                if ($createdB === null) {
                    return -1;
                }

                return $createdA <=> $createdB;
            });
            break;

        case 'completion_date':
            usort($filteredProperties, static function (array $a, array $b) use ($getCompletionDateTimestamp): int {
                $completionA = $getCompletionDateTimestamp($a);
                $completionB = $getCompletionDateTimestamp($b);

                if ($completionA === $completionB) {
                    return 0;
                }

                if ($completionA === null) {
                    return 1;
                }

                if ($completionB === null) {
                    return -1;
                }

                return $completionA <=> $completionB;
            });
            break;

        case 'newest':
        default:
            usort($filteredProperties, static function (array $a, array $b) use ($getCreatedAtTimestamp): int {
                $createdA = $getCreatedAtTimestamp($a);
                $createdB = $getCreatedAtTimestamp($b);

                if ($createdA === $createdB) {
                    return 0;
                }

                if ($createdA === null) {
                    return 1;
                }

                if ($createdB === null) {
                    return -1;
                }

                return $createdB <=> $createdA;
            });
            break;
    }
    $propertyCount = count($filteredProperties);

    if ($propertyCount === 0) {
        $currentPage = 1;
        $totalPages = 1;
        $offset = 0;
        $offplanProperties = [];
    } else {
        $totalPages = (int)ceil($propertyCount / $itemsPerPage);
        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }

        $offset = ($currentPage - 1) * $itemsPerPage;
        $offplanProperties = array_slice($filteredProperties, $offset, $itemsPerPage);
    }
} catch (Throwable $e) {
    $offplanProperties = [];
    $propertyCount = 0;
    $totalPages = 1;
    $currentPage = 1;
    $offset = 0;
}

$pageStart = $propertyCount > 0 ? $offset + 1 : 0;
$pageEnd = $propertyCount > 0 ? min($offset + count($offplanProperties), $propertyCount) : 0;
$propertyLabel = $propertyCount === 1 ? 'property' : 'properties';
$updatedLabel = date('F j, Y');

$uploadsBasePath = 'assets/uploads/';
$normalizeImagePath = static function (?string $path) use ($uploadsBasePath): ?string {
    if (!is_string($path)) {
        return null;
    }

    $path = trim(str_replace('\\', '/', $path));
    if ($path === '') {
        return null;
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
        return $path;
    }

    $path = ltrim($path, '/');

    $path = preg_replace('#^(?:admin/)?assets/uploads/#', '', $path);
    if ($path === null) {
        return null;
    }

    $path = preg_replace('#^uploads/#', '', $path);
    if ($path === null) {
        return null;
    }

    $path = ltrim($path, '/');
    if ($path === '') {
        return null;
    }

    $decodedPath = rawurldecode($path);
    $segments = array_values(array_filter(explode('/', $decodedPath), static fn($segment) => $segment !== ''));
    if ($segments === []) {
        return null;
    }

    $normalizedSegments = array_map(
        static fn(string $segment): string => str_replace('%2F', '/', rawurlencode($segment)),
        $segments
    );

    return $uploadsBasePath . implode('/', $normalizedSegments);
};

$title = 'Dubai Off-Plan Properties for Sale | High ROI Deals';

$iconSvgMap = [
    'map-pin' => <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 10c0 5-5.5 10.2-7.4 11.8a1 1 0 0 1-1.2 0C9.5 20.2 4 15 4 10a8 8 0 0 1 16 0z"></path>
            <circle cx="12" cy="10" r="3"></circle>
        </svg>
    SVG,
    'bed' => <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 20v-8a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8"></path>
            <path d="M3 16h18"></path>
            <path d="M7 12V9a3 3 0 0 1 6 0v3"></path>
            <path d="M21 20v-4"></path>
            <path d="M3 20v-4"></path>
        </svg>
    SVG,
    'bath' => <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12h18"></path>
            <path d="M6 12v4a4 4 0 0 0 4 4h4a4 4 0 0 0 4-4v-4"></path>
            <path d="M8 12V6a3 3 0 0 1 6 0v6"></path>
            <path d="M9 5a2 2 0 0 1 4 0"></path>
        </svg>
    SVG,
    'area' => <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="4" width="16" height="16" rx="2"></rect>
            <path d="M9 4v4"></path>
            <path d="M4 9h4"></path>
            <path d="M11 20v-4"></path>
            <path d="M20 15h-4"></path>
        </svg>
    SVG,
];

$renderIcon = static function (string $name) use ($iconSvgMap): string {
    return $iconSvgMap[$name] ?? '';
};

$minPriceOptions = [
    '' => 'Select Min',
    '300000' => 'AED 300,000',
    '500000' => 'AED 500,000',
    '1000000' => 'AED 1,000,000',
    '5000000' => 'AED 5,000,000',
    '10000000' => 'AED 10,000,000',
    '20000000' => 'AED 20,000,000',
];

$maxPriceOptions = [
    '' => 'Select Max',
    '1000000' => 'AED 1,000,000',
    '5000000' => 'AED 5,000,000',
    '10000000' => 'AED 10,000,000',
    '20000000' => 'AED 20,000,000',
    '30000000' => 'AED 30,000,000',
    '50000000' => 'AED 50,000,000+',
];

$filterQueryParams = [];
foreach (['q', 'project_name', 'location', 'property_type', 'bedrooms', 'location_query', 'completion_year', 'min_price', 'max_price', 'sort'] as $key) {
    $value = $filters[$key] ?? '';
    if ($value !== '' && $value !== null) {
        $filterQueryParams[$key] = (string)$value;
    }
}

$buildPageUrl = static function (int $page) use ($filterQueryParams): string {
    $params = $filterQueryParams;
    $params['page'] = $page;

    return '?' . http_build_query($params);
};
?>
<?php include 'includes/common-header.php'; ?>

<div id="adminPanel">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Topbar -->
    <?php include 'includes/topbar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <h1 class="main-heading">Property Listing</h1>
        <p class="subheading">Review and manage all properties in your portfolio.</p>
        <!-- parent: .hh-hero-01 -->
        <div class="hh-hero-01">
            <div class="container">
                <!-- Hero copy -->
                <div class="row">
                    <div class="col-12">
                        <header>
                            <h1>Exclusive Off-Plan Properties</h1>
                            <p>Discover handpicked off-plan projects across Dubai's most prestigious locations.
                                Exclusive prices, flexible payment plans, and prime investment opportunities.</p>
                        </header>
                    </div>
                </div>

                <!-- Property Details Filter Sections -->
                <div class="row">
                    <div class="col-12">
                        <form id="offplan-filter-form" method="get" action="property-listing.php">
                            <input type="hidden" name="page" value="1">
                            <div class="container">
                                <div class="row align-items-center mb-4">

                                    <!-- Project Name -->
                                    <div class="col-lg-3">

                                        <label>
                                            <span>
                                                <!-- blueprint -->
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-layout-dashboard">
                                                    <rect x="3" y="3" width="7" height="9" rx="1" />
                                                    <rect x="14" y="3" width="7" height="5" rx="1" />
                                                    <rect x="14" y="10" width="7" height="11" rx="1" />
                                                    <rect x="3" y="15" width="7" height="6" rx="1" />
                                                </svg>
                                                Project Name
                                            </span>
                                            <input type="search" name="project_name" placeholder="Enter Project Name" value="<?= htmlspecialchars($filters['project_name'], ENT_QUOTES, 'UTF-8') ?>">
                                        </label>

                                    </div>

                                    <!-- Location -->
                                    <div class="col-lg-3">
                                        <label>
                                            <span>
                                                <!-- location pin -->
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin w-4 h-4 mr-1" data-lov-id="src/components/PropertyCard.tsx:63:12" data-lov-name="MapPin" data-component-path="src/components/PropertyCard.tsx" data-component-line="63" data-component-file="PropertyCard.tsx" data-component-name="MapPin" data-component-content="%7B%22className%22%3A%22w-4%20h-4%20mr-1%22%7D">
                                                    <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path>
                                                    <circle cx="12" cy="10" r="3"></circle>
                                                </svg>
                                                Location
                                            </span>
                                            <select class="select-dropDownClass" name="location">
                                                <option value="">Select Location</option>
                                                <?php foreach ($filterOptions['locations'] as $locationOption): ?>
                                                    <option value="<?= htmlspecialchars($locationOption, ENT_QUOTES, 'UTF-8') ?>" <?= $filters['location'] === $locationOption ? 'selected' : '' ?>><?= htmlspecialchars($locationOption, ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>

                                        </label>
                                    </div>

                                    <!-- Type -->
                                    <div class="col-lg-3">
                                        <label>
                                            <span>
                                                <!-- home -->
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-house w-4 h-4" data-lov-id="src/components/SearchFilters.tsx:34:12" data-lov-name="Home" data-component-path="src/components/SearchFilters.tsx" data-component-line="34" data-component-file="SearchFilters.tsx" data-component-name="Home" data-component-content="%7B%22className%22%3A%22w-4%20h-4%22%7D">
                                                    <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
                                                    <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                                </svg>
                                                Type
                                            </span>
                                            <select id="property-type" class="select-dropDownClass" name="property_type">
                                                <option value="">Property Type</option>
                                                <?php foreach ($filterOptions['property_types'] as $typeOption): ?>
                                                    <option value="<?= htmlspecialchars($typeOption, ENT_QUOTES, 'UTF-8') ?>" <?= $filters['property_type'] === $typeOption ? 'selected' : '' ?>><?= htmlspecialchars($typeOption, ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>

                                        </label>
                                    </div>

                                    <!-- Bedrooms -->
                                    <div class="col-lg-3">
                                        <label>
                                            <span>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bed w-4 h-4" data-lov-id="src/components/PropertyCard.tsx:71:14" data-lov-name="Bed" data-component-path="src/components/PropertyCard.tsx" data-component-line="71" data-component-file="PropertyCard.tsx" data-component-name="Bed" data-component-content="%7B%22className%22%3A%22w-4%20h-4%22%7D">
                                                    <path d="M2 4v16"></path>
                                                    <path d="M2 8h18a2 2 0 0 1 2 2v10"></path>
                                                    <path d="M2 17h20"></path>
                                                    <path d="M6 8v9"></path>
                                                </svg>
                                                Bedrooms
                                            </span>
                                            <select class="select-dropDownClass" name="bedrooms">
                                                <option value="">All Bedrooms</option>
                                                <?php foreach ($filterOptions['bedrooms'] as $bedroomValue => $bedroomLabel): ?>
                                                    <option value="<?= htmlspecialchars((string)$bedroomValue, ENT_QUOTES, 'UTF-8') ?>" <?= (string)$filters['bedrooms'] === (string)$bedroomValue ? 'selected' : '' ?>><?= htmlspecialchars($bedroomLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    </div>

                                </div>

                                <div class="row align-items-center">

                                    <!-- Search input -->
                                    <div class="col-lg-3">

                                        <label>
                                            <span>
                                                <!-- search -->
                                                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M21 21 15.8 15.8M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" fill="none" stroke="currentColor" stroke-width="2" />
                                                </svg>
                                                Search Location
                                            </span>
                                            <input type="search" name="location_query" placeholder="Enter Property Location.." value="<?= htmlspecialchars($filters['location_query'], ENT_QUOTES, 'UTF-8') ?>">
                                        </label>
                                    </div>

                                    <!-- Completion Year -->
                                    <div class="col-lg-3">
                                        <label>
                                            <span>
                                                <!-- Completion Year pin -->
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-calendar w-4 h-4 mr-1">
                                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                                    <line x1="16" y1="2" x2="16" y2="6" />
                                                    <line x1="8" y1="2" x2="8" y2="6" />
                                                    <line x1="3" y1="10" x2="21" y2="10" />
                                                </svg>
                                                Completion Year
                                            </span>
                                            <select class="select-dropDownClass" name="completion_year">
                                                <option value="">Completion Year</option>
                                                <?php foreach ($filterOptions['completion_years'] as $completionYear): ?>
                                                    <option value="<?= (int)$completionYear ?>" <?= (string)$filters['completion_year'] === (string)$completionYear ? 'selected' : '' ?>><?= (int)$completionYear ?></option>
                                                <?php endforeach; ?>
                                            </select>


                                        </label>
                                    </div>

                                    <!-- Price Range -->
                                    <div class="col-lg-2">
                                        <label>
                                            <span>
                                                <!-- dollar icon -->
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-dollar-sign w-4 h-4">
                                                    <line x1="12" x2="12" y1="2" y2="22"></line>
                                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                                </svg>
                                                Min Price
                                            </span>
                                            <select class="select-dropDownClass" name="min_price">
                                                <?php foreach ($minPriceOptions as $value => $label): ?>
                                                    <?php $optionValue = (string)$value; ?>
                                                    <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= (string)$filters['min_price'] === $optionValue ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    </div>

                                    <div class="col-lg-2">
                                        <label>
                                            <span>
                                                <!-- dollar icon -->
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-dollar-sign w-4 h-4">
                                                    <line x1="12" x2="12" y1="2" y2="22"></line>
                                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                                </svg>
                                                Max Price
                                            </span>
                                            <select class="select-dropDownClass" name="max_price">
                                                <?php foreach ($maxPriceOptions as $value => $label): ?>
                                                    <?php $optionValue = (string)$value; ?>
                                                    <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= (string)$filters['max_price'] === $optionValue ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    </div>

                                    <!-- Actions -->
                                    <div class="col-lg-1">
                                        <div class="mt-4">
                                            <button type="submit">Search</button>
                                        </div>
                                    </div>

                                    <!-- Reset Button -->
                                    <div class="col-lg-1">
                                        <div class="mt-4">
                                            <button
                                                type="button"
                                                class="btn btn-danger"
                                                style="background-color: #d01f28; border: none; height: 48px; padding: 0 24px;"
                                                onclick="window.location.href='property-listing.php';">
                                                Reset
                                            </button>
                                        </div>
                                    </div>

                                </div>

                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>


        <!-- parent: .hh-properties-01 -->
        <div class="hh-properties-01">
            <div class="container">

                <!-- Heading + sort -->
                <div class="row">
                    <div class="col-12">
                        <div class="hh-properties-01-head">
                            <div>
                                <h2>Investment Opportunities</h2>
                                <p>
                                    <?php if ($propertyCount > 0): ?>
                                        Showing <?= htmlspecialchars((string)$pageStart, ENT_QUOTES, 'UTF-8') ?>–<?= htmlspecialchars((string)$pageEnd, ENT_QUOTES, 'UTF-8') ?> of <?= htmlspecialchars((string)$propertyCount, ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($propertyLabel, ENT_QUOTES, 'UTF-8') ?> • Updated <?= htmlspecialchars($updatedLabel, ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        Showing 0 <?= htmlspecialchars($propertyLabel, ENT_QUOTES, 'UTF-8') ?> • Updated <?= htmlspecialchars($updatedLabel, ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div>
                                <label>
                                    Sort by:
                                    <select
                                        name="sort"
                                        form="offplan-filter-form"
                                        onchange="document.getElementById('offplan-filter-form').submit();">
                                        <option value="price_desc" <?= $filters['sort'] === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                                        <option value="price_asc" <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                                        <option value="name_asc" <?= $filters['sort'] === 'name_asc' ? 'selected' : '' ?>>A to Z</option>
                                        <option value="newest" <?= $filters['sort'] === 'newest' || $filters['sort'] === '' ? 'selected' : '' ?>>Newly</option>
                                        <option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                                        <option value="completion_date" <?= $filters['sort'] === 'completion_date' ? 'selected' : '' ?>>Date</option>
                                    </select>
                                </label>
                                <div class="hh-properties-01-toggle">
                                    <button type="button" data-view="grid" class="active">
                                        <!-- grid icon -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-grid3x3 w-4 h-4" data-lov-id="src/components/PropertyListing.tsx:142:16" data-lov-name="Grid3X3" data-component-path="src/components/PropertyListing.tsx" data-component-line="142" data-component-file="PropertyListing.tsx" data-component-name="Grid3X3" data-component-content="%7B%22className%22%3A%22w-4%20h-4%22%7D">
                                            <rect width="18" height="18" x="3" y="3" rx="2"></rect>
                                            <path d="M3 9h18"></path>
                                            <path d="M3 15h18"></path>
                                            <path d="M9 3v18"></path>
                                            <path d="M15 3v18"></path>
                                        </svg>
                                    </button>
                                    <button type="button" data-view="list">
                                        <!-- list icon -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list w-4 h-4" data-lov-id="src/components/PropertyListing.tsx:145:16" data-lov-name="List" data-component-path="src/components/PropertyListing.tsx" data-component-line="145" data-component-file="PropertyListing.tsx" data-component-name="List" data-component-content="%7B%22className%22%3A%22w-4%20h-4%22%7D">
                                            <path d="M3 12h.01"></path>
                                            <path d="M3 18h.01"></path>
                                            <path d="M3 6h.01"></path>
                                            <path d="M8 12h13"></path>
                                            <path d="M8 18h13"></path>
                                            <path d="M8 6h13"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cards -->
                <div class="row hh-properties-01-grid">
                    <?php if ($offplanProperties): ?>
                        <?php foreach ($offplanProperties as $property): ?>
                            <?php
                            $heroBanner = $normalizeImagePath($property['hero_banner'] ?? null) ?? '';
                            $galleryImages = [];
                            if (!empty($property['gallery_images'])) {
                                $decodedGallery = json_decode((string)$property['gallery_images'], true);
                                if (is_array($decodedGallery)) {
                                    foreach ($decodedGallery as $imagePath) {
                                        $normalized = $normalizeImagePath(is_string($imagePath) ? $imagePath : null);
                                        if ($normalized !== null) {
                                            $galleryImages[] = $normalized;
                                        }
                                    }
                                }
                            }

                            $primaryImage = $heroBanner !== '' ? $heroBanner : ($galleryImages[0] ?? 'assets/images/placeholder-property.svg');
                            $projectName = trim((string)($property['project_name'] ?? ''));
                            if ($projectName === '') {
                                $projectName = trim((string)($property['property_title'] ?? ''));
                            }

                            $specs = [];
                            $bedroomValue = isset($property['bedroom']) ? trim((string)$property['bedroom']) : '';
                            if ($bedroomValue !== '') {
                                if (is_numeric($bedroomValue)) {
                                    $bedroomLabel = (int)$bedroomValue === 1 ? '1 Bed' : $bedroomValue . ' Beds';
                                } elseif (stripos($bedroomValue, 'bed') !== false || stripos($bedroomValue, 'studio') !== false) {
                                    $bedroomLabel = $bedroomValue;
                                } else {
                                    $bedroomLabel = $bedroomValue . ' Beds';
                                }
                                $specs[] = ['icon' => 'bed', 'text' => $bedroomLabel];
                            }
                            if (!empty($property['bathroom'])) {
                                $specs[] = ['icon' => 'bath', 'text' => trim((string)$property['bathroom']) . ' Baths'];
                            }
                            if (!empty($property['total_area'])) {
                                $specs[] = ['icon' => 'area', 'text' => trim((string)$property['total_area'])];
                            }

                            $priceCurrency = '';
                            $priceValue = '';
                            $rawPrice = trim((string)($property['starting_price'] ?? ''));
                            if ($rawPrice !== '') {
                                $priceDisplay = stripos($rawPrice, 'aed') === false ? 'AED ' . $rawPrice : $rawPrice;
                                if (stripos($priceDisplay, 'aed') === 0) {
                                    $priceCurrency = 'AED';
                                    $priceValue = trim(substr($priceDisplay, 3));
                                } else {
                                    $priceValue = $priceDisplay;
                                }
                            }
                            ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <a href="property-details.php?id=<?= (int)($property['id'] ?? 0) ?>" class="property-link">
                                    <article>
                                        <div class="hh-properties-01-img">
                                            <img src="<?= htmlspecialchars($primaryImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($projectName !== '' ? $projectName : 'Project', ENT_QUOTES, 'UTF-8') ?>">
                                            <div class="hh-properties-01-tags">
                                                <?php if (!empty($property['project_status'])): ?>
                                                    <span class="green"><?= htmlspecialchars($property['project_status'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($property['property_type'])): ?>
                                                    <span><?= htmlspecialchars($property['property_type'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="hh-properties-01-fav" aria-label="Save">♥</button>
                                        </div>
                                        <div class="hh-properties-01-body">
                                            <h3><?= htmlspecialchars($projectName !== '' ? $projectName : 'Untitled Project', ENT_QUOTES, 'UTF-8') ?></h3>
                                            <?php if (!empty($property['property_location'])): ?>
                                                <p>
                                                    <span class="spec-icon" aria-hidden="true"><?= $renderIcon('map-pin') ?></span>
                                                    <?= htmlspecialchars($property['property_location'], ENT_QUOTES, 'UTF-8') ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($specs): ?>
                                                <ul>
                                                    <?php foreach ($specs as $spec): ?>
                                                        <li>
                                                            <span class="spec-icon" aria-hidden="true"><?= $renderIcon($spec['icon']) ?></span>
                                                            <?= htmlspecialchars($spec['text'], ENT_QUOTES, 'UTF-8') ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                            <div class="hh-properties-01-foot">
                                                <?php if ($priceValue !== ''): ?>
                                                    <strong>
                                                        <?php if ($priceCurrency !== ''): ?>
                                                            <span><?= htmlspecialchars($priceCurrency, ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php endif; ?>
                                                        <?= htmlspecialchars($priceValue, ENT_QUOTES, 'UTF-8') ?>
                                                    </strong>
                                                <?php else: ?>
                                                    <strong>Price on request</strong>
                                                <?php endif; ?>
                                                <span class="details-link">View Details</span>
                                            </div>
                                        </div>
                                    </article>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <p class="mb-0">No off-plan properties available right now. Please check back soon.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="hh-pagination" aria-label="Off-plan properties pagination">
                        <ul>
                            <li class="prev<?= $currentPage <= 1 ? ' disabled' : '' ?>">
                                <?php if ($currentPage > 1): ?>
                                    <a href="<?= htmlspecialchars($buildPageUrl($currentPage - 1), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
                                <?php else: ?>
                                    <span>Previous</span>
                                <?php endif; ?>
                            </li>

                            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                                <li class="<?= $page === $currentPage ? 'active' : '' ?>">
                                    <?php if ($page === $currentPage): ?>
                                        <span><?= (int)$page ?></span>
                                    <?php else: ?>
                                        <a href="<?= htmlspecialchars($buildPageUrl($page), ENT_QUOTES, 'UTF-8') ?>"><?= (int)$page ?></a>
                                    <?php endif; ?>
                                </li>
                            <?php endfor; ?>

                            <li class="next<?= $currentPage >= $totalPages ? ' disabled' : '' ?>">
                                <?php if ($currentPage < $totalPages): ?>
                                    <a href="<?= htmlspecialchars($buildPageUrl($currentPage + 1), ENT_QUOTES, 'UTF-8') ?>">Next</a>
                                <?php else: ?>
                                    <span>Next</span>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            </div>
        </div>


        <script>
            document.querySelectorAll('.hh-properties-01-toggle button').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.hh-properties-01-toggle button')
                        .forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');

                    const grid = document.querySelector('.hh-properties-01-grid');
                    if (btn.dataset.view === 'list') {
                        grid.classList.add('list-view');
                    } else {
                        grid.classList.remove('list-view');
                    }
                });
            });
        </script>

        <style>
            .hh-properties-01-grid.list-view .col-12 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .hh-properties-01-grid.list-view article {
                display: flex;
                gap: 20px;
            }

            .hh-properties-01-grid.list-view .hh-properties-01-img {
                flex: 0 0 40%;
            }

            .hh-properties-01-grid.list-view .hh-properties-01-body {
                flex: 1;
            }

            .hh-pagination {
                margin-top: 40px;
                text-align: center;
                position: inherit;
            }

            .hh-pagination ul {
                list-style: none;
                padding: 0;
                margin: 0;
                display: inline-flex;
                gap: 8px;
            }

            .hh-pagination li {
                display: inline-flex;
            }

            .hh-pagination a,
            .hh-pagination span {
                display: inline-block;
                padding: 8px 14px;
                border: 1px solid #d0d4dc;
                border-radius: 999px;
                color: #004a44;
                text-decoration: none;
                font-size: 14px;
                transition: all 0.2s ease-in-out;
            }

            .hh-pagination a:hover {
                background-color: #004a44;
                color: #fff;
            }

            .hh-pagination li.active span {
                background-color: #004a44;
                color: #fff;
                cursor: default;
            }

            .hh-pagination li.disabled span {
                opacity: 0.5;
                cursor: not-allowed;
            }
        </style>
    </main>
</div>

<?php include 'includes/common-footer.php'; ?>