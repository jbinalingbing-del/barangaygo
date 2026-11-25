<?php
session_start();
include "db.php";
require_once __DIR__ . "/functions.php";

// Auth check: Resident dapat
if (!isset($_SESSION["resident_id"])) {
    header("Location: login.php");
    exit();
}

$resident_id = (int)$_SESSION["resident_id"];
$request_id  = isset($_GET["id"]) ? (int)$_GET["id"] : (int)($_POST["request_id"] ?? 0);

if ($request_id <= 0) {
    header("Location: my_requests.php?error=invalid_id");
    exit();
}

$services_map = [
    "barangay_clearance"       => "Barangay Clearance",
    "certificate_of_residency" => "Certificate of Residency",
    "certificate_of_indigency" => "Certificate of Indigency",
    "business_clearance"       => "Barangay Business Clearance",
];

$id_types = ["School ID", "National ID (PhilSys)", "Driver's License", "UMID"];

// 1. Kunin ang request mula sa database
$req = null;
try {
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM requests WHERE request_id = ? AND resident_id = ?");
        $stmt->execute([$request_id, $resident_id]);
        $req = $stmt->fetch();
    } elseif ($conn) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM requests WHERE request_id = ? AND resident_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $request_id, $resident_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $req = mysqli_fetch_assoc($res);
    }
} catch (Exception $e) {
    header("Location: my_requests.php?error=" . urlencode($e->getMessage()));
    exit();
}

if (!$req) {
    header("Location: my_requests.php?error=not_found");
    exit();
}

// Bawal i-edit kung hindi na 'Pending'
if ($req["status"] !== "Pending") {
    header("Location: my_requests.php?error=already_processed");
    exit();
}

$service_key  = $req["service"];
$service_name = $services_map[$service_key] ?? ucwords(str_replace("_", " ", $service_key));

$error   = "";
$message = "";

// Form processing (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $purpose  = trim($_POST["purpose"] ?? "");
    $id_type  = trim($_POST["id_type"] ?? "");

    // Specific fields
    $years_of_residency = isset($_POST["years_of_residency"]) ? (int)$_POST["years_of_residency"] : $req["years_of_residency"];
    $business_name      = trim($_POST["business_name"] ?? ($req["business_name"] ?? ""));
    $business_address   = trim($_POST["business_address"] ?? ($req["business_address"] ?? ""));
    $business_type      = trim($_POST["business_type"] ?? ($req["business_type"] ?? ""));

    if ($purpose === "") {
        $error = "Please specify the purpose of your request.";
    } elseif (!in_array($id_type, $id_types, true)) {
        $error = "Please select a valid ID type.";
    } elseif ($service_key === "certificate_of_residency" && ($years_of_residency < 0 || $years_of_residency > 120)) {
        $error = "Please specify how many years you have resided in the barangay (0 to 120).";
    } elseif ($service_key === "business_clearance" && ($business_name === "" || $business_address === "")) {
        $error = "Please provide the business name and business address.";
    } else {
        // Optional update of images
        $front_file  = $req["id_front"];
        $back_file   = $req["id_back"];
        $selfie_file = $req["selfie"];

        // Handle re-upload ng ID Front kung may bago
        if (isset($_FILES["id_front"]) && $_FILES["id_front"]["error"] !== UPLOAD_ERR_NO_FILE) {
            [$ok1, $res1] = save_upload("id_front", "New ID (Front)", false);
            if (!$ok1) $error = $res1;
            else $front_file = $res1;
        }

        // Handle re-upload ng ID Back kung may bago
        if ($error === "" && isset($_FILES["id_back"]) && $_FILES["id_back"]["error"] !== UPLOAD_ERR_NO_FILE) {
            [$ok2, $res2] = save_upload("id_back", "New ID (Back)", false);
            if (!$ok2) $error = $res2;
            else $back_file = $res2;
        }

        // Handle re-upload ng Selfie kung may bago
        if ($error === "" && isset($_FILES["selfie"]) && $_FILES["selfie"]["error"] !== UPLOAD_ERR_NO_FILE) {
            [$ok3, $res3] = save_upload("selfie", "New Selfie", false);
            if (!$ok3) $error = $res3;
            else $selfie_file = $res3;
        }

        if ($error === "") {
            try {
                if ($pdo) {
                    $u_sql = "UPDATE requests SET 
                                purpose = ?, 
                                id_type = ?, 
                                years_of_residency = ?, 
                                business_name = ?, 
                                business_address = ?, 
                                business_type = ?, 
                                id_front = ?, 
                                id_back = ?, 
                                selfie = ? 
                              WHERE request_id = ? AND resident_id = ? AND status = 'Pending'";
                    $u_stmt = $pdo->prepare($u_sql);
                    $u_stmt->execute([
                        $purpose,
                        $id_type,
                        $years_of_residency,
                        $business_name,
                        $business_address,
                        $business_type,
                        $front_file,
                        $back_file,
                        $selfie_file,
                        $request_id,
                        $resident_id
                    ]);
                } elseif ($conn) {
                    $u_sql = "UPDATE requests SET 
                                purpose = ?, 
                                id_type = ?, 
                                years_of_residency = ?, 
                                business_name = ?, 
                                business_address = ?, 
                                business_type = ?, 
                                id_front = ?, 
                                id_back = ?, 
                                selfie = ? 
                              WHERE request_id = ? AND resident_id = ? AND status = 'Pending'";
                    $u_stmt = mysqli_prepare($conn, $u_sql);
                    mysqli_stmt_bind_param(
                        $u_stmt,
                        "ssissssssii",
                        $purpose,
                        $id_type,
                        $years_of_residency,
                        $business_name,
                        $business_address,
                        $business_type,
                        $front_file,
                        $back_file,
                        $selfie_file,
                        $request_id,
                        $resident_id
                    );
                    mysqli_stmt_execute($u_stmt);
                }

                header("Location: my_requests.php?msg=updated&req_id=" . $request_id);
                exit();
            } catch (Exception $e) {
                $error = "Failed to update request: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Request #<?= $req["request_id"]; ?> - BarangayGo</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .edit-box {
            max-width: 680px;
            margin: 0 auto;
            background: var(--box);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 36px 32px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.04);
        }
        .edit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .file-preview-row {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 6px;
            font-size: 0.85rem;
            color: var(--text-soft);
        }
        .file-preview-thumb {
            width: 48px;
            height: 48px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid var(--border);
        }
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 28px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>

<div class="frame full" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
    <?php $active_nav = 'my_requests'; include 'resident_nav.php'; ?>

    <main class="page-main top">
        <a href="my_requests.php" class="back-link">&larr; Back to My Requests</a>

        <div class="edit-box" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
            <div class="edit-header">
                <div>
                    <h1 style="margin: 0; font-size: 1.6rem; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em;">✏️ Edit Request</h1>
                    <span style="font-size: 0.88rem; color: #64748b;">
                        Request #<?= $req["request_id"]; ?> &nbsp;·&nbsp; <?= htmlspecialchars($service_name, ENT_QUOTES); ?>
                    </span>
                </div>
                <span class="badge badge-pending" style="font-size: 0.85rem; padding: 4px 12px; font-weight: 700;">
                    <?= htmlspecialchars($req["status"], ENT_QUOTES); ?>
                </span>
            </div>

            <?php if ($error !== ""): ?>
                <div class="message" role="alert" style="margin-bottom: 20px;">
                    <?= htmlspecialchars($error, ENT_QUOTES); ?>
                </div>
            <?php endif; ?>

            <form action="edit_request.php?id=<?= $req["request_id"]; ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="request_id" value="<?= $req["request_id"]; ?>">

                <!-- Purpose -->
                <div class="field" style="max-width: none; margin-bottom: 20px;">
                    <label for="purpose" style="text-align: left; font-weight: 700; margin-bottom: 6px;">
                        Purpose
                    </label>
                    <textarea id="purpose" name="purpose" rows="3" required
                              style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.88rem; resize: vertical;"><?= htmlspecialchars($req["purpose"], ENT_QUOTES); ?></textarea>
                </div>

                <!-- If Certificate of Residency: Years of Residency -->
                <?php if ($service_key === "certificate_of_residency"): ?>
                    <div class="field" style="max-width: none; margin-bottom: 20px;">
                        <label for="years_of_residency" style="text-align: left; font-weight: 700; margin-bottom: 6px;">
                            How many years have you been residing in this barangay?
                        </label>
                        <input type="number" id="years_of_residency" name="years_of_residency" min="0" max="120"
                               value="<?= (int)$req["years_of_residency"]; ?>" style="width: 100%; height: 42px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
                    </div>
                <?php endif; ?>

                <!-- If Barangay Business Clearance: Business Fields -->
                <?php if ($service_key === "business_clearance"): ?>
                    <div class="field" style="max-width: none; margin-bottom: 16px;">
                        <label for="business_name" style="text-align: left; font-weight: 700; margin-bottom: 6px;">
                            Business Name
                        </label>
                        <input type="text" id="business_name" name="business_name" required
                               value="<?= htmlspecialchars($req["business_name"] ?? "", ENT_QUOTES); ?>"
                               style="width: 100%; height: 42px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
                    </div>

                    <div class="field" style="max-width: none; margin-bottom: 16px;">
                        <label for="business_address" style="text-align: left; font-weight: 700; margin-bottom: 6px;">
                            Business Address
                        </label>
                        <input type="text" id="business_address" name="business_address" required
                               value="<?= htmlspecialchars($req["business_address"] ?? "", ENT_QUOTES); ?>"
                               style="width: 100%; height: 42px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
                    </div>

                    <div class="field" style="max-width: none; margin-bottom: 20px;">
                        <label for="business_type" style="text-align: left; font-weight: 700; margin-bottom: 6px;">
                            Business Type
                        </label>
                        <input type="text" id="business_type" name="business_type"
                               value="<?= htmlspecialchars($req["business_type"] ?? "", ENT_QUOTES); ?>"
                               style="width: 100%; height: 42px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
                    </div>
                <?php endif; ?>

                <!-- ID Type -->
                <div class="field" style="max-width: none; margin-bottom: 22px;">
                    <label for="id_type" style="text-align: left; font-weight: 700; margin-bottom: 6px;">
                        ID Type
                    </label>
                    <select id="id_type" name="id_type" required
                            style="width: 100%; height: 42px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; background: #ffffff; color: #0f172a; font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
                        <?php foreach ($id_types as $t): ?>
                            <option value="<?= htmlspecialchars($t, ENT_QUOTES); ?>" <?= ($req["id_type"] === $t) ? "selected" : ""; ?>>
                                <?= htmlspecialchars($t, ENT_QUOTES); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Replace uploaded files (Optional) -->
                <div style="margin-top: 24px; padding: 18px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px;">
                    <h3 style="margin: 0 0 12px; font-size: 0.95rem; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-weight: 700;">🖼️ Replace ID or Selfie (Optional)</h3>
                    <p style="margin: 0 0 16px; font-size: 0.82rem; color: #64748b;">
                        Leave blank if you do not wish to replace the currently uploaded photos.
                    </p>

                    <!-- ID Front -->
                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; margin-bottom: 4px;">ID Front:</label>
                        <input type="file" name="id_front" accept="image/*" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
                        <?php if (!empty($req["id_front"])): ?>
                            <div class="file-preview-row">
                                <img src="uploads/<?= htmlspecialchars($req["id_front"], ENT_QUOTES); ?>" alt="Current ID Front" class="file-preview-thumb">
                                <span>Currently attached: <?= htmlspecialchars($req["id_front"], ENT_QUOTES); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ID Back -->
                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; margin-bottom: 4px;">ID Back:</label>
                        <input type="file" name="id_back" accept="image/*" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
                        <?php if (!empty($req["id_back"])): ?>
                            <div class="file-preview-row">
                                <img src="uploads/<?= htmlspecialchars($req["id_back"], ENT_QUOTES); ?>" alt="Current ID Back" class="file-preview-thumb">
                                <span>Currently attached: <?= htmlspecialchars($req["id_back"], ENT_QUOTES); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Selfie -->
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; margin-bottom: 4px;">Selfie holding ID:</label>
                        <input type="file" name="selfie" accept="image/*" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
                        <?php if (!empty($req["selfie"])): ?>
                            <div class="file-preview-row">
                                <img src="uploads/<?= htmlspecialchars($req["selfie"], ENT_QUOTES); ?>" alt="Current Selfie" class="file-preview-thumb">
                                <span>Currently attached: <?= htmlspecialchars($req["selfie"], ENT_QUOTES); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="form-actions" style="margin-top: 24px;">
                    <a href="my_requests.php" class="btn-new-request" style="text-decoration: none; padding: 10px 20px; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.82rem;">
                        Cancel
                    </a>
                    <button type="submit" class="btn" style="background: #1e293b; border-color: #1e293b; color: #fff; padding: 10px 24px; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.82rem; font-weight: 700;">
                        💾 Save Changes
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

</body>
</html>
