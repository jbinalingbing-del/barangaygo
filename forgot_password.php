<?php
session_start();
include "db.php";

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = is_string($_POST["email"] ?? null) ? trim($_POST["email"]) : "";
    if ($email == "") {
        $error = "Ilagay ang iyong rehistradong email.";
    } else {
        // Placeholder handling o logic para sa password reset
        $message = "Kung rehistrado ang email na ito, makakatanggap ka ng mga tagubilin para i-reset ang iyong password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - BarangayGo</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="page-center">

<div class="form-box">
    <h1>FORGOT PASSWORD</h1>
    <p class="sub">Ilagay ang iyong rehistradong email para ma-recover ang account.</p>

    <?php if ($error !== ""): ?>
        <p class="message" role="alert"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
    <?php endif; ?>

    <?php if ($message !== ""): ?>
        <p class="success" role="alert"><?= htmlspecialchars($message, ENT_QUOTES); ?></p>
    <?php endif; ?>

    <form action="forgot_password.php" method="post">
        <div class="field">
            <label class="sr-only" for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Email Address" autocomplete="email" required>
        </div>

        <div class="field login-action">
            <button type="submit" class="btn-block">SEND RESET LINK</button>
        </div>

        <div class="field" style="margin-top: 16px;">
            <a href="login.php" class="forgot-link">← Bumalik sa Login</a>
        </div>
    </form>
</div>

</body>
</html>
