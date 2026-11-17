<?php
session_start();
include "db.php";

// Admin lang ang puwede dito
if (!isset($_SESSION["admin_id"])) {
    header("Location: admin.php");
    exit();
}

// Mga serbisyo: key sa database => pangalan => page ng listahan
$services = [
    "barangay_clearance"       => "Barangay Clearance",
    "certificate_of_residency" => "Certificate of Residency",
    "certificate_of_indigency" => "Certificate of Indigency",
    "business_clearance"       => "Barangay Business Clearance",
];

// Bilang ng Pending bawat serbisyo
$pending = [];
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT service, COUNT(*) AS total FROM requests WHERE status = 'Pending' GROUP BY service");
        while ($row = $stmt->fetch()) {
            $pending[$row["service"]] = (int)$row["total"];
        }
    } elseif ($conn) {
        $result = mysqli_query($conn, "SELECT service, COUNT(*) AS total FROM requests WHERE status = 'Pending' GROUP BY service");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $pending[$row["service"]] = (int)$row["total"];
            }
        }
    }
} catch (Exception $e) {
    // Ignore error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="frame full">
    <nav class="frame-nav">
        <a href="admin_dashboard.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <a href="admin_dashboard.php" class="active">Dashboard</a>
            <a href="admin_history.php">Lahat ng Requests (History)</a>
            <a href="admin_logout.php">Logout</a>
        </div>
    </nav>

    <main class="page-main">
        <div class="dashboard">
            <h1>Dashboard</h1>
            <p class="page-sub">Pumili ng service para makita ang mga request.</p>

            <div class="dashboard-grid">
                <?php foreach ($services as $key => $name): ?>
                    <a href="admin_requests.php?service=<?= urlencode($key); ?>" class="stat-card">
                        <h3><?= htmlspecialchars($name, ENT_QUOTES); ?></h3>
                        <span class="count"><?= $pending[$key] ?? 0; ?></span>
                        <span class="label">Pending</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

</body>
</html>