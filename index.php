<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BarangayGo</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="frame full">
    <nav class="frame-nav">
        <a href="index.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <a href="login.php">Login</a>
            <a href="registration.php">Register</a>
            <a href="about.php">About</a>
        </div>
    </nav>

    <main class="landing">
        <small>WELCOME TO</small>
        <h1>BarangayGo</h1>
        <h2 class="landing-tagline">ONLINE REGISTRATION AND APPLICATION SERVICES</h2>
        <p class="landing-highlight">You can now apply online. Anytime. Anywhere.</p>
        <p class="landing-desc">Wala nang pila. Mag-request ng barangay documents gamit ang iyong phone, at aabisuhan ka kapag handa na ito.</p>
        <a href="registration.php" class="btn">Get Started</a>
    </main>

    <footer class="frame-footer">
        <span>BarangayGo &copy; <?= date("Y"); ?></span>
    </footer>
</div>

</body>
</html>