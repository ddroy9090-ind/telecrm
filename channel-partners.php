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
    'id' => 0,
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

$sanitize = static function (?string $value): string {
    return trim((string) $value);
};

$filterValues = [
    'search' => isset($_GET['search']) ? $sanitize($_GET['search']) : '',
    'status' => isset($_GET['status']) ? $sanitize($_GET['status']) : '',
    'country' => isset($_GET['country']) ? $sanitize($_GET['country']) : '',
];

if (!in_array($filterValues['status'], $allowedStatuses, true)) {
    $filterValues['status'] = '';
}

$filterValues['country'] = $filterValues['country'] !== '' ? $filterValues['country'] : '';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? 'channel-partners.php', PHP_URL_PATH) ?: 'channel-partners.php';

$availableCountries = [];

try {
    $countriesQuery = $pdo->query("SELECT DISTINCT country FROM all_partners WHERE country IS NOT NULL AND country <> '' ORDER BY country ASC");
    if ($countriesQuery) {
        $availableCountries = array_values(array_filter(array_map(static function ($row) {
            return isset($row['country']) ? (string) $row['country'] : '';
        }, $countriesQuery->fetchAll(PDO::FETCH_ASSOC)), static function ($value) {
            return $value !== '';
        }));
    }
} catch (Throwable $exception) {
    $availableCountries = [];
}

$uploadedFilePaths = [];
$filesToDeleteAfterSuccess = [];

$handleFileRemoval = static function (?string $relativePath): void {
    if (!is_string($relativePath) || $relativePath === '') {
        return;
    }

    $fullPath = __DIR__ . '/' . ltrim($relativePath, '/');
    if ($fullPath === '' || !is_file($fullPath)) {
        return;
    }

    @unlink($fullPath);
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
    $maxSize = 100 * 1024 * 1024; // 5 MB limit.
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
    if (isset($_POST['delete_partner_id'])) {
        $deletePartnerId = (int) ($_POST['delete_partner_id'] ?? 0);

        if ($deletePartnerId > 0) {
            try {
                $selectStatement = $pdo->prepare('SELECT rera_certificate, trade_license, agreement FROM all_partners WHERE id = :id');
                $selectStatement->execute([':id' => $deletePartnerId]);
                $partnerFiles = $selectStatement->fetch(PDO::FETCH_ASSOC) ?: [];

                $deleteStatement = $pdo->prepare('DELETE FROM all_partners WHERE id = :id');
                $deleteStatement->execute([':id' => $deletePartnerId]);

                foreach (['rera_certificate', 'trade_license', 'agreement'] as $fileField) {
                    if (isset($partnerFiles[$fileField])) {
                        $handleFileRemoval($partnerFiles[$fileField]);
                    }
                }

                header('Location: channel-partners.php?deleted=1');
                exit;
            } catch (Throwable $exception) {
                $formErrors['general'] = 'Unable to delete the selected partner. Please try again.';
            }
        } else {
            $formErrors['general'] = 'Invalid partner selected for deletion.';
        }
    }

    $editingPartnerId = (int) ($_POST['partner_id'] ?? 0);
    $isEditing = $editingPartnerId > 0;
    $existingPartner = null;

    if ($isEditing) {
        try {
            $selectPartnerStatement = $pdo->prepare('SELECT * FROM all_partners WHERE id = :id');
            $selectPartnerStatement->execute([':id' => $editingPartnerId]);
            $existingPartner = $selectPartnerStatement->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $exception) {
            $existingPartner = null;
        }

        if ($existingPartner === null) {
            $formErrors['general'] = 'The selected partner could not be found. Please refresh and try again.';
        }
    }

    $formValues['id'] = $editingPartnerId;
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

    $reraCertificatePath = $isEditing ? ($existingPartner['rera_certificate'] ?? null) : null;
    $tradeLicensePath = $isEditing ? ($existingPartner['trade_license'] ?? null) : null;
    $agreementPath = $isEditing ? ($existingPartner['agreement'] ?? null) : null;

    if (empty($formErrors)) {
        try {
            $uploadFields = [
                'rera_certificate' => 'rera_certificate',
                'trade_license' => 'trade_license',
                'agreement' => 'agreement',
            ];

            foreach ($uploadFields as $fileField) {
                $fileArray = $_FILES[$fileField] ?? null;
                $hasUpload = is_array($fileArray) && (($fileArray['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);

                if (!$hasUpload) {
                    continue;
                }

                $uploadedPath = $handleUpload($fileField);

                if ($uploadedPath === null) {
                    continue;
                }

                if ($fileField === 'rera_certificate') {
                    if ($reraCertificatePath && $reraCertificatePath !== $uploadedPath) {
                        $filesToDeleteAfterSuccess[] = $reraCertificatePath;
                    }
                    $reraCertificatePath = $uploadedPath;
                } elseif ($fileField === 'trade_license') {
                    if ($tradeLicensePath && $tradeLicensePath !== $uploadedPath) {
                        $filesToDeleteAfterSuccess[] = $tradeLicensePath;
                    }
                    $tradeLicensePath = $uploadedPath;
                } elseif ($fileField === 'agreement') {
                    if ($agreementPath && $agreementPath !== $uploadedPath) {
                        $filesToDeleteAfterSuccess[] = $agreementPath;
                    }
                    $agreementPath = $uploadedPath;
                }
            }
        } catch (Throwable $uploadException) {
            $formErrors['general'] = 'An unexpected error occurred while processing uploads.';
        }
    }

    if (empty($formErrors)) {
        try {
            $pdo->beginTransaction();

            if ($isEditing) {
                $updateSql = <<<SQL
                    UPDATE all_partners
                    SET
                        company_name = :company_name,
                        contact_person = :contact_person,
                        email = :email,
                        phone = :phone,
                        whatsapp = :whatsapp,
                        country = :country,
                        city = :city,
                        address = :address,
                        rera_number = :rera_number,
                        license_number = :license_number,
                        website = :website,
                        status = :status,
                        commission_structure = :commission_structure,
                        remarks = :remarks,
                        rera_certificate = :rera_certificate,
                        trade_license = :trade_license,
                        agreement = :agreement
                    WHERE id = :id
                SQL;

                $updateStatement = $pdo->prepare($updateSql);
                $updateStatement->execute([
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
                    ':id' => $editingPartnerId,
                ]);

                $pdo->commit();

                foreach ($filesToDeleteAfterSuccess as $filePath) {
                    $handleFileRemoval($filePath);
                }

                header('Location: channel-partners.php?updated=1');
                exit;
            }

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

            $filesToDeleteAfterSuccess = [];

            $formErrors['general'] = 'Unable to save the partner at this time. Please try again.';
        }
    } else {
        foreach ($uploadedFilePaths as $path) {
            if (is_string($path) && $path !== '' && file_exists($path)) {
                @unlink($path);
            }
        }

        $filesToDeleteAfterSuccess = [];
    }
}

if (isset($_GET['added']) && $_GET['added'] === '1') {
    $successMessage = 'Partner has been added successfully.';
} elseif (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $successMessage = 'Partner details have been updated successfully.';
} elseif (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $successMessage = 'Partner has been deleted successfully.';
}

try {
    $partnersQuerySql = 'SELECT * FROM all_partners';
    $conditions = [];
    $queryParameters = [];

    if ($filterValues['search'] !== '') {
        $conditions[] = '(
            company_name LIKE :search OR
            contact_person LIKE :search OR
            email LIKE :search OR
            phone LIKE :search OR
            whatsapp LIKE :search OR
            partner_code LIKE :search
        )';
        $queryParameters[':search'] = '%' . $filterValues['search'] . '%';
    }

    if ($filterValues['status'] !== '') {
        $conditions[] = 'status = :status';
        $queryParameters[':status'] = $filterValues['status'];
    }

    if ($filterValues['country'] !== '') {
        $conditions[] = 'country = :country';
        $queryParameters[':country'] = $filterValues['country'];
    }

    if (!empty($conditions)) {
        $partnersQuerySql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $partnersQuerySql .= ' ORDER BY created_at DESC';

    $statement = $pdo->prepare($partnersQuerySql);
    $statement->execute($queryParameters);
    $partners = $statement->fetchAll(PDO::FETCH_ASSOC);
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
        var filterForm = document.querySelector('[data-partner-filters]');
        var clearFiltersButton = document.getElementById('clearFilters');

        if (filterForm && clearFiltersButton) {
            var resetUrl = filterForm.getAttribute('data-reset-url') || window.location.pathname;
            clearFiltersButton.addEventListener('click', function () {
                window.location.href = resetUrl;
            });
        }

        var partnerTable = document.querySelector('[data-partner-table]');
        var partnerForm = document.getElementById('addPartnerForm');
        var addPartnerButton = document.querySelector('[data-open-lead-sidebar]');
        var deletePartnerForm = document.getElementById('deletePartnerForm');
        var deletePartnerInput = deletePartnerForm ? deletePartnerForm.querySelector('[name="delete_partner_id"]') : null;
        var sidebarTitle = document.querySelector('.lead-sidebar__header-title');
        var sidebarDescription = document.querySelector('.lead-sidebar__header-text .text-white');
        var submitButton = partnerForm ? partnerForm.querySelector('button[type="submit"]') : null;
        var partnerModalElement = document.getElementById('partnerDetailsModal');
        var partnerModalInstance = null;
        var preventSelector = '[data-prevent-lead-open]';

        if (!partnerTable) {
            return;
        }

        var defaultSidebarTitle = sidebarTitle ? sidebarTitle.textContent : '';
        var defaultSidebarDescription = sidebarDescription ? sidebarDescription.textContent : '';
        var defaultSubmitLabel = submitButton ? submitButton.textContent : '';

        var closeDropdownMenu = function (element) {
            if (!element) {
                return;
            }

            var dropdown = element.closest('.dropdown');
            if (!dropdown) {
                return;
            }

            var toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
            if (toggle && typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
                var dropdownInstance = bootstrap.Dropdown.getInstance(toggle) || new bootstrap.Dropdown(toggle);
                dropdownInstance.hide();
                return;
            }

            dropdown.classList.remove('show');
            var menu = dropdown.querySelector('.dropdown-menu');
            if (menu) {
                menu.classList.remove('show');
            }
        };

        var parsePartner = function (row) {
            if (!row) {
                return null;
            }

            var payload = row.getAttribute('data-partner-json');
            if (!payload) {
                return null;
            }

            try {
                return JSON.parse(payload);
            } catch (error) {
                console.error('Failed to parse partner payload', error);
                return null;
            }
        };

        var formatValue = function (value) {
            if (value === null || typeof value === 'undefined') {
                return '';
            }

            return String(value).trim();
        };

        var populateModal = function (partner) {
            if (!partnerModalElement) {
                return;
            }

            var detailContainer = partnerModalElement.querySelector('[data-partner-details]');
            if (!detailContainer) {
                return;
            }

            var setField = function (fieldName, value, options) {
                var target = detailContainer.querySelector('[data-field="' + fieldName + '"]');
                if (!target) {
                    return;
                }

                var displayValue = formatValue(value);
                var fallback = (options && options.fallback) || '—';

                if (options && options.render) {
                    options.render(target, displayValue, fallback, partner);
                    return;
                }

                target.textContent = displayValue !== '' ? displayValue : fallback;
            };

            var createdAt = formatValue(partner.created_at);
            if (createdAt !== '') {
                var createdDate = new Date(createdAt);
                if (!Number.isNaN(createdDate.getTime())) {
                    createdAt = createdDate.toLocaleString();
                }
            }

            setField('partner_code', partner.partner_code);
            setField('company_name', partner.company_name);
            setField('contact_person', partner.contact_person);
            setField('email', partner.email);
            setField('phone', partner.phone);
            setField('whatsapp', partner.whatsapp);
            setField('status', partner.status);
            setField('commission_structure', partner.commission_structure);
            setField('country', partner.country);
            setField('city', partner.city);
            setField('address', partner.address);
            setField('rera_number', partner.rera_number);
            setField('license_number', partner.license_number);
            setField('created_at', createdAt);
            setField('remarks', partner.remarks);
            setField('website', partner.website, {
                render: function (element, value, fallback) {
                    element.innerHTML = '';
                    if (value === '') {
                        element.textContent = fallback;
                        return;
                    }

                    var link = document.createElement('a');
                    link.href = value;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.textContent = value;
                    element.appendChild(link);
                },
            });

            var documentsField = detailContainer.querySelector('[data-field="documents"]');
            if (documentsField) {
                documentsField.innerHTML = '';
                var documents = [
                    { label: 'RERA Certificate', value: partner.rera_certificate },
                    { label: 'Trade License', value: partner.trade_license },
                    { label: 'Agreement / MOU', value: partner.agreement },
                ].filter(function (entry) {
                    return formatValue(entry.value) !== '';
                });

                if (!documents.length) {
                    var empty = document.createElement('span');
                    empty.className = 'text-muted';
                    empty.textContent = 'No documents uploaded.';
                    documentsField.appendChild(empty);
                } else {
                    var list = document.createElement('ul');
                    list.className = 'list-unstyled mb-0';

                    documents.forEach(function (entry) {
                        var listItem = document.createElement('li');
                        var link = document.createElement('a');
                        link.href = entry.value;
                        link.target = '_blank';
                        link.rel = 'noopener';
                        link.textContent = entry.label;
                        listItem.appendChild(link);
                        list.appendChild(listItem);
                    });

                    documentsField.appendChild(list);
                }
            }
        };

        partnerTable.querySelectorAll('tr[data-partner-json]').forEach(function (row) {
            row.addEventListener('click', function (event) {
                if (event.target.closest(preventSelector)) {
                    return;
                }

                var partner = parsePartner(row);
                if (!partner) {
                    return;
                }

                if (!partnerModalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                    return;
                }

                partnerModalInstance = bootstrap.Modal.getOrCreateInstance(partnerModalElement);
                if (!partnerModalInstance) {
                    return;
                }

                populateModal(partner);
                partnerModalInstance.show();
            });
        });

        var populateForm = function (partner) {
            if (!partnerForm) {
                return;
            }

            var elements = partnerForm.elements;
            if (!elements) {
                return;
            }

            var setInputValue = function (name, value) {
                var element = elements.namedItem(name);
                if (!element) {
                    return;
                }

                var displayValue = formatValue(value);

                if (element.tagName === 'SELECT') {
                    element.value = displayValue !== '' ? displayValue : '';
                    return;
                }

                element.value = displayValue;
            };

            setInputValue('partner_id', partner.id || 0);
            setInputValue('company_name', partner.company_name);
            setInputValue('contact_person', partner.contact_person);
            setInputValue('email', partner.email);
            setInputValue('phone', partner.phone);
            setInputValue('whatsapp', partner.whatsapp);
            setInputValue('country', partner.country);
            setInputValue('city', partner.city);
            setInputValue('address', partner.address);
            setInputValue('rera_number', partner.rera_number);
            setInputValue('license_number', partner.license_number);
            setInputValue('website', partner.website);
            setInputValue('status', partner.status);
            setInputValue('commission_structure', partner.commission_structure);
            setInputValue('remarks', partner.remarks);

            ['rera_certificate', 'trade_license', 'agreement'].forEach(function (fieldName) {
                var fileField = elements.namedItem(fieldName);
                if (fileField && fileField.type === 'file') {
                    fileField.value = '';
                }
            });

            if (sidebarTitle) {
                sidebarTitle.textContent = 'Edit Partner';
            }

            if (sidebarDescription) {
                sidebarDescription.textContent = 'Update the channel partner details and save your changes.';
            }

            if (submitButton) {
                submitButton.textContent = 'Update Partner';
            }
        };

        var openSidebarForEdit = function (partner) {
            if (!partnerForm) {
                return;
            }

            if (addPartnerButton) {
                addPartnerButton.click();
            } else {
                var sidebar = document.getElementById('leadSidebar');
                var overlay = document.getElementById('leadSidebarOverlay');
                if (sidebar) {
                    sidebar.classList.add('is-open');
                    sidebar.setAttribute('aria-hidden', 'false');
                }
                if (overlay) {
                    overlay.hidden = false;
                    overlay.classList.add('is-visible');
                }
                document.body.classList.add('lead-sidebar-open');
            }

            window.requestAnimationFrame(function () {
                populateForm(partner);
            });
        };

        var resetFormForCreate = function () {
            if (!partnerForm) {
                return;
            }

            window.requestAnimationFrame(function () {
                if (sidebarTitle) {
                    sidebarTitle.textContent = defaultSidebarTitle;
                }

                if (sidebarDescription) {
                    sidebarDescription.textContent = defaultSidebarDescription;
                }

                if (submitButton) {
                    submitButton.textContent = defaultSubmitLabel;
                }

                var idField = partnerForm.querySelector('[name="partner_id"]');
                if (idField) {
                    idField.value = '0';
                }
            });
        };

        if (addPartnerButton) {
            addPartnerButton.addEventListener('click', resetFormForCreate);
        }

        partnerTable.querySelectorAll('[data-partner-action]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                var action = button.getAttribute('data-partner-action');
                var row = button.closest('tr[data-partner-json]');
                var partner = parsePartner(row);

                if (action === 'view') {
                    closeDropdownMenu(button);

                    if (!partner) {
                        return;
                    }

                    if (partnerModalElement && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        partnerModalInstance = bootstrap.Modal.getOrCreateInstance(partnerModalElement);
                    }

                    if (!partnerModalInstance) {
                        return;
                    }

                    populateModal(partner);
                    partnerModalInstance.show();
                    return;
                }

                if (action === 'edit') {
                    closeDropdownMenu(button);

                    if (!partner) {
                        return;
                    }

                    openSidebarForEdit(partner);
                    return;
                }

                if (action === 'delete') {
                    closeDropdownMenu(button);

                    if (!deletePartnerForm || !deletePartnerInput) {
                        return;
                    }

                    var partnerId = Number(button.getAttribute('data-partner-id') || 0);
                    if (!partnerId) {
                        return;
                    }

                    var partnerName = button.getAttribute('data-partner-name') || '';
                    var confirmationMessage = partnerName
                        ? 'Are you sure you want to delete "' + partnerName + '"? This action cannot be undone.'
                        : 'Are you sure you want to delete this partner? This action cannot be undone.';

                    if (window.confirm(confirmationMessage)) {
                        deletePartnerInput.value = String(partnerId);
                        deletePartnerForm.submit();
                    }
                }
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
                        <form method="get" class="inner-wrap" data-partner-filters data-reset-url="<?= htmlspecialchars($currentPath, ENT_QUOTES, 'UTF-8') ?>">
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
                                        <input
                                            type="text"
                                            name="search"
                                            value="<?= htmlspecialchars($filterValues['search'], ENT_QUOTES, 'UTF-8') ?>"
                                            placeholder="Search by company, contact, email, or phone..."
                                            aria-label="Search"
                                        >
                                    </div>
                                </div>

                                <!-- Column 2: Status select (parent custom wrapper present) -->
                                <div>
                                    <div class="select-wrap">
                                        <select name="status" aria-label="Status filter" class="select-dropDownClass">
                                            <option value="">All Statuses</option>
                                            <?php foreach ($allowedStatuses as $statusOption): ?>
                                                <option value="<?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>" <?= $filterValues['status'] === $statusOption ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Column 3: Countries select (with outline-parent for focus visual) -->
                                <div>
                                    <div class="select-wrap outline">
                                        <select name="country" aria-label="Country filter" class="select-dropDownClass">
                                            <option value="">All Countries</option>
                                            <?php foreach ($availableCountries as $countryOption): ?>
                                                <option value="<?= htmlspecialchars($countryOption, ENT_QUOTES, 'UTF-8') ?>" <?= $filterValues['country'] === $countryOption ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($countryOption, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
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
                                        <button type="submit" class="btn btn-primary">
                                            Apply Filter
                                        </button>
                                    </div>
                                </div>

                            </div> <!-- /.filter-grid -->
                        </form> <!-- /.inner-wrap -->
                    </div> <!-- /.filter-bar -->
                </div> <!-- /.col-12 -->
            </div> <!-- /.row -->
        </div>

        <div class="card lead-table-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 lead-table" data-partner-table>
                        <thead>
                            <tr>
                                <th scope="col">P Code</th>
                                <th scope="col">Company Name</th>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">WhatsApp</th>
                                <!-- <th scope="col">Country</th> -->
                                <th scope="col">Status</th>
                                <!-- <th scope="col">Commission</th> -->
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
                                    $address = $partner['address'] ?? '';
                                    $status = $partner['status'] ?? '';
                                    $commission = $partner['commission_structure'] ?? '';
                                    $whatsapp = $partner['whatsapp'] ?? '';
                                    $reraNumber = $partner['rera_number'] ?? '';
                                    $licenseNumber = $partner['license_number'] ?? '';
                                    $website = $partner['website'] ?? '';
                                    $remarks = $partner['remarks'] ?? '';
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
                                    <?php
                                    $partnerPayload = [
                                        'id' => (int) ($partner['id'] ?? 0),
                                        'partner_code' => $partnerCode,
                                        'company_name' => $companyName,
                                        'contact_person' => $contactPerson,
                                        'email' => $email,
                                        'phone' => $phone,
                                        'whatsapp' => $whatsapp,
                                        'country' => $country,
                                        'city' => $city,
                                        'address' => $address,
                                        'rera_number' => $reraNumber,
                                        'license_number' => $licenseNumber,
                                        'website' => $website,
                                        'status' => $status,
                                        'commission_structure' => $commission,
                                        'remarks' => $remarks,
                                        'rera_certificate' => $partner['rera_certificate'] ?? null,
                                        'trade_license' => $partner['trade_license'] ?? null,
                                        'agreement' => $partner['agreement'] ?? null,
                                        'created_at' => $partner['created_at'] ?? null,
                                    ];
                                    $partnerJson = htmlspecialchars(json_encode($partnerPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr  class="lead-table-row" data-partner-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" data-partner-json="<?= $partnerJson ?>">
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
                                        <!-- <td>
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
                                        </td> -->
                                        <td>
                                            <span class="badge badge-role-manager"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <!-- <td>
                                            <?php if ($commission !== ''): ?>
                                                <?= htmlspecialchars($commission, ENT_QUOTES, 'UTF-8') ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td> -->
                                        <td>
                                            <div class="dropdown" data-prevent-lead-open>
                                                <button class="btn btn-link p-0 border-0 text-dark" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="bi bi-three-dots-vertical fs-5"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <button class="dropdown-item" type="button" data-partner-action="view" data-partner-id="<?= (int) $partner['id'] ?>">
                                                            <i class="bi bi-eye me-2"></i> View
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item" type="button" data-partner-action="edit" data-partner-id="<?= (int) $partner['id'] ?>">
                                                            <i class="bi bi-pencil me-2"></i> Edit
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item text-danger" type="button" data-partner-action="delete" data-partner-id="<?= (int) $partner['id'] ?>" data-partner-name="<?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?>">
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
                        <input type="hidden" name="partner_id" value="<?= isset($formValues['id']) ? (int) $formValues['id'] : 0 ?>">
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
        <form method="post" id="deletePartnerForm" class="d-none">
            <input type="hidden" name="delete_partner_id" value="0">
        </form>
        <div class="modal fade" id="partnerDetailsModal" tabindex="-1" aria-hidden="true" aria-labelledby="partnerDetailsModalLabel">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="partnerDetailsModalLabel">Partner Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body partner-details-modal">
                        <dl class="row mb-0" data-partner-details>
                            <div class="col-sm-4">
                                <dt>Partner Code</dt>
                                <dd data-field="partner_code">—</dd>
                            </div>
                            <div class="col-sm-4">
                                <dt>Company</dt>
                                <dd data-field="company_name">—</dd>
                            </div>
                            <div class="col-sm-4">
                                <dt>Contact Person</dt>
                                <dd data-field="contact_person">—</dd>
                            </div>
                            <div class="col-sm-4">
                                <dt>Email</dt>
                                <dd data-field="email">—</dd>
                            </div>
                            <div class="col-sm-4">
                                <dt>Phone</dt>
                                <dd data-field="phone">—</dd>
                            </div>
                            <div class="col-sm-4">
                                <dt>WhatsApp</dt>
                                <dd data-field="whatsapp">—</dd>
                            </div>
                            <div class="col-sm-4">
                                <dt>Status</dt>
                                <dd data-field="status">—</dd>
                            </div>
                            <div class="col-sm-4">
                                <dt>Commission</dt>
                                <dd data-field="commission_structure">—</dd>
                            </div>
                            <div class="col-sm-4">
                                <dt>Country</dt>
                                <dd data-field="country">—</dd>
                            </div>
                            <div class="col-sm-8">
                                <dt>Website</dt>
                                <dd data-field="website">—</dd>
                            </div>
                            
                            <div class="col-sm-4">
                                <dt>City</dt>
                                <dd data-field="city">—</dd>
                            </div>
                            <div class="col-12">
                                <dt>Address</dt>
                                <dd data-field="address">—</dd>
                            </div>
                            <div class="col-sm-4">
                                <dt>RERA Number</dt>
                                <dd data-field="rera_number">—</dd>
                            </div>
                            <div class="col-sm-4">
                                <dt>License Number</dt>
                                <dd data-field="license_number">—</dd>
                            </div>
                            <div class="col-sm-4">
                                <dt>Created</dt>
                                <dd data-field="created_at">—</dd>
                            </div>
                            <div class="col-12">
                                <dt>Remarks</dt>
                                <dd data-field="remarks">—</dd>
                            </div>
                            <div class="col-12">
                                <dt>Documents</dt>
                                <dd data-field="documents" class="doc">
                                    <span class="text-muted">No documents uploaded.</span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="modal-footer border-0 bg-white">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>
</body>

</html>