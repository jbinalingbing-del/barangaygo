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

// Document upload (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["document_file"])) {
    $res_id = (int)($_POST["resident_id"] ?? 0);
    $svc    = $_POST["service_name"] ?? "Document";
    $doc_remarks = trim($_POST["doc_remarks"] ?? "");

    $doc_dir = __DIR__ . "/uploads/documents/";
    if (!is_dir($doc_dir)) mkdir($doc_dir, 0755, true);

    $file     = $_FILES["document_file"];
    $ext      = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_ext = ["pdf", "jpg", "jpeg", "png", "webp"];
    $doc_error = "";

    if ($file["error"] !== UPLOAD_ERR_OK) {
        $php_errors = [
            1 => "File too large (PHP ini limit).",
            2 => "File too large (HTML form limit).",
            3 => "File partially uploaded.",
            4 => "No file selected.",
            6 => "Missing temporary folder.",
            7 => "Failed to write file to disk.",
        ];
        $doc_error = "Upload error: " . ($php_errors[$file["error"]] ?? "Code " . $file["error"]);
    } elseif (!in_array($ext, $allowed_ext)) {
        $doc_error = "Only PDF, JPG, PNG, or WebP files are allowed.";
    } elseif ($file["size"] > 10 * 1024 * 1024) {
        $doc_error = "File size must not exceed 10 MB.";
    } else {
        $filename = "req" . $request_id . "_doc_" . time() . "." . $ext;
        if (move_uploaded_file($file["tmp_name"], $doc_dir . $filename)) {
            try {
                // I-save ang document sa requests table
                if ($pdo) {
                    $pdo->prepare("UPDATE requests SET document_file = ? WHERE request_id = ?")
                        ->execute([$filename, $request_id]);
                    // Notification
                    $notif_msg = "📄 Your document for $svc is ready! You can now download and print it.";
                    if ($doc_remarks !== "") $notif_msg .= " Reminder: $doc_remarks";
                    $pdo->prepare("INSERT INTO notifications (resident_id, request_id, message) VALUES (?, ?, ?)")
                        ->execute([$res_id, $request_id, $notif_msg]);
                } elseif ($conn) {
                    $su = mysqli_prepare($conn, "UPDATE requests SET document_file = ? WHERE request_id = ?");
                    mysqli_stmt_bind_param($su, "si", $filename, $request_id);
                    mysqli_stmt_execute($su);
                    $notif_msg = "📄 Your document for $svc is ready! You can now download and print it.";
                    if ($doc_remarks !== "") $notif_msg .= " Reminder: $doc_remarks";
                    $nn = mysqli_prepare($conn, "INSERT INTO notifications (resident_id, request_id, message) VALUES (?, ?, ?)");
                    mysqli_stmt_bind_param($nn, "iis", $res_id, $request_id, $notif_msg);
                    mysqli_stmt_execute($nn);
                }
                $message = "Document successfully uploaded and resident notified!";
            } catch (Exception $e) {
                $error = "DB Error: " . $e->getMessage();
            }
        } else {
            $doc_error = "Unable to save file. Folder: $doc_dir | Writable: " . (is_writable($doc_dir) ? "YES" : "NO") . " | Exists: " . (is_dir($doc_dir) ? "YES" : "NO");
        }
    }
    if ($doc_error !== "") $error = $doc_error;
}

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

                // Send notification to resident
                $res_id = (int)$_POST["resident_id"];
                $svc    = $_POST["service_name"] ?? "Document";
                $notif_msg = "Your request for $svc has been marked as '$new_status'.";
                if ($remarks !== "") $notif_msg .= " Remarks: $remarks";

                $n_stmt = $pdo->prepare("INSERT INTO notifications (resident_id, request_id, message) VALUES (?, ?, ?)");
                $n_stmt->execute([$res_id, $request_id, $notif_msg]);
            } elseif ($conn) {
                $stmt = mysqli_prepare($conn, "UPDATE requests SET status = ?, remarks = ? WHERE request_id = ?");
                mysqli_stmt_bind_param($stmt, "ssi", $new_status, $remarks, $request_id);
                mysqli_stmt_execute($stmt);

                $res_id = (int)$_POST["resident_id"];
                $svc    = $_POST["service_name"] ?? "Document";
                $notif_msg = "Your request for $svc has been marked as '$new_status'.";
                if ($remarks !== "") $notif_msg .= " Remarks: $remarks";

                $n_stmt = mysqli_prepare($conn, "INSERT INTO notifications (resident_id, request_id, message) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($n_stmt, "iis", $res_id, $request_id, $notif_msg);
                mysqli_stmt_execute($n_stmt);
            }
            $message = "Request status successfully updated to: '$new_status'.";
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
    echo "Request not found.";
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

<div class="frame full" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
    <nav class="frame-nav">
        <a href="admin_dashboard.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="admin_residents.php">Residents</a>
            <a href="admin_history.php">All Requests</a>
            <a href="admin_requests.php?service=<?= urlencode($req['service']); ?>">Requests List</a>
            <a href="admin_logout.php">Logout</a>
        </div>
    </nav>

    <main class="page-main top">
        <a href="admin_requests.php?service=<?= urlencode($req['service']); ?>" class="back-link">&larr; Back to Requests List</a>
        <div class="view-card">

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 6px;">
                <h1 style="font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 1.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em;">Request #<?= $req["request_id"]; ?>: <?= htmlspecialchars($svc_title, ENT_QUOTES); ?></h1>
                <span class="badge <?= $badge_map[$req['status']] ?? 'badge-pending'; ?>" style="font-size: 0.9rem; padding: 4px 14px;">
                    <?= htmlspecialchars($req["status"], ENT_QUOTES); ?>
                </span>
            </div>
            <p class="page-sub">Request Date: <?= date("F j, Y, g:i a", strtotime($req["created_at"])); ?></p>

            <?php if ($message !== ""): ?>
                <p class="success" role="alert"><?= htmlspecialchars($message, ENT_QUOTES); ?></p>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <p class="message" role="alert"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
            <?php endif; ?>

            <div class="view-grid">
                <!-- Resident Information -->
                <div class="view-panel">
                    <h2>Resident Information</h2>
                    <div class="view-row"><span>Name:</span> <span><?= htmlspecialchars($req["full_name"], ENT_QUOTES); ?></span></div>
                    <div class="view-row"><span>Mobile:</span> <span><?= htmlspecialchars($req["mobile"] ?: "N/A", ENT_QUOTES); ?></span></div>
                    <div class="view-row"><span>Email:</span> <span><?= htmlspecialchars($req["email"], ENT_QUOTES); ?></span></div>
                    <div class="view-row"><span>Birthday / Age:</span> <span><?= htmlspecialchars($req["birthday"], ENT_QUOTES); ?> (<?= (int)$req["age"]; ?> yrs)</span></div>
                    <div class="view-row"><span>Gender:</span> <span><?= htmlspecialchars($req["sex"] ?: "N/A", ENT_QUOTES); ?></span></div>
                    <div class="view-row"><span>Civil Status:</span> <span><?= htmlspecialchars($req["civil_status"] ?: "N/A", ENT_QUOTES); ?></span></div>
                    <div class="view-row"><span>Address:</span> <span><?= htmlspecialchars($req["street"] . ", " . $req["barangay"] . ", " . $req["city"], ENT_QUOTES); ?></span></div>
                </div>

                <!-- Request Details -->
                <div class="view-panel">
                    <h2>Request Details</h2>
                    <div class="view-row"><span>Service:</span> <span><?= htmlspecialchars($svc_title, ENT_QUOTES); ?></span></div>
                    <div class="view-row"><span>Purpose:</span> <span><?= htmlspecialchars($req["purpose"], ENT_QUOTES); ?></span></div>
                    <?php if (!empty($req["years_of_residency"])): ?>
                        <div class="view-row"><span>Years of Residency:</span> <span><?= (int)$req["years_of_residency"]; ?> years</span></div>
                    <?php endif; ?>
                    <?php if (!empty($req["business_name"])): ?>
                        <div class="view-row"><span>Business Name:</span> <span><?= htmlspecialchars($req["business_name"], ENT_QUOTES); ?></span></div>
                        <div class="view-row"><span>Business Type:</span> <span><?= htmlspecialchars($req["business_type"] ?? "N/A", ENT_QUOTES); ?></span></div>
                        <div class="view-row"><span>Business Address:</span> <span><?= htmlspecialchars($req["business_address"] ?? "N/A", ENT_QUOTES); ?></span></div>
                    <?php endif; ?>
                    <div class="view-row"><span>ID Type Used:</span> <span><?= htmlspecialchars($req["id_type"] ?: "N/A", ENT_QUOTES); ?></span></div>
                    <?php if (!empty($req["remarks"])): ?>
                        <div class="view-row"><span>Admin Remarks:</span> <span style="color: var(--warn);"><?= htmlspecialchars($req["remarks"], ENT_QUOTES); ?></span></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Uploaded ID and Selfie -->
            <div class="view-panel" style="margin-top: 18px;">
                <h2>Uploaded Verification Documents (ID &amp; Selfie)</h2>
                <div class="id-gallery">
                    <?php if (!empty($req["id_front"])): ?>
                        <div class="id-item">
                            <a href="uploads/<?= htmlspecialchars($req["id_front"], ENT_QUOTES); ?>" target="_blank">
                                <img src="uploads/<?= htmlspecialchars($req["id_front"], ENT_QUOTES); ?>" alt="ID Front">
                            </a>
                            <small>ID (Front)</small>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($req["id_back"])): ?>
                        <div class="id-item">
                            <a href="uploads/<?= htmlspecialchars($req["id_back"], ENT_QUOTES); ?>" target="_blank">
                                <img src="uploads/<?= htmlspecialchars($req["id_back"], ENT_QUOTES); ?>" alt="ID Back">
                            </a>
                            <small>ID (Back)</small>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($req["selfie"])): ?>
                        <div class="id-item">
                            <a href="uploads/<?= htmlspecialchars($req["selfie"], ENT_QUOTES); ?>" target="_blank">
                                <img src="uploads/<?= htmlspecialchars($req["selfie"], ENT_QUOTES); ?>" alt="Selfie">
                            </a>
                            <small>Selfie holding ID</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Admin Action: Update Status -->
            <div class="view-panel" style="margin-top: 18px;">
                <h2>Admin Action</h2>
                <form method="POST" action="">
                    <input type="hidden" name="resident_id" value="<?= (int)$req['resident_id']; ?>">
                    <input type="hidden" name="service_name" value="<?= htmlspecialchars($svc_title, ENT_QUOTES); ?>">

                    <div class="field" style="max-width: none; margin-bottom: 12px;">
                        <label for="remarks" style="text-align: left; font-weight: 700;">Remarks / Notes (Optional for Notification):</label>
                        <input type="text" id="remarks" name="remarks" placeholder="E.g., Ready for pick-up at the barangay hall tomorrow from 8am-5pm." value="<?= htmlspecialchars($req['remarks'] ?? '', ENT_QUOTES); ?>" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
                    </div>

                    <div class="action-bar">
                        <button type="submit" name="action_status" value="Approved" class="btn btn-ok" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.8rem; font-weight: 700;">
                            ✓ Approve (Approved)
                        </button>
                        <button type="submit" name="action_status" value="Ready" class="btn" style="background: #0284c7; border-color: #0284c7; color: #fff; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.8rem; font-weight: 700;">
                            📄 Ready for Release (Ready)
                        </button>
                        <button type="submit" name="action_status" value="Released" class="btn btn-ok" style="background: #16a34a; border-color: #16a34a; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.8rem; font-weight: 700;">
                            🤝 Handed Over (Released)
                        </button>
                        <button type="submit" name="action_status" value="Rejected" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this request?')" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.8rem; font-weight: 700;">
                            ✕ Reject (Rejected)
                        </button>
                    </div>
                </form>
            </div>

            <!-- Send Document to Resident -->
            <div class="view-panel" style="margin-top: 18px; border: 1px solid var(--focus);">
                <h2>📤 Send Document to Resident</h2>

                <?php if (!empty($req["document_file"])): ?>
                <div style="
                    display: flex; align-items: center; gap: 14px;
                    background: #f0fdf4;
                    border: 1px solid #86efac;
                    border-radius: var(--radius);
                    padding: 14px 18px;
                    margin-bottom: 16px;
                ">
                    <span style="font-size: 2rem;">✅</span>
                    <div>
                        <strong style="color: #16a34a;">Document has been sent!</strong>
                        <div style="font-size: 0.82rem; color: #64748b; margin-top: 4px;">
                            File: <?= htmlspecialchars($req["document_file"], ENT_QUOTES); ?>
                        </div>
                        <a href="uploads/documents/<?= htmlspecialchars($req["document_file"], ENT_QUOTES); ?>"
                           target="_blank"
                           style="font-size: 0.82rem; color: #1e293b; font-weight: 700;">
                            View file →
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="resident_id" value="<?= (int)$req['resident_id']; ?>">
                    <input type="hidden" name="service_name" value="<?= htmlspecialchars($svc_title, ENT_QUOTES); ?>">

                    <div class="field" style="max-width: none; margin-bottom: 12px;">
                        <label style="text-align: left; font-weight: 700;">Upload Document / Certificate:</label>
                        <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
                        <p class="hint">PDF, JPG, PNG, or WebP · up to 10MB</p>
                    </div>

                    <div class="field" style="max-width: none; margin-bottom: 16px;">
                        <label for="doc_remarks" style="text-align: left; font-weight: 700;">Message / Reminder for Resident (optional):</label>
                        <input type="text" id="doc_remarks" name="doc_remarks"
                               placeholder="E.g., Your document is ready. You may now download and print it." style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
                    </div>

                    <button type="submit" class="btn" style="background: #1e293b; border-color: #1e293b; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em;">
                        📤 Send Document
                    </button>
                </form>
            </div>

            <!-- Request History for this Resident -->
            <div class="view-panel" style="margin-top: 18px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <h2 style="margin: 0;">📜 Request History for this Resident</h2>
                    <span style="font-size: 0.85rem; color: #64748b;">(Total Recorded: <?= count($resident_history); ?>)</span>
                </div>

                <?php if (empty($resident_history)): ?>
                    <p style="color: #64748b; font-size: 0.9rem; margin: 0;">No other previous requests for this resident.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.86rem; text-align: left; font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
                            <thead>
                                <tr style="border-bottom: 2px solid #cbd5e1; color: #475569;">
                                    <th style="padding: 10px 8px;">Req ID</th>
                                    <th style="padding: 10px 8px;">Service</th>
                                    <th style="padding: 10px 8px;">Purpose</th>
                                    <th style="padding: 10px 8px;">Date</th>
                                    <th style="padding: 10px 8px;">Status</th>
                                    <th style="padding: 10px 8px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resident_history as $h): ?>
                                    <?php 
                                        $is_current = ((int)$h["request_id"] === (int)$req["request_id"]);
                                        $h_svc_name = $services_map[$h["service"]] ?? ucwords(str_replace("_", " ", $h["service"]));
                                    ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0; <?= $is_current ? 'background: #f1f5f9;' : ''; ?>">
                                        <td style="padding: 10px 8px; font-weight: bold; color: #0f172a;">
                                            #<?= $h["request_id"]; ?>
                                            <?php if ($is_current): ?>
                                                <span style="font-size: 0.7rem; color: #0f172a; display: block; font-weight: 700;">(Current)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 10px 8px;"><?= htmlspecialchars($h_svc_name, ENT_QUOTES); ?></td>
                                        <td style="padding: 10px 8px; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($h['purpose'] ?? '', ENT_QUOTES); ?>">
                                            <?= htmlspecialchars($h["purpose"] ?: "N/A", ENT_QUOTES); ?>
                                        </td>
                                        <td style="padding: 10px 8px; white-space: nowrap; color: #64748b;">
                                            <?= date("M d, Y", strtotime($h["created_at"])); ?>
                                        </td>
                                        <td style="padding: 10px 8px;">
                                            <span class="badge <?= $badge_map[$h['status']] ?? 'badge-pending'; ?>" style="font-size: 0.75rem; padding: 2px 8px;">
                                                <?= htmlspecialchars($h["status"], ENT_QUOTES); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 10px 8px;">
                                            <?php if (!$is_current): ?>
                                                <a href="admin_view.php?id=<?= $h['request_id']; ?>" class="btn" style="padding: 4px 10px; font-size: 0.78rem; font-family: 'Verdana', Geneva, Tahoma, sans-serif;">View</a>
                                            <?php else: ?>
                                                <span style="color: #64748b; font-size: 0.8rem;">Current</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Admin Delete Action (CRUD: Delete) -->
            <div class="view-panel" style="margin-top: 18px; border-left: 4px solid var(--bad); background: #fef2f2;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3 style="margin: 0; color: #b91c1c; font-size: 1.05rem; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-weight: 700;">🗑️ Danger Zone: Delete Request</h3>
                        <p style="margin: 4px 0 0; font-size: 0.85rem; color: #475569;">
                            Permanently removes Request #<?= $req["request_id"]; ?> and all associated files and notifications from the database.
                        </p>
                    </div>
                    <form action="delete_request.php" method="POST" style="margin: 0;"
                          onsubmit="return confirm('ARE YOU SURE? This will permanently delete Request #<?= $req["request_id"]; ?>. This action cannot be undone!');">
                        <input type="hidden" name="request_id" value="<?= $req["request_id"]; ?>">
                        <button type="submit" class="btn" style="background: #ef4444; border-color: #ef4444; color: #fff; padding: 8px 18px; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.82rem; font-weight: 700;">
                            🗑️ Delete Request
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
