<?php
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

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
        <div class="hh-hero-01" style="background-image: url(assets/images/banner/offplan-banner.webp);">
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
                        <form id="offplan-filter-form" method="get" action="offplan-properties.php">
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
                                                onclick="window.location.href='offplan-properties.php';">
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

                            $primaryImage = $heroBanner !== '' ? $heroBanner : ($galleryImages[0] ?? 'assets/images/offplan/breez-by-danube.webp');
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
                                $specs[] = ['icon' => 'assets/icons/bed.png', 'text' => $bedroomLabel];
                            }
                            if (!empty($property['bathroom'])) {
                                $specs[] = ['icon' => 'assets/icons/bathroom.png', 'text' => trim((string)$property['bathroom']) . ' Baths'];
                            }
                            if (!empty($property['total_area'])) {
                                $specs[] = ['icon' => 'assets/icons/area.png', 'text' => trim((string)$property['total_area'])];
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
                                                <p><img src="assets/icons/location.png" width="16" alt=""> <?= htmlspecialchars($property['property_location'], ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                            <?php if ($specs): ?>
                                                <ul>
                                                    <?php foreach ($specs as $spec): ?>
                                                        <li><img src="<?= htmlspecialchars($spec['icon'], ENT_QUOTES, 'UTF-8') ?>" width="16" alt=""> <?= htmlspecialchars($spec['text'], ENT_QUOTES, 'UTF-8') ?></li>
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