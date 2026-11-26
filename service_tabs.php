<?php
// =============================================================================
// service_tabs.php — BarangayGo Modern Breadcrumbs Navigation Bar
// Pinalitan ang lumang 2x2 buttons ng malinis na breadcrumb trail
// =============================================================================

$active_service = $active_service ?? '';

$service_names = [
    'clearance' => 'Barangay Clearance',
    'residency' => 'Certificate of Residency',
    'indigency' => 'Certificate of Indigency',
    'business'  => 'Barangay Business Clearance',
];

$current_service_name = $service_names[$active_service] ?? 'Document Request';
?>

<!-- Modern Breadcrumbs Bar -->
<nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <div class="breadcrumb-container">
                <ol class="breadcrumb-list">
            <li class="breadcrumb-item">
                <a href="services.php">Services</a>
            </li>
            <li class="breadcrumb-separator" aria-hidden="true">&rsaquo;</li>

            <li class="breadcrumb-item active" aria-current="page">
                <span><?= htmlspecialchars($current_service_name, ENT_QUOTES); ?></span>
            </li>
        </ol>
    </div>
</nav>
