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

<!-- Modern Breadcrumbs Bar (may inline styles para siguradong maayos kahit naka-cache ang CSS) -->
<style>
.breadcrumb-bar {
    width: 100%;
    margin-bottom: 24px;
    background: #ffffff;
    border: 1px solid #e5e5e7;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}
.breadcrumb-container {
    display: flex;
    align-items: center;
    padding: 12px 18px;
    overflow-x: auto;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
}
.breadcrumb-list {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: 0.88rem;
    font-weight: 500;
}
.breadcrumb-item {
    display: inline-flex;
    align-items: center;
}
.breadcrumb-item a {
    color: #6b6b73;
    text-decoration: none;
    font-weight: 500;
}
.breadcrumb-item a:hover {
    color: #6d4df2;
    text-decoration: underline;
}
.breadcrumb-item.active span {
    color: #111111;
    font-weight: 700;
}
.breadcrumb-muted {
    color: #6b6b73;
}
.breadcrumb-separator {
    display: inline-flex;
    align-items: center;
    padding: 0 10px;
    color: #a1a1aa;
    font-size: 1.1rem;
    user-select: none;
}
</style>
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
