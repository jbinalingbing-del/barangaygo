<?php
// =============================================================================
// index.php - BarangayGo Landing Page / Home Page
// =============================================================================
// Tungkulin:
// - Pangunahing pahina ng BarangayGo portal para sa mga bisita.
// - Nagpapakita ng welcome hero banner, maikling paliwanag, at "Get Started" link papuntang registration.
// =============================================================================
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BarangayGo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Naka-cache buster ang CSS para laging updated sa browser -->
    <link rel="stylesheet" href="styles.css?v=<?= time(); ?>">
</head>
<body>

<div class="frame full">
    <!-- Pang-itaas na Navigation Bar na may logo -->
    <nav class="frame-nav">
        <a href="index.php" class="brand">
            <img src="assets/community-connect-logo.png" alt="BarangayGo Community Connect" class="brand-nav-logo">
            <span>BarangayGo</span>
        </a>
        <div class="nav-links">
            <a href="login.php">Login</a>
            <a href="registration.php">Register</a>
            <a href="about.php">About</a>
        </div>
    </nav>

    <!-- Pangunahing nilalaman: Hero grid na responsive (desktop, tablet, cellphone) -->
    <main class="landing">
        <div class="landing-hero-grid">
            <div class="landing-hero-content">
                <small>WELCOME TO</small>
                <h1>BarangayGo</h1>
                <h2 class="landing-tagline">ONLINE REGISTRATION AND APPLICATION SERVICES</h2>
                <p class="landing-highlight">You can now apply online. Anytime. Anywhere.</p>
                <p class="landing-desc" style="color:#111111;font-size:1rem;line-height:1.65;font-weight:400;">Skip the lines. Request barangay documents directly from your phone, and receive notifications once ready for pickup or download.</p>
                <a href="registration.php" class="btn">Get Started</a>
            </div>
            <div class="landing-hero-media">
                <img src="assets/barangaygo-hero.png" alt="BarangayGo Online Services" class="hero-illustration">
            </div>
        </div>
    </main>

    <!-- Footer sa ibaba -->
    <footer class="frame-footer">
        <span>BarangayGo &copy; <?= date("Y"); ?></span>
    </footer>
</div>

</body>
</html>