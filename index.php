<?php
/**
 * MAIN DASHBOARD WRAPPER
 */
require_once __DIR__ . '/includes/session.php';
require_login(); // Security check from session.php

// Page-specific variables
$page_title = "Dashboard | CAO I-M-S";
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $page_title ?></title>

    <link rel="icon" type="image/x-icon" href="<?= site_url('assets/img/favicon/favicon.ico') ?>" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= site_url('assets/vendor/fonts/boxicons.css') ?>" />

    <link rel="stylesheet" href="<?= site_url('assets/vendor/css/core.css') ?>" class="template-customizer-core-css" />
    <link rel="stylesheet" href="<?= site_url('assets/vendor/css/theme-default.css') ?>" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="<?= site_url('assets/css/demo.css') ?>" />
    <link rel="stylesheet" href="<?= site_url('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') ?>" />
    <link rel="stylesheet" href="<?= site_url('assets/vendor/libs/apex-charts/apex-charts.css') ?>" />

    <script src="<?= site_url('assets/vendor/js/helpers.js') ?>"></script>
    <script src="<?= site_url('assets/js/config.js') ?>"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            
            <?php include __DIR__ . '/includes/sidebar.php'; ?>

            <!-- <div class="layout-page"> -->
                
                <?php include __DIR__ . '/includes/navbar.php'; ?>

                <div class="content-wrapper">
                    
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <?php include 'includes/content.php'; ?>
                    </div>

                    <?php include __DIR__ . '/includes/footer.php'; ?>

                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <script src="<?= site_url('assets/vendor/libs/jquery/jquery.js') ?>"></script>
    <script src="<?= site_url('assets/vendor/libs/popper/popper.js') ?>"></script>
    <script src="<?= site_url('assets/vendor/js/bootstrap.js') ?>"></script>
    <script src="<?= site_url('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') ?>"></script>
    <script src="<?= site_url('assets/vendor/js/menu.js') ?>"></script>
    <script src="<?= site_url('assets/js/main.js') ?>"></script>
</body>
</html>