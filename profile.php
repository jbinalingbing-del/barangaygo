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

$valid_barangays = [
    "Abella", "Bagumbayan Norte", "Bagumbayan Sur", "Balatas",
    "Calauag", "Cararayan", "Carolina", "Concepcion Grande", "Concepcion Pequena",
    "Dayangdang", "Del Rosario", "Dinaga", "Igualdad Interior",
    "Lerma", "Liboton", "Mabolo", "Pacol", "Panicuason",
    "Penafrancia", "Sabang", "San Felipe", "San Francisco",
    "San Isidro", "Sta. Cruz", "Tabuco", "Tinago", "Triangulo"
];

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
        $error = "First Name and Last Name cannot be empty.";
    } elseif ($mobile !== "" && !preg_match('/^09\d{9}$/', $mobile)) {
        $error = "Mobile Number must be 11 digits (e.g. 09XXXXXXXXX).";
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
            $message = "Your profile has been updated successfully!";

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
            $error = "Error saving profile: " . $e->getMessage();
        }
    }
}

// Display picture
$pic_path = "assets/default_avatar.svg";
if (!empty($user["profile_pic"]) && file_exists(__DIR__ . "/uploads/" . $user["profile_pic"])) {
    $pic_path = "uploads/" . htmlspecialchars($user["profile_pic"], ENT_QUOTES);
}

// ── Kunin ang request stats at history ng user ──
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

<div class="frame full" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
    <?php $active_nav = 'profile'; include 'resident_nav.php'; ?>

    <main class="page-main top">
        <a href="services.php" class="back-link">&larr; Back</a>
        <div class="profile-card" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
            <div class="profile-header">
                <div>
                    <h1 style="font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em;">My Profile</h1>
                    <p class="sub">View and update your personal information and photo.</p>
                </div>
            </div>

            <?php if ($message !== ""): ?>
                <p class="success" role="alert"><?= htmlspecialchars($message, ENT_QUOTES); ?></p>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <p class="message" role="alert"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
            <?php endif; ?>

            <form method="POST" action="profile.php" enctype="multipart/form-data" id="profileForm">
                <div class="profile-grid">
                    <!-- Resident Photo -->
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

                        <label for="profile_pic" class="btn-change-photo" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.8rem;">
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

                    <!-- Form Fields for Edit Information -->
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
                                <label for="sex">Gender</label>
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
                                <select id="barangay" name="barangay" required>
                                    <option value="">Select Barangay</option>
                                    <?php 
                                    $curr_bgy = $user["barangay"] ?? "";
                                    if ($curr_bgy !== "" && !in_array($curr_bgy, $valid_barangays, true)): 
                                    ?>
                                        <option value="<?= htmlspecialchars($curr_bgy, ENT_QUOTES); ?>" selected>
                                            <?= htmlspecialchars($curr_bgy, ENT_QUOTES); ?>
                                        </option>
                                    <?php endif; ?>
                                    <?php foreach ($valid_barangays as $b): ?>
                                        <option value="<?= htmlspecialchars($b, ENT_QUOTES); ?>" <?= ($curr_bgy === $b) ? "selected" : ""; ?>>
                                            <?= htmlspecialchars($b, ENT_QUOTES); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="city">City / Municipality</label>
                                <input type="text" id="city" name="city" value="<?= htmlspecialchars($user["city"] ?? "Naga City", ENT_QUOTES); ?>" required>
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

                <!-- Footer Actions: Cancel and Save Changes -->
                <div class="profile-actions">
                    <a href="services.php" class="btn btn-cancel" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.82rem;">Cancel</a>
                    <button type="submit" class="btn btn-ok" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.82rem; font-weight: 700;">Save Changes</button>
                </div>
            </form>

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
