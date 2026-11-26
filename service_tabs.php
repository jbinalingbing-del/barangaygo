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
        <!-- Home / Menu Icon -->
        <a href="dashboard.php" class="breadcrumb-icon" title="Dashboard">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </a>

        <ol class="breadcrumb-list">
            <li class="breadcrumb-item">
                <a href="dashboard.php">Home</a>
            </li>
            <li class="breadcrumb-separator" aria-hidden="true">&rsaquo;</li>
            
            <li class="breadcrumb-item">
                <a href="services.php">Services</a>
            </li>
            <li class="breadcrumb-separator" aria-hidden="true">&rsaquo;</li>
            
            <li class="breadcrumb-item">
                <span class="breadcrumb-muted">Request</span>
            </li>
            <li class="breadcrumb-separator" aria-hidden="true">&rsaquo;</li>

            <li class="breadcrumb-item active" aria-current="page">
                <span><?= htmlspecialchars($current_service_name, ENT_QUOTES); ?></span>
            </li>
        </ol>
    </div>
</nav>
