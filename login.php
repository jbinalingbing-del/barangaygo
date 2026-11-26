<?php
// =============================================================================
// login.php - BarangayGo Resident Login Portal
// =============================================================================
// FUNCTION SUMMARY:
// - Tumatanggap ng registered email at password ng residente.
// - Function: Sinusuri sa 'residents' table at nagve-verify gamit ang password_verify().
// - Function: Nagtatakda ng active session ($_SESSION['resident_id']) kapag tama.
// =============================================================================

session_start();
include "db.php";

// =============================================================================
// [FUNCTION 1] ALREADY LOGGED IN CHECK:
// Kung may aktibong login na, diretso agad sa services.php
// =============================================================================
if (isset($_SESSION["resident_id"])) {
    header("Location: services.php");
    exit();
}

$error = "";
$email = "";

// =============================================================================
// [FUNCTION 2] LOGIN AUTHENTICATION (POST):
// Sinusuri ang credentials at bina-validate ang password hash.
// =============================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = is_string($_POST["email"] ?? null) ? trim($_POST["email"]) : "";
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";

    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";
    } elseif (!$pdo && !$conn) {
        $error = "No database connection. Please ensure WAMP is running.";
    } else {
        try {
            $row = null;
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT resident_id, full_name, password FROM residents WHERE email = ?");
                $stmt->execute([$email]);
                $row = $stmt->fetch();
            } else {
                $stmt = mysqli_prepare($conn, "SELECT resident_id, full_name, password FROM residents WHERE email = ?");
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $r_id, $r_name, $r_hash);
                if (mysqli_stmt_fetch($stmt)) {
                    $row = ["resident_id" => $r_id, "full_name" => $r_name, "password" => $r_hash];
                }
            }

            // I-verify ang tinype na password laban sa naka-encrypt (hash) sa database
            if ($row && password_verify($password, $row["password"])) {
                session_regenerate_id(true);
                $_SESSION["resident_id"] = $row["resident_id"];
                $_SESSION["full_name"]   = $row["full_name"];
                header("Location: services.php");
                exit();
            } else {
                $error = "Invalid email or password.";
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page - BarangayGo</title>
    
    <!-- Link sa pangkalahatang stylesheet ng website -->
    <link rel="stylesheet" href="styles.css">

    <!-- ======================================================================= -->
    <!-- [KULAY AT STYLES] DITO NAKA-DESIGN ANG MGA KULAY NG BACKGROUND AT KAHON -->
    <!-- ======================================================================= -->
    <style>
        /*
           KULAY NG BODY / BACKGROUND:
           Pinamamahalaan ng styles.css (background: #ffffff na may accent glow).
        */
        
        /* Kahon ng login form */
        .login-container .form-box {
            background-color: #ffffff; /* Kulay ng puting kahon */
            border: 1px solid #e5e5e7; /* Kulay ng border */
        }

        /* Kulay ng Login Button sa labas ng kahon */
        .outside-login button {
            background-color: #6d4df2; /* BarangayGo Purple Theme */
            color: #ffffff;
        }
    </style>
</head>
<body>

<div class="frame full">
    <!-- Navigation Bar sa itaas -->
    <nav class="frame-nav">
        <a href="index.php" class="brand">
            <img src="assets/community-connect-logo.png" alt="BarangayGo Logo" class="brand-nav-logo">
            <span>BarangayGo</span>
        </a>
        <div class="nav-links">
            <a href="login.php" class="active">Login</a>
            <a href="registration.php">Register</a>
            <a href="about.php">About</a>
        </div>
    </nav>

    <main class="page-main">
        <div class="split">
            <!-- Kaliwang bahagi: Welcome Banner at Logo -->
            <div class="welcome has-logo">
                <!-- =================================================================== -->
                <!-- [PICTURE / LOGO LOCATION] DITO NAKALAGAY ANG MALAKING HERO LOGO     -->
                <!-- =================================================================== -->
                <div class="welcome-top-logo">
                    <!-- Palitan ang 'assets/barangaygo-hero.png' kung may ibang logo o picture -->
                    <img src="assets/barangaygo-hero.png" alt="BarangayGo Logo" class="welcome-logo-img">
                </div>
                <small>WELCOME TO</small>
                <h2>BARANGAYGO</h2>
            </div>

            <!-- Kanang bahagi: Login Form -->
            <form action="login.php" method="post" class="login-container" id="loginForm" novalidate>
                <!-- Mobile only Hero Banner na may Logo para kitang-kita sa phone -->
                <div class="mobile-login-hero">
                    <img src="assets/barangaygo-hero.png" alt="BarangayGo Logo">
                    <small>WELCOME TO</small>
                    <h2>BARANGAYGO</h2>
                </div>

                <p class="sub-outside">Start Using BARANGAYGO Services Today</p>

                <div class="form-box">
                    <h1 class="role-title">LOGIN</h1>

                    <!-- Abiso kapag galing sa Password Reset (Berde) -->
                    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'reset_success'): ?>
                        <p class="success" role="alert">Your password has been reset successfully! You can now log in with your new password.</p>
                    <?php endif; ?>

                    <!-- Abiso kapag may error sa pag-login (Pula) -->
                    <?php if ($error !== ""): ?>
                        <p class="message" role="alert"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
                    <?php endif; ?>

                    <!-- Email Field -->
                    <div class="field">
                        <label class="sr-only" for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="Email" autocomplete="email" spellcheck="false"
                               class="<?= $error !== '' ? 'input-error' : ''; ?>"
                               value="<?= htmlspecialchars($email, ENT_QUOTES); ?>" required>
                        <span class="field-error" id="err-email"></span>
                    </div>

                    <!-- Password Field na may Eye Toggle sa loob -->
                    <div class="field">
                        <div class="password-wrapper">
                            <label class="sr-only" for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Password" autocomplete="current-password"
                                   class="<?= $error !== '' ? 'input-error' : ''; ?>" required>
                            
                            <!-- Eye icon toggle button -->
                            <span class="eye-toggle" id="togglePassword" title="Show / Hide password">
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
                        <span class="field-error" id="err-password"></span>
                    </div>

                    <!-- Link papuntang Forgot Password -->
                    <div class="field form-options">
                        <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
                    </div>
                </div>

                <!-- Submit Button sa labas ng box -->
                <div class="outside-login">
                    <button type="submit" class="btn-block">LOGIN</button>
                </div>
            </form>
        </div>
    </main>
</div>

<!-- =========================================================================== -->
<!-- [JAVASCRIPT FUNCTION] SHOW / HIDE PASSWORD SCRIPT                           -->
<!-- =========================================================================== -->
<script>
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');
const eyeOpen = togglePassword.querySelector('.eye-open');
const eyeClosed = togglePassword.querySelector('.eye-closed');

togglePassword.addEventListener('click', function () {
    const isPassword = passwordInput.getAttribute('type') === 'password';
    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
    
    if (isPassword) {
        eyeOpen.style.display = 'none';
        eyeClosed.style.display = 'block';
    } else {
        eyeOpen.style.display = 'block';
        eyeClosed.style.display = 'none';
    }
});

// Pula ang input kapag walang laman o mali ang email
const emailInput = document.getElementById('email');
const loginForm = document.getElementById('loginForm');

function validateLoginField(input, errId, msg) {
    if (!input.value.trim()) {
        input.classList.add('input-error');
        document.getElementById(errId).textContent = msg;
        return false;
    }
    input.classList.remove('input-error');
    document.getElementById(errId).textContent = '';
    return true;
}

// Suriin ang format ng email at lagyan ng pulang border kung walang laman o mali
function validateLoginEmail() {
    const val = emailInput.value.trim();
    if (!val) {
        emailInput.classList.add('input-error');
        document.getElementById('err-email').textContent = 'Please enter your email.';
        return false;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
        emailInput.classList.add('input-error');
        document.getElementById('err-email').textContent = 'Please enter a valid email address.';
        return false;
    }
    emailInput.classList.remove('input-error');
    document.getElementById('err-email').textContent = '';
    return true;
}

emailInput.addEventListener('blur', validateLoginEmail);
emailInput.addEventListener('input', function () {
    const val = this.value.trim();
    if (val && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
        this.classList.remove('input-error');
        document.getElementById('err-email').textContent = '';
    }
});

passwordInput.addEventListener('blur', function () {
    validateLoginField(this, 'err-password', 'Please enter your password.');
});
passwordInput.addEventListener('input', function () {
    if (this.value.trim()) {
        this.classList.remove('input-error');
        document.getElementById('err-password').textContent = '';
    }
});

// Harangin ang pag-submit kung may kulang o maling field sa login form
loginForm.addEventListener('submit', function (e) {
    const okEmail = validateLoginEmail();
    const okPass  = validateLoginField(passwordInput, 'err-password', 'Please enter your password.');
    if (!okEmail || !okPass) {
        e.preventDefault();
    }
});
</script>

</body>
</html>
