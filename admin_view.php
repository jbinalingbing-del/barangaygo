<?php
session_start();
include "db.php";

// Admin check
if (!isset($_SESSION["admin_id"])) {
    header("Location: admin.php");
    exit();
}

$request_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($request_id <= 0) {
    header("Location: admin_dashboard.php");
    exit();
}

$message = "";
$error   = "";

// Status updates (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_status"])) {
    $new_status = $_POST["action_status"];
    $remarks    = trim($_POST["remarks"] ?? "");
    $allowed    = ["Approved", "Ready", "Released", "Rejected"];

    if (in_array($new_status, $allowed, true)) {
        try {
            if ($pdo) {
                $stmt = $pdo->prepare("UPDATE requests SET status = ?, remarks = ? WHERE request_id = ?");
                $stmt->execute([$new_status, $remarks, $request_id]);

                // Magpadala ng notification sa resident
                $res_id = (int)$_POST["resident_id"];
                $svc    = $_POST["service_name"] ?? "Dokumento";
                $notif_msg = "Ang iyong request para sa $svc ay naging '$new_status'.";
                if ($remarks !== "") $notif_msg .= " Dahilan/Paalala: $remarks";

                $n_stmt = $pdo->prepare("INSERT INTO notifications (resident_id, request_id, message) VALUES (?, ?, ?)");
                $n_stmt->execute([$res_id, $request_id, $notif_msg]);
            } elseif ($conn) {
                $stmt = mysqli_prepare($conn, "UPDATE requests SET status = ?, remarks = ? WHERE request_id = ?");
                mysqli_stmt_bind_param($stmt, "ssi", $new_status, $remarks, $request_id);
                mysqli_stmt_execute($stmt);

                $res_id = (int)$_POST["resident_id"];
                $svc    = $_POST["service_name"] ?? "Dokumento";
                $notif_msg = "Ang iyong request para sa $svc ay naging '$new_status'.";
                if ($remarks !== "") $notif_msg .= " Dahilan/Paalala: $remarks";

                $n_stmt = mysqli_prepare($conn, "INSERT INTO notifications (resident_id, request_id, message) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($n_stmt, "iis", $res_id, $request_id, $notif_msg);
                mysqli_stmt_execute($n_stmt);
            }
            $message = "Na-update ang status ng request sa: '$new_status'.";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Kunin ang request details at resident info
$req = null;
try {
    if ($pdo) {
        $stmt = $pdo->prepare("
            SELECT r.*, res.full_name, res.email, res.mobile, res.birthday, res.age, res.sex, 
                   res.civil_status, res.street, res.barangay, res.city, res.province, res.region, res.profile_pic
            FROM requests r
            JOIN residents res ON res.resident_id = r.resident_id
            WHERE r.request_id = ?
        ");
        $stmt->execute([$request_id]);
        $req = $stmt->fetch();
    } elseif ($conn) {
        $stmt = mysqli_prepare($conn, "
            SELECT r.*, res.full_name, res.email, res.mobile, res.birthday, res.age, res.sex, 
                   res.civil_status, res.street, res.barangay, res.city, res.province, res.region, res.profile_pic
            FROM requests r
            JOIN residents res ON res.resident_id = r.resident_id
            WHERE r.request_id = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $request_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $req = mysqli_fetch_assoc($res);
    }
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

if (!$req) {
    echo "Hindi nahanap ang request.";
    exit();
}

$services_map = [
    "barangay_clearance"       => "Barangay Clearance",
    "certificate_of_residency" => "Certificate of Residency",
    "certificate_of_indigency" => "Certificate of Indigency",
    "business_clearance"       => "Barangay Business Clearance",
];
$svc_title = $services_map[$req["service"]] ?? ucwords(str_replace("_", " ", $req["service"]));

$badge_map = [
    "Pending"  => "badge-pending",
    "Approved" => "badge-approved",
    "Ready"    => "badge-ready",
    "Released" => "badge-released",
    "Rejected" => "badge-rejected",
];

// Kunin ang buong kasaysayan ng mga kahilingan ng residenteng ito
$resident_history = [];
try {
    if ($pdo) {
        $h_stmt = $pdo->prepare("SELECT request_id, service, purpose, status, remarks, created_at 
                                FROM requests 
                                WHERE resident_id = ? 
                                ORDER BY created_at DESC");
        $h_stmt->execute([(int)$req["resident_id"]]);
        $resident_history = $h_stmt->fetchAll();
    } elseif ($conn) {
        $h_stmt = mysqli_prepare($conn, "SELECT request_id, service, purpose, status, remarks, created_at 
                                        FROM requests 
                                        WHERE resident_id = ? 
                                        ORDER BY created_at DESC");
        $rid = (int)$req["resident_id"];
        mysqli_stmt_bind_param($h_stmt, "i", $rid);
        mysqli_stmt_execute($h_stmt);
        $h_res = mysqli_stmt_get_result($h_stmt);
        while ($h_row = mysqli_fetch_assoc($h_res)) {
            $resident_history[] = $h_row;
        }
    }
} catch (Exception $e) {
    // ignore history fetch error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request #<?= $req["request_id"]; ?> - Admin</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .view-card {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
        }
        .view-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 18px;
        }
        .view-panel {
            background: var(--box);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
        }
        .view-panel h2 {
            font-size: 1rem;
            color: var(--focus);
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }
        .view-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 6px 0;
            font-size: 0.85rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .view-row span:first-child {
            color: var(--text-soft);
            flex-shrink: 0;
        }
        .view-row span:last-child {
            text-align: right;
            font-weight: 500;
        }
        .id-gallery {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 12px;
        }
        .id-item {
            text-align: center;
        }
        .id-item img {
            width: 100%;
            height: 140px;
            object-fit: cover;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            cursor: pointer;
            transition: transform 0.2s;
        }
        .id-item img:hover {
            transform: scale(1.03);
            border-color: var(--focus);
        }
        .id-item small {
            display: block;
            margin-top: 4px;
            font-size: 0.72rem;
            color: var(--text-soft);
        }
        .action-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        @media (max-width: 768px) {
            .view-grid { grid-template-columns: 1fr; }
            .id-gallery { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="frame full">
    <nav class="frame-nav">
        <a href="admin_dashboard.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="admin_history.php">Lahat ng Requests</a>
            <a href="admin_requests.php?service=<?= urlencode($req['service']); ?>">Talaan</a>
            <a href="admin_logout.php">Logout</a>
        </div>
    </nav>

    <main class="page-main top">
        <div class="view-card">
            <a href="admin_requests.php?service=<?= urlencode($req['service']); ?>" class="back-link">&larr; Bumalik sa Talaan</a>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 6px;">
                <h1>Request #<?= $req["request_id"]; ?> — <?= htmlspecialchars($svc_title, ENT_QUOTES); ?></h1>
                <span class="badge <?= $badge_map[$req['status']] ?? 'badge-pending'; ?>" style="font-size: 0.9rem; padding: 4px 14px;">
                    <?= htmlspecialchars($req["status"], ENT_QUOTES); ?>
                </span>
            </div>
            <p class="page-sub">Petsa ng Kahilingan: <?= date("F j, Y, g:i a", strtotime($req["created_at"])); ?></p>

            <?php if ($message !== ""): ?>
                <p class="success" role="alert"><?= htmlspecialchars($message, ENT_QUOTES); ?></p>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <p class="message" role="alert"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
            <?php endif; ?>

            <div class="view-grid">
                <!-- Impormasyon ng Residente -->
                <div class="view-panel">
                    <h2>Impormasyon ng Residente</h2>
                    <div class="view-row"><span>Pangalan:</span> <span><?= htmlspecialchars($req["full_name"], ENT_QUOTES); ?></span></div>
                    <div class="view-row"><span>Mobile:</span> <span><?= htmlspecialchars($req["mobile"] ?: "—", ENT_QUOTES); ?></span></div>
                    <div class="view-row"><span>Email:</span> <span><?= htmlspecialchars($req["email"], ENT_QUOTES); ?></span></div>
                    <div class="view-row"><span>Kaarawan / Edad:</span> <span><?= htmlspecialchars($req["birthday"], ENT_QUOTES); ?> (<?= (int)$req["age"]; ?> yrs)</span></div>
                    <div class="view-row"><span>Kasarian:</span> <span><?= htmlspecialchars($req["sex"] ?: "—", ENT_QUOTES); ?></span></div>
                    <div class="view-row"><span>Civil Status:</span> <span><?= htmlspecialchars($req["civil_status"] ?: "—", ENT_QUOTES); ?></span></div>
                    <div class="view-row"><span>Tirahan:</span> <span><?= htmlspecialchars($req["street"] . ", " . $req["barangay"] . ", " . $req["city"], ENT_QUOTES); ?></span></div>
                </div>

                <!-- Detalye ng Request -->
                <div class="view-panel">
                    <h2>Detalye ng Kahilingan</h2>
                    <div class="view-row"><span>Serbisyo:</span> <span><?= htmlspecialchars($svc_title, ENT_QUOTES); ?></span></div>
                    <div class="view-row"><span>Layunin (Purpose):</span> <span><?= htmlspecialchars($req["purpose"], ENT_QUOTES); ?></span></div>
                    <?php if (!empty($req["years_of_residency"])): ?>
                        <div class="view-row"><span>Taon ng Paninirahan:</span> <span><?= (int)$req["years_of_residency"]; ?> taon</span></div>
                    <?php endif; ?>
                    <?php if (!empty($req["business_name"])): ?>
                        <div class="view-row"><span>Negosyo:</span> <span><?= htmlspecialchars($req["business_name"], ENT_QUOTES); ?></span></div>
                        <div class="view-row"><span>Uri ng Negosyo:</span> <span><?= htmlspecialchars($req["business_type"] ?? "—", ENT_QUOTES); ?></span></div>
                        <div class="view-row"><span>Address ng Negosyo:</span> <span><?= htmlspecialchars($req["business_address"] ?? "—", ENT_QUOTES); ?></span></div>
                    <?php endif; ?>
                    <div class="view-row"><span>Uri ng ID na Ginamit:</span> <span><?= htmlspecialchars($req["id_type"] ?: "—", ENT_QUOTES); ?></span></div>
                    <?php if (!empty($req["remarks"])): ?>
                        <div class="view-row"><span>Admin Remarks:</span> <span style="color: var(--warn);"><?= htmlspecialchars($req["remarks"], ENT_QUOTES); ?></span></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Na-upload na ID at Selfie -->
            <div class="view-panel" style="margin-top: 18px;">
                <h2>Nai-upload na mga Katibayan (ID &amp; Selfie)</h2>
                <div class="id-gallery">
                    <?php if (!empty($req["id_front"])): ?>
                        <div class="id-item">
                            <a href="uploads/<?= htmlspecialchars($req["id_front"], ENT_QUOTES); ?>" target="_blank">
                                <img src="uploads/<?= htmlspecialchars($req["id_front"], ENT_QUOTES); ?>" alt="ID Front">
                            </a>
                            <small>ID (Harap)</small>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($req["id_back"])): ?>
                        <div class="id-item">
                            <a href="uploads/<?= htmlspecialchars($req["id_back"], ENT_QUOTES); ?>" target="_blank">
                                <img src="uploads/<?= htmlspecialchars($req["id_back"], ENT_QUOTES); ?>" alt="ID Back">
                            </a>
                            <small>ID (Likod)</small>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($req["selfie"])): ?>
                        <div class="id-item">
                            <a href="uploads/<?= htmlspecialchars($req["selfie"], ENT_QUOTES); ?>" target="_blank">
                                <img src="uploads/<?= htmlspecialchars($req["selfie"], ENT_QUOTES); ?>" alt="Selfie">
                            </a>
                            <small>Selfie hawak ang ID</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Aksyon ng Admin: Update Status -->
            <div class="view-panel" style="margin-top: 18px;">
                <h2>Aksyon ng Admin</h2>
                <form method="POST" action="">
                    <input type="hidden" name="resident_id" value="<?= (int)$req['resident_id']; ?>">
                    <input type="hidden" name="service_name" value="<?= htmlspecialchars($svc_title, ENT_QUOTES); ?>">

                    <div class="field" style="max-width: none; margin-bottom: 12px;">
                        <label for="remarks" style="text-align: left;">Remarks / Dahilan (Opsyonal para sa Abiso):</label>
                        <input type="text" id="remarks" name="remarks" placeholder="Hal. Handa nang kunin sa barangay hall bukas mula 8am-5pm." value="<?= htmlspecialchars($req['remarks'] ?? '', ENT_QUOTES); ?>">
                    </div>

                    <div class="action-bar">
                        <button type="submit" name="action_status" value="Approved" class="btn btn-ok">
                            ✓ Aprubahan (Approved)
                        </button>
                        <button type="submit" name="action_status" value="Ready" class="btn" style="background: var(--info); border-color: var(--info); color: #000;">
                            📄 Handa nang Kunin (Ready)
                        </button>
                        <button type="submit" name="action_status" value="Released" class="btn btn-ok" style="background: #2563eb; border-color: #2563eb;">
                            🤝 Naibigay Na (Released)
                        </button>
                        <button type="submit" name="action_status" value="Rejected" class="btn btn-danger" onclick="return confirm('Sigurado ka bang tatanggihan ang request na ito?')">
                            ✕ Tanggihan (Reject)
                        </button>
                    </div>
                </form>
            </div>

            <!-- Kasaysayan ng mga Kahilingan ng Residenteng Ito -->
            <div class="view-panel" style="margin-top: 18px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <h2 style="margin: 0;">📜 Kasaysayan ng mga Kahilingan ng Residenteng Ito</h2>
                    <span style="font-size: 0.85rem; color: var(--text-soft);">(Kabuuang Naitala: <?= count($resident_history); ?>)</span>
                </div>

                <?php if (empty($resident_history)): ?>
                    <p style="color: var(--text-soft); font-size: 0.9rem; margin: 0;">Walang ibang nakaraang kahilingan ang residenteng ito.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem; text-align: left;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border); color: var(--text-soft);">
                                    <th style="padding: 10px 8px;">Req ID</th>
                                    <th style="padding: 10px 8px;">Serbisyo</th>
                                    <th style="padding: 10px 8px;">Layunin (Purpose)</th>
                                    <th style="padding: 10px 8px;">Petsa</th>
                                    <th style="padding: 10px 8px;">Status</th>
                                    <th style="padding: 10px 8px;">Aksyon</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resident_history as $h): ?>
                                    <?php 
                                        $is_current = ((int)$h["request_id"] === (int)$req["request_id"]);
                                        $h_svc_name = $services_map[$h["service"]] ?? ucwords(str_replace("_", " ", $h["service"]));
                                    ?>
                                    <tr style="border-bottom: 1px solid var(--border); <?= $is_current ? 'background: rgba(142, 68, 173, 0.15);' : ''; ?>">
                                        <td style="padding: 10px 8px; font-weight: bold;">
                                            #<?= $h["request_id"]; ?>
                                            <?php if ($is_current): ?>
                                                <span style="font-size: 0.7rem; color: var(--focus); display: block;">(Kasalukuyan)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 10px 8px;"><?= htmlspecialchars($h_svc_name, ENT_QUOTES); ?></td>
                                        <td style="padding: 10px 8px; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($h['purpose'] ?? '', ENT_QUOTES); ?>">
                                            <?= htmlspecialchars($h["purpose"] ?: "—", ENT_QUOTES); ?>
                                        </td>
                                        <td style="padding: 10px 8px; white-space: nowrap; color: var(--text-soft);">
                                            <?= date("M d, Y", strtotime($h["created_at"])); ?>
                                        </td>
                                        <td style="padding: 10px 8px;">
                                            <span class="badge <?= $badge_map[$h['status']] ?? 'badge-pending'; ?>" style="font-size: 0.75rem; padding: 2px 8px;">
                                                <?= htmlspecialchars($h["status"], ENT_QUOTES); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 10px 8px;">
                                            <?php if (!$is_current): ?>
                                                <a href="admin_view.php?id=<?= $h['request_id']; ?>" class="btn" style="padding: 4px 10px; font-size: 0.78rem;">Tingnan</a>
                                            <?php else: ?>
                                                <span style="color: var(--text-soft); font-size: 0.8rem;">Bukas na</span>
                                            <?php endif; ?>
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
