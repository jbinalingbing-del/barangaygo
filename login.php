<?php
session_start();
include "db.php";

if (isset($_SESSION["resident_id"])) {
    header("Location: services.php");
    exit();
}

$error = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = is_string($_POST["email"] ?? null) ? trim($_POST["email"]) : "";
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";

    if ($email === "" || $password === "") {
        $error = "Ilagay ang email at password.";
    } elseif (!$pdo && !$conn) {
        $error = "Wala pang database connection. Pakisiguradong naka-start ang WAMP.";
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

            if ($row && password_verify($password, $row["password"])) {
                session_regenerate_id(true);
                $_SESSION["resident_id"] = $row["resident_id"];
                $_SESSION["full_name"]   = $row["full_name"];
                header("Location: services.php");
                exit();
            } else {
                $error = "Mali ang email o password.";
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
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="frame full">
    <nav class="frame-nav">
        <a href="index.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <a href="login.php" class="active">Login</a>
            <a href="registration.php">Register</a>
            <a href="about.php">About</a>
        </div>
    </nav>

    <main class="page-main">
        <div class="split">
            <div class="welcome">
                <small>WELCOME TO</small>
                <h2>BARANGAYGO</h2>
            </div>

            <form action="login.php" method="post" class="login-container">
                <p class="sub-outside">Start Using BARANGAYGO Services Today</p>

                <div class="form-box">
                    <!-- Pinalaking Resident Header -->
                    <h1 class="role-title">RESIDENT</h1>

                    <?php if ($error !== ""): ?>
                        <p class="message" role="alert"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
                    <?php endif; ?>

                    <div class="field">
                        <label class="sr-only" for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="Email" autocomplete="email"
                               value="<?= htmlspecialchars($email, ENT_QUOTES); ?>" required>
                    </div>

                    <!-- Password Field na may Eye Toggle sa loob -->
                    <div class="field">
                        <div class="password-wrapper">
                            <label class="sr-only" for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Password" autocomplete="current-password" required>
                            
                            <span class="eye-toggle" id="togglePassword" title="Show / Hide password">
                                <!-- Eye Open Icon -->
                                <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <!-- Eye Closed Icon -->
                                <svg class="eye-icon eye-closed" style="display: none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </span>
                        </div>
                    </div>

                    <!-- Forgot Password Link -->
                    <div class="field form-options">
                        <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
                    </div>
                </div>

                <!-- LOGIN BUTTON SA LABAS NG BOX -->
                <div class="outside-login">
                    <button type="submit" class="btn-block">LOGIN</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
// Toggle Show / Hide Password Script
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
</script>

</body>
</html>
