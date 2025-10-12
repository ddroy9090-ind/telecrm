<?php
$pageTitle = $pageTitle ?? 'Admin Panel - Dashboard';
$metaDescription = $metaDescription ?? '';
$metaKeywords = $metaKeywords ?? '';
$htmlLang = $htmlLang ?? 'hi';
$additionalStyles = $additionalStyles ?? [];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if ($metaDescription !== ''): ?>
        <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($metaKeywords !== ''): ?>
        <meta name="keywords" content="<?= htmlspecialchars($metaKeywords, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <link rel="icon" href="assets/images/logo/favicon.svg" type="image/svg+xml">
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Component Libraries -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/country-select-js@2.0.1/build/css/countrySelect.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/css/intlTelInput.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
    <link href="assets/css/properties.css" rel="stylesheet">

    <?php foreach ($additionalStyles as $styleHref): ?>
        <?php if (is_string($styleHref) && $styleHref !== ''): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($styleHref, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
    <?php endforeach; ?>

</head>
<body>

