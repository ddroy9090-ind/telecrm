<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/config.php';

try {
    $pdo = hh_db();
} catch (Throwable $exception) {
    die('Unable to connect to the database.');
}

$createPartnersTableSql = <<<SQL
    CREATE TABLE IF NOT EXISTS all_partners (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        partner_code VARCHAR(32) UNIQUE,
        company_name VARCHAR(255) NOT NULL,
        contact_person VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(64) NOT NULL,
        whatsapp VARCHAR(64) DEFAULT NULL,
        country VARCHAR(100) NOT NULL,
        city VARCHAR(100) DEFAULT NULL,
        address TEXT,
        rera_number VARCHAR(100) DEFAULT NULL,
        license_number VARCHAR(100) DEFAULT NULL,
        website VARCHAR(255) DEFAULT NULL,
        status ENUM('Pending', 'Active', 'Inactive') NOT NULL DEFAULT 'Pending',
        commission_structure VARCHAR(100) NOT NULL,
        remarks TEXT,
        rera_certificate VARCHAR(255) DEFAULT NULL,
        trade_license VARCHAR(255) DEFAULT NULL,
        agreement VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$pdo->exec($createPartnersTableSql);

$uploadDirectory = __DIR__ . '/uploads/partners';
$formErrors = [];
$formValues = [
    'company_name' => '',
    'contact_person' => '',
    'email' => '',
    'phone' => '',
    'whatsapp' => '',
    'country' => '',
    'city' => '',
    'address' => '',
    'rera_number' => '',
    'license_number' => '',
    'website' => '',
    'status' => 'Pending',
    'commission_structure' => '',
    'remarks' => '',
];

$successMessage = '';

$allowedStatuses = ['Pending', 'Active', 'Inactive'];

$uploadedFilePaths = [];

$sanitize = static function (?string $value): string {
    return trim((string) $value);
};

$handleUpload = static function (string $fieldName) use (&$formErrors, &$uploadedFilePaths, $uploadDirectory): ?string {
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $formErrors[$fieldName] = 'Unable to upload the selected file. Please try again.';
        return null;
    }

    $fileSize = (int) ($file['size'] ?? 0);
    $maxSize = 5 * 1024 * 1024; // 5 MB limit.
    if ($fileSize > $maxSize) {
        $formErrors[$fieldName] = 'The uploaded file exceeds the 5MB size limit.';
        return null;
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($extension, $allowedExtensions, true)) {
        $formErrors[$fieldName] = 'Please upload a PDF, JPG, or PNG file.';
        return null;
    }

    if (!is_dir($uploadDirectory)) {
        if (!mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            $formErrors[$fieldName] = 'Unable to prepare the upload directory.';
            return null;
        }
    }

    $filename = sprintf(
        '%s_%s.%s',
        $fieldName,
        bin2hex(random_bytes(8)),
        $extension
    );

    $destinationPath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $destinationPath)) {
        $formErrors[$fieldName] = 'Unable to move the uploaded file. Please try again.';
        return null;
    }

    $uploadedFilePaths[] = $destinationPath;

    return 'uploads/partners/' . $filename;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['company_name'] = $sanitize($_POST['company_name'] ?? '');
    $formValues['contact_person'] = $sanitize($_POST['contact_person'] ?? '');
    $formValues['email'] = $sanitize($_POST['email'] ?? '');
    $formValues['phone'] = $sanitize($_POST['phone'] ?? '');
    $formValues['whatsapp'] = $sanitize($_POST['whatsapp'] ?? '');
    $formValues['country'] = $sanitize($_POST['country'] ?? '');
    $formValues['city'] = $sanitize($_POST['city'] ?? '');
    $formValues['address'] = $sanitize($_POST['address'] ?? '');
    $formValues['rera_number'] = $sanitize($_POST['rera_number'] ?? '');
    $formValues['license_number'] = $sanitize($_POST['license_number'] ?? '');
    $formValues['website'] = $sanitize($_POST['website'] ?? '');
    $formValues['status'] = $sanitize($_POST['status'] ?? 'Pending');
    $formValues['commission_structure'] = $sanitize($_POST['commission_structure'] ?? '');
    $formValues['remarks'] = $sanitize($_POST['remarks'] ?? '');

    if ($formValues['company_name'] === '') {
        $formErrors['company_name'] = 'Company name is required.';
    }

    if ($formValues['contact_person'] === '') {
        $formErrors['contact_person'] = 'Contact person is required.';
    }

    if ($formValues['email'] === '' || !filter_var($formValues['email'], FILTER_VALIDATE_EMAIL)) {
        $formErrors['email'] = 'A valid email address is required.';
    }

    if ($formValues['phone'] === '') {
        $formErrors['phone'] = 'Phone number is required.';
    }

    if ($formValues['country'] === '') {
        $formErrors['country'] = 'Country is required.';
    }

    if (!in_array($formValues['status'], $allowedStatuses, true)) {
        $formErrors['status'] = 'Please select a valid status.';
    }

    if ($formValues['commission_structure'] === '') {
        $formErrors['commission_structure'] = 'Commission structure is required.';
    }

    if ($formValues['website'] !== '' && !filter_var($formValues['website'], FILTER_VALIDATE_URL)) {
        $formErrors['website'] = 'Please provide a valid website URL.';
    }

    $reraCertificatePath = null;
    $tradeLicensePath = null;
    $agreementPath = null;

    if (empty($formErrors)) {
        try {
            $reraCertificatePath = $handleUpload('rera_certificate');
            $tradeLicensePath = $handleUpload('trade_license');
            $agreementPath = $handleUpload('agreement');
        } catch (Throwable $uploadException) {
            $formErrors['general'] = 'An unexpected error occurred while processing uploads.';
        }
    }

    if (empty($formErrors)) {
        try {
            $pdo->beginTransaction();

            $insertSql = <<<SQL
                INSERT INTO all_partners (
                    partner_code,
                    company_name,
                    contact_person,
                    email,
                    phone,
                    whatsapp,
                    country,
                    city,
                    address,
                    rera_number,
                    license_number,
                    website,
                    status,
                    commission_structure,
                    remarks,
                    rera_certificate,
                    trade_license,
                    agreement
                ) VALUES (
                    NULL,
                    :company_name,
                    :contact_person,
                    :email,
                    :phone,
                    :whatsapp,
                    :country,
                    :city,
                    :address,
                    :rera_number,
                    :license_number,
                    :website,
                    :status,
                    :commission_structure,
                    :remarks,
                    :rera_certificate,
                    :trade_license,
                    :agreement
                )
            SQL;

            $statement = $pdo->prepare($insertSql);
            $statement->execute([
                ':company_name' => $formValues['company_name'],
                ':contact_person' => $formValues['contact_person'],
                ':email' => $formValues['email'],
                ':phone' => $formValues['phone'],
                ':whatsapp' => $formValues['whatsapp'] !== '' ? $formValues['whatsapp'] : null,
                ':country' => $formValues['country'],
                ':city' => $formValues['city'] !== '' ? $formValues['city'] : null,
                ':address' => $formValues['address'] !== '' ? $formValues['address'] : null,
                ':rera_number' => $formValues['rera_number'] !== '' ? $formValues['rera_number'] : null,
                ':license_number' => $formValues['license_number'] !== '' ? $formValues['license_number'] : null,
                ':website' => $formValues['website'] !== '' ? $formValues['website'] : null,
                ':status' => $formValues['status'],
                ':commission_structure' => $formValues['commission_structure'],
                ':remarks' => $formValues['remarks'] !== '' ? $formValues['remarks'] : null,
                ':rera_certificate' => $reraCertificatePath,
                ':trade_license' => $tradeLicensePath,
                ':agreement' => $agreementPath,
            ]);

            $newPartnerId = (int) $pdo->lastInsertId();
            $partnerCode = sprintf('CP-%04d', $newPartnerId);

            $updateStatement = $pdo->prepare('UPDATE all_partners SET partner_code = :partner_code WHERE id = :id');
            $updateStatement->execute([
                ':partner_code' => $partnerCode,
                ':id' => $newPartnerId,
            ]);

            $pdo->commit();

            header('Location: channel-partners.php?added=1');
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            foreach ($uploadedFilePaths as $path) {
                if (is_string($path) && $path !== '' && file_exists($path)) {
                    @unlink($path);
                }
            }

            $formErrors['general'] = 'Unable to save the partner at this time. Please try again.';
        }
    } else {
        foreach ($uploadedFilePaths as $path) {
            if (is_string($path) && $path !== '' && file_exists($path)) {
                @unlink($path);
            }
        }
    }
}

if (isset($_GET['added']) && $_GET['added'] === '1') {
    $successMessage = 'Partner has been added successfully.';
}

try {
    $partnersQuery = $pdo->query('SELECT * FROM all_partners ORDER BY created_at DESC');
    $partners = $partnersQuery ? $partnersQuery->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $exception) {
    $partners = [];
    if (!isset($formErrors['general'])) {
        $formErrors['general'] = 'Unable to load the partner list.';
    }
}

$totalPartners = count($partners);
$activePartners = 0;
$pendingPartners = 0;
$inactivePartners = 0;

foreach ($partners as $partnerRow) {
    $status = $partnerRow['status'] ?? '';
    if ($status === 'Active') {
        $activePartners++;
    } elseif ($status === 'Pending') {
        $pendingPartners++;
    } elseif ($status === 'Inactive') {
        $inactivePartners++;
    }
}

if (!isset($pageInlineScripts) || !is_array($pageInlineScripts)) {
    $pageInlineScripts = [];
}

$pageInlineScripts[] = <<<HTML
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var addPartnerButton = document.querySelector('[data-open-lead-sidebar]');
        if (!addPartnerButton) {
            return;
        }

        var preventSelector = '[data-prevent-lead-open]';
        document.querySelectorAll('[data-partner-status]').forEach(function (row) {
            row.addEventListener('click', function (event) {
                if (event.target.closest(preventSelector)) {
                    return;
                }

                if (row.getAttribute('data-partner-status') !== 'Active') {
                    return;
                }

                addPartnerButton.click();
            });
        });
    });
</script>
HTML;

$pageTitle = 'Channel Partners';

include __DIR__ . '/includes/common-header.php';
?>

<div id="adminPanel">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-6">
                    <h1 class="main-heading">All Channel Partners</h1>
                    <p class="subheading">Overview of your channel partner network</p>
                </div>
                <div class="col-lg-6">
                    <div class="text-end">
                        <button type="button" class="btn btn-primary" data-open-lead-sidebar aria-controls="leadSidebar">
                            <i class="bx bx-user-plus me-1"></i> Add Partner
                        </button>
                    </div>
                </div>
            </div>
            <div class="row g-3 lead-stats">
                <div class="col-md-3">
                    <div class="stat-card total-leads">
                        <h6>Total Partners</h6>
                        <h2><?= (int) $totalPartners ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card active-leads">
                        <h6>Active</h6>
                        <h2><?= (int) $activePartners ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card closed-leads">
                        <h6>Pending</h6>
                        <h2><?= (int) $pendingPartners ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card lost-leads">
                        <h6>Inactive</h6>
                        <h2><?= (int) $inactivePartners ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success" role="alert">
                    <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($formErrors)): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($formErrors as $errorKey => $errorMessage): ?>
                            <?php if ($errorKey === 'general'): ?>
                                <li><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php elseif (is_string($errorMessage) && $errorMessage !== ''): ?>
                                <li><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Bootstrap row used below (BOOTSTRAP class used by .row); note row itself has bootstrap class only -->
            <div class="row">
                <!-- To respect "no mixing" rule: each column uses Bootstrap col-* classes only -->
                <div class="col-12">
                    <div class="filter-bar">
                        <!-- A custom inner wrapper that will hold grid layout (custom class only) -->
                        <div class="inner-wrap">
                            <div class="filter-grid">
                                <!-- Column 1: Search (Bootstrap col was above so inner grid controls width) -->
                                <div>
                                    <!-- Parent div for input styles (custom) -->
                                    <div class="search-wrap">
                                        <!-- Icon -->
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"></circle>
                                            <path d="M20 20 L16.65 16.65" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path>
                                        </svg>
                                        <!-- Input has NO class per rule (parent has class) -->
                                        <input type="text" placeholder="Search by company, contact, email, or phone..." aria-label="Search">
                                    </div>
                                </div>

                                <!-- Column 2: Status select (parent custom wrapper present) -->
                                <div>
                                    <div class="select-wrap">
                                        <select aria-label="Status filter" class="select-dropDownClass">
                                            <option>All Statuses</option>
                                            <option>Active</option>
                                            <option>Pending</option>
                                            <option>Closed</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Column 3: Countries select (with outline-parent for focus visual) -->
                                <div>
                                    <div class="select-wrap outline">
                                        <select aria-label="Country filter" class="select-dropDownClass">
                                            <option>All Countries</option>
                                            <option>UAE</option>
                                            <option>India</option>
                                            <option>USA</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Column 4: Clear button -->
                                <div>
                                    <div class="action-wrap d-flex gap-2">
                                        <!-- Button has NO class (parent has class) -->
                                        <button type="button" id="clearFilters" aria-label="Clear filters">
                                            <span class="x" aria-hidden="true">&times;</span> Clear
                                        </button>
                                        <button type="button" class="btn btn-primary">
                                            Apply Filter
                                        </button>
                                    </div>
                                </div>

                            </div> <!-- /.filter-grid -->
                        </div> <!-- /.inner-wrap -->
                    </div> <!-- /.col-12 -->
                </div> <!-- /.row -->
            </div> <!-- /.filter-bar -->
        </div>

        <div class="card lead-table-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 lead-table">
                        <thead>
                            <tr>
                                <th scope="col">P Code</th>
                                <th scope="col">Company Name</th>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">WhatsApp</th>
                                <th scope="col">Country</th>
                                <th scope="col">Status</th>
                                <th scope="col">Commission</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($partners)): ?>
                                <?php foreach ($partners as $partner): ?>
                                    <?php
                                    $partnerCode = $partner['partner_code'] ?: sprintf('CP-%04d', (int) $partner['id']);
                                    $companyName = $partner['company_name'] ?? '';
                                    $contactPerson = $partner['contact_person'] ?? '';
                                    $email = $partner['email'] ?? '';
                                    $phone = $partner['phone'] ?? '';
                                    $country = $partner['country'] ?? '';
                                    $city = $partner['city'] ?? '';
                                    $status = $partner['status'] ?? '';
                                    $commission = $partner['commission_structure'] ?? '';
                                    $whatsapp = $partner['whatsapp'] ?? '';
                                    $avatarInitial = mb_strtoupper(mb_substr($companyName !== '' ? $companyName : ($contactPerson !== '' ? $contactPerson : 'P'), 0, 1, 'UTF-8'));
                                    $statusClass = 'bg-secondary';

                                    if ($status === 'Active') {
                                        $statusClass = 'bg-success';
                                    } elseif ($status === 'Pending') {
                                        $statusClass = 'bg-warning text-dark';
                                    } elseif ($status === 'Inactive') {
                                        $statusClass = 'bg-dark';
                                    }
                                    ?>
                                    <tr class="lead-table-row" data-partner-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                                        <td>
                                            <?= htmlspecialchars($partnerCode, ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td>
                                            <div class="lead-info">
                                                <div class="avatar" data-lead-avatar><?= htmlspecialchars($avatarInitial, ENT_QUOTES, 'UTF-8') ?></div>
                                                <div><strong><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></strong></div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($contactPerson !== ''): ?>
                                                <span class="d-block fw-semibold"><?= htmlspecialchars($contactPerson, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Not provided</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($email !== ''): ?>
                                                <a class="text-decoration-none" href="javascript:void(0)">
                                                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Not provided</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if ($whatsapp !== ''): ?>
                                                    <span><i class="bi bi-whatsapp me-1 text-success"></i><?= htmlspecialchars($whatsapp, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $locationParts = array_filter([$country, $city], static function ($value) {
                                                return $value !== null && $value !== '';
                                            });
                                            $location = implode(', ', $locationParts);
                                            ?>
                                            <?php if ($location !== ''): ?>
                                                <?= htmlspecialchars($location, ENT_QUOTES, 'UTF-8') ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not provided</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-role-manager"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td>
                                            <?php if ($commission !== ''): ?>
                                                <?= htmlspecialchars($commission, ENT_QUOTES, 'UTF-8') ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="dropdown" data-prevent-lead-open>
                                                <button class="btn btn-link p-0 border-0 text-dark" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="bi bi-three-dots-vertical fs-5"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <button class="dropdown-item" type="button" data-action="view" data-partner-id="<?= (int) $partner['id'] ?>">
                                                            <i class="bi bi-eye me-2"></i> View
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item" type="button" data-action="edit" data-partner-id="<?= (int) $partner['id'] ?>">
                                                            <i class="bi bi-pencil me-2"></i> Edit
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item text-danger" type="button" data-action="delete" data-partner-id="<?= (int) $partner['id'] ?>">
                                                            <i class="bi bi-trash me-2"></i> Delete
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        No partners found. Use the "Add Partner" button to create one.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <nav class="p-3" aria-label="Lead pagination">
                    <div class="row">

                        <div class="col-lg-12">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item disabled">
                                    <span class="page-link" aria-hidden="true">&laquo;</span>
                                </li>
                                <li class="page-item active">
                                    <span class="page-link" aria-current="page">1</span>
                                </li>
                                <li class="page-item disabled">
                                    <span class="page-link" aria-hidden="true">&raquo;</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>
            </div>
        </div>


        <div class="lead-sidebar-overlay" id="leadSidebarOverlay" hidden></div>
        <aside class="lead-sidebar" id="leadSidebar" aria-hidden="true">
            <div class="lead-sidebar__inner">
                <header class="lead-sidebar__header">
                    <div class="lead-sidebar__header-background"></div>
                    <div class="lead-sidebar__header-actions">
                        <button type="button" class="lead-sidebar__action-btn lead-sidebar__close" data-action="close" aria-label="Close sidebar">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="lead-sidebar__header-content">
                        <div class="lead-sidebar__header-text">
                            <p class="lead-sidebar__header-title mb-1">Add New Partner</p>
                            <p class="text-white small">Enter partner details to add them to your network</p>
                        </div>
                    </div>
                </header>

                <div class="lead-sidebar__body">
                    <form id="addPartnerForm" class="lead-sidebar__form" method="post" enctype="multipart/form-data" novalidate>
                        <section class="lead-sidebar__section">
                            <h3 class="lead-sidebar__section-title">Basic Details</h3>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="company_name" placeholder="Enter company name" required data-sidebar-focus value="<?= htmlspecialchars($formValues['company_name'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="contact_person" placeholder="Full name" required value="<?= htmlspecialchars($formValues['contact_person'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" placeholder="email@example.com" required value="<?= htmlspecialchars($formValues['email'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" name="phone" placeholder="+971-50-123-4567" required value="<?= htmlspecialchars($formValues['phone'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">WhatsApp</label>
                                    <input type="tel" class="form-control" name="whatsapp" placeholder="+971-50-123-4567" value="<?= htmlspecialchars($formValues['whatsapp'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Country <span class="text-danger">*</span></label>
                                    <select class="select-dropDownClass" name="country" required>
                                        <option value="">Select country</option>
                                        <?php
                                        $countries = [
                                            'India',
                                            'United Arab Emirates',
                                            'United Kingdom',
                                            'United States',
                                            'Saudi Arabia',
                                            'Canada',
                                            'Australia',
                                            'Qatar',
                                            'Singapore',
                                            'Germany',
                                        ];
                                        foreach ($countries as $countryOption):
                                            $isSelected = $formValues['country'] === $countryOption;
                                            ?>
                                            <option value="<?= htmlspecialchars($countryOption, ENT_QUOTES, 'UTF-8') ?>" <?= $isSelected ? 'selected' : '' ?>><?= htmlspecialchars($countryOption, ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" placeholder="Enter city" value="<?= htmlspecialchars($formValues['city'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" placeholder="Full address" rows="2"><?= htmlspecialchars($formValues['address'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                            </div>
                        </section>

                        <section class="lead-sidebar__section mt-4">
                            <h3 class="lead-sidebar__section-title">Compliance & Business</h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">RERA Number</label>
                                    <input type="text" class="form-control" name="rera_number" placeholder="RERA-12345" value="<?= htmlspecialchars($formValues['rera_number'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">License Number</label>
                                    <input type="text" class="form-control" name="license_number" placeholder="LIC-67890" value="<?= htmlspecialchars($formValues['license_number'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Website</label>
                                    <input type="url" class="form-control" name="website" placeholder="https://example.com" value="<?= htmlspecialchars($formValues['website'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="select-dropDownClass" name="status" required>
                                        <?php foreach ($allowedStatuses as $statusOption): ?>
                                            <option value="<?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>" <?= $formValues['status'] === $statusOption ? 'selected' : '' ?>><?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Commission Structure <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="commission_structure" placeholder="e.g., 3% or 3000" required value="<?= htmlspecialchars($formValues['commission_structure'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Remarks</label>
                                    <textarea class="form-control" name="remarks" placeholder="Additional notes..." rows="2"><?= htmlspecialchars($formValues['remarks'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                            </div>
                        </section>

                        <section class="lead-sidebar__section mt-4">
                            <h3 class="lead-sidebar__section-title">Documents Upload</h3>
                            <div class="mb-3">
                                <label class="form-label">RERA Certificate</label>
                                <input type="file" class="form-control" name="rera_certificate" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">PDF, JPG, PNG (Max 5MB)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Trade License</label>
                                <input type="file" class="form-control" name="trade_license" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">PDF, JPG, PNG (Max 5MB)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Agreement/MOU</label>
                                <input type="file" class="form-control" name="agreement" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">PDF, JPG, PNG (Max 5MB)</small>
                            </div>
                        </section>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary me-2">Save Partner</button>
                            <button type="button" class="btn btn-outline-secondary" data-action="close">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </aside>
    </main>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>
</body>

</html>