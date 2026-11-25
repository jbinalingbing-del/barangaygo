<?php
session_start();
include "db.php";

if (!isset($_SESSION["resident_id"])) {
    header("Location: login.php");
    exit();
}

$resident_id = (int)$_SESSION["resident_id"];

// Kunin ang bilang ng bawat status at pinakabagong 5 requests
$req_stats = [
    "total"    => 0,
    "pending"  => 0,
    "approved" => 0,
    "ready"    => 0,
    "released" => 0,
    "rejected" => 0,
];
$recent_requests = [];

try {
    if ($pdo) {
        $st_stmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM requests WHERE resident_id = ? GROUP BY status");
        $st_stmt->execute([$resident_id]);
        while ($st = $st_stmt->fetch()) {
            $key = strtolower($st["status"]);
            if (isset($req_stats[$key])) $req_stats[$key] = (int)$st["cnt"];
            $req_stats["total"] += (int)$st["cnt"];
        }
        $rc_stmt = $pdo->prepare("SELECT request_id, service, status, created_at FROM requests WHERE resident_id = ? ORDER BY created_at DESC LIMIT 5");
        $rc_stmt->execute([$resident_id]);
        $recent_requests = $rc_stmt->fetchAll();
    } elseif ($conn) {
        $st_res = mysqli_query($conn, "SELECT status, COUNT(*) AS cnt FROM requests WHERE resident_id = $resident_id GROUP BY status");
        if ($st_res) {
            while ($st = mysqli_fetch_assoc($st_res)) {
                $key = strtolower($st["status"]);
                if (isset($req_stats[$key])) $req_stats[$key] = (int)$st["cnt"];
                $req_stats["total"] += (int)$st["cnt"];
            }
        }
        $rc_res = mysqli_query($conn, "SELECT request_id, service, status, created_at FROM requests WHERE resident_id = $resident_id ORDER BY created_at DESC LIMIT 5");
        if ($rc_res) {
            while ($row = mysqli_fetch_assoc($rc_res)) {
                $recent_requests[] = $row;
            }
        }
    }
} catch (Exception $e) {}

$services_map = [
    "barangay_clearance"       => "Barangay Clearance",
    "certificate_of_residency" => "Certificate of Residency",
    "certificate_of_indigency" => "Certificate of Indigency",
    "business_clearance"       => "Business Clearance",
];
$badge_map = [
    "Pending"  => "badge-pending",
    "Approved" => "badge-approved",
    "Ready"    => "badge-ready",
    "Released" => "badge-released",
    "Rejected" => "badge-rejected",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BarangayGo</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="frame full" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
    <!-- Naka-lock na navigation bar sa itaas -->
    <?php $active_nav = 'dashboard'; include 'resident_nav.php'; ?>

    <main class="page-main top">
        <a href="services.php" class="back-link">&larr; Back</a>
        <div class="profile-card" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
            <div class="profile-header">
                <div>
                    <h1 style="font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em;">Dashboard</h1>
                    <p class="sub">Overview of your barangay document requests and recent activity.</p>
                </div>
            </div>

            <!-- Buod ng bilang ng mga request at pinakabagong 5 requests -->
            <div style="margin-top: 20px;">
                <h2 style="margin: 0 0 16px; font-size: 1.1rem; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-weight: 700;">📊 My Documents &amp; Request History</h2>

                <!-- Stats Row -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 12px; margin-bottom: 24px;">
                    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 10px; text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: 700; color: #0f172a; font-family: 'Verdana', Geneva, Tahoma, sans-serif; line-height: 1;"><?= $req_stats["total"]; ?></div>
                        <div style="font-size: 0.72rem; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 6px; font-weight: 700;">Total</div>
                    </div>

                    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 10px; text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: 700; color: #0f172a; font-family: 'Verdana', Geneva, Tahoma, sans-serif; line-height: 1;"><?= $req_stats["pending"]; ?></div>
                        <div style="font-size: 0.72rem; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 6px; font-weight: 700;">Pending</div>
                    </div>

                    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 10px; text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: 700; color: #0f172a; font-family: 'Verdana', Geneva, Tahoma, sans-serif; line-height: 1;"><?= $req_stats["approved"]; ?></div>
                        <div style="font-size: 0.72rem; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 6px; font-weight: 700;">Approved</div>
                    </div>

                    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 10px; text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: 700; color: #0f172a; font-family: 'Verdana', Geneva, Tahoma, sans-serif; line-height: 1;"><?= $req_stats["ready"]; ?></div>
                        <div style="font-size: 0.72rem; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 6px; font-weight: 700;">Ready</div>
                    </div>

                    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 10px; text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: 700; color: #0f172a; font-family: 'Verdana', Geneva, Tahoma, sans-serif; line-height: 1;"><?= $req_stats["released"]; ?></div>
                        <div style="font-size: 0.72rem; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 6px; font-weight: 700;">Released</div>
                    </div>

                    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 10px; text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: 700; color: #0f172a; font-family: 'Verdana', Geneva, Tahoma, sans-serif; line-height: 1;"><?= $req_stats["rejected"]; ?></div>
                        <div style="font-size: 0.72rem; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 6px; font-weight: 700;">Rejected</div>
                    </div>
                </div>

                <!-- Recent Requests Table -->
                <?php if (empty($recent_requests)): ?>
                    <p style="color: #64748b; font-size: 0.88rem; text-align: center; padding: 20px 0;">
                        You have not submitted any requests yet. <a href="services.php" style="color: #0f172a; font-weight: 700;">Submit a request now &rarr;</a>
                    </p>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                        <p style="margin: 0; font-size: 0.85rem; color: #64748b;">Most recent <?= count($recent_requests); ?> request(s):</p>
                        <a href="my_requests.php" class="pill-view-all" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.8rem; font-weight: 700;">View all &rarr;</a>
                    </div>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
                            <thead>
                                <tr style="border-bottom: 2px solid #cbd5e1;">
                                    <th style="padding: 8px 10px; text-align: left; color: #475569; font-size: 0.72rem; text-transform: uppercase; font-weight: 700;">ID</th>
                                    <th style="padding: 8px 10px; text-align: left; color: #475569; font-size: 0.72rem; text-transform: uppercase; font-weight: 700;">Document</th>
                                    <th style="padding: 8px 10px; text-align: left; color: #475569; font-size: 0.72rem; text-transform: uppercase; font-weight: 700;">Date</th>
                                    <th style="padding: 8px 10px; text-align: left; color: #475569; font-size: 0.72rem; text-transform: uppercase; font-weight: 700;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_requests as $rr): ?>
                                    <?php
                                        $rr_name = $services_map[$rr["service"]] ?? ucwords(str_replace("_", " ", $rr["service"]));
                                        $rr_st   = $rr["status"];
                                        $rr_date = date("M d, Y", strtotime($rr["created_at"]));
                                        $rr_time = date("g:i A", strtotime($rr["created_at"]));
                                    ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 10px 10px; color: #0f172a; font-weight: 700;">#<?= $rr["request_id"]; ?></td>
                                        <td style="padding: 10px 10px; color: #0f172a; font-weight: 600;"><?= htmlspecialchars($rr_name, ENT_QUOTES); ?></td>
                                        <td style="padding: 10px 10px; color: #64748b;">
                                            <?= $rr_date; ?>
                                            <span style="display: block; font-size: 0.72rem;"><?= $rr_time; ?></span>
                                        </td>
                                        <td style="padding: 10px 10px;">
                                            <span class="badge <?= $badge_map[$rr_st] ?? 'badge-pending'; ?>" style="font-size: 0.72rem; padding: 3px 10px;">
                                                <?= htmlspecialchars($rr_st, ENT_QUOTES); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

</body>
</html>
