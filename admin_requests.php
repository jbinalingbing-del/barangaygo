<?php
session_start();
include "db.php";

// Admin lang ang puwede dito
if (!isset($_SESSION["admin_id"])) {
    header("Location: admin.php");
    exit();
}

// Mga serbisyo (ito lang ang tinatanggap sa ?service=)
$services = [
    "barangay_clearance"       => "Barangay Clearance",
    "certificate_of_residency" => "Certificate of Residency",
    "certificate_of_indigency" => "Certificate of Indigency",
    "business_clearance"       => "Barangay Business Clearance",
];

$service_key = is_string($_GET["service"] ?? null) ? $_GET["service"] : "";
if (!isset($services[$service_key])) {
    header("Location: admin_dashboard.php");
    exit();
}
$service_name = $services[$service_key];

// Mga status at ang class ng badge nila
$statuses = [
    "Pending"           => "badge-pending",
    "Approved"          => "badge-approved",
    "Rejected"          => "badge-rejected",
    "Ready"             => "badge-ready",
    "Released"          => "badge-released",
];

$status = is_string($_GET["status"] ?? null) ? $_GET["status"] : "Pending";
if ($status === "Ready for Release") $status = "Ready";
if (!isset($statuses[$status])) {
    $status = "Pending";
}

// Kunin ang mga request ng napiling serbisyo at status
$rows  = [];
$error = "";

if (!$pdo && !$conn) {
    $error = "Wala pang database connection.";
} else {
    try {
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT r.request_id, res.full_name, r.created_at, r.status
                                   FROM requests r
                                   JOIN residents res ON res.resident_id = r.resident_id
                                   WHERE r.service = ? AND r.status = ?
                                   ORDER BY r.created_at DESC");
            $stmt->execute([$service_key, $status]);
            while ($r = $stmt->fetch()) {
                $rows[] = [
                    "id"     => $r["request_id"],
                    "name"   => $r["full_name"],
                    "date"   => date("M d, Y", strtotime($r["created_at"])),
                    "status" => $r["status"],
                ];
            }
        } elseif ($conn) {
            $stmt = mysqli_prepare($conn, "SELECT r.request_id, res.full_name, r.created_at, r.status
                                           FROM requests r
                                           JOIN residents res ON res.resident_id = r.resident_id
                                           WHERE r.service = ? AND r.status = ?
                                           ORDER BY r.created_at DESC");
            mysqli_stmt_bind_param($stmt, "ss", $service_key, $status);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $request_id, $full_name, $created_at, $row_status);

            while (mysqli_stmt_fetch($stmt)) {
                $rows[] = [
                    "id"     => $request_id,
                    "name"   => $full_name,
                    "date"   => date("M d, Y", strtotime($created_at)),
                    "status" => $row_status,
                ];
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($service_name, ENT_QUOTES); ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="frame full">
    <nav class="frame-nav">
        <a href="admin_dashboard.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="admin_history.php">Lahat ng Requests</a>
            <a href="admin_logout.php">Logout</a>
        </div>
    </nav>

    <main class="page-main top">
        <div class="admin-list">
            <a href="admin_dashboard.php" class="back-link">&larr; Back</a>

            <h1><?= htmlspecialchars($service_name, ENT_QUOTES); ?></h1>
            <p class="page-sub">Mga request ng iyong barangay.</p>

            <form method="GET" action="" class="filter-bar">
                <input type="hidden" name="service" value="<?= htmlspecialchars($service_key, ENT_QUOTES); ?>">
                <label for="status">Filter:</label>
                <select id="status" name="status" onchange="this.form.submit()">
                    <?php foreach ($statuses as $name => $badge): ?>
                        <option value="<?= htmlspecialchars($name, ENT_QUOTES); ?>"<?= $status === $name ? " selected" : ""; ?>><?= htmlspecialchars($name, ENT_QUOTES); ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="btn-sm">Apply</button></noscript>
            </form>

            <?php if ($error !== ""): ?>
                <p class="message" role="alert"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
            <?php endif; ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th class="num">Blg.</th>
                            <th>Pangalan</th>
                            <th>Petsa</th>
                            <th>Status</th>
                            <th class="action"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows) == 0): ?>
                            <tr>
                                <td colspan="5" class="empty">Wala pang <?= htmlspecialchars(strtolower($status), ENT_QUOTES); ?> na request.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $i => $row): ?>
                                <tr>
                                    <td><?= $i + 1; ?></td>
                                    <td><?= htmlspecialchars($row["name"], ENT_QUOTES); ?></td>
                                    <td><?= htmlspecialchars($row["date"], ENT_QUOTES); ?></td>
                                    <td><span class="badge <?= $statuses[$row["status"]] ?? "badge-pending"; ?>"><?= htmlspecialchars($row["status"], ENT_QUOTES); ?></span></td>
                                    <td class="action"><a href="admin_view.php?id=<?= (int) $row["id"]; ?>" class="btn btn-sm">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

</body>
</html>