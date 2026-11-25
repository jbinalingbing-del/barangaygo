<?php
session_start();
include "db.php";

// Admin access check
if (!isset($_SESSION["admin_id"])) {
    header("Location: admin.php");
    exit();
}

$valid_barangays = [
    "Abella","Bagumbayan Norte","Bagumbayan Sur","Balatas",
    "Calauag","Cararayan","Carolina","Concepcion Grande","Concepcion Pequena",
    "Dayangdang","Del Rosario","Dinaga","Igualdad Interior",
    "Lerma","Liboton","Mabolo","Pacol","Panicuason",
    "Penafrancia","Sabang","San Felipe","San Francisco",
    "San Isidro","Sta. Cruz","Tabuco","Tinago","Triangulo"
];

$error   = "";
$salutation  = "Mr.";
$first_name  = "";
$middle_name = "";
$last_name   = "";
$birthday    = "";
$sex         = "Male";
$civil_status= "Single";
$mobile      = "";
$street      = "";
$barangay    = "Concepcion Pequena";
$email       = "";
$password    = "BarangayGo@2026";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $salutation  = trim($_POST["salutation"]   ?? "Mr.");
    $first_name  = trim($_POST["first_name"]   ?? "");
    $middle_name = trim($_POST["middle_name"]  ?? "");
    $last_name   = trim($_POST["last_name"]    ?? "");
    $full_name   = trim("$salutation $first_name $middle_name $last_name");

    $birthday     = trim($_POST["birthday"]     ?? "");
    $sex          = trim($_POST["sex"]          ?? "Male");
    $civil_status = trim($_POST["civil_status"] ?? "Single");
    $mobile       = trim($_POST["mobile"]       ?? "");
    $street       = trim($_POST["street"]       ?? "");
    $barangay     = trim($_POST["barangay"]     ?? "");
    $region       = "Region V - Bicol Region";
    $province     = "Camarines Sur";
    $city         = "Naga City";

    $email    = trim($_POST["email"]    ?? "");
    $password = $_POST["password"]      ?? "";

    $dob = DateTime::createFromFormat("Y-m-d", $birthday);
    $age = $dob ? $dob->diff(new DateTime())->y : 0;

    if ($first_name === "" || $last_name === "") {
        $error = "Please enter resident's name (First Name and Last Name).";
    } elseif (!$dob || $dob > new DateTime()) {
        $error = "Invalid date of birth (Birthday).";
    } elseif (!in_array($barangay, $valid_barangays, true)) {
        $error = "Please select a valid barangay in Naga City.";
    } elseif ($mobile === "" || strlen($mobile) !== 11 || !str_starts_with($mobile, "09")) {
        $error = "Please enter a valid 11-digit mobile number starting with 09.";
    } elseif ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!$pdo && !$conn) {
        $error = "No database connection.";
    } else {
        try {
            // Check email existence
            $exists = false;
            if ($pdo) {
                $chk = $pdo->prepare("SELECT resident_id FROM residents WHERE email = ?");
                $chk->execute([$email]);
                $exists = (bool)$chk->fetch();
            } elseif ($conn) {
                $chk = mysqli_prepare($conn, "SELECT resident_id FROM residents WHERE email = ?");
                mysqli_stmt_bind_param($chk, "s", $email);
                mysqli_stmt_execute($chk);
                mysqli_stmt_store_result($chk);
                $exists = mysqli_stmt_num_rows($chk) > 0;
            }

            if ($exists) {
                $error = "A resident is already registered with this email address ($email).";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if ($pdo) {
                    $ins = $pdo->prepare("
                        INSERT INTO residents 
                            (salutation, first_name, middle_name, last_name, full_name, birthday, age, sex, civil_status, mobile, street, region, province, city, barangay, email, password)
                        VALUES 
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([
                        $salutation, $first_name, $middle_name, $last_name, $full_name,
                        $birthday, $age, $sex, $civil_status, $mobile, $street,
                        $region, $province, $city, $barangay, $email, $hash
                    ]);
                } elseif ($conn) {
                    $ins = mysqli_prepare($conn, "
                        INSERT INTO residents 
                            (salutation, first_name, middle_name, last_name, full_name, birthday, age, sex, civil_status, mobile, street, region, province, city, barangay, email, password)
                        VALUES 
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    mysqli_stmt_bind_param(
                        $ins,
                        "ssssssissssssssss",
                        $salutation, $first_name, $middle_name, $last_name, $full_name,
                        $birthday, $age, $sex, $civil_status, $mobile, $street,
                        $region, $province, $city, $barangay, $email, $hash
                    );
                    mysqli_stmt_execute($ins);
                }

                header("Location: admin_residents.php?msg=added&name=" . urlencode($full_name));
                exit();
            }
        } catch (Exception $e) {
            $error = "Error sa pag-save: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Resident - BarangayGo Admin</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .form-card {
            max-width: 760px;
            margin: 0 auto;
            background: var(--box);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 36px 36px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.04);
        }
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-grid-3 {
            display: grid;
            grid-template-columns: 100px 1fr 1fr;
            gap: 12px;
        }
        .form-field {
            margin-bottom: 18px;
            text-align: left;
        }
        .form-field label {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text);
        }
        .form-field input, .form-field select {
            width: 100%;
            height: 44px;
            padding: 0 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.92rem;
            background: var(--box);
            color: var(--text);
        }
        @media (max-width: 650px) {
            .form-grid-2, .form-grid-3 {
                grid-template-columns: 1fr;
            }
            .form-card {
                padding: 24px 18px;
            }
        }
    </style>
</head>
<body>

<div class="frame full" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
    <nav class="frame-nav">
        <a href="admin_dashboard.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="admin_residents.php" class="active">Residents</a>
            <a href="admin_history.php">All Requests</a>
            <a href="admin_logout.php">Logout</a>
        </div>
    </nav>

    <main class="page-main top" style="justify-content: flex-start;">
        <a href="admin_residents.php" class="back-link">&larr; Back to Residents List</a>

        <div class="form-card" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
            <div style="margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #cbd5e1;">
                <h1 style="margin: 0; font-size: 1.65rem; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em;">➕ Add New Resident</h1>
                <p class="page-sub" style="margin: 4px 0 0;">Manual registration for walk-in residents or official barangay records.</p>
            </div>

            <?php if ($error !== ""): ?>
                <div class="message" role="alert" style="margin-bottom: 22px;">
                    <?= htmlspecialchars($error, ENT_QUOTES); ?>
                </div>
            <?php endif; ?>

            <form action="admin_resident_add.php" method="POST">
                <!-- Name -->
                <div class="form-grid-3">
                    <div class="form-field">
                        <label>Salutation</label>
                        <select name="salutation">
                            <option value="Mr." <?= ($salutation === "Mr.") ? "selected" : ""; ?>>Mr.</option>
                            <option value="Ms." <?= ($salutation === "Ms.") ? "selected" : ""; ?>>Ms.</option>
                            <option value="Mrs." <?= ($salutation === "Mrs.") ? "selected" : ""; ?>>Mrs.</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>First Name</label>
                        <input type="text" name="first_name" required value="<?= htmlspecialchars($first_name, ENT_QUOTES); ?>">
                    </div>
                    <div class="form-field">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" value="<?= htmlspecialchars($middle_name, ENT_QUOTES); ?>">
                    </div>
                </div>

                <div class="form-field">
                    <label>Last Name</label>
                    <input type="text" name="last_name" required value="<?= htmlspecialchars($last_name, ENT_QUOTES); ?>">
                </div>

                <!-- Personal Info -->
                <div class="form-grid-3" style="grid-template-columns: 1.5fr 1fr 1fr;">
                    <div class="form-field">
                        <label>Birthday</label>
                        <input type="date" name="birthday" required value="<?= htmlspecialchars($birthday, ENT_QUOTES); ?>">
                    </div>
                    <div class="form-field">
                        <label>Gender</label>
                        <select name="sex">
                            <option value="Male" <?= ($sex === "Male") ? "selected" : ""; ?>>Male</option>
                            <option value="Female" <?= ($sex === "Female") ? "selected" : ""; ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Civil Status</label>
                        <select name="civil_status">
                            <option value="Single" <?= ($civil_status === "Single") ? "selected" : ""; ?>>Single</option>
                            <option value="Married" <?= ($civil_status === "Married") ? "selected" : ""; ?>>Married</option>
                            <option value="Widowed" <?= ($civil_status === "Widowed") ? "selected" : ""; ?>>Widowed</option>
                            <option value="Separated" <?= ($civil_status === "Separated") ? "selected" : ""; ?>>Separated</option>
                        </select>
                    </div>
                </div>

                <!-- Contact & Address -->
                <div class="form-grid-2">
                    <div class="form-field">
                        <label>Mobile Number (09...)</label>
                        <input type="text" name="mobile" maxlength="11" placeholder="09123456789" required value="<?= htmlspecialchars($mobile, ENT_QUOTES); ?>">
                    </div>
                    <div class="form-field">
                        <label>Barangay (Naga City)</label>
                        <select name="barangay" required>
                            <?php foreach ($valid_barangays as $b): ?>
                                <option value="<?= htmlspecialchars($b, ENT_QUOTES); ?>" <?= ($barangay === $b) ? "selected" : ""; ?>>
                                    <?= htmlspecialchars($b, ENT_QUOTES); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-field">
                    <label>Street Address</label>
                    <input type="text" name="street" placeholder="e.g. Zone 3, Mabini St." required value="<?= htmlspecialchars($street, ENT_QUOTES); ?>">
                </div>

                <!-- Account Credentials -->
                <div style="margin-top: 24px; padding: 18px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px;">
                    <h3 style="margin: 0 0 12px; font-size: 0.95rem; color: #0f172a; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-weight: 700; text-transform: uppercase;">🔐 Account Login Credentials</h3>
                    <div class="form-grid-2">
                        <div class="form-field" style="margin-bottom: 0;">
                            <label>Email Address</label>
                            <input type="email" name="email" required placeholder="resident@email.com" value="<?= htmlspecialchars($email, ENT_QUOTES); ?>">
                        </div>
                        <div class="form-field" style="margin-bottom: 0;">
                            <label>Initial Password</label>
                            <input type="text" name="password" required value="<?= htmlspecialchars($password, ENT_QUOTES); ?>">
                            <small style="color: #64748b; font-size: 0.75rem; display: block; margin-top: 4px;">Resident can change this password later in their profile.</small>
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 28px;">
                    <a href="admin_residents.php" class="btn-new-request" style="text-decoration: none; padding: 10px 20px; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.82rem;">Cancel</a>
                    <button type="submit" class="btn" style="background: #1e293b; border-color: #1e293b; color: #fff; padding: 10px 24px; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.82rem; font-weight: 700;">
                        💾 Save Resident
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

</body>
</html>
