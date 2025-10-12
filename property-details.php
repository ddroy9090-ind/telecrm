<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

hh_session_start();

$propertyId = isset($_GET['id']) ? (int) ($_GET['id'] ?? 0) : 0;

$leadFormError = $_SESSION['offplan_lead_error'] ?? null;
unset($_SESSION['offplan_lead_error']);

if ($propertyId <= 0) {
    http_response_code(404);
    echo 'Property not found.';
    exit;
}

try {
    $pdo = hh_db();
    $stmt = $pdo->prepare('SELECT * FROM properties_list WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $propertyId]);
    $property = $stmt->fetch();
} catch (Throwable $e) {
    $property = false;
}

if (!$property) {
    http_response_code(404);
    echo 'Property not found.';
    exit;
}

$decodeList = static function (?string $json): array {
    if (!$json) {
        return [];
    }

    try {
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return [];
    }

    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_filter(
        $decoded,
        static fn($value): bool => is_string($value) && $value !== '' || (is_array($value) && !empty(array_filter($value, static fn($v) => $v !== '' && $v !== null)))
    ));
};

$extractParagraphs = static function ($value): array {
    if (!is_string($value)) {
        return [];
    }

    $value = trim($value);
    if ($value === '') {
        return [];
    }

    $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $withBreaks = preg_replace(
        [
            '#<\s*br\s*/?>#i',
            '#<\s*/p\s*>#i',
            '#<\s*/div\s*>#i',
            '#<\s*/li\s*>#i',
            '#<\s*/h[1-6]\s*>#i',
            '#<\s*/tr\s*>#i',
            '#<\s*/table\s*>#i',
            '#<\s*/ul\s*>#i',
            '#<\s*/ol\s*>#i',
        ],
        "\n",
        $decoded
    );

    $withBreaks = preg_replace('#<\s*li\b[^>]*>#i', '- ', $withBreaks);
    $withBreaks = preg_replace('#<\s*(p|div|h[1-6]|tr|td|th)\b[^>]*>#i', '', $withBreaks);

    $withoutTags = strip_tags($withBreaks);
    $normalized = preg_replace("/\r\n|\r|\n/", "\n", $withoutTags);

    return array_values(array_filter(array_map(
        static function (string $paragraph): string {
            $paragraph = trim(preg_replace('/\s+/u', ' ', $paragraph));
            return $paragraph;
        },
        preg_split("/\n+/", $normalized) ?: []
    ), static fn($paragraph): bool => $paragraph !== ''));
};

$uploadsBasePath = 'admin/assets/uploads/properties/';
$legacyUploadsPrefix = 'assets/uploads/properties/';
$normalizeImagePath = static function (?string $path) use ($uploadsBasePath, $legacyUploadsPrefix): ?string {
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

    if (str_starts_with($path, $uploadsBasePath)) {
        return $path;
    }

    if (str_starts_with($path, $legacyUploadsPrefix)) {
        return $uploadsBasePath . substr($path, strlen($legacyUploadsPrefix));
    }

    return $uploadsBasePath . $path;
};

$heroBanner = is_string($property['hero_banner'] ?? '') && $property['hero_banner'] !== ''
    ? $normalizeImagePath($property['hero_banner'])
    : null;

$galleryImages = array_values(array_filter(
    array_map(
        fn($path): ?string => $normalizeImagePath(is_string($path) ? $path : null),
        $decodeList($property['gallery_images'] ?? null)
    )
));

if (!$galleryImages && $heroBanner) {
    $galleryImages[] = $heroBanner;
}

$primaryImage = $heroBanner ?? ($galleryImages[0] ?? 'assets/images/offplan/breez-by-danube.webp');

if (!$galleryImages) {
    $galleryImages[] = $primaryImage;
}

$galleryCount = count($galleryImages);

$floorPlansRaw = $decodeList($property['floor_plans'] ?? null);
$floorPlans = [];
foreach ($floorPlansRaw as $plan) {
    if (!is_array($plan)) {
        continue;
    }

    $floorPlans[] = [
        'title' => isset($plan['title']) && is_string($plan['title']) ? trim($plan['title']) : '',
        'area' => isset($plan['area']) && is_string($plan['area']) ? trim($plan['area']) : '',
        'price' => isset($plan['price']) && is_string($plan['price']) ? trim($plan['price']) : '',
        'file' => isset($plan['file']) && is_string($plan['file'])
            ? ($normalizeImagePath($plan['file']) ?? trim($plan['file']))
            : '',
    ];
}

$locationAccessRaw = $decodeList($property['location_accessibility'] ?? null);
$locationAccess = [];
foreach ($locationAccessRaw as $item) {
    if (!is_array($item)) {
        continue;
    }

    $landmark = isset($item['landmark_name']) && is_string($item['landmark_name']) ? trim($item['landmark_name']) : '';
    $distance = isset($item['distance_time']) && is_string($item['distance_time']) ? trim($item['distance_time']) : '';
    $category = isset($item['category']) && is_string($item['category']) ? trim($item['category']) : '';

    if ($landmark === '' && $distance === '' && $category === '') {
        continue;
    }

    $locationAccess[] = [
        'landmark' => $landmark,
        'distance' => $distance,
        'category' => $category,
    ];
}

if (!$locationAccess) {
    $landmark = trim((string) ($property['landmark_name'] ?? ''));
    $distance = trim((string) ($property['distance_time'] ?? ''));
    $category = trim((string) ($property['category'] ?? ''));

    if ($landmark !== '' || $distance !== '' || $category !== '') {
        $locationAccess[] = [
            'landmark' => $landmark,
            'distance' => $distance,
            'category' => $category,
        ];
    }
}

$completionDate = null;
if (!empty($property['completion_date'])) {
    try {
        $completionDate = (new DateTime((string) $property['completion_date']))->format('F Y');
    } catch (Throwable $e) {
        $completionDate = trim((string) $property['completion_date']);
    }
}

$startingPrice = trim((string) ($property['starting_price'] ?? ''));
$startingPriceDisplay = $startingPrice;
if ($startingPriceDisplay !== '' && stripos($startingPriceDisplay, 'aed') === false) {
    $startingPriceDisplay = 'AED ' . $startingPriceDisplay;
}
$startingPriceCurrency = '';
$startingPriceValue = '';
if ($startingPriceDisplay !== '') {
    if (stripos($startingPriceDisplay, 'aed') === 0) {
        $startingPriceCurrency = 'AED';
        $startingPriceValue = trim(substr($startingPriceDisplay, 3));
    } else {
        $startingPriceValue = $startingPriceDisplay;
    }
}

$developerLogo = is_string($property['developer_logo'] ?? '') && $property['developer_logo'] !== ''
    ? $normalizeImagePath($property['developer_logo'])
    : null;

$permitBarcode = is_string($property['permit_barcode'] ?? '') && $property['permit_barcode'] !== ''
    ? $normalizeImagePath($property['permit_barcode'])
    : null;

$videoLink = trim((string) ($property['video_link'] ?? ''));
$videoTitle = trim((string) ($property['video_title'] ?? ''));
$videoSubtitle = trim((string) ($property['video_subtitle'] ?? ''));
$videoDuration = trim((string) ($property['video_duration'] ?? ''));
$videoViews = trim((string) ($property['video_views'] ?? ''));
$videoTagsRaw = $decodeList($property['video_tags'] ?? null);
$videoTags = array_values(array_filter(array_map(
    static fn($tag): string => is_string($tag) ? trim($tag) : '',
    $videoTagsRaw
), static fn($tag): bool => $tag !== ''));
$locationMap = trim((string) ($property['location_map'] ?? ''));
$locationHighlight = trim((string) ($property['location_highlight'] ?? ''));
if ($locationHighlight === '' && isset($property['property_location'])) {
    $locationHighlight = trim((string) $property['property_location']);
}
$brochure = trim((string) ($property['brochure'] ?? ''));
if ($brochure !== '') {
    $normalizedBrochure = $normalizeImagePath($brochure);
    if ($normalizedBrochure !== null) {
        $brochure = $normalizedBrochure;
    }
}

$featureItems = array_values(array_filter([
    $property['project_status'] ?? null,
    $property['property_type'] ? 'Property Type: ' . $property['property_type'] : null,
    $property['bedroom'] ? 'Bedrooms: ' . $property['bedroom'] : null,
    $property['bathroom'] ? 'Bathrooms: ' . $property['bathroom'] : null,
    $property['parking'] ? 'Parking: ' . $property['parking'] : null,
    $property['total_area'] ? 'Total Area: ' . $property['total_area'] : null,
    $completionDate ? 'Completion: ' . $completionDate : null,
]));

if (!empty($property['project_name'])) {
    array_unshift($featureItems, 'Project Name: ' . trim((string) $property['project_name']));
}

$amenitiesList = [];
$amenitiesRaw = $decodeList($property['amenities'] ?? null);
foreach ($amenitiesRaw as $amenity) {
    if (is_string($amenity)) {
        $amenity = trim($amenity);
        if ($amenity !== '') {
            $amenitiesList[] = $amenity;
        }
    }
}

$resolveAmenityIcon = static function (string $label): string {
    $normalized = trim($label);
    if ($normalized === '') {
        return 'bi bi-check-circle';
    }

    $lower = function_exists('mb_strtolower') ? mb_strtolower($normalized) : strtolower($normalized);

    $iconMap = [
        ['keywords' => ['pool', 'swim'], 'icon' => 'bi bi-water'],
        ['keywords' => ['gym', 'fitness'], 'icon' => 'bi bi-activity'],
        ['keywords' => ['parking'], 'icon' => 'bi bi-p-square'],
        ['keywords' => ['security', 'guard'], 'icon' => 'bi bi-shield-check'],
        ['keywords' => ['park', 'garden', 'green'], 'icon' => 'bi bi-tree'],
        ['keywords' => ['bbq', 'barbecue', 'grill'], 'icon' => 'bi bi-fire'],
        ['keywords' => ['play area', 'playground', 'kids', 'children'], 'icon' => 'bi bi-emoji-smile'],
        ['keywords' => ['spa', 'wellness'], 'icon' => 'bi bi-flower1'],
        ['keywords' => ['beach'], 'icon' => 'bi bi-sun'],
        ['keywords' => ['shopping', 'mall', 'retail'], 'icon' => 'bi bi-bag'],
        ['keywords' => ['school', 'education'], 'icon' => 'bi bi-mortarboard'],
        ['keywords' => ['hospital', 'clinic', 'medical'], 'icon' => 'bi bi-hospital'],
        ['keywords' => ['wifi', 'internet'], 'icon' => 'bi bi-wifi'],
        ['keywords' => ['clubhouse', 'club house', 'community hall'], 'icon' => 'bi bi-house'],
        ['keywords' => ['lobby'], 'icon' => 'bi bi-door-open'],
        ['keywords' => ['air condition', 'ac'], 'icon' => 'bi bi-snow'],
        ['keywords' => ['sauna', 'steam'], 'icon' => 'bi bi-thermometer-half'],
        ['keywords' => ['cinema', 'theatre', 'theater'], 'icon' => 'bi bi-film'],
        ['keywords' => ['tennis'], 'icon' => 'bi bi-tennis-ball'],
        ['keywords' => ['basketball'], 'icon' => 'fa-regular fa-person-basketball'],
        ['keywords' => ['golf'], 'icon' => 'bi bi-flag'],
        ['keywords' => ['jogging', 'running', 'cycle'], 'icon' => 'bi bi-person-running'],
        ['keywords' => ['elevator', 'lift'], 'icon' => 'bi bi-arrow-down-up'],
    ];

    foreach ($iconMap as $entry) {
        foreach ($entry['keywords'] as $keyword) {
            if ($keyword !== '' && str_contains($lower, $keyword)) {
                return $entry['icon'];
            }
        }
    }

    return 'bi bi-check-circle';
};

$aboutProjectParagraphs = $extractParagraphs($property['about_project'] ?? null);

$aboutDeveloperParagraphs = $extractParagraphs($property['about_developer'] ?? null);

$specItems = [];
if (!empty($property['bedroom'])) {
    $specItems[] = ['icon' => 'assets/icons/bed.png', 'label' => trim((string) $property['bedroom']), 'suffix' => ' Bedrooms'];
}
if (!empty($property['bathroom'])) {
    $specItems[] = ['icon' => 'assets/icons/bathroom.png', 'label' => trim((string) $property['bathroom']), 'suffix' => ' Bathrooms'];
}
if (!empty($property['parking'])) {
    $specItems[] = ['icon' => 'assets/icons/parking.png', 'label' => trim((string) $property['parking']), 'suffix' => ' Parking'];
}
if (!empty($property['total_area'])) {
    $specItems[] = ['icon' => 'assets/icons/area.png', 'label' => trim((string) $property['total_area']), 'suffix' => ''];
}
if ($completionDate) {
    $specItems[] = ['icon' => 'assets/icons/calendar.png', 'label' => $completionDate, 'suffix' => ' Completion'];
}

$investmentHighlights = array_filter([
    ['label' => 'ROI Potential', 'value' => $property['roi_potential'] ?? null, 'note' => ''],
    ['label' => 'Capital Growth', 'value' => $property['capital_growth'] ?? null, 'note' => ''],
    ['label' => 'Occupancy Rate', 'value' => $property['occupancy_rate'] ?? null, 'note' => ''],
    ['label' => 'Resale Value', 'value' => $property['resale_value'] ?? null, 'note' => ''],
], static fn($item) => isset($item['value']) && trim((string) $item['value']) !== '');

$paymentSchedule = array_filter([
    ['title' => 'Booking Amount', 'percentage' => $property['booking_percentage'] ?? null, 'amount' => $property['booking_amount'] ?? null],
    ['title' => 'During Construction', 'percentage' => $property['during_construction_percentage'] ?? null, 'amount' => $property['during_construction_amount'] ?? null],
    ['title' => 'On Handover', 'percentage' => $property['handover_percentage'] ?? null, 'amount' => $property['handover_amount'] ?? null],
], static fn($item) => (isset($item['percentage']) && trim((string) $item['percentage']) !== '') || (isset($item['amount']) && trim((string) $item['amount']) !== ''));

$propertyTitle = trim((string) ($property['property_title'] ?? ''));
$titleText = $propertyTitle !== '' ? $propertyTitle : 'Property Details';
$metaTitle = trim((string) ($property['meta_title'] ?? ''));
if ($metaTitle === '') {
    $metaTitle = $titleText;
}
$metaKeywords = trim((string) ($property['meta_keywords'] ?? ''));
$metaDescription = trim((string) ($property['meta_description'] ?? ''));

$developerStats = array_values(array_filter([
    ['label' => 'Established', 'value' => $property['developer_established'] ?? null],
    ['label' => 'Completed Projects', 'value' => $property['completed_projects'] ?? null],
    ['label' => 'International Awards', 'value' => $property['international_awards'] ?? null],
    ['label' => 'On-Time Delivery', 'value' => $property['on_time_delivery'] ?? null],
], static fn($stat) => isset($stat['value']) && trim((string) $stat['value']) !== ''));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/logo/favicon.svg" type="image/x-icon">
    <title><?= htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if ($metaDescription !== ''): ?>
        <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($metaKeywords !== ''): ?>
        <meta name="keywords" content="<?= htmlspecialchars($metaKeywords, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/vendors/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/country-select-js@2.0.1/build/css/countrySelect.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/css/intlTelInput.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/properties.css">
</head>

<body>

    <?php if ($leadFormError): ?>
        <div class="container position-relative" style="z-index: 1050;">
            <div class="alert alert-warning alert-dismissible fade show mt-3" role="alert">
                <?= htmlspecialchars($leadFormError, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>


    <!-- parent: .hh-property-hero -->
    <div class="hh-property-hero " data-animation-in="animate__fadeIn" data-animation-out="animate__fadeOut"
        style="background-image: url('<?= htmlspecialchars($primaryImage, ENT_QUOTES, 'UTF-8') ?>');">
        <!-- Top bar fixed at top of hero -->
        <div class="hh-property-hero-top">
            <a href="offplan-properties.php" class="hh-property-hero-back">← Back to Listings</a>
            <div class="hh-property-hero-top-actions">
                <button type="button" class="hh-primarypill" onclick="openPopup()"><img width="14"
                        src="assets/flaticons/phone.png" alt=""> Contact Us</button>
            </div>
        </div>
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <!-- Info block -->
                    <div class="hh-property-hero-info ">
                        <?php if (!empty($property['project_status']) || !empty($property['property_type'])): ?>
                            <div class="hh-property-hero-tags">
                                <?php if (!empty($property['project_status'])): ?>
                                    <span
                                        class="green"><?= htmlspecialchars($property['project_status'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <?php if (!empty($property['property_type'])): ?>
                                    <span><?= htmlspecialchars($property['property_type'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <h1><?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?></h1>
                        <?php if (!empty($property['project_name'])): ?>
                            <p class="text-white fw-semibold mb-2 d-none">Project Name:
                                <?= htmlspecialchars($property['project_name'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <?php if (!empty($property['property_location'])): ?>
                            <div class="hh-property-hero-loc"><img src="assets/icons/location.png" alt=""
                                    width="16"><?= htmlspecialchars($property['property_location'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($startingPriceValue !== ''): ?>
                            <div class="hh-property-hero-price">
                                <?php if ($startingPriceCurrency !== ''): ?>
                                    <span
                                        class="AED"><?= htmlspecialchars($startingPriceCurrency, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?= htmlspecialchars($startingPriceValue, ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($startingPriceValue, ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                                <span style="display: block;">Starting from</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($specItems): ?>
                            <ul class="hh-property-hero-specs">
                                <?php foreach ($specItems as $spec): ?>
                                    <li>
                                        <img src="<?= htmlspecialchars($spec['icon'], ENT_QUOTES, 'UTF-8') ?>" alt=""
                                            width="16">
                                        <?= htmlspecialchars(trim($spec['label'] . ' ' . $spec['suffix']), ENT_QUOTES, 'UTF-8') ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Bottom CTA buttons -->
                    <div class="hh-property-hero-ctas " data-animation-in="animate__flipInX"
                        data-animation-out="animate__flipOutX">
                        <button type="button" class="cta-solid" onclick="openPopup()">Enquire Now</button>
                        <button type="button" class="cta-outline" onclick="Brochurepopup()">Download Brochure</button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- parent: .hh-gallery-01 -->
    <div class="hh-gallery-01 ">
        <div class="container">
            <!-- Gallery + Agent -->
            <div class="row">
                <!-- Left: Gallery -->
                <div class="col-12 col-lg-8">
                    <div class="hh-gallery-01-head " data-animation-in="animate__fadeInDown"
                        data-animation-out="animate__fadeOutUp">
                        <h3>Property Gallery</h3>
                        <div class="hh-gallery-01-head-actions">
                            <button type="button" class="ghost" data-action="view-all">
                                <svg width="18" height="18" viewBox="0 0 24 24">
                                    <path d="M4 5h7v6H4zM13 5h7v6h-7zM4 13h7v6H4zM13 13h7v6h-7z" fill="currentColor" />
                                </svg>
                                View All (<?= $galleryCount ?>)
                            </button>
                            <?php if ($videoLink !== ''): ?>
                                <button type="button" class="solid" data-action="video"
                                    data-video="<?= htmlspecialchars($videoLink, ENT_QUOTES, 'UTF-8') ?>">
                                    <svg width="18" height="18" viewBox="0 0 24 24">
                                        <path d="M4 5h11a2 2 0 0 1 2 2v1.5l3-2v11l-3-2V17a2 2 0 0 1-2 2H4z"
                                            fill="currentColor" />
                                    </svg>
                                    Video Tour
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="hh-gallery-01-wrap " data-animation-in="animate__fadeInLeft"
                        data-animation-out="animate__fadeOutLeft">

                        <!-- Main swiper -->
                        <div class="swiper hh-gallery-01-main">
                            <div class="swiper-wrapper">
                                <?php if ($galleryImages): ?>
                                    <?php foreach ($galleryImages as $image): ?>
                                        <div class="swiper-slide">
                                            <img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>"
                                                alt="<?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="swiper-slide">
                                        <img src="assets/images/offplan/breez-by-danube.webp" alt="Placeholder image">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- overlay controls -->
                            <button type="button" class="nav prev" aria-label="Previous"></button>
                            <button type="button" class="nav next" aria-label="Next"></button>
                            <button type="button" class="fullscreen" aria-label="Full screen">
                                <svg width="18" height="18" viewBox="0 0 24 24">
                                    <path
                                        d="M9 5H5v4H3V3h6v2zm6-2h6v6h-2V5h-4V3zM5 15H3v6h6v-2H5v-4zm16 0v6h-6v-2h4v-4h2z"
                                        fill="currentColor" />
                                </svg>
                            </button>
                            <div class="fraction"><span><?= $galleryCount > 0 ? 1 : 0 ?></span> of
                                <span><?= $galleryCount ?></span>
                            </div>
                            <div class="progress"><i
                                    style="width: <?= $galleryCount > 0 ? 100 / max($galleryCount, 1) : 0 ?>%"></i>
                            </div>
                        </div>

                        <!-- Thumbs swiper -->
                        <div class="swiper hh-gallery-01-thumbs">
                            <div class="swiper-wrapper">
                                <?php if ($galleryImages): ?>
                                    <?php foreach ($galleryImages as $image): ?>
                                        <div class="swiper-slide"><img
                                                src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt=""></div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="swiper-slide"><img src="assets/images/offplan/breez-by-danube.webp" alt="">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Agent panel -->
                <div class="col-12 col-lg-4">
                    <aside class="hh-gallery-01-agent " data-animation-in="animate__flipInY"
                        data-animation-out="animate__flipOutY">
                        <div class="card-head">
                            <div class="avatar">
                                <img src="assets/icons/profile.png" alt="" width="100%">
                            </div>
                            <div class="info">
                                <strong>Sarah Al-Mansouri</strong>
                                <span>Senior Property Consultant</span>
                                <em><b style="color: #fff; font-size: 18px;">★★★★★</b> · 5.0 Rating</em>
                            </div>
                        </div>

                        <div class="cta-row">
                            <button type="button" class="call" onclick="window.location.href='tel:+971 42554683'">
                                <img src="assets/flaticons/phone.png" alt="" width="16">
                                Call
                            </button>
                            <button type="button" class="wa"
                                onclick="window.open('https://wa.me/97142554683','_blank')">
                                <img src="assets/flaticons/whatsapp.png" alt="WhatsApp" width="20">
                                WhatsApp
                            </button>

                        </div>

                        <button type="button" class="ghost-wide" onclick="openPopup()">
                            <img src="assets/icons/calendar.png" alt="" width="16">
                            Schedule Viewing
                        </button>

                        <div class="actions">
                            <!-- <button type="button">
                                <img src="assets/icons/video-call.png" alt="" width="20">
                                3D Virtual Tour
                            </button> -->
                            <button type="button" onclick="Brochurepopup()">
                                <img src="assets/icons/brochure-download.png" alt="" width="20">
                                Download Brochure
                            </button>
                            <button type="button" onclick="openPopup()">
                                <img src="assets/icons/floorplan.png" alt="" width="20">
                                View Floor Plans
                            </button>
                        </div>
                    </aside>
                </div>
            </div>
        </div>

        <!-- Lightbox (custom, no extra lib) -->
        <div class="hh-gallery-01-lightbox" aria-hidden="true">
            <button type="button" class="lb-close" aria-label="Close">×</button>
            <button type="button" class="lb-prev" aria-label="Previous"></button>
            <img alt="">
            <button type="button" class="lb-next" aria-label="Next"></button>
        </div>
    </div>

    <!-- parent: .hh-details-01 -->
    <div class="hh-details-01 ">
        <div class="container">
            <!-- Body -->
            <div class="row">
                <div class="col-12 col-lg-8">
                    <nav class="hh-tabs " data-animation-in="animate__fadeInDown"
                        data-animation-out="animate__fadeOutUp" role="tablist" aria-label="Property details tabs">
                        <ul>
                            <li>
                                <button id="hh-tab-overview-btn" type="button" class="active" role="tab"
                                    aria-selected="true" aria-controls="hh-tab-overview" data-bs-toggle="tab"
                                    data-bs-target="#hh-tab-overview">
                                    Overview
                                </button>
                            </li>
                            <li>
                                <button id="hh-tab-features-btn" type="button" role="tab" aria-selected="false"
                                    aria-controls="hh-tab-features" data-bs-toggle="tab"
                                    data-bs-target="#hh-tab-features">
                                    Key Features & Amenities
                                </button>
                            </li>
                            <li>
                                <button id="hh-tab-floor-btn" type="button" role="tab" aria-selected="false"
                                    aria-controls="hh-tab-floor" data-bs-toggle="tab" data-bs-target="#hh-tab-floor">
                                    Floor Plan
                                </button>
                            </li>
                            <li>
                                <button id="hh-tab-developer-btn" type="button" role="tab" aria-selected="false"
                                    aria-controls="hh-tab-developer" data-bs-toggle="tab"
                                    data-bs-target="#hh-tab-developer">
                                    Developer
                                </button>
                            </li>
                        </ul>
                    </nav>

                    <!-- Bootstrap required wrapper -->
                    <div class="tab-content">

                        <!-- Overview -->
                        <div id="hh-tab-overview" class="tab-pane fade show active" role="tabpanel"
                            aria-labelledby="hh-tab-overview-btn">
                            <div class="container-fluid">
                                <div class="row">
                                    <div class="col-lg-12 p-0">
                                        <div class="project-overview ">
                                            <div class="project-header">
                                                <h3><?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?></h3>
                                                <?php if (!empty($property['property_location'])): ?>
                                                    <h6><?= htmlspecialchars($property['property_location'], ENT_QUOTES, 'UTF-8') ?>
                                                    </h6>
                                                <?php endif; ?>
                                            </div>
                                            <?php foreach ($aboutProjectParagraphs as $paragraph): ?>
                                                <p><?= htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endforeach; ?>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Features -->
                        <div id="hh-tab-features" class="tab-pane fade" role="tabpanel"
                            aria-labelledby="hh-tab-features-btn">
                            <!-- parent: .hh-amenities-01 -->
                            <div class="hh-amenities-01 ">
                                <div class="amenities-list-1">
                                    <?php if ($amenitiesList): ?>
                                        <ul class="amenities-list">
                                            <?php foreach ($amenitiesList as $amenity): ?>
                                                <?php $iconClass = $resolveAmenityIcon($amenity); ?>
                                                <li>
                                                    <i class="<?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?>"
                                                        aria-hidden="true"></i>
                                                    <span><?= htmlspecialchars($amenity, ENT_QUOTES, 'UTF-8') ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php elseif ($featureItems): ?>
                                        <ul class="amenities-list">
                                            <?php foreach ($featureItems as $item): ?>
                                                <?php $iconClass = $resolveAmenityIcon($item); ?>
                                                <li>
                                                    <i class="<?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?>"
                                                        aria-hidden="true"></i>
                                                    <span><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="mb-0">Feature details will be available soon.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Floor Plan -->
                        <div id="hh-tab-floor" class="tab-pane fade" role="tabpanel" aria-labelledby="hh-tab-floor-btn">
                            <div class="hh-floorplans-01 ">
                                <div class="container-fluid p-0">
                                    <?php if ($floorPlans): ?>
                                        <div class="row">
                                            <div class="col-12 col-lg-7">
                                                <div class="fp-canvas">
                                                    <?php foreach ($floorPlans as $index => $plan): ?>
                                                        <?php $paneId = 'fp-tab-' . $index; ?>
                                                        <div class="fp-pane<?= $index === 0 ? ' active' : '' ?>"
                                                            id="<?= htmlspecialchars($paneId, ENT_QUOTES, 'UTF-8') ?>">
                                                            <?php if (!empty($plan['file'])): ?>
                                                                <div class="fp-image">
                                                                    <img src="<?= htmlspecialchars($plan['file'], ENT_QUOTES, 'UTF-8') ?>"
                                                                        alt="<?= htmlspecialchars(($plan['title'] ?: 'Floor Plan') . ' layout', ENT_QUOTES, 'UTF-8') ?>"
                                                                        data-fp-index="<?= $index ?>">
                                                                    <button type="button" class="fp-view" aria-label="View image"
                                                                        data-fp-index="<?= $index ?>">
                                                                        <!-- <img src="assets/icons/plus.svg" alt="" aria-hidden="true"> -->
                                                                        <svg width="18" height="18" viewBox="0 0 24 24">
                                                                            <path d="M9 5H5v4H3V3h6v2zm6-2h6v6h-2V5h-4V3zM5 15H3v6h6v-2H5v-4zm16 0v6h-6v-2h4v-4h2z" fill="currentColor"></path>
                                                                        </svg>
                                                                    </button>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="fp-placeholder">Floor plan preview not available.</div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="col-12 col-lg-5">
                                                <aside class="fp-aside">
                                                    <?php foreach ($floorPlans as $index => $plan): ?>
                                                        <?php $targetId = '#fp-tab-' . $index; ?>
                                                        <button type="button" class="fp-box<?= $index === 0 ? ' active' : '' ?>"
                                                            data-bs-toggle="tab"
                                                            data-bs-target="<?= htmlspecialchars($targetId, ENT_QUOTES, 'UTF-8') ?>">
                                                            <div class="fp-box-head">
                                                                <img src="assets/icons/floorplan.png" alt="">
                                                                <div>
                                                                    <strong><?= htmlspecialchars($plan['title'] ?: ('Floor Plan ' . ($index + 1)), ENT_QUOTES, 'UTF-8') ?></strong>
                                                                </div>
                                                            </div>
                                                            <ul class="fp-meta">
                                                                <?php if (!empty($plan['area'])): ?>
                                                                    <li><em>Total
                                                                            Area</em><b><?= htmlspecialchars($plan['area'], ENT_QUOTES, 'UTF-8') ?></b>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <?php if (!empty($property['bedroom'])): ?>
                                                                    <li><em>Bedrooms</em><b><?= htmlspecialchars($property['bedroom'], ENT_QUOTES, 'UTF-8') ?></b>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <?php if (!empty($plan['price'])): ?>
                                                                    <li><em>Price</em><b><?= htmlspecialchars($plan['price'], ENT_QUOTES, 'UTF-8') ?></b>
                                                                    </li>
                                                                <?php endif; ?>
                                                            </ul>
                                                        </button>
                                                    <?php endforeach; ?>
                                                </aside>
                                            </div>
                                        </div>
                                        <div class="fp-lightbox" aria-hidden="true">
                                            <button type="button" class="fp-lightbox-close" aria-label="Close">×</button>
                                            <button type="button" class="fp-lightbox-nav prev" aria-label="Previous"></button>
                                            <img alt="Floor plan preview">
                                            <button type="button" class="fp-lightbox-nav next" aria-label="Next"></button>
                                        </div>
                                    <?php else: ?>
                                        <p class="mb-0">Floor plans will be shared soon.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Developer -->
                        <div id="hh-tab-developer" class="tab-pane fade" role="tabpanel"
                            aria-labelledby="hh-tab-developer-btn">
                            <div class="hh-developer-01">
                                <div class="container-fluid p-0">
                                    <div class="row">
                                        <div class="col-12">
                                            <section class="dev-card " data-animation-in="animate__flipInX"
                                                data-animation-out="animate__flipOutX">
                                                <div class="dev-head">
                                                    <div class="dev-ico">
                                                        <img src="assets/flaticons/residential.png" width="25" alt="">
                                                    </div>
                                                    <div class="dev-title">
                                                        <strong><?= htmlspecialchars($property['developer_name'] ?: 'Developer', ENT_QUOTES, 'UTF-8') ?></strong>
                                                    </div>
                                                </div>
                                                <div class="dev-body">

                                                    <div class="row justify-content-start align-items-center">
                                                        <div class="col-lg-12">
                                                            <?php if ($developerLogo): ?>
                                                                <div class="developer-profile-logo">
                                                                    <img class="img-fluid"
                                                                        src="<?= htmlspecialchars($developerLogo, ENT_QUOTES, 'UTF-8') ?>"
                                                                        alt="<?= htmlspecialchars($property['developer_name'] ?: 'Developer logo', ENT_QUOTES, 'UTF-8') ?>">
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php foreach ($aboutDeveloperParagraphs as $paragraph): ?>
                                                                <p><?= htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') ?>
                                                                </p>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>

                                                    <?php if ($developerStats): ?>
                                                        <div class="row dev-stats">
                                                            <?php foreach ($developerStats as $stat): ?>
                                                                <div class="col-6 col-lg-3">
                                                                    <div class="stat">
                                                                        <strong><?= htmlspecialchars($stat['value'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                                        <span><?= htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </section>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>


                <!-- Right: sidebar -->
                <div class="col-12 col-lg-4">
                    <aside>

                        <div class="agent-card " id="contactAgent" data-animation-in="animate__fadeIn"
                            data-animation-out="animate__fadeOut">

                            <div class="agent-head">
                                <div class="avatar">
                                    <img src="assets/icons/chat.png" alt="">
                                </div>
                                <div class="agent-info">
                                    <strong>Contact Agent</strong>
                                </div>
                            </div>

                            <form method="POST" action="process_offplan_lead">
                                <input type="hidden" name="redirect"
                                    value="property-details.php?id=<?= (int) $propertyId ?>#contactAgent">
                                <input type="hidden" name="property_id" value="<?= (int) $propertyId ?>">
                                <input type="hidden" name="property_title"
                                    value="<?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="form_type" value="popup">
                                <label>
                                    <input type="text" name="name" id="full_name" placeholder="Enter your full name" required>
                                </label>

                                <label>
                                    <input type="email" name="email" id="email_address" placeholder="your.email@example.com" required>
                                </label>

                                <label>
                                    <input type="text" name="country" id="country" placeholder="Enter your country" required>
                                </label>

                                <label>
                                    <input type="tel" name="phone" id="mobile_number" placeholder="+971 50 123 4567" class="mt-3" required>
                                </label>

                                <div class="g-recaptcha" data-sitekey="6LfsT9IrAAAAALx6HawW63nF2e1c9nLRJwXNDxTM"></div>

                                <button type="submit" class="send mt-3">Submit</button>
                                <p class="reg-note terms-and-privacy">
                                    By clicking Submit, you agree to our
                                    <a href="terms-and-conditions.php">Terms</a> &amp;
                                    <a href="privacy-policy.php">Privacy Policy</a>.
                                </p>
                            </form>

                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>

    <!-- parent: .hh-invest-01 -->
    <div class="hh-invest-01 ">
        <div class="container">
            <div class="row">
                <!-- LEFT: Highlights + Payment Plan -->
                <div class="col-12 col-lg-8">

                    <!-- Investment Highlights -->
                    <section class="inv-high ">
                        <header>
                            <span><img src="assets/icons/growth-chart.png" alt="" width="25"></span>
                            <h4>Investment Highlights</h4>
                        </header>

                        <?php if ($investmentHighlights): ?>
                            <div class="hi-grid">
                                <?php foreach ($investmentHighlights as $highlight): ?>
                                    <div>
                                        <strong><?= htmlspecialchars((string) $highlight['value'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span><?= htmlspecialchars($highlight['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($highlight['note'])): ?>
                                            <em><?= htmlspecialchars($highlight['note'], ENT_QUOTES, 'UTF-8') ?></em>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="mb-0">Investment highlights will be shared soon.</p>
                        <?php endif; ?>
                    </section>

                    <!-- Flexible Payment Plan -->
                    <section class="pay-plan ">
                        <header>
                            <span><img src="assets/icons/wallet.png" alt="" width="25"></span>
                            <h4>Flexible Payment Plan</h4>
                        </header>

                        <?php if ($paymentSchedule): ?>
                            <div class="plan-list">
                                <?php foreach ($paymentSchedule as $item): ?>
                                    <?php
                                    $percentageText = trim((string) ($item['percentage'] ?? ''));
                                    $amountText = trim((string) ($item['amount'] ?? ''));
                                    ?>
                                    <div class="plan-item">
                                        <?php if ($percentageText !== ''): ?>
                                            <div class="pct"><?= htmlspecialchars($percentageText, ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <div class="txt">
                                            <strong><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        </div>
                                        <div class="amt">
                                            <?php if ($amountText !== ''): ?>
                                                <b><?= htmlspecialchars($amountText, ENT_QUOTES, 'UTF-8') ?></b>
                                            <?php endif; ?>
                                            <?php if ($percentageText !== ''): ?>
                                                <em><?= htmlspecialchars($percentageText, ENT_QUOTES, 'UTF-8') ?></em>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="mb-0">Payment plan details will be updated shortly.</p>
                        <?php endif; ?>
                    </section>

                </div>

                <!-- RIGHT: Mortgage Calculator -->
                <div class="col-12 col-lg-4 " data-animation-in="animate__fadeInRight"
                    data-animation-out="animate__fadeOutRight">
                    <aside class="mort-card " data-animation-in="animate__flipInY"
                        data-animation-out="animate__flipOutY">
                        <header>
                            <!-- <img src="assets/icons/mortgage.png" alt="" width="20"> -->
                            <h5>Mortgage Calculator</h5>
                        </header>

                        <!-- price + rate -->
                        <div class="fld-row">
                            <label>Property Price</label>
                            <div class="amount">
                                <span class="adorn">$</span>
                                <input id="mc-price" type="text" value="2,500,000" inputmode="numeric" />
                            </div>
                        </div>

                        <div class="fld-row">
                            <label>Interest Rate (%)</label>
                            <div class="amount">
                                <span class="adorn">%</span>
                                <input id="mc-rate" type="number" step="0.1" value="3.5" />
                            </div>
                        </div>

                        <!-- Down payment -->
                        <div class="range-row">
                            <div class="r-label">
                                <span>Down Payment : <b id="mc-dp-lbl">25%</b> (<b id="mc-dp-amt">AED 625,000</b>)</span>
                            </div>
                            <input id="mc-dp" type="range" min="10" max="50" step="1" value="25" />
                            <div class="r-scale">
                                <span>10%</span><span>50%</span>
                            </div>
                        </div>

                        <!-- Loan term -->
                        <div class="range-row">
                            <div class="r-label">
                                <span>Loan Term : <b id="mc-term-lbl">25 years</b></span>
                            </div>
                            <input id="mc-term" type="range" min="10" max="35" step="1" value="25" />
                            <div class="r-scale">
                                <span>10 years</span><span>35 years</span>
                            </div>
                        </div>

                        <!-- Results -->
                        <div class="result-row">
                            <div class="pill gray">
                                <span>Loan Amount</span>
                                <strong id="mc-loan">AED 1,875,000</strong>
                            </div>
                            <div class="pill green">
                                <span>Monthly Payment</span>
                                <strong id="mc-monthly">AED 9,387</strong>
                            </div>
                        </div>

                        <div class="totals">
                            <div>
                                <span>Total Interest</span>
                                <b id="mc-interest">AED 941,008</b>
                            </div>
                            <div>
                                <span>Total Payment</span>
                                <b id="mc-total">AED 2,816,008</b>
                            </div>
                            <div>
                                <span>P&I Payment</span>
                                <b id="mc-pi">AED 9,387</b>
                            </div>
                        </div>

                        <button type="button" class="cta">Get Pre-Approved</button>
                    </aside>
                </div>
            </div>
        </div>
    </div>

    <!-- parent: .hh-location-01 -->
    <div class="hh-location-01 ">
        <div class="container">
            <!-- Heading -->
            <div class="row">
                <div class="col-12">
                    <div class="hh-location-card-head">
                        <div class="hh-location-card-icon">
                            <img src="assets/icons/location.png" alt="Interactive map icon">
                        </div>
                        <div class="hh-location-card-text">
                            <h4>Prime Location & Connectivity</h4>
                            <?php if ($locationHighlight !== ''): ?>
                                <span><?= htmlspecialchars($locationHighlight, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span>Explore the neighbourhood and nearby amenities.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main grid -->
            <div class="row g-4">
                <div class="col-lg-8 col-lg-8" data-animation-in="animate__fadeInLeft"
                    data-animation-out="animate__fadeOutLeft">
                    <div class="hh-location-01-map-card">

                        <div class="hh-location-01-map" data-animation-in="animate__fadeIn"
                            data-animation-out="animate__fadeOut">
                            <?php if ($locationMap !== ''): ?>
                                <?php if (stripos($locationMap, '<iframe') !== false): ?>
                                    <?= $locationMap ?>
                                <?php else: ?>
                                    <iframe src="<?= htmlspecialchars($locationMap, ENT_QUOTES, 'UTF-8') ?>" width="100%"
                                        height="375px" style="border:0;" allowfullscreen loading="lazy"
                                        referrerpolicy="no-referrer-when-downgrade"></iframe>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="map-placeholder">
                                    <img src="assets/icons/globe.png" alt="Map coming soon">
                                    <strong>Location map coming soon</strong>
                                    <span>We're preparing an interactive experience for this property.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-4" data-animation-in="animate__fadeInRight"
                    data-animation-out="animate__fadeOutRight">
                    <div class="hh-location-01-side">
                        <div class="hh-location-01-permit">
                            <div class="head">
                                <img src="assets/icons/home.svg" alt="Property permit icon">
                                <strong>Property Permit</strong>
                            </div>
                            <?php if ($permitBarcode || ($property['permit_no'] ?? '') !== '' || $completionDate): ?>
                                <div class="qr-row">
                                    <?php if ($permitBarcode): ?>
                                        <img class="qr" src="<?= htmlspecialchars($permitBarcode, ENT_QUOTES, 'UTF-8') ?>"
                                            alt="Property permit QR code" width="120">
                                    <?php endif; ?>
                                    <div class="permit-box">
                                        <span>Permit Number</span>
                                        <b><?= htmlspecialchars($property['permit_no'] ?: 'Available on request', ENT_QUOTES, 'UTF-8') ?></b>
                                        <?php if ($completionDate): ?>
                                            <em>Completion: <?= htmlspecialchars($completionDate, ENT_QUOTES, 'UTF-8') ?></em>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="mb-0">Permit information will be shared soon.</p>
                            <?php endif; ?>
                        </div>

                        <div class="hh-location-01-contact">
                            <div class="head">
                                <img src="assets/icons/video-call.png" alt="Contact icon">
                                <strong>Quick Contact</strong>
                            </div>
                            <p>Speak with our property specialists for personalised assistance.</p>
                            <a class="call" href="tel:+97142554683">
                                <img src="assets/icons/customer-support.png" alt="Call icon">
                                <span>Call Now: +971 425 54683</span>
                            </a>
                            <a class="email" href="mailto:contact@houzzhunt.com">
                                <img src="assets/icons/message.png" alt="Email icon">
                                <span>Email Agent</span>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="hh-location-01-landmarks" id="LandMarkList">
                        <div class="hh-landmarks-head">
                            <strong>Nearby Landmarks</strong>
                            <span>Stay connected to the best spots around the community.</span>
                        </div>
                        <?php if ($locationAccess): ?>
                            <?php
                            $landmarkIconKeywords = [
                                'mall' => 'assets/icons/location.png',
                                'marina' => 'assets/icons/location.png',
                                'airport' => 'assets/icons/location.png',
                                'metro' => 'assets/icons/location.png',
                                'station' => 'assets/icons/location.png',
                                'school' => 'assets/icons/location.png',
                                'hospital' => 'assets/icons/location.png',
                                'beach' => 'assets/icons/location.png',
                                'park' => 'assets/icons/location.png',
                                'tower' => 'assets/icons/location.png',
                            ];
                            $defaultLandmarkIcon = 'assets/icons/location.png';
                            ?>
                            <div class="hh-landmarks-grid">
                                <?php foreach ($locationAccess as $item): ?>
                                    <?php
                                    $landmarkRaw = is_string($item['landmark']) ? $item['landmark'] : '';
                                    $categoryRaw = is_string($item['category']) ? $item['category'] : '';
                                    $distanceRaw = is_string($item['distance']) ? $item['distance'] : '';

                                    $categoryValue = trim($categoryRaw);
                                    $distanceValue = trim($distanceRaw);
                                    $landmarkKey = strtolower($landmarkRaw);
                                    $categoryKey = strtolower($categoryValue);

                                    $iconPath = $defaultLandmarkIcon;
                                    foreach ($landmarkIconKeywords as $keyword => $path) {
                                        if (($categoryKey !== '' && str_contains($categoryKey, $keyword)) ||
                                            ($landmarkKey !== '' && str_contains($landmarkKey, $keyword))
                                        ) {
                                            $iconPath = $path;
                                            break;
                                        }
                                    }

                                    $metaParts = array_values(array_filter([
                                        $distanceValue,
                                        $categoryValue,
                                    ], static fn($value): bool => $value !== ''));

                                    $iconAlt = $categoryValue !== '' ? $categoryValue . ' icon' : 'Landmark icon';
                                    ?>
                                    <div class="hh-landmark-card">
                                        <div class="hh-landmark-icon">
                                            <img src="<?= htmlspecialchars($iconPath, ENT_QUOTES, 'UTF-8') ?>"
                                                alt="<?= htmlspecialchars($iconAlt, ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                        <div class="hh-landmark-details">
                                            <div class="hh-landmark-name">
                                                <?= htmlspecialchars($landmarkRaw, ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <?php if ($metaParts): ?>
                                                <div class="hh-landmark-meta">
                                                    <?php foreach ($metaParts as $index => $metaPart): ?>
                                                        <?php if ($index > 0): ?>
                                                            <span class="hh-landmark-meta-separator">|</span>
                                                        <?php endif; ?>
                                                        <span><?= htmlspecialchars($metaPart, ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="mb-0">Connectivity details will be available soon.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- parent: .hh-register-01 -->
    <div class="hh-register-01 ">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12">
                    <form class="reg-card " action="#" method="post" novalidate>
                        <input type="hidden" name="property_id" value="<?= (int) $propertyId ?>">
                        <input type="hidden" name="property_title"
                            value="<?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="reg-head">
                            <h3>Register your interest</h3>
                            <p>Fill form below and our agent will contact you shortly.</p>
                        </div>

                        <div class="row">

                            <div class="col-12 col-lg-4">
                                <!-- <label for="ri-name">Full Name*</label> -->
                                <input id="ri-name" name="full_name" type="text" placeholder="Full Name*">
                            </div>

                            <div class="col-12 col-lg-4">
                                <!-- <label for="ri-email">Email*</label> -->
                                <input id="ri-email" name="email" type="email" placeholder="Email Address*">
                            </div>

                            <div class="col-12 col-lg-4">
                                <!-- <label for="ri-mobile">Mobile*</label> -->
                                <input id="ri-mobile" name="mobile" type="tel" placeholder="50 123 4567">
                            </div>

                            <div class="col-12 col-lg-4">
                                <!-- <label for="ri-project">Interested In*</label> -->
                                <select id="ri-project" name="project_name" class="select-dropDownClass">
                                    <option value="">Interested In</option>
                                    <option value="jumeirah-reside">Secondary</option>
                                    <option value="downtown-dubai">Offplan</option>
                                </select>
                            </div>


                            <div class="col-12 col-lg-4">
                                <!-- <label for="ri-budget">Select Country*</label> -->
                                <input id="ri-budget" name="budget_range" type="text" placeholder="Budget Range*">
                            </div>

                            <div class="col-12 col-lg-4">
                                <!-- <label class="only-for-space">&nbsp;</label> -->
                                <button type="submit" class="submit-btn">Submit Details</button>
                            </div>
                        </div>

                        <p class="reg-note">
                            By clicking Submit, you agree to our
                            <a href="terms-and-conditions.php">Terms</a> &amp;
                            <a href="privacy-policy.php">Privacy Policy</a>.
                        </p>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <!-- footer five start -->
    <div class="footer-section-five">
        <div class="container">
            <div class="row gutter-y-30">
                <div class="col-12 col-lg-3 col-md-6">
                    <div class="footer-about-five">
                        <div class="footer-logo-five animate fadeInUp wow">
                            <img src="assets/images/logo/logo.svg">
                        </div>
                        <p class="lead">Your trusted partner in premium real estate across the UAE and Middle East. We
                            bring
                            you curated properties, expert advice and a smooth real estate experience tailored for
                            modern
                            living.
                        </p>
                        <ul class="footer-social-media-five">
                            <li>
                                <a href="https://www.instagram.com/houzzhunt/?hl=en"><img
                                        src="assets/icons/instagram.png" alt="icon"></a>
                            </li>
                            <li>
                                <a href="https://www.facebook.com/people/Houzz-Hunt/61574436629351/"><img
                                        src="assets/icons/facebook.png" alt="icon"></a>
                            </li>
                            <li>
                                <a href="https://x.com/HouzzHunt"><img src="assets/icons/twitter.png" alt="icon"></a>
                            </li>
                            <li>
                                <a href="https://www.linkedin.com/company/houzz-hunt/"><img
                                        src="assets/icons/linkedin.png" alt="icon"></a>
                            </li>
                            <li>
                                <a href="https://www.youtube.com/@HouzzHunt"><img src="assets/icons/youtube.png"
                                        alt="icon"></a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6">
                    <div class="footer-widget-two">
                        <h4>Usefull Links</h4>
                        <ul class="footer-menu-two">
                            <li>

                                <a href="index.php">Home</a>
                            </li>
                            <li>

                                <a href="about.php">About</a>
                            </li>
                            <li>

                                <a href="services.php">Services </a>
                            </li>
                            <li>

                                <a href="blogs.php">Blogs</a>
                            </li>
                            <li>

                                <a href="contact.php">Contact Us</a>
                            </li>
                            <li>

                                <a href="careers.php">Career</a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6">
                    <div class="footer-widget-two">
                        <h4>Services</h4>
                        <ul class="footer-menu-two">
                            <li>

                                <a href="residential.php">Residential</a>
                            </li>
                            <li>

                                <a href="commercial.php">Commercial</a>
                            </li>
                            <li>

                                <a href="mortgage-services.php">Mortgage Services</a>
                            </li>
                            <li>

                                <a href="investment.php">Investment</a>
                            </li>
                            <li>

                                <a href="valuation-advisory.php">Valuation & Advisory</a>
                            </li>
                            <li>

                                <a href="research.php">Research</a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="col-12 col-lg-3 col-md-6">
                    <div class="footer-widget-contact">
                        <h4>Contact Us</h4>
                        <ul class="footer-location-four">
                            <li>
                                <span><img src="assets/images/svg/footer-two/footer-two-mail.svg" alt="icon"></span>
                                <a href="mailto:contact@houzzhunt.com">contact@houzzhunt.com</a>
                            </li>
                            <li>
                                <span><img src="assets/images/svg/footer-two/footer-two-address.svg" alt="icon"></span>
                                <p>806, Capital Golden Tower

                                    Business Bay, Dubai, U.A.E</p>
                            </li>
                            <li>
                                <span><img src="assets/images/svg/footer-two/footer-two-call.svg" alt="icon"></span>
                                <a href="telto:+97142554683">+971 42554683</a>
                            </li>
                        </ul>

                    </div>
                </div>

            </div>
        </div>
    </div>
    <!-- footer five end -->

    <!-- Popup Form -->
    <div class="popup-overlay" id="propertyEnquirey">
        <div class="popup-content">
            <div class="popup-image"></div>
            <div class="popup-form">
                <div class="popup-close" onclick="closePopup()">X</div>
                <h4 class="heading-title"><span>Register Your Interest</span></h4>
                <p style="font-size: 14px !important; margin-bottom: 10px;">
                    Unlock expert advice, exclusive listings & investment insights.
                </p>
                <form method="POST" class="appointment-form" action="process_offplan_lead">
                    <input type="hidden" name="redirect"
                        value="property-details.php?id=<?= (int) $propertyId ?>#propertyEnquirey">
                    <input type="hidden" name="property_id" value="<?= (int) $propertyId ?>">
                    <input type="hidden" name="property_title"
                        value="<?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="form_type" value="popup">
                    <div class="form-group">
                        <label for="full_name">Enter Name</label>
                        <input type="text" name="name" id="full_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="email_address">Enter Email</label>
                        <input type="email" name="email" id="email_address" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="country">Select Country</label>
                        <input type="text" name="country" id="country" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="mobile_number">Phone Number</label>
                        <input type="tel" name="phone" id="mobile_number" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <div class="g-recaptcha" data-sitekey="6LfsT9IrAAAAALx6HawW63nF2e1c9nLRJwXNDxTM"></div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="gradient-btn btn-green-glossy w-100 mt-3 text-center">
                            Submit Enquiry
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Download Brochure -->
    <div class="popup-overlay" id="downloadBrochure">
        <div class="popup-content">
            <div class="popup-image"></div>
            <div class="popup-form">
                <div class="popup-close" onclick="closeBrochurepopup()">X</div>
                <h4 class="heading-title"><span>Download Brochure</span></h4>
                <p style="font-size: 14px !important; margin-bottom: 10px;">
                    Get your brochure instantly. Enter your details below to access the download.
                </p>
                <form method="POST" class="appointment-form" action="process_offplan_lead">
                    <input type="hidden" name="redirect"
                        value="property-details.php?id=<?= (int) $propertyId ?>#downloadBrochure">
                    <input type="hidden" name="property_id" value="<?= (int) $propertyId ?>">
                    <input type="hidden" name="property_title"
                        value="<?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="form_type" value="brochure">
                    <input type="hidden" name="brochure_url"
                        value="<?= htmlspecialchars($brochure, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label for="brochure_name">Full Name</label>
                        <input type="text" name="brochure_name" id="brochure_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="brochure_email">Email Address</label>
                        <input type="email" name="brochure_email" id="brochure_email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="brochure_country">Country</label>
                        <input type="text" name="brochure_country" id="brochure_country" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="brochure_phone">Phone Number</label>
                        <input type="tel" name="brochure_phone" id="brochure_phone" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <div class="g-recaptcha" data-sitekey="6LfsT9IrAAAAALx6HawW63nF2e1c9nLRJwXNDxTM"></div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="gradient-btn btn-green-glossy w-100 mt-3 text-center">
                            Download Brochure
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/vendors/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendors/jquery/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/country-select-js@2.0.1/build/js/countrySelect.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/intlTelInput.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <!-- reCAPTCHA script -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const animatedElements = Array.from(document.querySelectorAll('.[data-animation-in]'));

            if (!animatedElements.length) {
                return;
            }

            const ensureBaseClasses = (el) => {
                if (!el.classList.contains('animate__animated')) {
                    el.classList.add('animate__animated');
                }
            };

            const handleAnimationEnd = (event) => {
                const el = event.currentTarget;
                if (event.target !== el) {
                    return;
                }

                const inClass = el.dataset.animationIn;
                const outClass = el.dataset.animationOut;

                if (inClass && el.classList.contains(inClass)) {
                    el.classList.remove(inClass);
                }

                if (outClass && el.classList.contains(outClass)) {
                    el.classList.remove(outClass);
                    el.classList.remove('animate-scroll-visible');
                }
            };

            animatedElements.forEach((el) => {
                el.addEventListener('animationend', handleAnimationEnd);
            });

            if (!('IntersectionObserver' in window)) {
                animatedElements.forEach((el) => {
                    ensureBaseClasses(el);
                    el.classList.add('animate-scroll-visible');
                    const inClass = el.dataset.animationIn;
                    if (inClass) {
                        el.classList.add(inClass);
                    }
                });
                return;
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    const el = entry.target;
                    const inClass = el.dataset.animationIn;
                    const outClass = el.dataset.animationOut;

                    ensureBaseClasses(el);

                    if (entry.isIntersecting) {
                        el.classList.add('animate-scroll-visible');
                        if (outClass) {
                            el.classList.remove(outClass);
                        }
                        if (inClass) {
                            el.classList.remove(inClass);
                            // Force reflow to restart the animation
                            void el.offsetWidth;
                            el.classList.add(inClass);
                        }
                    } else {
                        if (outClass) {
                            if (inClass) {
                                el.classList.remove(inClass);
                            }
                            el.classList.add(outClass);
                        } else {
                            el.classList.remove('animate-scroll-visible');
                            if (inClass) {
                                el.classList.remove(inClass);
                            }
                        }
                    }
                });
            }, {
                threshold: 0.2,
                rootMargin: '0px 0px -10% 0px'
            });

            animatedElements.forEach((el) => observer.observe(el));
        });
    </script>

    <script>
        /* ---- Swiper thumbs + main ---- */
        (function() {
            // thumbs
            const thumbs = new Swiper('.hh-gallery-01 .hh-gallery-01-thumbs', {
                slidesPerView: 4.5,
                spaceBetween: 12,
                watchSlidesProgress: true,
                breakpoints: {
                    576: {
                        slidesPerView: 5,
                        spaceBetween: 12
                    },
                    992: {
                        slidesPerView: 5,
                        spaceBetween: 14
                    }
                }
            });

            // main
            const main = new Swiper('.hh-gallery-01 .hh-gallery-01-main', {
                slidesPerView: 1,
                speed: 500,
                effect: 'slide',
                thumbs: {
                    swiper: thumbs
                },
                on: {
                    slideChange: function() {
                        // update fraction + progress
                        const cur = this.realIndex + 1;
                        const total = this.slides.length;
                        const root = this.el.closest('.hh-gallery-01');
                        root.querySelector('.fraction span:first-child').textContent = cur;
                        root.querySelector('.fraction span:last-child').textContent = total;
                        root.querySelector('.progress i').style.width = (cur / total * 100) + '%';
                    }
                }
            });

            // init fraction at load
            main.emit('slideChange');

            // custom nav
            const prevBtn = document.querySelector('.hh-gallery-01 .nav.prev');
            if (prevBtn) {
                prevBtn.addEventListener('click', () => main.slidePrev());
            }
            const nextBtn = document.querySelector('.hh-gallery-01 .nav.next');
            if (nextBtn) {
                nextBtn.addEventListener('click', () => main.slideNext());
            }

            // Lightbox
            const lb = document.querySelector('.hh-gallery-01 .hh-gallery-01-lightbox');
            const lbImg = lb.querySelector('img');
            let lbIndex = 0;

            function openLB(i) {
                lbIndex = i;
                lbImg.src = main.slides[lbIndex].querySelector('img').src;
                lb.classList.add('open');
                lb.setAttribute('aria-hidden', 'false');
            }

            function closeLB() {
                lb.classList.remove('open');
                lb.setAttribute('aria-hidden', 'true');
            }

            function prevLB() {
                lbIndex = (lbIndex - 1 + main.slides.length) % main.slides.length;
                lbImg.src = main.slides[lbIndex].querySelector('img').src;
                main.slideTo(lbIndex);
            }

            function nextLB() {
                lbIndex = (lbIndex + 1) % main.slides.length;
                lbImg.src = main.slides[lbIndex].querySelector('img').src;
                main.slideTo(lbIndex);
            }

            // click on main image or "View All"
            document.querySelectorAll('.hh-gallery-01 .hh-gallery-01-main .swiper-slide img').forEach((img, i) => {
                img.style.cursor = 'zoom-in';
                img.addEventListener('click', () => openLB(i));
            });
            const viewAllBtn = document.querySelector('.hh-gallery-01 [data-action="view-all"]');
            if (viewAllBtn) {
                viewAllBtn.addEventListener('click', () => openLB(main.realIndex));
            }
            const fullscreenBtn = document.querySelector('.hh-gallery-01 .fullscreen');
            if (fullscreenBtn) {
                fullscreenBtn.addEventListener('click', () => openLB(main.realIndex));
            }
            const videoBtn = document.querySelector('.hh-gallery-01 [data-action="video"]');
            if (videoBtn && videoBtn.dataset.video) {
                videoBtn.addEventListener('click', () => {
                    window.open(videoBtn.dataset.video, '_blank');
                });
            }

            // lb controls
            lb.querySelector('.lb-close').addEventListener('click', closeLB);
            lb.querySelector('.lb-prev').addEventListener('click', prevLB);
            lb.querySelector('.lb-next').addEventListener('click', nextLB);
            lb.addEventListener('click', (e) => {
                if (e.target === lb) closeLB();
            });

            // keyboard nav
            window.addEventListener('keydown', (e) => {
                if (!lb.classList.contains('open')) return;
                if (e.key === 'Escape') closeLB();
                if (e.key === 'ArrowLeft') prevLB();
                if (e.key === 'ArrowRight') nextLB();
            });
        })();
    </script>

    <script>
        (function() {
            const section = document.querySelector('.hh-floorplans-01');
            if (!section) return;
            const canvas = section.querySelector('.fp-canvas');
            const buttons = Array.from(section.querySelectorAll('.fp-aside [data-bs-toggle="tab"]'));
            if (!canvas || buttons.length === 0) {
                return;
            }

            function showPane(targetSel) {
                canvas.querySelectorAll('.fp-pane').forEach(p => p.classList.remove('active'));
                const pane = canvas.querySelector(targetSel);
                if (pane) pane.classList.add('active');
            }

            function activateButton(index) {
                buttons.forEach(b => {
                    b.classList.remove('active');
                    b.setAttribute('aria-selected', 'false');
                });
                const btn = buttons[index];
                if (!btn) {
                    return;
                }
                btn.classList.add('active');
                btn.setAttribute('aria-selected', 'true');
                const targetSel = btn.getAttribute('data-bs-target');
                if (targetSel) {
                    showPane(targetSel);
                }
            }

            activateButton(0);

            buttons.forEach((btn, index) => {
                btn.addEventListener('click', function() {
                    activateButton(index);
                });
            });

            const lightbox = section.querySelector('.fp-lightbox');
            const lbImg = lightbox ? lightbox.querySelector('img') : null;
            const viewButtons = Array.from(section.querySelectorAll('.fp-view'));
            const planImages = Array.from(section.querySelectorAll('.fp-pane img[data-fp-index]'))
                .map(img => {
                    const planIndex = Number(img.getAttribute('data-fp-index'));
                    if (Number.isNaN(planIndex)) {
                        return null;
                    }
                    return {
                        index: planIndex,
                        el: img
                    };
                })
                .filter(Boolean)
                .sort((a, b) => a.index - b.index);
            let lbIndex = 0;

            function syncActive(index) {
                activateButton(index);
            }

            function setLightboxImage(position) {
                const item = planImages[position];
                if (!lbImg || !item) {
                    return;
                }
                const img = item.el;
                lbImg.src = img.src;
                lbImg.alt = img.alt || 'Floor plan preview';
                lbIndex = position;
                syncActive(item.index);
            }

            function openLightbox(index) {
                if (!lightbox || !lbImg) {
                    return;
                }
                const position = planImages.findIndex(item => item.index === index);
                if (position === -1) {
                    return;
                }
                lightbox.classList.add('open');
                lightbox.setAttribute('aria-hidden', 'false');
                setLightboxImage(position);
            }

            function closeLightbox() {
                if (!lightbox) {
                    return;
                }
                lightbox.classList.remove('open');
                lightbox.setAttribute('aria-hidden', 'true');
            }

            function prevLightbox() {
                if (!planImages.length) return;
                const position = (lbIndex - 1 + planImages.length) % planImages.length;
                setLightboxImage(position);
            }

            function nextLightbox() {
                if (!planImages.length) return;
                const position = (lbIndex + 1) % planImages.length;
                setLightboxImage(position);
            }

            if (lightbox && planImages.length <= 1) {
                lightbox.classList.add('single');
            }

            viewButtons.forEach(btn => {
                btn.addEventListener('click', (event) => {
                    const index = Number(btn.getAttribute('data-fp-index'));
                    if (!Number.isNaN(index)) {
                        event.stopPropagation();
                        openLightbox(index);
                    }
                });
            });

            planImages.forEach(item => {
                const img = item.el;
                img.style.cursor = 'zoom-in';
                img.addEventListener('click', () => {
                    openLightbox(item.index);
                });
            });

            if (lightbox) {
                const closeBtn = lightbox.querySelector('.fp-lightbox-close');
                const prevBtn = lightbox.querySelector('.fp-lightbox-nav.prev');
                const nextBtn = lightbox.querySelector('.fp-lightbox-nav.next');

                if (closeBtn) {
                    closeBtn.addEventListener('click', closeLightbox);
                }
                if (prevBtn) {
                    prevBtn.addEventListener('click', prevLightbox);
                }
                if (nextBtn) {
                    nextBtn.addEventListener('click', nextLightbox);
                }

                lightbox.addEventListener('click', (event) => {
                    if (event.target === lightbox) {
                        closeLightbox();
                    }
                });

                window.addEventListener('keydown', (event) => {
                    if (!lightbox.classList.contains('open')) {
                        return;
                    }
                    if (event.key === 'Escape') {
                        closeLightbox();
                    } else if (event.key === 'ArrowLeft') {
                        prevLightbox();
                    } else if (event.key === 'ArrowRight') {
                        nextLightbox();
                    }
                });
            }
        })();
    </script>

    <script>
        (function() {
            const root = document.querySelector('.hh-invest-01');
            if (!root) return;

            const priceEl = root.querySelector('#mc-price');
            const rateEl = root.querySelector('#mc-rate');
            const dpEl = root.querySelector('#mc-dp');
            const termEl = root.querySelector('#mc-term');

            const dpLbl = root.querySelector('#mc-dp-lbl');
            const dpAmt = root.querySelector('#mc-dp-amt');
            const termLbl = root.querySelector('#mc-term-lbl');

            const outLoan = root.querySelector('#mc-loan');
            const outMon = root.querySelector('#mc-monthly');
            const outInt = root.querySelector('#mc-interest');
            const outTot = root.querySelector('#mc-total');
            const outPI = root.querySelector('#mc-pi');

            function toNum(str) {
                return Number(String(str).replace(/[^\d.]/g, ''));
            }

            function fmt(n) {
                const parts = Math.round(n).toString().split('');
                for (let i = parts.length - 3; i > 0; i -= 3) {
                    parts.splice(i, 0, ',');
                }
                return 'AED ' + parts.join('');
            }

            function compute() {
                const P = toNum(priceEl.value || 0);
                const rA = Number(rateEl.value || 0); // annual %
                const dp = Number(dpEl.value || 0); // %
                const yrs = Number(termEl.value || 0);

                const down = P * dp / 100;
                const L = Math.max(P - down, 0);
                const i = (rA / 100) / 12;
                const n = yrs * 12;

                const M = (i > 0) ? (L * i * Math.pow(1 + i, n)) / (Math.pow(1 + i, n) - 1) : (n > 0 ? L / n : 0);
                const totalPay = M * n;
                const totalInt = totalPay - L;

                dpLbl.textContent = dp + '%';
                dpAmt.textContent = fmt(down);
                termLbl.textContent = yrs + ' years';

                outLoan.textContent = fmt(L);
                outMon.textContent = fmt(M);
                outPI.textContent = fmt(M);
                outTot.textContent = fmt(totalPay);
                outInt.textContent = fmt(totalInt);
            }

            // formatting on blur for price
            priceEl.addEventListener('blur', () => {
                priceEl.value = (toNum(priceEl.value) || 0).toLocaleString();
                compute();
            });
            [priceEl, rateEl].forEach(el => el.addEventListener('input', compute));
            [dpEl, termEl].forEach(el => el.addEventListener('input', compute));

            // init
            priceEl.value = (toNum(priceEl.value) || 0).toLocaleString();
            compute();
        })();
    </script>

    <script>
        function openPopup() {
            document.getElementById("propertyEnquirey").classList.add("show");
            document.body.classList.add("no-scroll");
        }

        function closePopup() {
            document.getElementById("propertyEnquirey").classList.remove("show");
            document.body.classList.remove("no-scroll");
        }

        function Brochurepopup() {
            document.getElementById("downloadBrochure").classList.add("show");
            document.body.classList.add("no-scroll");
        }

        function closeBrochurepopup() {
            document.getElementById("downloadBrochure").classList.remove("show");
            document.body.classList.remove("no-scroll");
        }

        // Optional: Auto open after delay
        // window.addEventListener("load", function () {
        //   setTimeout(function () {
        //     openPopup();
        //   }, 3000);
        // });
    </script>

    <script>
        $(document).ready(function() {
            $("#country").countrySelect({
                defaultCountry: "ae",
                preferredCountries: ['ae', 'in', 'gb'] // gb = United Kingdom
            });

            $("#brochure_country").countrySelect({
                defaultCountry: "ae",
                preferredCountries: ['ae', 'in', 'gb']
            });

            $("#ri-budget").countrySelect({
                defaultCountry: "ae",
                preferredCountries: ['ae', 'in', 'gb']
            });
        });
    </script>

    <script>
        // Initialize multiple inputs with intlTelInput
        function initIntlTelInput(id) {
            const input = document.querySelector(id);
            if (!input) return null;

            const iti = window.intlTelInput(input, {
                initialCountry: "ae", // Default UAE
                separateDialCode: true,
                preferredCountries: ["ae", "in", "us", "gb", "sa"], // Common options
            });

            // Get full number on form submit
            input.form.addEventListener("submit", function() {
                input.value = iti.getNumber();
            });

            return iti;
        }

        // Apply on both IDs
        const itiPhone = initIntlTelInput("#phone");
        const itiRiMobile = initIntlTelInput("#ri-mobile");
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.select-dropDownClass').forEach(el => {
                new Choices(el, {
                    searchEnabled: false,
                    itemSelectText: '',
                    shouldSort: false
                });
            });
        });
    </script>

</body>

</html>