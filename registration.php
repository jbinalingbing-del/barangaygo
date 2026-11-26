<?php
include "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $salutation  = trim($_POST["salutation"]   ?? "");
    $first_name  = trim($_POST["first_name"]   ?? "");
    $middle_name = trim($_POST["middle_name"]  ?? "");
    $last_name   = trim($_POST["last_name"]    ?? "");
    $full_name   = trim("$salutation $first_name $middle_name $last_name");

    $bday_year  = $_POST["bday_year"]  ?? "";
    $bday_month = $_POST["bday_month"] ?? "";
    $bday_day   = $_POST["bday_day"]   ?? "";
    $birthday   = "$bday_year-$bday_month-$bday_day";

    $sex          = trim($_POST["sex"]          ?? "");
    $civil_status = trim($_POST["civil_status"] ?? "");
    $mobile       = trim($_POST["mobile"]       ?? "");

    $street   = trim($_POST["street"]   ?? "");
    $region   = "Region V - Bicol Region";
    $province = "Camarines Sur";
    $city     = "Naga City";
    $barangay = trim($_POST["barangay"] ?? "");
    $email    = trim($_POST["email"]    ?? "");
    $password = $_POST["password"]         ?? "";
    $confirm  = $_POST["confirm_password"] ?? "";
    $agree    = $_POST["agree"]            ?? "";
    $not_robot = $_POST["not_robot"] ?? "";

    $valid_barangays = [
        "Abella","Bagumbayan Norte","Bagumbayan Sur","Balatas",
        "Calauag","Cararayan","Carolina","Concepcion Grande","Concepcion Pequena",
        "Dayangdang","Del Rosario","Dinaga","Igualdad Interior",
        "Lerma","Liboton","Mabolo","Pacol","Panicuason",
        "Penafrancia","Sabang","San Felipe","San Francisco",
        "San Isidro","Sta. Cruz","Tabuco","Tinago","Triangulo"
    ];

    $dob = DateTime::createFromFormat("Y-m-d", $birthday);
    $age = $dob ? $dob->diff(new DateTime())->y : 0;

    // ========================================================
    // [reCAPTCHA LOCATION 1]: Backend Server-side Verification
    // Tinitiyak sa server na sinagutan ng user ang Google reCAPTCHA
    // ========================================================
    $g_recaptcha = trim($_POST["g-recaptcha-response"] ?? "");

    if (!$agree) {
        $message = "You must accept the Terms of Service before registering.";
    } elseif (empty($g_recaptcha)) {
        $message = "Please confirm that you are not a robot before registering.";
    } elseif (!$dob || $dob > new DateTime()) {
        $message = "Please enter a valid birthday.";
    // Kailangang 18 taong gulang pataas bago makapag-rehistro
    } elseif ($age < 18) {
        $message = "You must be at least 18 years old to register.";
    } elseif (!in_array($barangay, $valid_barangays, true)) {
        $message = "Please select a valid barangay in Naga City.";
    } elseif (strpos($email, '!') !== false || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address (exclamation mark '!' is not allowed).";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $message = "Passwords do not match.";
    } elseif (!$pdo && !$conn) {
        $message = "No database connection available.";
    } else {
        try {
            // Check kung existing na ang email
            $exists = false;
            if ($pdo) {
                $check = $pdo->prepare("SELECT resident_id FROM residents WHERE email = ?");
                $check->execute([$email]);
                $exists = (bool)$check->fetch();
            } else {
                $check = mysqli_prepare($conn, "SELECT resident_id FROM residents WHERE email = ?");
                mysqli_stmt_bind_param($check, "s", $email);
                mysqli_stmt_execute($check);
                mysqli_stmt_store_result($check);
                $exists = mysqli_stmt_num_rows($check) > 0;
            }

            if ($exists) {
                $message = "An account is already registered with this email address.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if ($pdo) {
                    $stmt = $pdo->prepare("
                        INSERT INTO residents
                            (full_name, salutation, first_name, middle_name, last_name,
                             birthday, age, sex, civil_status, mobile,
                             street, region, province, city, barangay,
                             email, password)
                        VALUES
                            (?, ?, ?, ?, ?,
                             ?, ?, ?, ?, ?,
                             ?, ?, ?, ?, ?,
                             ?, ?)
                    ");
                    $stmt->execute([
                        $full_name, $salutation, $first_name, $middle_name, $last_name,
                        $birthday,  $age,        $sex,        $civil_status, $mobile,
                        $street,    $region,     $province,   $city,         $barangay,
                        $email,     $hash
                    ]);
                } else {
                    $stmt = mysqli_prepare($conn, "
                        INSERT INTO residents
                            (full_name, salutation, first_name, middle_name, last_name,
                             birthday, age, sex, civil_status, mobile,
                             street, region, province, city, barangay,
                             email, password)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    mysqli_stmt_bind_param($stmt, "ssssssissssssssss",
                        $full_name, $salutation, $first_name, $middle_name, $last_name,
                        $birthday,  $age,        $sex,        $civil_status, $mobile,
                        $street,    $region,     $province,   $city,         $barangay,
                        $email,     $hash
                    );
                    mysqli_stmt_execute($stmt);
                }

                header("Location: login.php");
                exit;
            }
        } catch (Exception $e) {
            $message = "Database error: " . $e->getMessage();
        }
    }
}

// Preserve posted values
$v_sal    = $_POST["salutation"]   ?? "";
$v_first  = htmlspecialchars($_POST["first_name"]  ?? "", ENT_QUOTES);
$v_mid    = htmlspecialchars($_POST["middle_name"] ?? "", ENT_QUOTES);
$v_last   = htmlspecialchars($_POST["last_name"]   ?? "", ENT_QUOTES);
$v_yr     = $_POST["bday_year"]  ?? "";
$v_mo     = $_POST["bday_month"] ?? "";
$v_dy     = $_POST["bday_day"]   ?? "";
$v_sex    = $_POST["sex"]          ?? "";
$v_civil  = $_POST["civil_status"] ?? "";
$v_mobile = htmlspecialchars($_POST["mobile"] ?? "", ENT_QUOTES);
$v_street = htmlspecialchars($_POST["street"]   ?? "", ENT_QUOTES);
$v_region = htmlspecialchars($_POST["region"]   ?? "", ENT_QUOTES);
$v_prov   = htmlspecialchars($_POST["province"] ?? "", ENT_QUOTES);
$v_city   = htmlspecialchars($_POST["city"]     ?? "", ENT_QUOTES);
$v_bgy    = htmlspecialchars($_POST["barangay"] ?? "", ENT_QUOTES);
$v_email  = htmlspecialchars($_POST["email"]    ?? "", ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration - BarangayGo</title>
    <link rel="stylesheet" href="styles.css">

    <!-- ========================================================
         [reCAPTCHA LOCATION 2]: Google reCAPTCHA API Script
         Ito ang opisyal na JavaScript library mula sa Google
         ======================================================== -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<style>
/* ---- Authentic Google reCAPTCHA v2 Anchor (from Google Security Blog) ---- */
.captcha-wrapper {
    margin: 18px 0 6px;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.captcha-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 1px solid #d3d3d3;
    border-radius: 3px;
    padding: 0 14px;
    background: #f9f9f9;
    width: 304px;
    height: 78px;
    box-shadow: 0 0 4px 1px rgba(0,0,0,0.08);
    user-select: none;
    box-sizing: border-box;
    transition: border-color 0.2s, background-color 0.2s;
}
.captcha-box.captcha-error {
    border-color: #dc2626 !important;
    background: #fef2f2 !important;
}
.captcha-left {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
}
.captcha-checkbox {
    width: 28px;
    height: 28px;
    border: 2px solid #c1c1c1;
    border-radius: 2px;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
    position: relative;
    box-sizing: border-box;
}
.captcha-checkbox:hover:not(.loading):not(.verified) {
    border-color: #b2b2b2;
    box-shadow: inset 0 1px 1px rgba(0,0,0,0.1);
}
.captcha-checkbox.loading {
    border: none !important;
    background: transparent !important;
    cursor: wait;
}
.captcha-checkbox.verified {
    border: none !important;
    background: transparent !important;
    cursor: default;
}

/* Material Design Spinner identical to Google GIF */
.recaptcha-spinner-svg {
    width: 28px;
    height: 28px;
    animation: rc-rotate 1.4s linear infinite;
    transform-origin: center center;
    display: block;
}
.recaptcha-spinner-path {
    stroke: #4285f4;
    stroke-dasharray: 1, 200;
    stroke-dashoffset: 0;
    stroke-linecap: round;
    animation: rc-dash 1.4s ease-in-out infinite;
}
@keyframes rc-rotate {
    100% {
        transform: rotate(360deg);
    }
}
@keyframes rc-dash {
    0% {
        stroke-dasharray: 1, 200;
        stroke-dashoffset: 0;
    }
    50% {
        stroke-dasharray: 89, 200;
        stroke-dashoffset: -35px;
    }
    100% {
        stroke-dasharray: 89, 200;
        stroke-dashoffset: -124px;
    }
}

/* Animated Checkmark stroke-drawing */
.recaptcha-checkmark-svg {
    width: 32px;
    height: 32px;
    display: block;
    animation: rc-check-pop 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
.recaptcha-checkmark-path {
    stroke: #0f9d58;
    stroke-width: 4.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
    stroke-dasharray: 40;
    stroke-dashoffset: 40;
    animation: rc-check-draw 0.35s ease-out 0.05s forwards;
}
@keyframes rc-check-draw {
    to {
        stroke-dashoffset: 0;
    }
}
@keyframes rc-check-pop {
    0% {
        transform: scale(0.5);
        opacity: 0;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.captcha-label {
    font-size: 14px;
    font-family: Arial, Helvetica, sans-serif;
    color: #282727;
    font-weight: 500;
    cursor: pointer;
    letter-spacing: 0.1px;
}
.captcha-right {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 60px;
    padding-left: 8px;
}
.captcha-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 2px;
}
.captcha-brand {
    font-size: 10px;
    color: #555555;
    font-family: Arial, Helvetica, sans-serif;
    font-weight: 600;
    letter-spacing: 0.02em;
    line-height: 1;
}
.captcha-tos {
    font-size: 8px;
    color: #777777;
    font-family: Arial, Helvetica, sans-serif;
    margin-top: 3px;
    line-height: 1;
}
/* Malawak na registration layout — full screen width */
.request.reg-wide,
.reg-wide {
    width: 100%;
    max-width: none;
}
.reg-wide .form-box {
    width: 100%;
    max-width: none;
    padding: 36px 48px;
}
.reg-wide .row.two {
    gap: 0 18px;
}
.reg-wide .field {
    max-width: none;
    width: 100%;
    margin: 0 0 14px;
}
.reg-wide .field input,
.reg-wide .field select {
    width: 100%;
    box-sizing: border-box;
}
.reg-wide .form-section {
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: var(--focus);
    margin: 22px 0 14px;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--border);
    text-align: left;
}
</style></head>
<body>

<div class="frame full">
    <nav class="frame-nav">
        <a href="index.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <a href="login.php">Login</a>
            <a href="registration.php" class="active">Register</a>
            <a href="about.php">About</a>
        </div>
    </nav>

    <main class="page-main top">
        <a href="login.php" class="back-link">&larr; Back</a>
        <div class="request reg-wide">
            <div class="form-box left">
                <h1>REGISTRATION</h1>

                <?php if ($message != ""): ?>
                    <p class="message" role="alert"><?= htmlspecialchars($message, ENT_QUOTES); ?></p>
                <?php endif; ?>

                <form method="POST" action="" id="regForm" novalidate>

                    <!-- ============================================================
                         SECTION 1: PERSONAL INFORMATION
                         Layout: 2-column grid (Title + First Name, Middle + Last Name,
                         Sex/Gender + Civil Status, Birthday + Age)
                         ============================================================ -->
                    <div class="form-section">PERSONAL INFORMATION</div>

                    <!-- Row 1: Title (Salutation) + First Name -->
                    <div class="row two">
                        <div class="field full-mobile">
                            <label for="salutation">Title</label>
                            <select name="salutation" id="salutation" class="reg-select">
                                <option value="">Select</option>
                                <option value="Mr."  <?= $v_sal === "Mr."   ? "selected" : "" ?>>Mr.</option>
                                <option value="Mrs." <?= $v_sal === "Mrs."  ? "selected" : "" ?>>Mrs.</option>
                                <option value="Ms."  <?= $v_sal === "Ms."   ? "selected" : "" ?>>Ms.</option>
                                <option value="Dr."  <?= $v_sal === "Dr."   ? "selected" : "" ?>>Dr.</option>
                                <option value="Engr." <?= $v_sal === "Engr." ? "selected" : "" ?>>Engr.</option>
                                <option value="Atty." <?= $v_sal === "Atty." ? "selected" : "" ?>>Atty.</option>
                            </select>
                        </div>
                        <div class="field full-mobile">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name"
                                   placeholder="First Name"
                                   value="<?= $v_first ?>" required>
                            <span class="field-error" id="err-first"></span>
                        </div>
                    </div>

                    <!-- Row 2: Middle Name + Last Name -->
                    <div class="row two">
                        <div class="field full-mobile">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" placeholder="Middle Name" value="<?= $v_mid ?>">
                        </div>
                        <div class="field full-mobile">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" placeholder="Last Name" value="<?= $v_last ?>" required>
                            <span class="field-error" id="err-last"></span>
                        </div>
                    </div>

                    <!-- Row 3: Sex / Gender + Civil Status -->
                    <div class="row two">
                        <div class="field full-mobile">
                            <label for="sex">Gender</label>
                            <select name="sex" id="sex" class="reg-select" required>
                                <option value="">Select</option>
                                <option value="Male"   <?= $v_sex === "Male"   ? "selected" : "" ?>>Male</option>
                                <option value="Female" <?= $v_sex === "Female" ? "selected" : "" ?>>Female</option>
                            </select>
                            <span class="field-error" id="err-sex"></span>
                        </div>
                        <div class="field full-mobile">
                            <label for="civil_status">Civil Status</label>
                            <select name="civil_status" id="civil_status" class="reg-select" required>
                                <option value="">Select</option>
                                <option value="Single"    <?= $v_civil === "Single"    ? "selected" : "" ?>>Single</option>
                                <option value="Married"   <?= $v_civil === "Married"   ? "selected" : "" ?>>Married</option>
                                <option value="Widowed"   <?= $v_civil === "Widowed"   ? "selected" : "" ?>>Widowed</option>
                                <option value="Separated" <?= $v_civil === "Separated" ? "selected" : "" ?>>Separated</option>
                                <option value="Annulled"  <?= $v_civil === "Annulled"  ? "selected" : "" ?>>Annulled</option>
                            </select>
                            <span class="field-error" id="err-civil"></span>
                        </div>
                    </div>

                    <!-- Row 4: Birthday (full width) -->
                    <div class="field">
                        <label for="birthday_display">Birthday</label>
                        <!-- Hidden selects para sa database submission -->
                        <select name="bday_year" id="bday_year" style="display:none;">
                            <option value="">Year</option>
                            <?php for ($y = date("Y"); $y >= 1900; $y--): ?>
                                <option value="<?= $y ?>" <?= $v_yr == $y ? "selected" : "" ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="bday_month" id="bday_month" style="display:none;">
                            <option value="">Month</option>
                            <?php
                            $months = ["01"=>"January","02"=>"February","03"=>"March","04"=>"April",
                                       "05"=>"May","06"=>"June","07"=>"July","08"=>"August",
                                       "09"=>"September","10"=>"October","11"=>"November","12"=>"December"];
                            foreach ($months as $k => $m):
                            ?>
                                <option value="<?= $k ?>" <?= $v_mo == $k ? "selected" : "" ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="bday_day" id="bday_day" style="display:none;">
                            <option value="">Day</option>
                            <?php for ($d = 1; $d <= 31; $d++): $dd = str_pad($d, 2, "0", STR_PAD_LEFT); ?>
                                <option value="<?= $dd ?>" <?= $v_dy == $dd ? "selected" : "" ?>><?= $d ?></option>
                            <?php endfor; ?>
                        </select>
                        <!-- Visible birthday picker (date input) -->
                        <input type="date" id="birthday_display" name="birthday_display"
                               value="<?= ($v_yr && $v_mo && $v_dy) ? "$v_yr-$v_mo-$v_dy" : '' ?>"
                               max="<?= date('Y-m-d') ?>" required>
                        <span class="field-error" id="err-year"></span>
                        <span class="field-error" id="err-month" style="display:none;"></span>
                        <span class="field-error" id="err-day" style="display:none;"></span>
                    </div>

                    <!-- ============================================================
                         SECTION 2: CONTACT & ACCOUNT DETAILS
                         Layout: 2-column (Phone + Email)
                         ============================================================ -->
                    <div class="form-section">CONTACT &amp; ACCOUNT DETAILS</div>

                    <!-- Row: Phone/Mobile + Email -->
                    <div class="row two">
                        <div class="field full-mobile">
                            <label for="mobile">Phone / Mobile Number</label>
                            <!-- inputmode="numeric" at pattern at JS block = numbers only, no letters -->
                            <input type="tel" id="mobile" name="mobile"
                                   placeholder="09XXXXXXXXX"
                                   value="<?= $v_mobile ?>"
                                   inputmode="numeric"
                                   pattern="[0-9]*"
                                   maxlength="11" required>
                            <span class="field-error" id="err-mobile"></span>
                        </div>
                        <div class="field full-mobile">
                            <label for="email">Email Address (Registered)</label>
                            <input type="email" id="email" name="email" placeholder="name@example.com" value="<?= $v_email ?>" required>
                            <span class="field-error" id="err-email"></span>
                        </div>
                    </div>

                    <!-- Password + Confirm (2-column) -->
                    <div class="row two">
                        <div class="field full-mobile">
                            <label for="password">Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="password" name="password" placeholder="Enter new Password" required autocomplete="new-password">
                                <span class="eye-toggle" data-target="password" title="Show / Hide password">
                                    <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg class="eye-icon eye-closed" style="display: none;" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                        <line x1="1" y1="1" x2="23" y2="23"></line>
                                    </svg>
                                </span>
                            </div>
                            <span class="field-error" id="err-password"></span>
                        </div>
                        <div class="field full-mobile">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new Password" required autocomplete="new-password">
                                <span class="eye-toggle" data-target="confirm_password" title="Show / Hide password">
                                    <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg class="eye-icon eye-closed" style="display: none;" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                        <line x1="1" y1="1" x2="23" y2="23"></line>
                                    </svg>
                                </span>
                            </div>
                            <span class="field-error" id="err-confirm"></span>
                        </div>
                    </div>

                    <!-- ============================================================
                         SECTION 3: RESIDENTIAL ADDRESS
                         Layout: Street full-width, then 2-column grid
                         ============================================================ -->
                    <div class="form-section">RESIDENTIAL ADDRESS</div>

                    <!-- Street: full width -->
                    <div class="field">
                        <label for="street">Street / House No. / Purok</label>
                        <input type="text" id="street" name="street" placeholder="Street / House No. / Purok" value="<?= $v_street ?>" required>
                        <span class="field-error" id="err-street"></span>
                    </div>

                    <!-- Row: Barangay + City/Municipality -->
                    <div class="row two">
                        <div class="field full-mobile">
                            <label for="barangay">Barangay</label>
                            <select id="barangay" name="barangay" required>
                                <option value="">Select Barangay</option>
                                <?php
                                $naga_barangays = [
                                    "Abella","Bagumbayan Norte","Bagumbayan Sur","Balatas",
                                    "Calauag","Cararayan","Carolina","Concepcion Grande","Concepcion Pequena",
                                    "Dayangdang","Del Rosario","Dinaga","Igualdad Interior",
                                    "Lerma","Liboton","Mabolo","Pacol","Panicuason",
                                    "Penafrancia","Sabang","San Felipe","San Francisco",
                                    "San Isidro","Sta. Cruz","Tabuco","Tinago","Triangulo"
                                ];
                                foreach ($naga_barangays as $bgy):
                                ?>
                                    <option value="<?= htmlspecialchars($bgy, ENT_QUOTES); ?>"
                                        <?= (isset($_POST['barangay']) && $_POST['barangay'] === $bgy) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($bgy, ENT_QUOTES); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-error" id="err-barangay"></span>
                        </div>
                        <div class="field full-mobile">
                            <label for="city">City / Municipality</label>
                            <input type="text" id="city" name="city" value="Naga City" readonly style="background:#f4f4f4; color:#555; cursor:not-allowed;">
                            <input type="hidden" name="city" value="Naga City">
                        </div>
                    </div>

                    <!-- Row: Province + Region -->
                    <div class="row two">
                        <div class="field full-mobile">
                            <label for="province">Province</label>
                            <input type="text" id="province" name="province" value="Camarines Sur" readonly style="background:#f4f4f4; color:#555; cursor:not-allowed;">
                            <input type="hidden" name="province" value="Camarines Sur">
                        </div>
                        <div class="field full-mobile">
                            <label for="region">Region</label>
                            <input type="text" id="region" name="region" value="Region V - Bicol Region" readonly style="background:#f4f4f4; color:#555; cursor:not-allowed;">
                            <input type="hidden" name="region" value="Region V - Bicol Region">
                        </div>
                    </div>

                    <!-- TERMS OF SERVICE CHECKBOX -->
                    <div class="tos-wrapper">
                        <label class="tos-label">
                            <input type="checkbox" id="agree" name="agree" value="1">
                            <span>READ and ACCEPT <a href="#" id="tosLink" style="color:#7c3aed;font-weight:700;text-decoration:none;">TERMS OF SERVICES</a></span>
                        </label>
                        <span class="field-error" id="err-agree"></span>
                    </div>

                    <!-- TOS Modal -->
                    <div id="tosModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;align-items:center;justify-content:center;">
                        <div style="background:#fff;border-radius:10px;max-width:520px;width:90%;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,0.18);">
                            <div style="padding:18px 22px 10px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
                                <strong style="font-size:1rem;color:#1e1b4b;">TERMS OF SERVICE - BarangayGo</strong>
                                <button type="button" id="tosClose" style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:#6b7280;line-height:1;">&times;</button>
                            </div>
                            <div style="overflow-y:auto;padding:18px 22px;font-size:0.85rem;color:#374151;line-height:1.7;flex:1;">
                                <p style="margin:0 0 10px;">Using <strong>BarangayGo</strong> requires agreement to the following terms and conditions. Please read carefully before registering.</p>

                                <strong style="color:#111827;">1. Collection of Information</strong>
                                <p style="margin:4px 0 12px;">Your personal information (name, address, birthday, email, mobile number, and ID photo) will be collected for identity verification and processing your barangay requests.</p>

                                <strong style="color:#111827;">2. Use of Information</strong>
                                <p style="margin:4px 0 12px;">Your information will only be used for:<br>&bull; Processing your requested documents<br>&bull; Sending notifications and status updates regarding your requests<br>&bull; Verifying your residency in Naga City</p>

                                <strong style="color:#111827;">3. Data Protection</strong>
                                <p style="margin:4px 0 12px;">Your personal data is protected in accordance with <strong>Republic Act No. 10173</strong> (Data Privacy Act of 2012). It will never be shared with any third party without your explicit consent.</p>

                                <strong style="color:#111827;">4. User Responsibilities</strong>
                                <p style="margin:4px 0 12px;">You are responsible for:<br>&bull; Ensuring all submitted information is accurate and truthful<br>&bull; Maintaining the confidentiality of your account credentials<br>&bull; Updating the barangay if your details change</p>

                                <strong style="color:#111827;">5. Document Verification</strong>
                                <p style="margin:4px 0 12px;">Uploaded verification documents (ID and selfie) are used solely to verify your identity. These files remain protected and encrypted within the system.</p>

                                <strong style="color:#111827;">6. Account Termination</strong>
                                <p style="margin:4px 0 0;">The barangay reserves the right to suspend or delete accounts found to have submitted fraudulent information or misused public services.</p>
                            </div>
                            <div style="padding:14px 22px;border-top:1px solid #e5e7eb;text-align:right;">
                                <button type="button" id="tosCloseBtn" style="background:#1e293b;color:#fff;border:none;border-radius:6px;padding:8px 22px;font-size:0.9rem;cursor:pointer;font-family:'Verdana',sans-serif;font-weight:700;">Close</button>
                            </div>
                        </div>
                    </div>


                    <!-- ========================================================
                         [reCAPTCHA LOCATION 3]: Frontend reCAPTCHA Widget
                         Dito ipinapakita ang checkbox widget sa loob ng form
                         (Gamit ang official Google Test Sitekey para gumana sa localhost)
                         ======================================================== -->
                    <div class="captcha-wrapper" id="captchaWrapper" style="margin: 18px 0 8px; display: flex; flex-direction: column; align-items: center;">
                        <div class="g-recaptcha" data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI" data-callback="onRecaptchaSuccess" id="recaptcha"></div>
                        <span class="field-error" id="err-robot" style="margin-top: 6px;"></span>
                    </div>
                    <div class="actions" style="margin-top:20px;text-align:center;">
                        <button type="submit">REGISTER</button>
                    </div>

                </form>
            </div>
        </div>
    </main>

</div>

<script>
// ---- Sync birthday_display date input to hidden selects ----
function computeAge() {
    const dateInput = document.getElementById('birthday_display');
    const val = dateInput ? dateInput.value : '';
    if (val) {
        const parts = val.split('-');
        document.getElementById('bday_year').value  = parts[0];
        document.getElementById('bday_month').value = parts[1];
        document.getElementById('bday_day').value   = parts[2];
    } else {
        document.getElementById('bday_year').value  = '';
        document.getElementById('bday_month').value = '';
        document.getElementById('bday_day').value   = '';
    }
}
const bdayInput = document.getElementById('birthday_display');
if (bdayInput) bdayInput.addEventListener('change', computeAge);
// Run on page load in case of POST back
computeAge();

// ---- Real-time Validation (pula pag mali / kulang) ----
function markError(input, errId, msg) {
    input.classList.add('input-error');
    input.classList.remove('input-ok');
    const el = document.getElementById(errId);
    if (el) el.textContent = msg;
}
function markOk(input, errId) {
    input.classList.remove('input-error');
    input.classList.add('input-ok');
    const el = document.getElementById(errId);
    if (el) el.textContent = '';
}

function validateField(input, errId, label) {
    if (!input.value.trim()) {
        markError(input, errId, `Please enter ${label}.`);
        return false;
    }
    markOk(input, errId);
    return true;
}

// Attach blur validation to required text inputs
const fields = [
    { id: 'first_name',  err: 'err-first',    label: 'First Name' },
    { id: 'last_name',   err: 'err-last',     label: 'Last Name' },
    { id: 'street',      err: 'err-street',   label: 'Street' },
    { id: 'email',       err: 'err-email',    label: 'Email' },
    { id: 'mobile',      err: 'err-mobile',   label: 'Mobile Number' },
];

fields.forEach(f => {
    const el = document.getElementById(f.id);
    if (!el) return;
    el.addEventListener('blur', () => validateField(el, f.err, f.label));
    el.addEventListener('input', () => {
        if (el.value.trim()) markOk(el, f.err);
    });
});

// Barangay dropdown validation
document.getElementById('barangay').addEventListener('change', function() {
    if (!this.value) {
        this.classList.add('input-error');
        document.getElementById('err-barangay').textContent = 'Please select a barangay.';
    } else {
        this.classList.remove('input-error');
        this.classList.add('input-ok');
        document.getElementById('err-barangay').textContent = '';
    }
});

// ---- PHONE NUMBER: Numbers only (block letters, symbols except +) ----
document.getElementById('mobile').addEventListener('input', function() {
    // Remove any non-numeric characters
    this.value = this.value.replace(/[^0-9]/g, '');
});
document.getElementById('mobile').addEventListener('keydown', function(e) {
    // Allow: backspace, delete, tab, arrows, home, end
    const allowedKeys = ['Backspace','Delete','Tab','ArrowLeft','ArrowRight','Home','End'];
    if (allowedKeys.includes(e.key)) return;
    // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
    if ((e.ctrlKey || e.metaKey) && ['a','c','v','x'].includes(e.key.toLowerCase())) return;
    // Block non-numeric keys
    if (!/^[0-9]$/.test(e.key)) {
        e.preventDefault();
    }
});

// I-block ang '!' habang nagta-type o nagpe-paste sa email field
document.getElementById('email').addEventListener('input', function() {
    if (this.value.includes('!')) {
        this.value = this.value.replace(/!/g, '');
    }
});

// Email format
document.getElementById('email').addEventListener('blur', function() {
    if (this.value && (this.value.includes('!') || !/^[^\s@!]+@[^\s@!]+\.[^\s@!]+$/.test(this.value))) {
        markError(this, 'err-email', 'Invalid email address format.');
    } else if (this.value) {
        markOk(this, 'err-email');
    }
});

// Password validation
document.getElementById('password').addEventListener('blur', function() {
    if (!this.value) {
        markError(this, 'err-password', 'Please enter a password.');
    } else if (this.value.length < 8) {
        markError(this, 'err-password', 'Password must be at least 8 characters.');
    } else {
        markOk(this, 'err-password');
    }
});

// Confirm password
document.getElementById('confirm_password').addEventListener('blur', function() {
    const pw = document.getElementById('password').value;
    if (!this.value) {
        markError(this, 'err-confirm', 'Please confirm your password.');
    } else if (this.value !== pw) {
        markError(this, 'err-confirm', 'Passwords do not match.');
    } else {
        markOk(this, 'err-confirm');
    }
});

// Helper para makuha ang eksaktong edad mula sa YYYY-MM-DD
function getComputedAge(val) {
    if (!val) return null;
    const parts = val.split('-');
    if (parts.length !== 3) return null;
    const dob = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const mo = today.getMonth() - dob.getMonth();
    if (mo < 0 || (mo === 0 && today.getDate() < dob.getDate())) age--;
    return age;
}

// Suriin kung wasto ang birthday at kung 18 taong gulang pataas na ang nagpaparehistro
function validateBirthdayAge() {
    const bdayDisp = document.getElementById('birthday_display');
    const ageInput = document.getElementById('age');
    if (!bdayDisp || !bdayDisp.value) {
        if (bdayDisp) markError(bdayDisp, 'err-year', 'Please select your birthday.');
        if (ageInput) ageInput.classList.remove('input-error');
        return false;
    }
    const age = getComputedAge(bdayDisp.value);
    if (age === null || age < 0 || age >= 130) {
        markError(bdayDisp, 'err-year', 'Please enter a valid birthday.');
        if (ageInput) ageInput.classList.add('input-error');
        return false;
    }
    if (age < 18) {
        markError(bdayDisp, 'err-year', 'You must be at least 18 years old to register.');
        if (ageInput) ageInput.classList.add('input-error');
        return false;
    }
    markOk(bdayDisp, 'err-year');
    if (ageInput) ageInput.classList.remove('input-error');
    return true;
}

// Birthday display input validation on change (18+ required)
const bdayField = document.getElementById('birthday_display');
if (bdayField) {
    bdayField.addEventListener('change', validateBirthdayAge);
}

// Sex & Civil Status selects
['sex','civil_status'].forEach((id) => {
    const errIds = { sex: 'err-sex', civil_status: 'err-civil' };
    const el = document.getElementById(id);
    el.addEventListener('change', function() {
        if (!this.value) {
            el.classList.add('input-error');
            document.getElementById(errIds[id]).textContent = 'Please select an option.';
        } else {
            el.classList.remove('input-error');
            el.classList.add('input-ok');
            document.getElementById(errIds[id]).textContent = '';
        }
    });
});

// Mobile validation
document.getElementById('mobile').addEventListener('blur', function() {
    const val = this.value.trim();
    if (!val) {
        markError(this, 'err-mobile', 'Please enter your mobile number.');
    } else if (!/^09\d{9}$/.test(val)) {
        markError(this, 'err-mobile', 'Format: 09XXXXXXXXX (11 digits).');
    } else {
        markOk(this, 'err-mobile');
    }
});

// Form submit validation
document.getElementById('regForm').addEventListener('submit', function(e) {
    let valid = true;

    fields.forEach(f => {
        const el = document.getElementById(f.id);
        if (!el) return;
        if (!validateField(el, f.err, f.label)) valid = false;
    });

    // Barangay dropdown check
    const bgy = document.getElementById('barangay');
    if (!bgy.value) {
        bgy.classList.add('input-error');
        document.getElementById('err-barangay').textContent = 'Please select a barangay.';
        valid = false;
    }

    // Mobile format check
    const mob = document.getElementById('mobile');
    if (!/^09\d{9}$/.test(mob.value.trim())) {
        markError(mob, 'err-mobile', 'Format: 09XXXXXXXXX (11 digits).');
        valid = false;
    }

    // Sex & Civil Status
    ['sex','civil_status'].forEach((id) => {
        const errIds = { sex: 'err-sex', civil_status: 'err-civil' };
        const el = document.getElementById(id);
        if (!el.value) {
            el.classList.add('input-error');
            document.getElementById(errIds[id]).textContent = 'Please select an option.';
            valid = false;
        }
    });

    // Birthday & Age (18+ required)
    if (!validateBirthdayAge()) {
        valid = false;
    }

    // Password
    const pw = document.getElementById('password');
    if (!pw.value || pw.value.length < 8) {
        markError(pw, 'err-password', 'Password must be at least 8 characters.');
        valid = false;
    }
    const cp = document.getElementById('confirm_password');
    if (cp.value !== pw.value) {
        markError(cp, 'err-confirm', 'Passwords do not match.');
        valid = false;
    }

    // TOS
    const agree = document.getElementById('agree');
    if (!agree.checked) {
        document.getElementById('err-agree').textContent = 'You must accept the Terms of Service.';
        agree.closest('.tos-wrapper').classList.add('tos-error');
        valid = false;
    } else {
        document.getElementById('err-agree').textContent = '';
        agree.closest('.tos-wrapper').classList.remove('tos-error');
    }

    // ========================================================
    // [reCAPTCHA LOCATION 4]: Client-side Submit Validation
    // Sinusuri gamit ang grecaptcha.getResponse() bago ma-submit ang form
    // ========================================================
    let recaptchaSolved = false;
    if (typeof grecaptcha !== 'undefined') {
        try {
            if (grecaptcha.getResponse().length > 0) {
                recaptchaSolved = true;
            }
        } catch (err) {}
    }
    if (!recaptchaSolved) {
        document.getElementById('err-robot').textContent = 'Please confirm that you are not a robot.';
        valid = false;
    } else {
        document.getElementById('err-robot').textContent = '';
    }
    if (!valid) e.preventDefault();
});

function onRecaptchaSuccess() {
    document.getElementById('err-robot').textContent = '';
}

document.getElementById('agree').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('err-agree').textContent = '';
        this.closest('.tos-wrapper').classList.remove('tos-error');
    }
});

// TOS Modal
const tosModal   = document.getElementById('tosModal');
const tosLink    = document.getElementById('tosLink');
const tosClose   = document.getElementById('tosClose');
const tosCloseBtn = document.getElementById('tosCloseBtn');

function openTOS(e) {
    e.preventDefault();
    tosModal.style.display = 'flex';
}
function closeTOS() {
    tosModal.style.display = 'none';
}

tosLink.addEventListener('click', openTOS);
tosClose.addEventListener('click', closeTOS);
tosCloseBtn.addEventListener('click', closeTOS);
tosModal.addEventListener('click', function(e) {
    if (e.target === tosModal) closeTOS();
});

// Ipakita o itago ang password kapag pinindot ang eye icon
document.querySelectorAll('.eye-toggle[data-target]').forEach(function(toggle) {
    toggle.addEventListener('click', function() {
        const input = document.getElementById(toggle.getAttribute('data-target'));
        if (!input) return;
        const isPassword = input.getAttribute('type') === 'password';
        input.setAttribute('type', isPassword ? 'text' : 'password');
        const eyeOpen = toggle.querySelector('.eye-open');
        const eyeClosed = toggle.querySelector('.eye-closed');
        if (eyeOpen && eyeClosed) {
            eyeOpen.style.display = isPassword ? 'none' : 'block';
            eyeClosed.style.display = isPassword ? 'block' : 'none';
        }
    });
});
</script>

</body>
</html>