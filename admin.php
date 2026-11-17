<?php
session_start();
include "db.php";

// Kung naka-login na ang admin, diretso sa dashboard
if (isset($_SESSION["admin_id"])) {
    header("Location: admin_dashboard.php");
    exit();
}

$error   = "";
$success = "";
$tab     = $_POST["tab"] ?? ($_GET["tab"] ?? "login");

// Tiyakin kung may 'email' column ang admins table, at idagdag kung wala pa
$hasEmailCol = false;
try {
    if ($pdo) {
        $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'email'");
        $hasEmailCol = (bool)$stmt->fetch();
        if (!$hasEmailCol) {
            $pdo->exec("ALTER TABLE admins ADD COLUMN email VARCHAR(255) NULL AFTER username");
            $hasEmailCol = true;
        }
    } elseif ($conn) {
        $res = mysqli_query($conn, "SHOW COLUMNS FROM admins LIKE 'email'");
        $hasEmailCol = ($res && mysqli_num_rows($res) > 0);
        if (!$hasEmailCol) {
            @mysqli_query($conn, "ALTER TABLE admins ADD COLUMN email VARCHAR(255) NULL AFTER username");
            $hasEmailCol = true;
        }
    }
} catch (Exception $e) {
    // Kung hindi man ma-alter, magpapatuloy pa rin nang walang email column
    $hasEmailCol = false;
}

// ==========================================
// 1. PROCESS: CREATE ADMIN ACCOUNT (REGISTER)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && $tab === "register") {
    $full_name = trim($_POST["full_name"] ?? "");
    $username  = trim($_POST["username"]  ?? "");
    $email     = trim($_POST["email"]     ?? "");
    $password  = $_POST["password"]         ?? "";
    $confirm   = $_POST["confirm_password"] ?? "";

    if ($full_name === "" || $username === "" || $password === "") {
        $error = "Punan ang lahat ng kinakailangang impormasyon.";
    } elseif (strlen($password) < 6) {
        $error = "Dapat hindi bababa sa 6 characters ang password.";
    } elseif ($password !== $confirm) {
        $error = "Hindi magkatugma ang password.";
    } elseif (!$pdo && !$conn) {
        $error = "Wala pang database connection. Pakisiguradong naka-start ang WAMP.";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Check kung may kaparehong username
            $exists = false;
            if ($pdo) {
                $check = $pdo->prepare("SELECT admin_id FROM admins WHERE username = ?");
                $check->execute([$username]);
                $exists = (bool)$check->fetch();
            } else {
                $check = mysqli_prepare($conn, "SELECT admin_id FROM admins WHERE username = ?");
                mysqli_stmt_bind_param($check, "s", $username);
                mysqli_stmt_execute($check);
                mysqli_stmt_store_result($check);
                $exists = mysqli_stmt_num_rows($check) > 0;
            }

            if ($exists) {
                // I-update ang password at info ng existing username
                if ($hasEmailCol) {
                    if ($pdo) {
                        $up = $pdo->prepare("UPDATE admins SET password = ?, full_name = ?, email = ? WHERE username = ?");
                        $up->execute([$hash, $full_name, $email, $username]);
                    } else {
                        $up = mysqli_prepare($conn, "UPDATE admins SET password = ?, full_name = ?, email = ? WHERE username = ?");
                        mysqli_stmt_bind_param($up, "ssss", $hash, $full_name, $email, $username);
                        mysqli_stmt_execute($up);
                    }
                } else {
                    if ($pdo) {
                        $up = $pdo->prepare("UPDATE admins SET password = ?, full_name = ? WHERE username = ?");
                        $up->execute([$hash, $full_name, $username]);
                    } else {
                        $up = mysqli_prepare($conn, "UPDATE admins SET password = ?, full_name = ? WHERE username = ?");
                        mysqli_stmt_bind_param($up, "sss", $hash, $full_name, $username);
                        mysqli_stmt_execute($up);
                    }
                }
                
                $new_id = $pdo ? $pdo->query("SELECT admin_id FROM admins WHERE username = " . $pdo->quote($username))->fetchColumn()
                               : mysqli_fetch_row(mysqli_query($conn, "SELECT admin_id FROM admins WHERE username = '" . mysqli_real_escape_string($conn, $username) . "'"))[0];
            } else {
                // Bagong Admin insert
                if ($hasEmailCol) {
                    if ($pdo) {
                        $stmt = $pdo->prepare("INSERT INTO admins (username, email, password, full_name) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$username, $email, $hash, $full_name]);
                        $new_id = $pdo->lastInsertId();
                    } else {
                        $stmt = mysqli_prepare($conn, "INSERT INTO admins (username, email, password, full_name) VALUES (?, ?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $hash, $full_name);
                        mysqli_stmt_execute($stmt);
                        $new_id = mysqli_insert_id($conn);
                    }
                } else {
                    if ($pdo) {
                        $stmt = $pdo->prepare("INSERT INTO admins (username, password, full_name) VALUES (?, ?, ?)");
                        $stmt->execute([$username, $hash, $full_name]);
                        $new_id = $pdo->lastInsertId();
                    } else {
                        $stmt = mysqli_prepare($conn, "INSERT INTO admins (username, password, full_name) VALUES (?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "sss", $username, $hash, $full_name);
                        mysqli_stmt_execute($stmt);
                        $new_id = mysqli_insert_id($conn);
                    }
                }
            }

            // Awtomatikong mag-login pagkatapos gumawa ng account
            session_regenerate_id(true);
            $_SESSION["admin_id"]   = $new_id;
            $_SESSION["admin_name"] = $full_name;
            header("Location: admin_dashboard.php");
            exit();

        } catch (Exception $e) {
            $error = "Error sa paggawa ng account: " . $e->getMessage();
        }
    }
}

// ==========================================
// 2. PROCESS: ADMIN LOGIN
// ==========================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && $tab === "login") {
    $user_input = trim($_POST["user_input"] ?? "");
    $password   = $_POST["password"]         ?? "";

    if ($user_input === "" || $password === "") {
        $error = "Ilagay ang iyong username at password.";
    } elseif (!$pdo && !$conn) {
        $error = "Wala pang database connection. Pakisiguradong naka-start ang WAMP.";
    } else {
        try {
            $row = null;
            if ($hasEmailCol) {
                if ($pdo) {
                    $stmt = $pdo->prepare("SELECT admin_id, username, full_name, password FROM admins WHERE username = ? OR (email != '' AND email = ?)");
                    $stmt->execute([$user_input, $user_input]);
                    $row = $stmt->fetch();
                } else {
                    $stmt = mysqli_prepare($conn, "SELECT admin_id, username, full_name, password FROM admins WHERE username = ? OR (email != '' AND email = ?)");
                    mysqli_stmt_bind_param($stmt, "ss", $user_input, $user_input);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    $row = mysqli_fetch_assoc($res);
                }
            } else {
                if ($pdo) {
                    $stmt = $pdo->prepare("SELECT admin_id, username, full_name, password FROM admins WHERE username = ?");
                    $stmt->execute([$user_input]);
                    $row = $stmt->fetch();
                } else {
                    $stmt = mysqli_prepare($conn, "SELECT admin_id, username, full_name, password FROM admins WHERE username = ?");
                    mysqli_stmt_bind_param($stmt, "s", $user_input);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    $row = mysqli_fetch_assoc($res);
                }
            }

            // Verify
            if ($row && (password_verify($password, $row["password"]) || $password === $row["password"])) {
                session_regenerate_id(true);
                $_SESSION["admin_id"]   = $row["admin_id"];
                $_SESSION["admin_name"] = $row["full_name"] ?: $row["username"];
                header("Location: admin_dashboard.php");
                exit();
            } else {
                $error = "Mali ang username o password.";
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
    <title>BarangayGo - Admin Portal</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .admin-nav-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            background: var(--bg);
            padding: 6px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }
        .admin-tab-btn {
            flex: 1;
            padding: 10px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-align: center;
            color: var(--text-soft);
            background: transparent;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .admin-tab-btn.active {
            background: var(--accent);
            color: var(--white);
            box-shadow: 0 4px 12px var(--accent-glow);
        }
    </style>
</head>
<body>

<div class="frame full">
    <nav class="frame-nav">
        <a href="index.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <span style="font-size: 0.85rem; color: var(--text-soft);">Admin Secure Portal</span>
        </div>
    </nav>

    <main class="page-main">
        <div class="split">
            <div class="welcome">
                <small>ADMINISTRATOR ACCESS</small>
                <h2>BARANGAYGO</h2>
                <p style="margin-top: 14px; color: var(--label); font-size: 1rem; line-height: 1.6;">
                    Ligtas at eksklusibong portal para sa pamunuan ng barangay. Dito maaaring mag-login o lumikha ng bagong admin account upang mapamahalaan ang mga requests at serbisyo.
                </p>
            </div>

            <div class="login-container">
                <div class="form-box">
                    <!-- Tab Switcher -->
                    <div class="admin-nav-tabs">
                        <button type="button" class="admin-tab-btn <?= $tab === 'login' ? 'active' : '' ?>" id="btnTabLogin" onclick="switchTab('login')">
                            ADMIN LOGIN
                        </button>
                        <button type="button" class="admin-tab-btn <?= $tab === 'register' ? 'active' : '' ?>" id="btnTabRegister" onclick="switchTab('register')">
                            CREATE ACCOUNT
                        </button>
                    </div>

                    <?php if ($error !== ""): ?>
                        <p class="message" role="alert"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
                    <?php endif; ?>

                    <!-- TAB 1: LOGIN FORM -->
                    <form action="admin.php" method="post" id="formLogin" style="<?= $tab === 'login' ? '' : 'display: none;' ?>">
                        <input type="hidden" name="tab" value="login">

                        <div class="field">
                            <label class="sr-only" for="user_input">Username or Email</label>
                            <input type="text" id="user_input" name="user_input" placeholder="Username or Email" autocomplete="username"
                                   value="<?= htmlspecialchars($_POST['user_input'] ?? '', ENT_QUOTES); ?>" required>
                        </div>

                        <div class="field">
                            <div class="password-wrapper">
                                <label class="sr-only" for="login_password">Password</label>
                                <input type="password" id="login_password" name="password" placeholder="Password" autocomplete="current-password" required>
                                
                                <span class="eye-toggle" id="toggleLoginPassword" title="Show / Hide password">
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

                        <div class="field" style="margin-top: 24px;">
                            <button type="submit" class="btn-block">LOGIN AS ADMIN</button>
                        </div>
                    </form>

                    <!-- TAB 2: REGISTER / CREATE ADMIN ACCOUNT FORM -->
                    <form action="admin.php" method="post" id="formRegister" style="<?= $tab === 'register' ? '' : 'display: none;' ?>">
                        <input type="hidden" name="tab" value="register">

                        <div class="field">
                            <label for="full_name" style="text-align: left;">Full Name</label>
                            <input type="text" id="full_name" name="full_name" placeholder="Ex. Juan Dela Cruz"
                                   value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES); ?>" required>
                        </div>

                        <div class="field">
                            <label for="reg_username" style="text-align: left;">Desired Username</label>
                            <input type="text" id="reg_username" name="username" placeholder="Ex. admin, kapitan"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES); ?>" required>
                        </div>

                        <div class="field">
                            <label for="reg_email" style="text-align: left;">Email Address <small class="muted">(Optional)</small></label>
                            <input type="email" id="reg_email" name="email" placeholder="admin@barangay.gov.ph"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES); ?>">
                        </div>

                        <div class="field">
                            <label for="reg_password" style="text-align: left;">Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="reg_password" name="password" placeholder="At least 6 characters" required>
                                <span class="eye-toggle" id="toggleRegPassword">
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

                        <div class="field">
                            <label for="confirm_password" style="text-align: left;">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
                        </div>

                        <div class="field" style="margin-top: 24px;">
                            <button type="submit" class="btn-block">CREATE ADMIN ACCOUNT</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </main>

    <footer class="frame-footer">
        <span>BarangayGo &copy; <?= date("Y"); ?></span>
    </footer>
</div>

<script>
// Tab switcher
function switchTab(t) {
    var formLogin = document.getElementById('formLogin');
    var formRegister = document.getElementById('formRegister');
    var btnTabLogin = document.getElementById('btnTabLogin');
    var btnTabRegister = document.getElementById('btnTabRegister');

    if (t === 'login') {
        formLogin.style.display = 'block';
        formRegister.style.display = 'none';
        btnTabLogin.classList.add('active');
        btnTabRegister.classList.remove('active');
    } else {
        formLogin.style.display = 'none';
        formRegister.style.display = 'block';
        btnTabLogin.classList.remove('active');
        btnTabRegister.classList.add('active');
    }
}

// Password toggle helper
function setupToggle(toggleId, inputId) {
    var toggle = document.getElementById(toggleId);
    var input = document.getElementById(inputId);
    if (!toggle || !input) return;
    var openIcon = toggle.querySelector('.eye-open');
    var closedIcon = toggle.querySelector('.eye-closed');

    toggle.addEventListener('click', function () {
        var isPw = input.getAttribute('type') === 'password';
        input.setAttribute('type', isPw ? 'text' : 'password');
        openIcon.style.display = isPw ? 'none' : 'block';
        closedIcon.style.display = isPw ? 'block' : 'none';
    });
}

setupToggle('toggleLoginPassword', 'login_password');
setupToggle('toggleRegPassword', 'reg_password');
</script>

</body>
</html>
