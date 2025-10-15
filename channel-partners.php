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

$createPartnerDocumentsTableSql = <<<SQL
    CREATE TABLE IF NOT EXISTS partner_documents (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        partner_id INT UNSIGNED NOT NULL,
        document_type VARCHAR(100) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        mime_type VARCHAR(100) DEFAULT NULL,
        file_size INT UNSIGNED DEFAULT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_partner_documents_partner
            FOREIGN KEY (partner_id)
            REFERENCES all_partners (id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$pdo->exec($createPartnersTableSql);
$pdo->exec($createPartnerDocumentsTableSql);

$respondWithJson = static function (array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

$fetchPartnerForResponse = static function (PDO $pdo, int $partnerId): ?array {
    if ($partnerId <= 0) {
        return null;
    }

    $statement = $pdo->prepare('SELECT * FROM all_partners WHERE id = :id');
    $statement->execute([':id' => $partnerId]);
    $partner = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$partner) {
        return null;
    }

    $createdAt = isset($partner['created_at'])
        ? date('d M Y, h:i A', strtotime((string) $partner['created_at']))
        : null;

    $documents = [
        'rera_certificate' => $partner['rera_certificate'] ?? null,
        'trade_license' => $partner['trade_license'] ?? null,
        'agreement' => $partner['agreement'] ?? null,
    ];

    $documentPayload = [];
    foreach ($documents as $key => $path) {
        if ($path === null || $path === '') {
            $documentPayload[$key] = null;
            continue;
        }

        $documentPayload[$key] = [
            'url' => hh_asset($path),
            'name' => basename((string) $path),
        ];
    }

    return [
        'id' => (int) $partner['id'],
        'partner_code' => $partner['partner_code'] ?? null,
        'company_name' => $partner['company_name'] ?? null,
        'contact_person' => $partner['contact_person'] ?? null,
        'email' => $partner['email'] ?? null,
        'phone' => $partner['phone'] ?? null,
        'whatsapp' => $partner['whatsapp'] ?? null,
        'country' => $partner['country'] ?? null,
        'city' => $partner['city'] ?? null,
        'address' => $partner['address'] ?? null,
        'status' => $partner['status'] ?? null,
        'commission_structure' => $partner['commission_structure'] ?? null,
        'remarks' => $partner['remarks'] ?? null,
        'rera_number' => $partner['rera_number'] ?? null,
        'license_number' => $partner['license_number'] ?? null,
        'website' => $partner['website'] ?? null,
        'created_at' => $createdAt,
        'documents' => $documentPayload,
    ];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'fetch_partner') {
        $partnerId = (int) ($_POST['partner_id'] ?? 0);
        $partner = $fetchPartnerForResponse($pdo, $partnerId);

        if ($partner === null) {
            $respondWithJson(['status' => 'error', 'message' => 'Partner not found.'], 404);
        }

        $respondWithJson(['status' => 'success', 'data' => $partner]);
    }

    if ($action === 'delete_partner') {
        $partnerId = (int) ($_POST['partner_id'] ?? 0);

        if ($partnerId <= 0) {
            $respondWithJson(['status' => 'error', 'message' => 'Invalid partner selected.'], 400);
        }

        $statement = $pdo->prepare('SELECT rera_certificate, trade_license, agreement FROM all_partners WHERE id = :id');
        $statement->execute([':id' => $partnerId]);
        $existingFiles = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$existingFiles) {
            $respondWithJson(['status' => 'error', 'message' => 'Partner not found.'], 404);
        }

        try {
            $pdo->beginTransaction();

            $deleteStatement = $pdo->prepare('DELETE FROM all_partners WHERE id = :id');
            $deleteStatement->execute([':id' => $partnerId]);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $respondWithJson(['status' => 'error', 'message' => 'Unable to delete the partner. Please try again.'], 500);
        }

        foreach ($existingFiles as $path) {
            if (is_string($path) && $path !== '') {
                $fullPath = __DIR__ . '/' . ltrim($path, '/');
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
        }

        $respondWithJson(['status' => 'success', 'message' => 'Partner deleted successfully.']);
    }

    if ($action === 'load_partner_for_edit') {
        $partnerId = (int) ($_POST['partner_id'] ?? 0);
        $partner = $fetchPartnerForResponse($pdo, $partnerId);

        if ($partner === null) {
            $respondWithJson(['status' => 'error', 'message' => 'Partner not found.'], 404);
        }

        $respondWithJson(['status' => 'success', 'data' => $partner]);
    }

    $respondWithJson(['status' => 'error', 'message' => 'Unsupported action requested.'], 400);
}

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
$uploadedFileMetadata = [];

$sanitize = static function (?string $value): string {
    return trim((string) $value);
};

$handleUpload = static function (string $fieldName) use (&$formErrors, &$uploadedFilePaths, &$uploadedFileMetadata, $uploadDirectory): ?array {
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

    $metadata = [
        'db_path' => 'uploads/partners/' . $filename,
        'original_name' => $originalName,
        'mime_type' => (string) ($file['type'] ?? ''),
        'file_size' => $fileSize,
    ];

    $uploadedFileMetadata[$fieldName] = $metadata;

    return $metadata;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $partnerAction = $_POST['partner_action'] ?? 'create';
    $editingPartnerId = (int) ($_POST['partner_id'] ?? 0);
    $isUpdateRequest = $partnerAction === 'update';
    $existingPartner = null;

    if ($isUpdateRequest) {
        if ($editingPartnerId <= 0) {
            $formErrors['general'] = 'Invalid partner selected for update.';
        } else {
            $partnerStatement = $pdo->prepare('SELECT * FROM all_partners WHERE id = :id');
            $partnerStatement->execute([':id' => $editingPartnerId]);
            $existingPartner = $partnerStatement->fetch(PDO::FETCH_ASSOC);

            if (!$existingPartner) {
                $formErrors['general'] = 'The selected partner could not be found.';
            }
        }
    }

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

    $reraCertificateUpload = null;
    $tradeLicenseUpload = null;
    $agreementUpload = null;

    if (empty($formErrors)) {
        try {
            $reraCertificateUpload = $handleUpload('rera_certificate');
            $tradeLicenseUpload = $handleUpload('trade_license');
            $agreementUpload = $handleUpload('agreement');
        } catch (Throwable $uploadException) {
            $formErrors['general'] = 'An unexpected error occurred while processing uploads.';
        }
    }

    if (empty($formErrors)) {
        try {
            $pdo->beginTransaction();

            $documentFields = [
                'rera_certificate' => 'RERA Certificate',
                'trade_license' => 'Trade License',
                'agreement' => 'Agreement/MOU',
            ];

            if ($isUpdateRequest && $existingPartner !== null) {
                $filesToDeleteAfterSuccess = [];

                $currentFileValues = [
                    'rera_certificate' => $existingPartner['rera_certificate'] ?? null,
                    'trade_license' => $existingPartner['trade_license'] ?? null,
                    'agreement' => $existingPartner['agreement'] ?? null,
                ];

                foreach ($documentFields as $field => $label) {
                    if (!isset($uploadedFileMetadata[$field])) {
                        continue;
                    }

                    $currentFileValues[$field] = $uploadedFileMetadata[$field]['db_path'];
                    if (!empty($existingPartner[$field])) {
                        $filesToDeleteAfterSuccess[] = __DIR__ . '/' . ltrim((string) $existingPartner[$field], '/');
                    }
                }

                $updateSql = <<<SQL
                    UPDATE all_partners SET
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
                    ':rera_certificate' => $currentFileValues['rera_certificate'],
                    ':trade_license' => $currentFileValues['trade_license'],
                    ':agreement' => $currentFileValues['agreement'],
                    ':id' => $editingPartnerId,
                ]);

                $deleteDocumentStatement = $pdo->prepare('DELETE FROM partner_documents WHERE partner_id = :partner_id AND document_type = :document_type');
                $insertDocumentStatement = $pdo->prepare(
                    'INSERT INTO partner_documents (partner_id, document_type, original_name, file_path, mime_type, file_size) VALUES (:partner_id, :document_type, :original_name, :file_path, :mime_type, :file_size)'
                );

                foreach ($documentFields as $field => $label) {
                    if (!isset($uploadedFileMetadata[$field])) {
                        continue;
                    }

                    $metadata = $uploadedFileMetadata[$field];

                    $deleteDocumentStatement->execute([
                        ':partner_id' => $editingPartnerId,
                        ':document_type' => $label,
                    ]);

                    $insertDocumentStatement->execute([
                        ':partner_id' => $editingPartnerId,
                        ':document_type' => $label,
                        ':original_name' => $metadata['original_name'],
                        ':file_path' => $metadata['db_path'],
                        ':mime_type' => $metadata['mime_type'] !== '' ? $metadata['mime_type'] : null,
                        ':file_size' => $metadata['file_size'],
                    ]);
                }

                $pdo->commit();

                foreach ($filesToDeleteAfterSuccess as $oldFile) {
                    if (is_string($oldFile) && $oldFile !== '' && file_exists($oldFile)) {
                        @unlink($oldFile);
                    }
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
                ':rera_certificate' => $reraCertificateUpload['db_path'] ?? null,
                ':trade_license' => $tradeLicenseUpload['db_path'] ?? null,
                ':agreement' => $agreementUpload['db_path'] ?? null,
            ]);

            $newPartnerId = (int) $pdo->lastInsertId();
            $partnerCode = sprintf('CP-%04d', $newPartnerId);

            $updateStatement = $pdo->prepare('UPDATE all_partners SET partner_code = :partner_code WHERE id = :id');
            $updateStatement->execute([
                ':partner_code' => $partnerCode,
                ':id' => $newPartnerId,
            ]);

            $insertDocumentSql = <<<SQL
                INSERT INTO partner_documents (
                    partner_id,
                    document_type,
                    original_name,
                    file_path,
                    mime_type,
                    file_size
                ) VALUES (
                    :partner_id,
                    :document_type,
                    :original_name,
                    :file_path,
                    :mime_type,
                    :file_size
                )
            SQL;

            $documentStatement = $pdo->prepare($insertDocumentSql);

            foreach ($documentFields as $field => $label) {
                if (!isset($uploadedFileMetadata[$field])) {
                    continue;
                }

                $metadata = $uploadedFileMetadata[$field];

                $documentStatement->execute([
                    ':partner_id' => $newPartnerId,
                    ':document_type' => $label,
                    ':original_name' => $metadata['original_name'],
                    ':file_path' => $metadata['db_path'],
                    ':mime_type' => $metadata['mime_type'] !== '' ? $metadata['mime_type'] : null,
                    ':file_size' => $metadata['file_size'],
                ]);
            }

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

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $successMessage = 'Partner has been updated successfully.';
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
        var leadSidebar = document.getElementById('leadSidebar');
        var overlay = document.getElementById('leadSidebarOverlay');
        var detailsView = document.getElementById('partnerDetailsView');
        var addPartnerForm = document.getElementById('addPartnerForm');
        var partnerActionInput = document.getElementById('partnerActionInput');
        var partnerIdInput = document.getElementById('partnerIdInput');
        var body = document.body;
        var preventSelector = '[data-prevent-lead-open]';

        if (!leadSidebar || !overlay || !detailsView) {
            return;
        }

        var titleElement = leadSidebar.querySelector('[data-sidebar-title]');
        var subtitleElement = leadSidebar.querySelector('[data-sidebar-subtitle]');
        var defaultTitle = titleElement ? titleElement.textContent.trim() : '';
        var defaultSubtitle = subtitleElement ? subtitleElement.textContent.trim() : '';
        var overlayHideTimeout = null;

        var detailFields = {};
        detailsView.querySelectorAll('[data-partner-detail]').forEach(function (element) {
            var key = element.getAttribute('data-partner-detail');
            if (key) {
                detailFields[key] = element;
            }
        });

        var documentFields = {};
        detailsView.querySelectorAll('[data-partner-document]').forEach(function (element) {
            var key = element.getAttribute('data-partner-document');
            if (key) {
                documentFields[key] = element;
            }
        });

        var closeTrigger = leadSidebar.querySelector('.lead-sidebar__close');
        var addPartnerTrigger = document.querySelector('[data-open-lead-sidebar]');

        function showOverlay() {
            if (!overlay) {
                return;
            }

            if (overlayHideTimeout) {
                window.clearTimeout(overlayHideTimeout);
                overlayHideTimeout = null;
            }

            overlay.hidden = false;
            window.requestAnimationFrame(function () {
                overlay.classList.add('is-visible');
            });
        }

        function hideOverlay() {
            if (!overlay) {
                return;
            }

            overlay.classList.remove('is-visible');
            if (overlayHideTimeout) {
                window.clearTimeout(overlayHideTimeout);
            }

            overlayHideTimeout = window.setTimeout(function () {
                overlay.hidden = true;
                overlayHideTimeout = null;
            }, 200);
        }

        function openSidebar() {
            leadSidebar.classList.add('is-open');
            leadSidebar.setAttribute('aria-hidden', 'false');
            body.classList.add('lead-sidebar-open');
            showOverlay();
        }

        function showFormContent() {
            detailsView.hidden = true;
            detailsView.setAttribute('aria-hidden', 'true');

            if (addPartnerForm) {
                addPartnerForm.hidden = false;
                addPartnerForm.removeAttribute('aria-hidden');
            }

            leadSidebar.classList.remove('is-viewing-partner');
        }

        function showDetailsContent() {
            if (addPartnerForm) {
                addPartnerForm.hidden = true;
                addPartnerForm.setAttribute('aria-hidden', 'true');
            }

            detailsView.hidden = false;
            detailsView.removeAttribute('aria-hidden');
            leadSidebar.classList.add('is-viewing-partner');
        }

        function applyTextValue(element, value, options) {
            if (!element) {
                return;
            }

            var settings = Object.assign({
                fallback: 'Not provided',
                preserveLineBreaks: false
            }, options || {});

            var hasValue = value !== null && typeof value !== 'undefined' && String(value).trim() !== '';

            if (hasValue) {
                var normalized = String(value).trim();

                if (settings.preserveLineBreaks) {
                    var fragments = normalized.split(/\r?\n/);
                    element.innerHTML = '';
                    fragments.forEach(function (fragment, index) {
                        element.appendChild(document.createTextNode(fragment));
                        if (index < fragments.length - 1) {
                            element.appendChild(document.createElement('br'));
                        }
                    });
                } else {
                    element.textContent = normalized;
                }

                element.classList.remove('is-empty');
            } else {
                element.textContent = settings.fallback;
                element.classList.add('is-empty');
            }
        }

        function applyLinkValue(element, value, builder, fallback) {
            if (!element) {
                return;
            }

            var emptyText = fallback || 'Not provided';
            var linkBuilder = typeof builder === 'function' ? builder : function (input) {
                return input;
            };

            element.innerHTML = '';

            var hasValue = value !== null && typeof value !== 'undefined' && String(value).trim() !== '';
            if (hasValue) {
                var normalized = String(value).trim();
                var href = linkBuilder(normalized);
                var anchor = document.createElement('a');
                anchor.href = href || '#';
                anchor.textContent = normalized;

                if (/^https?:/i.test(anchor.href)) {
                    anchor.target = '_blank';
                    anchor.rel = 'noopener';
                }

                anchor.classList.add('text-decoration-none');
                element.appendChild(anchor);
                element.classList.remove('is-empty');
            } else {
                element.textContent = emptyText;
                element.classList.add('is-empty');
            }
        }

        function applyDocumentValue(element, documentInfo) {
            if (!element) {
                return;
            }

            element.innerHTML = '';

            if (documentInfo && documentInfo.url) {
                var anchor = document.createElement('a');
                anchor.href = documentInfo.url;
                anchor.textContent = documentInfo.name && documentInfo.name.trim() !== '' ? documentInfo.name : 'View document';
                anchor.target = '_blank';
                anchor.rel = 'noopener';
                anchor.classList.add('text-decoration-none');
                element.appendChild(anchor);
                element.classList.remove('is-empty');
            } else {
                element.textContent = 'Not uploaded';
                element.classList.add('is-empty');
            }
        }

        function populateDetails(partnerData) {
            if (!partnerData) {
                return;
            }

            applyTextValue(detailFields.partner_code, partnerData.partner_code, { fallback: 'Not available' });
            applyTextValue(detailFields.company_name, partnerData.company_name);
            applyTextValue(detailFields.contact_person, partnerData.contact_person);
            applyTextValue(detailFields.status, partnerData.status, { fallback: 'Not set' });
            applyTextValue(detailFields.commission_structure, partnerData.commission_structure, { fallback: 'Not set' });
            applyTextValue(detailFields.remarks, partnerData.remarks, { preserveLineBreaks: true });

            applyLinkValue(detailFields.email, partnerData.email, function (value) {
                return 'mailto:' + value;
            });

            applyLinkValue(detailFields.phone, partnerData.phone, function (value) {
                return 'tel:' + value;
            });

            applyTextValue(detailFields.whatsapp, partnerData.whatsapp);
            applyTextValue(detailFields.country, partnerData.country);
            applyTextValue(detailFields.city, partnerData.city);
            applyTextValue(detailFields.address, partnerData.address, { preserveLineBreaks: true });

            applyTextValue(detailFields.rera_number, partnerData.rera_number);
            applyTextValue(detailFields.license_number, partnerData.license_number);

            applyLinkValue(detailFields.website, partnerData.website, function (value) {
                var trimmed = value.trim();
                if (/^https?:\/\//i.test(trimmed)) {
                    return trimmed;
                }
                return 'https://' + trimmed;
            });

            applyTextValue(detailFields.created_at, partnerData.created_at, { fallback: 'Not available' });

            if (partnerData.documents) {
                Object.keys(documentFields).forEach(function (key) {
                    applyDocumentValue(documentFields[key], partnerData.documents[key] || null);
                });
            } else {
                Object.keys(documentFields).forEach(function (key) {
                    applyDocumentValue(documentFields[key], null);
                });
            }
        }

        function parsePartnerDataFromRow(row) {
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
                console.error('Unable to parse partner data', error);
                return null;
            }
        }

        function setFormFieldValue(fieldName, value) {
            if (!addPartnerForm) {
                return;
            }

            var field = addPartnerForm.elements.namedItem(fieldName);
            if (!field) {
                return;
            }

            if (typeof field.length === 'number' && typeof field.value === 'undefined' && field.length > 0) {
                field = field[0];
            }

            if (field && typeof field.value !== 'undefined') {
                field.value = value !== null && typeof value !== 'undefined' ? String(value) : '';
            }
        }

        function populateFormFields(partnerData) {
            if (!partnerData || !addPartnerForm) {
                return;
            }

            setFormFieldValue('company_name', partnerData.company_name || '');
            setFormFieldValue('contact_person', partnerData.contact_person || '');
            setFormFieldValue('email', partnerData.email || '');
            setFormFieldValue('phone', partnerData.phone || '');
            setFormFieldValue('whatsapp', partnerData.whatsapp || '');
            setFormFieldValue('country', partnerData.country || '');
            setFormFieldValue('city', partnerData.city || '');
            setFormFieldValue('address', partnerData.address || '');
            setFormFieldValue('rera_number', partnerData.rera_number || '');
            setFormFieldValue('license_number', partnerData.license_number || '');
            setFormFieldValue('website', partnerData.website || '');
            setFormFieldValue('status', partnerData.status || '');
            setFormFieldValue('commission_structure', partnerData.commission_structure || '');
            setFormFieldValue('remarks', partnerData.remarks || '');
        }

        function updateHeaderForForm(mode, partnerData) {
            if (!titleElement || !subtitleElement) {
                return;
            }

            if (mode === 'edit') {
                var name = partnerData && partnerData.company_name ? partnerData.company_name.trim() : '';
                titleElement.textContent = name !== '' ? 'Edit: ' + name : 'Edit Partner';
                subtitleElement.textContent = 'Update partner information and documents';
            } else {
                titleElement.textContent = defaultTitle;
                subtitleElement.textContent = defaultSubtitle;
            }
        }

        function updateHeaderForDetails(partnerData) {
            if (!titleElement || !subtitleElement) {
                return;
            }

            var name = partnerData && partnerData.company_name ? partnerData.company_name.trim() : '';
            titleElement.textContent = name !== '' ? name : 'Partner Details';

            if (partnerData && partnerData.partner_code && partnerData.partner_code.trim() !== '') {
                subtitleElement.textContent = 'Partner Code: ' + partnerData.partner_code;
            } else {
                subtitleElement.textContent = 'Review partner information';
            }
        }

        function showFormMode(options) {
            options = options || {};
            var mode = options.mode === 'edit' ? 'edit' : 'create';
            var shouldReset = options.resetForm === true;
            var shouldOpen = options.shouldOpen !== false;
            var partnerData = options.partner || null;

            showFormContent();

            if (shouldReset && addPartnerForm) {
                addPartnerForm.reset();
            }

            if (partnerActionInput) {
                partnerActionInput.value = mode === 'edit' ? 'update' : 'create';
            }

            if (partnerIdInput) {
                partnerIdInput.value = mode === 'edit' && partnerData && partnerData.id ? partnerData.id : '';
            }

            if (mode === 'edit' && partnerData) {
                populateFormFields(partnerData);
            }

            updateHeaderForForm(mode, partnerData);

            if (shouldOpen) {
                openSidebar();
            }
        }

        function showPartnerDetails(partnerData) {
            if (!partnerData) {
                return;
            }

            populateDetails(partnerData);
            updateHeaderForDetails(partnerData);
            showDetailsContent();
            openSidebar();
        }

        function closeSidebar() {
            leadSidebar.classList.remove('is-open');
            leadSidebar.setAttribute('aria-hidden', 'true');
            body.classList.remove('lead-sidebar-open');
            leadSidebar.classList.remove('is-viewing-partner');
            hideOverlay();
            showFormMode({
                mode: 'create',
                resetForm: false,
                shouldOpen: false
            });
        }

        function sendPartnerRequest(action, params) {
            var payload = new URLSearchParams();
            payload.append('action', action);

            Object.keys(params || {}).forEach(function (key) {
                if (typeof params[key] !== 'undefined') {
                    payload.append(key, params[key]);
                }
            });

            return fetch('channel-partners.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload.toString()
            }).then(function (response) {
                return response.json().catch(function () {
                    return {};
                }).then(function (data) {
                    if (!response.ok) {
                        var message = data && data.message ? data.message : 'Unable to process the request.';
                        throw new Error(message);
                    }

                    if (!data || typeof data.status === 'undefined') {
                        throw new Error('Unexpected server response.');
                    }

                    if (data.status !== 'success') {
                        throw new Error(data.message || 'Request failed.');
                    }

                    return data;
                });
            });
        }

        function fetchPartnerData(partnerId) {
            if (!partnerId) {
                return Promise.reject(new Error('Invalid partner selected.'));
            }

            return sendPartnerRequest('fetch_partner', { partner_id: partnerId }).then(function (result) {
                return result.data || null;
            });
        }

        function loadPartnerForEdit(partnerId) {
            if (!partnerId) {
                return Promise.reject(new Error('Invalid partner selected.'));
            }

            return sendPartnerRequest('load_partner_for_edit', { partner_id: partnerId }).then(function (result) {
                return result.data || null;
            });
        }

        function deletePartner(partnerId) {
            if (!partnerId) {
                return Promise.reject(new Error('Invalid partner selected.'));
            }

            return sendPartnerRequest('delete_partner', { partner_id: partnerId });
        }

        function closeAnyDropdown(element) {
            if (!element) {
                return;
            }

            var dropdown = element.closest('.dropdown');
            if (!dropdown) {
                return;
            }

            var toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
            if (toggle && typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
                var instance = bootstrap.Dropdown.getInstance(toggle) || new bootstrap.Dropdown(toggle);
                instance.hide();
            } else {
                dropdown.classList.remove('show');
                var menu = dropdown.querySelector('.dropdown-menu');
                if (menu) {
                    menu.classList.remove('show');
                }
            }
        }

        function handleViewAction(partnerId, fallbackData) {
            return fetchPartnerData(partnerId).then(function (partner) {
                showPartnerDetails(partner);
            }).catch(function (error) {
                if (fallbackData) {
                    console.warn('Falling back to table data for partner view:', error);
                    showPartnerDetails(fallbackData);
                    return;
                }

                window.alert(error.message || 'Unable to load partner details.');
            });
        }

        function handleEditAction(partnerId) {
            return loadPartnerForEdit(partnerId).then(function (partner) {
                showFormMode({
                    mode: 'edit',
                    partner: partner,
                    resetForm: true
                });
            }).catch(function (error) {
                window.alert(error.message || 'Unable to load partner for editing.');
            });
        }

        function handleDeleteAction(partnerId) {
            if (!window.confirm('Are you sure you want to delete this partner? This action cannot be undone.')) {
                return;
            }

            deletePartner(partnerId).then(function () {
                window.location.reload();
            }).catch(function (error) {
                window.alert(error.message || 'Unable to delete the partner.');
            });
        }

        document.querySelectorAll('[data-action="view"][data-partner-id]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();

                closeAnyDropdown(button);

                var row = button.closest('tr[data-partner-id]');
                var partnerId = row ? parseInt(row.getAttribute('data-partner-id'), 10) : parseInt(button.getAttribute('data-partner-id'), 10);
                var fallback = row ? parsePartnerDataFromRow(row) : null;

                if (!partnerId) {
                    window.alert('Unable to determine the selected partner.');
                    return;
                }

                handleViewAction(partnerId, fallback);
            });
        });

        document.querySelectorAll('[data-action="edit"][data-partner-id]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();

                closeAnyDropdown(button);

                var partnerId = parseInt(button.getAttribute('data-partner-id'), 10);
                if (!partnerId) {
                    window.alert('Unable to determine the selected partner.');
                    return;
                }

                handleEditAction(partnerId);
            });
        });

        document.querySelectorAll('[data-action="delete"][data-partner-id]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();

                closeAnyDropdown(button);

                var partnerId = parseInt(button.getAttribute('data-partner-id'), 10);
                if (!partnerId) {
                    window.alert('Unable to determine the selected partner.');
                    return;
                }

                handleDeleteAction(partnerId);
            });
        });

        var partnerTableBody = document.querySelector('.lead-table tbody');
        if (partnerTableBody) {
            partnerTableBody.addEventListener('click', function (event) {
                var targetElement = event.target instanceof Element ? event.target : null;

                if (!targetElement) {
                    return;
                }

                if (targetElement.closest('[data-action]') || (typeof targetElement.closest === 'function' && targetElement.closest(preventSelector))) {
                    return;
                }

                var clickedRow = targetElement.closest('tr[data-partner-id]');
                if (!clickedRow) {
                    return;
                }

                var partnerId = parseInt(clickedRow.getAttribute('data-partner-id'), 10);
                if (!partnerId) {
                    return;
                }

                var fallback = parsePartnerDataFromRow(clickedRow);
                handleViewAction(partnerId, fallback);
            });
        }

        if (addPartnerTrigger) {
            addPartnerTrigger.addEventListener('click', function () {
                showFormMode({
                    mode: 'create',
                    resetForm: true
                });
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function () {
                closeSidebar();
            });
        }

        if (closeTrigger) {
            closeTrigger.addEventListener('click', function () {
                closeSidebar();
            });
        }

        var viewCloseButton = detailsView.querySelector('[data-partner-details-close]');
        if (viewCloseButton) {
            viewCloseButton.addEventListener('click', function (event) {
                event.preventDefault();
                closeSidebar();
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeSidebar();
            }
        });

        var initialMode = addPartnerForm ? addPartnerForm.getAttribute('data-initial-mode') : 'create';
        if (initialMode === 'edit') {
            showFormMode({
                mode: 'edit',
                resetForm: false,
                shouldOpen: false
            });
        } else {
            showFormMode({
                mode: 'create',
                resetForm: false,
                shouldOpen: false
            });
        }
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

                                    $createdAtRaw = $partner['created_at'] ?? '';
                                    $createdAtDisplay = '';

                                    if ($createdAtRaw !== '') {
                                        try {
                                            $createdAtDisplay = (new DateTimeImmutable($createdAtRaw))->format('d M Y, h:i A');
                                        } catch (Throwable $dateException) {
                                            $createdAtDisplay = $createdAtRaw;
                                        }
                                    }

                                    $partnerDocuments = [
                                        'rera_certificate' => $partner['rera_certificate'] ?? null,
                                        'trade_license' => $partner['trade_license'] ?? null,
                                        'agreement' => $partner['agreement'] ?? null,
                                    ];

                                    $documentsForView = [];

                                    foreach ($partnerDocuments as $documentKey => $documentPath) {
                                        if ($documentPath === null || $documentPath === '') {
                                            $documentsForView[$documentKey] = null;
                                            continue;
                                        }

                                        $documentsForView[$documentKey] = [
                                            'url' => hh_asset($documentPath),
                                            'name' => basename((string) $documentPath),
                                        ];
                                    }

                                    $partnerDataForView = [
                                        'id' => (int) ($partner['id'] ?? 0),
                                        'partner_code' => $partnerCode,
                                        'company_name' => $companyName,
                                        'contact_person' => $contactPerson,
                                        'email' => $email,
                                        'phone' => $phone,
                                        'whatsapp' => $whatsapp,
                                        'country' => $country,
                                        'city' => $city,
                                        'address' => $partner['address'] ?? '',
                                        'status' => $status,
                                        'commission_structure' => $commission,
                                        'remarks' => $partner['remarks'] ?? '',
                                        'rera_number' => $partner['rera_number'] ?? '',
                                        'license_number' => $partner['license_number'] ?? '',
                                        'website' => $partner['website'] ?? '',
                                        'created_at' => $createdAtDisplay,
                                        'documents' => $documentsForView,
                                    ];

                                    $partnerJson = json_encode($partnerDataForView, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                    if ($partnerJson === false) {
                                        $partnerJson = '{}';
                                    }
                                    ?>
                                    <tr class="lead-table-row" data-partner-id="<?= (int) ($partner['id'] ?? 0) ?>" data-partner-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" data-partner-json='<?= htmlspecialchars($partnerJson, ENT_QUOTES, 'UTF-8') ?>'>
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
                            <p class="lead-sidebar__header-title mb-1" data-sidebar-title>Add New Partner</p>
                            <p class="text-white small" data-sidebar-subtitle>Enter partner details to add them to your network</p>
                        </div>
                    </div>
                </header>

                <div class="lead-sidebar__body">
                    <div id="partnerDetailsView" class="lead-sidebar__view" hidden>
                        <section class="lead-sidebar__section">
                            <h3 class="lead-sidebar__section-title">Partner Overview</h3>
                            <div class="lead-sidebar__details">
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">Partner Code</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="partner_code"></div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">Company Name</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="company_name"></div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">Contact Person</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="contact_person"></div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">Status</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="status"></div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">Commission Structure</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="commission_structure"></div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">Remarks</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="remarks"></div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="lead-sidebar__section mt-4">
                            <h3 class="lead-sidebar__section-title">Contact & Location</h3>
                            <div class="lead-sidebar__details">
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">Email</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="email"></div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">Phone</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="phone"></div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">WhatsApp</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="whatsapp"></div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">Country</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="country"></div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">City</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="city"></div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">Address</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="address"></div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="lead-sidebar__section mt-4">
                            <h3 class="lead-sidebar__section-title">Compliance & Activity</h3>
                            <div class="lead-sidebar__details">
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">RERA Number</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="rera_number"></div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">License Number</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="license_number"></div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">Website</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="website"></div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">Created On</div>
                                        <div class="lead-sidebar__item-value" data-partner-detail="created_at"></div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="lead-sidebar__section mt-4">
                            <h3 class="lead-sidebar__section-title">Documents</h3>
                            <div class="lead-sidebar__details">
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">RERA Certificate</div>
                                        <div class="lead-sidebar__item-value" data-partner-document="rera_certificate"></div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">Trade License</div>
                                        <div class="lead-sidebar__item-value" data-partner-document="trade_license"></div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <div class="lead-sidebar__item-content">
                                        <div class="lead-sidebar__item-label">Agreement / MOU</div>
                                        <div class="lead-sidebar__item-value" data-partner-document="agreement"></div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="text-end mt-4">
                            <button type="button" class="btn btn-outline-secondary" data-partner-details-close>Close</button>
                        </div>
                    </div>

                    <?php
                    $initialFormMode = isset($_POST['partner_action']) && $_POST['partner_action'] === 'update' ? 'edit' : 'create';
                    ?>
                    <form id="addPartnerForm" class="lead-sidebar__form" method="post" enctype="multipart/form-data" novalidate data-initial-mode="<?= htmlspecialchars($initialFormMode, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="partner_action" id="partnerActionInput" value="<?= htmlspecialchars($_POST['partner_action'] ?? 'create', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="partner_id" id="partnerIdInput" value="<?= isset($_POST['partner_id']) ? (int) $_POST['partner_id'] : '' ?>">
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