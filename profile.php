<?php
session_start();
include "db.php";
require_once __DIR__ . "/functions.php";

// Kailangang naka-login ang residente
if (!isset($_SESSION["resident_id"])) {
    header("Location: login.php");
    exit();
}

$resident_id = $_SESSION["resident_id"];
$message     = "";
$error       = "";

// Awtomatikong idagdag ang column na profile_pic kung wala pa sa database
try {
    if ($pdo) {
        $pdo->exec("ALTER TABLE residents ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(255) NULL AFTER full_name");
    } elseif ($conn) {
        @mysqli_query($conn, "ALTER TABLE residents ADD COLUMN profile_pic VARCHAR(255) NULL AFTER full_name");
    }
} catch (Exception $e) {
    // Ignore kung existing na
}

// 1. Kunin ang kasalukuyang detalye ng residente
$user = null;
if ($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM residents WHERE resident_id = ?");
    $stmt->execute([$resident_id]);
    $user = $stmt->fetch();
} elseif ($conn) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM residents WHERE resident_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $resident_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($res);
}

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// 2. Pag nag-submit ng Edit Profile
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $salutation  = trim($_POST["salutation"]   ?? $user["salutation"]);
    $first_name  = trim($_POST["first_name"]   ?? $user["first_name"]);
    $middle_name = trim($_POST["middle_name"]  ?? $user["middle_name"]);
    $last_name   = trim($_POST["last_name"]    ?? $user["last_name"]);
    $full_name   = trim("$salutation $first_name $middle_name $last_name");

    $mobile       = trim($_POST["mobile"]       ?? $user["mobile"]);
    $sex          = trim($_POST["sex"]          ?? $user["sex"]);
    $civil_status = trim($_POST["civil_status"] ?? $user["civil_status"]);

    $street   = trim($_POST["street"]   ?? $user["street"]);
    $barangay = trim($_POST["barangay"] ?? $user["barangay"]);
    $city     = trim($_POST["city"]     ?? $user["city"]);
    $province = trim($_POST["province"] ?? $user["province"]);
    $region   = trim($_POST["region"]   ?? $user["region"]);

    $new_pic = $user["profile_pic"] ?? null;

    // Check kung may bagong picture na in-upload
    if (isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] !== UPLOAD_ERR_NO_FILE) {
        [$ok, $uploaded] = save_upload("profile_pic", "Profile Photo", false);
        if ($ok && $uploaded !== "") {
            $new_pic = $uploaded;
        } elseif (!$ok) {
            $error = $uploaded;
        }
    }

    if ($first_name === "" || $last_name === "") {
        $error = "Hindi dapat blangko ang First Name at Last Name.";
    } elseif ($mobile !== "" && !preg_match('/^09\d{9}$/', $mobile)) {
        $error = "Dapat 11 digits ang Mobile Number (ex. 09XXXXXXXXX).";
    } elseif ($error === "") {
        try {
            if ($pdo) {
                $sql = "UPDATE residents SET 
                            salutation = ?, first_name = ?, middle_name = ?, last_name = ?, full_name = ?,
                            mobile = ?, sex = ?, civil_status = ?,
                            street = ?, barangay = ?, city = ?, province = ?, region = ?,
                            profile_pic = ?
                        WHERE resident_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $salutation, $first_name, $middle_name, $last_name, $full_name,
                    $mobile, $sex, $civil_status,
                    $street, $barangay, $city, $province, $region,
                    $new_pic, $resident_id
                ]);
            } elseif ($conn) {
                $sql = "UPDATE residents SET 
                            salutation = ?, first_name = ?, middle_name = ?, last_name = ?, full_name = ?,
                            mobile = ?, sex = ?, civil_status = ?,
                            street = ?, barangay = ?, city = ?, province = ?, region = ?,
                            profile_pic = ?
                        WHERE resident_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssssssssssssssi",
                    $salutation, $first_name, $middle_name, $last_name, $full_name,
                    $mobile, $sex, $civil_status,
                    $street, $barangay, $city, $province, $region,
                    $new_pic, $resident_id
                );
                mysqli_stmt_execute($stmt);
            }

            $_SESSION["full_name"] = $full_name;
            $message = "Matagumpay na na-update ang iyong Profile!";

            // Refresh data
            $user["salutation"]   = $salutation;
            $user["first_name"]   = $first_name;
            $user["middle_name"]  = $middle_name;
            $user["last_name"]    = $last_name;
            $user["full_name"]    = $full_name;
            $user["mobile"]       = $mobile;
            $user["sex"]          = $sex;
            $user["civil_status"] = $civil_status;
            $user["street"]       = $street;
            $user["barangay"]     = $barangay;
            $user["city"]         = $city;
            $user["province"]     = $province;
            $user["region"]       = $region;
            $user["profile_pic"]  = $new_pic;

        } catch (Exception $e) {
            $error = "Error sa pag-save: " . $e->getMessage();
        }
    }
}

// Display picture
$pic_path = "assets/default_avatar.svg";
if (!empty($user["profile_pic"]) && file_exists(__DIR__ . "/uploads/" . $user["profile_pic"])) {
    $pic_path = "uploads/" . htmlspecialchars($user["profile_pic"], ENT_QUOTES);
}

// ── Kunin ang request stats at history ng user ──
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
        // Stats
        $st_stmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM requests WHERE resident_id = ? GROUP BY status");
        $st_stmt->execute([$resident_id]);
        while ($st = $st_stmt->fetch()) {
            $key = strtolower($st["status"]);
            if (isset($req_stats[$key])) $req_stats[$key] = (int)$st["cnt"];
            $req_stats["total"] += (int)$st["cnt"];
        }
        // Recent 5
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
} catch (Exception $e) {
    // ignore
}

$services_map_prof = [
    "barangay_clearance"       => "Barangay Clearance",
    "certificate_of_residency" => "Certificate of Residency",
    "certificate_of_indigency" => "Certificate of Indigency",
    "business_clearance"       => "Business Clearance",
];
$badge_map_prof = [
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
    <title>My Profile - BarangayGo</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="frame full">
    <nav class="frame-nav">
        <a href="services.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <a href="services.php">Mga Serbisyo</a>
            <a href="my_requests.php">My Requests</a>
            <a href="profile.php" class="active">Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </nav>

    <main class="page-main top">
        <div class="profile-card">
            <div class="profile-header">
                <div>
                    <h1>My Profile</h1>
                    <p class="sub">Tingnan at i-edit ang iyong impormasyon at litrato.</p>
                </div>
                <a href="services.php" class="profile-close" title="Bumalik sa Services">&times;</a>
            </div>

            <?php if ($message !== ""): ?>
                <p class="success" role="alert"><?= htmlspecialchars($message, ENT_QUOTES); ?></p>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <p class="message" role="alert"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
            <?php endif; ?>

            <form method="POST" action="profile.php" enctype="multipart/form-data" id="profileForm">
                <div class="profile-grid">
                    <!-- KALIWA: Litrato ng Residente -->
                    <div class="profile-sidebar">
                        <div class="avatar-wrap">
                            <?php if (!empty($user["profile_pic"]) && file_exists(__DIR__ . "/uploads/" . $user["profile_pic"])): ?>
                                <img src="uploads/<?= htmlspecialchars($user["profile_pic"], ENT_QUOTES); ?>" alt="Resident Photo" id="avatarPreview" class="avatar-img">
                            <?php else: ?>
                                <div id="avatarFallback" class="avatar-fallback">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="70" height="70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                </div>
                                <img src="" alt="Preview" id="avatarPreview" class="avatar-img" style="display: none;">
                            <?php endif; ?>
                        </div>

                        <label for="profile_pic" class="btn-change-photo">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                <circle cx="12" cy="13" r="4"></circle>
                            </svg>
                            Change Photo
                        </label>
                        <input type="file" id="profile_pic" name="profile_pic" accept="image/jpeg,image/png,image/webp" style="display: none;">

                        <div class="profile-badge-role">RESIDENT</div>
                        <p class="profile-resident-id">ID: #<?= str_pad($resident_id, 5, "0", STR_PAD_LEFT); ?></p>
                    </div>

                    <!-- KANAN: Form Fields para sa Edit Information -->
                    <div class="profile-form-body">
                        <div class="form-section-title">Personal Information</div>

                        <div class="row two">
                            <div class="field">
                                <label for="salutation">Title</label>
                                <select id="salutation" name="salutation">
                                    <option value="" <?= empty($user["salutation"]) ? "selected" : "" ?>>Title</option>
                                    <option value="Mr." <?= ($user["salutation"] ?? "") === "Mr." ? "selected" : "" ?>>Mr.</option>
                                    <option value="Mrs." <?= ($user["salutation"] ?? "") === "Mrs." ? "selected" : "" ?>>Mrs.</option>
                                    <option value="Ms." <?= ($user["salutation"] ?? "") === "Ms." ? "selected" : "" ?>>Ms.</option>
                                    <option value="Dr." <?= ($user["salutation"] ?? "") === "Dr." ? "selected" : "" ?>>Dr.</option>
                                    <option value="Engr." <?= ($user["salutation"] ?? "") === "Engr." ? "selected" : "" ?>>Engr.</option>
                                    <option value="Atty." <?= ($user["salutation"] ?? "") === "Atty." ? "selected" : "" ?>>Atty.</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user["first_name"] ?? "", ENT_QUOTES); ?>" required>
                            </div>
                        </div>

                        <div class="row two">
                            <div class="field">
                                <label for="middle_name">Middle Name</label>
                                <input type="text" id="middle_name" name="middle_name" value="<?= htmlspecialchars($user["middle_name"] ?? "", ENT_QUOTES); ?>">
                            </div>
                            <div class="field">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user["last_name"] ?? "", ENT_QUOTES); ?>" required>
                            </div>
                        </div>

                        <div class="row two">
                            <div class="field">
                                <label for="sex">Sex / Gender</label>
                                <select id="sex" name="sex">
                                    <option value="Male" <?= ($user["sex"] ?? "") === "Male" ? "selected" : "" ?>>Male</option>
                                    <option value="Female" <?= ($user["sex"] ?? "") === "Female" ? "selected" : "" ?>>Female</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="civil_status">Civil Status</label>
                                <select id="civil_status" name="civil_status">
                                    <option value="Single" <?= ($user["civil_status"] ?? "") === "Single" ? "selected" : "" ?>>Single</option>
                                    <option value="Married" <?= ($user["civil_status"] ?? "") === "Married" ? "selected" : "" ?>>Married</option>
                                    <option value="Widowed" <?= ($user["civil_status"] ?? "") === "Widowed" ? "selected" : "" ?>>Widowed</option>
                                    <option value="Separated" <?= ($user["civil_status"] ?? "") === "Separated" ? "selected" : "" ?>>Separated</option>
                                    <option value="Annulled" <?= ($user["civil_status"] ?? "") === "Annulled" ? "selected" : "" ?>>Annulled</option>
                                </select>
                            </div>
                        </div>

                        <div class="row two">
                            <div class="field">
                                <label for="birthday">Birthday (Fixed)</label>
                                <input type="text" id="birthday" value="<?= htmlspecialchars($user["birthday"] ?? "", ENT_QUOTES); ?>" readonly>
                            </div>
                            <div class="field">
                                <label for="age">Age</label>
                                <input type="text" id="age" value="<?= htmlspecialchars($user["age"] ?? "", ENT_QUOTES); ?> yrs old" readonly>
                            </div>
                        </div>

                        <div class="form-section-title" style="margin-top: 20px;">Contact &amp; Account Details</div>

                        <div class="row two">
                            <div class="field">
                                <label for="mobile">Phone / Mobile Number</label>
                                <input type="tel" id="mobile" name="mobile" value="<?= htmlspecialchars($user["mobile"] ?? "", ENT_QUOTES); ?>" placeholder="09XXXXXXXXX">
                            </div>
                            <div class="field">
                                <label for="email">Email Address (Registered)</label>
                                <input type="email" id="email" value="<?= htmlspecialchars($user["email"] ?? "", ENT_QUOTES); ?>" readonly>
                            </div>
                        </div>

                        <div class="form-section-title" style="margin-top: 20px;">Residential Address</div>

                        <div class="field">
                            <label for="street">Street / House No. / Purok</label>
                            <input type="text" id="street" name="street" value="<?= htmlspecialchars($user["street"] ?? "", ENT_QUOTES); ?>" required>
                        </div>

                        <div class="row two">
                            <div class="field">
                                <label for="barangay">Barangay</label>
                                <input type="text" id="barangay" name="barangay" value="<?= htmlspecialchars($user["barangay"] ?? "", ENT_QUOTES); ?>" required>
                            </div>
                            <div class="field">
                                <label for="city">City / Municipality</label>
                                <input type="text" id="city" name="city" value="<?= htmlspecialchars($user["city"] ?? "", ENT_QUOTES); ?>" required>
                            </div>
                        </div>

                        <div class="row two">
                            <div class="field">
                                <label for="province">Province</label>
                                <input type="text" id="province" name="province" value="<?= htmlspecialchars($user["province"] ?? "", ENT_QUOTES); ?>" required>
                            </div>
                            <div class="field">
                                <label for="region">Region</label>
                                <input type="text" id="region" name="region" value="<?= htmlspecialchars($user["region"] ?? "", ENT_QUOTES); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions: Cancel at Save Changes -->
                <div class="profile-actions">
                    <a href="services.php" class="btn btn-cancel">Cancel</a>
                    <button type="submit" class="btn btn-ok">Save Changes</button>
                </div>
            </form>

            <!-- ══════════════════════════════════════════
                 STATS AT HISTORY NG MGA REQUEST
                 ══════════════════════════════════════════ -->
            <div style="margin-top: 32px; border-top: 1px solid var(--border); padding-top: 28px;">
                <h2 style="margin: 0 0 16px; font-size: 1.1rem;">📊 Aking mga Dokumento at Kasaysayan</h2>

                <!-- Stats Row -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 12px; margin-bottom: 24px;">

                    <div style="background: var(--box-2); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 14px 10px; text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: 800; color: var(--focus);"><?= $req_stats["total"]; ?></div>
                        <div style="font-size: 0.72rem; color: var(--text-soft); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;">Kabuuan</div>
                    </div>

                    <div style="background: var(--box-2); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 14px 10px; text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: 800; color: var(--warn);"><?= $req_stats["pending"]; ?></div>
                        <div style="font-size: 0.72rem; color: var(--text-soft); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;">Pending</div>
                    </div>

                    <div style="background: var(--box-2); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 14px 10px; text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: 800; color: var(--ok);"><?= $req_stats["approved"]; ?></div>
                        <div style="font-size: 0.72rem; color: var(--text-soft); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;">Approved</div>
                    </div>

                    <div style="background: var(--box-2); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 14px 10px; text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: 800; color: var(--info);"><?= $req_stats["ready"]; ?></div>
                        <div style="font-size: 0.72rem; color: var(--text-soft); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;">Handa na</div>
                    </div>

                    <div style="background: var(--box-2); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 14px 10px; text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: 800; color: #60a5fa;"><?= $req_stats["released"]; ?></div>
                        <div style="font-size: 0.72rem; color: var(--text-soft); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;">Nakuha na</div>
                    </div>

                    <div style="background: var(--box-2); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 14px 10px; text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: 800; color: var(--bad);"><?= $req_stats["rejected"]; ?></div>
                        <div style="font-size: 0.72rem; color: var(--text-soft); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;">Tinanggihan</div>
                    </div>

                </div>

                <!-- Recent Requests Table -->
                <?php if (empty($recent_requests)): ?>
                    <p style="color: var(--text-soft); font-size: 0.88rem; text-align: center; padding: 20px 0;">
                        Wala ka pang naisusumiteng kahilingan. <a href="services.php">Mag-request na ngayon →</a>
                    </p>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-soft);">Pinakabagong <?= count($recent_requests); ?> na kahilingan:</p>
                        <a href="my_requests.php" style="font-size: 0.82rem; color: var(--focus);">Tingnan lahat →</a>
                    </div>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border);">
                                    <th style="padding: 8px 10px; text-align: left; color: var(--text-soft); font-size: 0.72rem; text-transform: uppercase; font-weight: 600;">ID</th>
                                    <th style="padding: 8px 10px; text-align: left; color: var(--text-soft); font-size: 0.72rem; text-transform: uppercase; font-weight: 600;">Dokumento</th>
                                    <th style="padding: 8px 10px; text-align: left; color: var(--text-soft); font-size: 0.72rem; text-transform: uppercase; font-weight: 600;">Petsa</th>
                                    <th style="padding: 8px 10px; text-align: left; color: var(--text-soft); font-size: 0.72rem; text-transform: uppercase; font-weight: 600;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_requests as $rr): ?>
                                    <?php
                                        $rr_name = $services_map_prof[$rr["service"]] ?? ucwords(str_replace("_", " ", $rr["service"]));
                                        $rr_st   = $rr["status"];
                                        $rr_date = date("M d, Y", strtotime($rr["created_at"]));
                                        $rr_time = date("g:i A", strtotime($rr["created_at"]));
                                    ?>
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <td style="padding: 10px 10px; color: var(--text-soft);">#<?= $rr["request_id"]; ?></td>
                                        <td style="padding: 10px 10px; color: var(--text); font-weight: 500;"><?= htmlspecialchars($rr_name, ENT_QUOTES); ?></td>
                                        <td style="padding: 10px 10px; color: var(--text-soft);">
                                            <?= $rr_date; ?>
                                            <span style="display: block; font-size: 0.72rem;"><?= $rr_time; ?></span>
                                        </td>
                                        <td style="padding: 10px 10px;">
                                            <span class="badge <?= $badge_map_prof[$rr_st] ?? 'badge-pending'; ?>" style="font-size: 0.72rem; padding: 3px 10px;">
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
            <!-- end stats & history -->

        </div><!-- end profile-card -->
    </main>
</div>

<script>
// Live Image Preview pag pumili ng bagong picture
var picInput = document.getElementById('profile_pic');
var avatarImg = document.getElementById('avatarPreview');
var avatarFallback = document.getElementById('avatarFallback');

picInput.addEventListener('change', function () {
    if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) {
            avatarImg.src = e.target.result;
            avatarImg.style.display = 'block';
            if (avatarFallback) {
                avatarFallback.style.display = 'none';
            }
        };
        reader.readAsDataURL(this.files[0]);
    }
});
</script>

</body>
</html>
