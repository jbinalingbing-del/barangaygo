<?php
session_start();

// Kailangang naka-login para makita ang page na ito
// if (!isset($_SESSION["resident_id"])) {
//     header("Location: login.php");
//     exit();
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mga Serbisyo - BarangayGo</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="frame full">
    <nav class="frame-nav">
        <a href="services.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <a href="services.php" class="active">Mga Serbisyo</a>
            <a href="my_requests.php">My Requests</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </nav>

    <main class="page-main">
        <div class="services">
            <h1>Ano ang maitutulong ng barangay sa iyo?</h1>
            <p>Pumili ng serbisyo para magsimula.</p>

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
