<?php
// =============================================================================
// forgot_password.php - BarangayGo Password Reset Flow
// =============================================================================
// FUNCTION SUMMARY:
// - Step 1: Residente maglalagay ng registered email address.
// - Function: Random 6-digit OTP code generator & database expiration timer (15 mins).
// - Function: Ligtas na pagpapadala ng code sa email inbox via Gmail SMTP.
// - Step 2: Residente maglalagay ng 6-digit OTP code at bagong password.
// - Function: Password hashing gamit ang Bcrypt (PASSWORD_DEFAULT) at database update.
// =============================================================================

session_start();
include "db.php";
require_once "mail_helper.php";

$error = "";
$message = "";

// =============================================================================
// [FUNCTION 1] DATABASE AUTO-CHECK:
// Tinitiyak na mayroong 'reset_code' at 'reset_expires' columns sa 'residents' table.
// =============================================================================
if (function_exists('ensure_barangaygo_database_ready')) {
    if ($conn) {
        ensure_barangaygo_database_ready($conn);
    }
}

// =============================================================================
// [FUNCTION 2] CANCEL / START OVER:
// Binubura ang reset session kapag pinindot ang "Change email address".
// =============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    unset($_SESSION['reset_email'], $_SESSION['reset_notice']);
    header("Location: forgot_password.php");
    exit();
}

// Tukuyin kung nasa Step 1 (Enter Email) o Step 2 (Enter OTP & New Password)
$current_step = 1;
if (isset($_GET['step']) && $_GET['step'] === 'verify' && !empty($_SESSION['reset_email'])) {
    $current_step = 2;
    if (!empty($_SESSION['reset_notice'])) {
        $message = $_SESSION['reset_notice'];
        unset($_SESSION['reset_notice']);
    }
}

// =============================================================================
// [FUNCTION 3] RESEND CODE (STEP 2):
// Gumagawa ng panibagong 6-digit code at muling nagpapadala sa email kung na-expire.
// =============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'resend' && !empty($_SESSION['reset_email'])) {
    $target_email = $_SESSION['reset_email'];
    $resident = null;

    if ($pdo) {
        $stmt = $pdo->prepare("SELECT resident_id, full_name, email FROM residents WHERE email = ? LIMIT 1");
        $stmt->execute([$target_email]);
        $resident = $stmt->fetch();
    } elseif ($conn) {
        $stmt = mysqli_prepare($conn, "SELECT resident_id, full_name, email FROM residents WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $target_email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $resident = mysqli_fetch_assoc($res);
    }

    if ($resident) {
        // Gumawa ng bagong 6-digit code at itakda ang 15-minute expiration
        $new_code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + (15 * 60)); // 15 minutes

        // I-save ang bagong code sa database
        if ($pdo) {
            $up = $pdo->prepare("UPDATE residents SET reset_code = ?, reset_expires = ? WHERE resident_id = ?");
            $up->execute([$new_code, $expires, $resident['resident_id']]);
        } elseif ($conn) {
            $up = mysqli_prepare($conn, "UPDATE residents SET reset_code = ?, reset_expires = ? WHERE resident_id = ?");
            mysqli_stmt_bind_param($up, "ssi", $new_code, $expires, $resident['resident_id']);
            mysqli_stmt_execute($up);
        }

        // Ipadala sa Gmail inbox
        $mail_result = send_password_reset_code($resident['email'], $resident['full_name'], $new_code);
        if ($mail_result['success']) {
            $message = "A new verification code has been sent directly to your email inbox.";
        } else {
            $error = $mail_result['message'];
        }
        $current_step = 2;
    } else {
        unset($_SESSION['reset_email'], $_SESSION['reset_notice']);
        header("Location: forgot_password.php");
        exit();
    }
}

// =============================================================================
// [FUNCTION 4] STEP 1 SUBMIT: REQUEST VERIFICATION CODE
// Tinitingnan kung rehistrado ang email, gagawa ng OTP, at ipapadala sa email.
// =============================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['btn_request_code'])) {
    $email = is_string($_POST["email"] ?? null) ? trim($_POST["email"]) : "";

    if ($email === "") {
        $error = "Please enter your registered email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!$pdo && !$conn) {
        $error = "Database connection error. Please ensure WAMP MySQL is running.";
    } else {
        $resident = null;
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT resident_id, full_name, email FROM residents WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $resident = $stmt->fetch();
        } elseif ($conn) {
            $stmt = mysqli_prepare($conn, "SELECT resident_id, full_name, email FROM residents WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $resident = mysqli_fetch_assoc($res);
        }

        if ($resident) {
            // Gumawa ng random na 6-digit code
            $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', time() + (15 * 60)); // Expiration: 15 minutes

            // Itago ang code at expiration time sa database (residents table)
            if ($pdo) {
                $up = $pdo->prepare("UPDATE residents SET reset_code = ?, reset_expires = ? WHERE resident_id = ?");
                $up->execute([$code, $expires, $resident['resident_id']]);
            } elseif ($conn) {
                $up = mysqli_prepare($conn, "UPDATE residents SET reset_code = ?, reset_expires = ? WHERE resident_id = ?");
                mysqli_stmt_bind_param($up, "ssi", $code, $expires, $resident['resident_id']);
                mysqli_stmt_execute($up);
            }

            // Ipadala ang 6-digit code diretso sa email ng residente
            $mail_res = send_password_reset_code($resident['email'], $resident['full_name'], $code);

            if ($mail_res['success']) {
                $_SESSION['reset_email'] = $resident['email'];
                $_SESSION['reset_notice'] = "A 6-digit verification code has been sent directly to your email inbox (" . htmlspecialchars($resident['email']) . "). Please check your inbox or spam folder.";
                header("Location: forgot_password.php?step=verify");
                exit();
            } else {
                $error = $mail_res['message'];
            }
        } else {
            $error = "No resident account was found with that email address. Please make sure you entered the correct email.";
        }
    }
}

// =============================================================================
// [FUNCTION 5] STEP 2 SUBMIT: VERIFY CODE & UPDATE PASSWORD
// Sinusuri ang OTP code at expiration, bina-hash ang bagong password at sine-save.
// =============================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['btn_reset_password'])) {
    $target_email     = $_SESSION['reset_email'] ?? '';
    $entered_code     = is_string($_POST["code"] ?? null) ? trim($_POST["code"]) : "";
    $new_password     = is_string($_POST["new_password"] ?? null) ? $_POST["new_password"] : "";
    $confirm_password = is_string($_POST["confirm_password"] ?? null) ? $_POST["confirm_password"] : "";

    $current_step = 2;

    if ($target_email === "") {
        header("Location: forgot_password.php");
        exit();
    } elseif ($entered_code === "") {
        $error = "Please enter the 6-digit verification code received in your email.";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirmation password do not match.";
    } else {
        $resident = null;
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT resident_id, reset_code, reset_expires FROM residents WHERE email = ? LIMIT 1");
            $stmt->execute([$target_email]);
            $resident = $stmt->fetch();
        } elseif ($conn) {
            $stmt = mysqli_prepare($conn, "SELECT resident_id, reset_code, reset_expires FROM residents WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $target_email);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $resident = mysqli_fetch_assoc($res);
        }

        if (!$resident) {
            $error = "Resident record not found. Please start over.";
        } else {
            $db_code = trim((string)($resident['reset_code'] ?? ''));
            $expires = $resident['reset_expires'] ?? null;
            $now = date('Y-m-d H:i:s');

            if ($db_code === "" || $db_code !== $entered_code) {
                $error = "The verification code you entered is invalid. Please check your email and try again.";
            } elseif ($expires && $expires < $now) {
                $error = "This verification code has expired. Please click 'Resend Code' to receive a new one in your email.";
            } else {
                // I-hash ang bagong password gamit ang PHP default secure hashing (Bcrypt)
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);

                // I-update ang password sa database at burahin ang reset_code para hindi na magamit muli
                if ($pdo) {
                    $up = $pdo->prepare("UPDATE residents SET password = ?, reset_code = NULL, reset_expires = NULL WHERE resident_id = ?");
                    $up->execute([$hashed, $resident['resident_id']]);
                } elseif ($conn) {
                    $up = mysqli_prepare($conn, "UPDATE residents SET password = ?, reset_code = NULL, reset_expires = NULL WHERE resident_id = ?");
                    mysqli_stmt_bind_param($up, "si", $hashed, $resident['resident_id']);
                    mysqli_stmt_execute($up);
                }

                // Linisin ang session at i-redirect sa Login Page na may tagumpay na abiso
                unset($_SESSION['reset_email'], $_SESSION['reset_notice']);
                header("Location: login.php?msg=reset_success");
                exit();
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
    <title>Forgot Password - BarangayGo</title>
    
    <!-- Link sa pangunahing stylesheet ng website -->
    <link rel="stylesheet" href="styles.css">

    <!-- ======================================================================= -->
    <!-- [KULAY AT STYLES] DITO NAKA-DESIGN ANG MGA KULAY NG BACKGROUND AT KAHON -->
    <!-- ======================================================================= -->
    <style>
        /* 
           KULAY NG BODY / PINAKA-BACKGROUND NG PAGE:
           Naka-set sa styles.css sa ':root' (var(--bg) = #ffffff puti, may subtle accent glow).
           Kung nais palitan ang buong background, pwede mong baguhin dito:
        */
        body.page-center {
            background-color: #f8fafc; /* Kulay ng pinakalikod na background (Light grayish blue) */
        }

        /* 
           KULAY NG WHITE CARD BOX (KAHON NG FORM):
           Default: background-color: #ffffff (Puti)
        */
        .form-box {
            background-color: #ffffff; /* Kulay ng mismong kahon */
            border: 1px solid #e2e8f0; /* Kulay ng manipis na border */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); /* Soft shadow */
        }

        /* Kulay at style ng 6-Digit OTP Box */
        .otp-input {
            text-align: center;
            letter-spacing: 6px;
            font-size: 1.35rem;
            font-weight: 700;
            font-family: 'Consolas', 'Courier New', monospace;
            background-color: #ffffff; /* Background sa loob ng text field */
            color: #0f172a;            /* Kulay ng itim na numero */
            border: 1.5px solid #cbd5e1;
        }

        /* Kulay ng Notification Info Box sa Step 2 */
        .step-info {
            background-color: #f1f5f9; /* Banayad na kulay abo/asul */
            border: 1px solid #e2e8f0;
            border-radius: var(--radius);
            padding: 12px 14px;
            font-size: 0.85rem;
            color: #334155;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        /* Logo / Picture container styling */
        .forgot-logo-container {
            text-align: center;
            margin-bottom: 16px;
        }
        .forgot-logo-img {
            max-height: 65px;
            width: auto;
            object-fit: contain;
            display: inline-block;
        }
    </style>
</head>
<body class="page-center">

<div class="form-box" style="max-width: 440px; width: 100%;">

    <!-- ======================================================================= -->
    <!-- [PICTURE / LOGO LOCATION] DITO NAKALAGAY ANG LOGO O LITRATO NG BARANGAY -->
    <!-- ======================================================================= -->
    <div class="forgot-logo-container">
        <!-- 
            Palitan ang src="assets/barangaygo-hero.png" kung nais maglagay ng ibang picture o logo:
            Halimbawa: src="assets/my_custom_logo.png"
        -->
        <img src="assets/barangaygo-hero.png" alt="BarangayGo Logo" class="forgot-logo-img">
    </div>

    <h1>FORGOT PASSWORD</h1>

    <?php if ($current_step === 1): ?>
        <p class="sub">Enter your registered email address and we will send a 6-digit verification code directly to your email inbox to reset your password.</p>
    <?php else: ?>
        <p class="sub">Enter the 6-digit code sent to your email inbox and create your new secure password.</p>
    <?php endif; ?>

    <!-- Abiso kung may Error (Pula) -->
    <?php if ($error !== ""): ?>
        <p class="message" role="alert"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
    <?php endif; ?>

    <!-- Abiso kung Tagumpay (Berde) -->
    <?php if ($message !== ""): ?>
        <p class="success" role="alert"><?= htmlspecialchars($message, ENT_QUOTES); ?></p>
    <?php endif; ?>

    <?php if ($current_step === 1): ?>
        <!-- =================================================================== -->
        <!-- [FORM: STEP 1] PAG-TYPE NG EMAIL ADDRESS                            -->
        <!-- =================================================================== -->
        <form action="forgot_password.php" method="post">
            <div class="field">
                <label for="email" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">Registered Email Address</label>
                <input type="email" id="email" name="email" placeholder="name@example.com" autocomplete="email" required autofocus>
            </div>

            <div class="field login-action" style="margin-top: 20px;">
                <button type="submit" name="btn_request_code" class="btn-block">SEND VERIFICATION CODE</button>
            </div>

            <div class="field" style="margin-top: 16px; text-align: center;">
                <a href="login.php" class="forgot-link">← Back to Login</a>
            </div>
        </form>

    <?php else: ?>
        <!-- =================================================================== -->
        <!-- [FORM: STEP 2] PAG-TYPE NG OTP CODE AT BAGONG PASSWORD             -->
        <!-- =================================================================== -->

        <div class="step-info">
            Verification code sent to:<br>
            <strong style="color: #0f172a; word-break: break-all;"><?= htmlspecialchars($_SESSION['reset_email'] ?? '', ENT_QUOTES); ?></strong>
            <div style="margin-top: 6px;">
                <a href="forgot_password.php?action=cancel" style="font-size: 0.8rem; color: #6d4df2; text-decoration: underline;">Change email address</a>
            </div>
        </div>

        <form action="forgot_password.php?step=verify" method="post">
            <!-- Input para sa 6-digit code mula sa email -->
            <div class="field">
                <label for="code" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">6-Digit Verification Code</label>
                <input type="text" id="code" name="code" class="otp-input" placeholder="000000" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required autofocus>
            </div>

            <!-- Input para sa Bagong Password -->
            <div class="field" style="margin-top: 14px;">
                <label for="new_password" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">New Password (min. 8 characters)</label>
                <div class="password-wrapper">
                    <input type="password" id="new_password" name="new_password" placeholder="Enter new password" minlength="8" autocomplete="new-password" required>
                    <!-- Eye icon toggle para makita/itago ang password -->
                    <span class="eye-toggle" id="toggleNewPassword" title="Show / Hide password">
                        <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="eye-icon eye-closed" style="display: none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </span>
                </div>
            </div>

            <!-- Input para sa Pag-confirm ng Bagong Password -->
            <div class="field" style="margin-top: 14px;">
                <label for="confirm_password" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">Confirm New Password</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-type new password" minlength="8" autocomplete="new-password" required>
                    <!-- Eye icon toggle para makita/itago ang password -->
                    <span class="eye-toggle" id="toggleConfirmPassword" title="Show / Hide password">
                        <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="eye-icon eye-closed" style="display: none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </span>
                </div>
            </div>

            <!-- Submit Button para sa Bagong Password -->
            <div class="field login-action" style="margin-top: 20px;">
                <button type="submit" name="btn_reset_password" class="btn-block">RESET PASSWORD</button>
            </div>

            <div class="field" style="margin-top: 16px; display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                <a href="forgot_password.php?action=resend" class="forgot-link">Resend Code</a>
                <a href="login.php" class="forgot-link">Back to Login</a>
            </div>
        </form>

        <!-- =================================================================== -->
        <!-- [JAVASCRIPT FUNCTION] SHOW / HIDE EYE TOGGLE SCRIPT                -->
        <!-- =================================================================== -->
        <script>
        function setupToggle(buttonId, inputId) {
            const btn = document.getElementById(buttonId);
            const input = document.getElementById(inputId);
            if (!btn || !input) return;

            const eyeOpen = btn.querySelector('.eye-open');
            const eyeClosed = btn.querySelector('.eye-closed');

            btn.addEventListener('click', function () {
                const isPassword = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPassword ? 'text' : 'password');
                if (isPassword) {
                    eyeOpen.style.display = 'none';
                    eyeClosed.style.display = 'block';
                } else {
                    eyeOpen.style.display = 'block';
                    eyeClosed.style.display = 'none';
                }
            });
        }

        // I-activate ang eye toggle para sa parehong password fields
        setupToggle('toggleNewPassword', 'new_password');
        setupToggle('toggleConfirmPassword', 'confirm_password');
        </script>
    <?php endif; ?>

</div>

</body>
</html>
