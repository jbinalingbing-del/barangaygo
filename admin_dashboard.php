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
$total_residents = 0;
try {
    if ($pdo) {
        $total_residents = (int)$pdo->query("SELECT COUNT(*) FROM residents")->fetchColumn();
        $stmt = $pdo->query("SELECT service, COUNT(*) AS total FROM requests WHERE status = 'Pending' GROUP BY service");
        while ($row = $stmt->fetch()) {
            $pending[$row["service"]] = (int)$row["total"];
        }
    } elseif ($conn) {
        $tr = mysqli_query($conn, "SELECT COUNT(*) FROM residents");
        if ($tr) $total_residents = (int)mysqli_fetch_row($tr)[0];
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
            <a href="admin_residents.php">Residents</a>
            <a href="admin_history.php">All Requests</a>
            <a href="admin_logout.php">Logout</a>
        </div>
    </nav>

    <main class="page-main">
        <div class="dashboard">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
                <div>
                    <h1 style="margin: 0; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em;">Dashboard</h1>
                    <p class="page-sub" style="margin: 4px 0 0;">Select a service to view requests or manage registered residents.</p>
                </div>
                <a href="admin_residents.php" class="btn" style="background: #1e293b; border-color: #1e293b; color: #fff; text-decoration: none; padding: 10px 18px; font-weight: 700; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.02em;">
                    👥 Manage Residents (<?= $total_residents; ?>)
                </a>
            </div>

            <div class="dashboard-grid">
                <?php foreach ($services as $key => $name): ?>
                    <a href="admin_requests.php?service=<?= urlencode($key); ?>" class="stat-card">
                        <h3><?= htmlspecialchars($name, ENT_QUOTES); ?></h3>
                        <span class="count" style="color: #0f172a; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-weight: 700;"><?= $pending[$key] ?? 0; ?></span>
                        <span class="label">Pending</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

</body>
</html>