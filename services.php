<?php
// =============================================================================
// services.php - BarangayGo Services Portal
// =============================================================================
// Tungkulin:
// - Ipinapakita ang 4 na pangunahing serbisyo ng barangay:
//   1. Barangay Clearance
//   2. Certificate of Residency
//   3. Certificate of Indigency
//   4. Barangay Business Clearance
// - Gumagamit ng shared locked navigation bar (resident_nav.php).
// =============================================================================
session_start();

// Kailangang naka-login para makita ang page na ito
if (!isset($_SESSION["resident_id"])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - BarangayGo</title>
    <!-- Naka-cache buster ang CSS para siguradong updated sa lahat ng devices -->
    <link rel="stylesheet" href="styles.css?v=<?= @filemtime(__DIR__ . '/styles.css') ?: 1; ?>">
</head>
<body>

<div class="frame full">
    <!-- Ipakita ang shared resident navigation bar -->
    <?php $active_nav = 'services'; include 'resident_nav.php'; ?>

    <main class="page-main">
        <div class="services">
            <h1>How can the barangay help you today?</h1>

            <!-- Grid ng apat na serbisyo -->
            <div class="service-menu">
                <a href="request_clearance.php" class="service-btn">Barangay Clearance</a>
                <a href="request_residency.php" class="service-btn">Certificate of Residency</a>
                <a href="request_indigency.php" class="service-btn">Certificate of Indigency</a>
                <a href="request_business.php" class="service-btn">Barangay Business Clearance</a>
            </div>
        </div>
    </main>
</div>

</body>
</html>
