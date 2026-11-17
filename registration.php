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
    $region   = trim($_POST["region"]   ?? "");
    $province = trim($_POST["province"] ?? "");
    $city     = trim($_POST["city"]     ?? "");
    $barangay = trim($_POST["barangay"] ?? "");
    $email    = trim($_POST["email"]    ?? "");
    $password = $_POST["password"]         ?? "";
    $confirm  = $_POST["confirm_password"] ?? "";
    $agree    = $_POST["agree"]            ?? "";

    $dob = DateTime::createFromFormat("Y-m-d", $birthday);
    $age = $dob ? $dob->diff(new DateTime())->y : 0;

    if (!$agree) {
        $message = "Kailangan mong tanggapin ang Terms of Service bago mag-register.";
    } elseif (!$dob || $dob > new DateTime()) {
        $message = "Mali ang birthday.";
    } elseif (strlen($password) < 8) {
        $message = "Dapat hindi bababa sa 8 characters ang password.";
    } elseif ($password !== $confirm) {
        $message = "Hindi magkapareho ang password.";
    } elseif (!$pdo && !$conn) {
        $message = "Wala pang database connection. Pakisiguradong naka-start ang WAMP.";
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
                $message = "May account na ang email na ito.";
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
</head>
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
        <div class="request">
            <div class="form-box left">
                <h1>REGISTRATION</h1>

                <?php if ($message != ""): ?>
                    <p class="message" role="alert"><?= htmlspecialchars($message, ENT_QUOTES); ?></p>
                <?php endif; ?>

                <form method="POST" action="" id="regForm" novalidate>

                    <!-- BIRTHDAY (Horizontal) -->
                    <div class="form-section">Personal Information</div>
                    <label class="reg-label">Birth Date</label>
                    <div class="bday-row">
                        <div class="bday-cell">
                            <select name="bday_year" id="bday_year" class="reg-select" required>
                                <option value="">— Year —</option>
                                <?php for ($y = date("Y"); $y >= 1900; $y--): ?>
                                    <option value="<?= $y ?>" <?= $v_yr == $y ? "selected" : "" ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="field-error" id="err-year"></span>
                        </div>
                        <div class="bday-cell">
                            <select name="bday_month" id="bday_month" class="reg-select" required>
                                <option value="">— Month —</option>
                                <?php
                                $months = ["01"=>"January","02"=>"February","03"=>"March","04"=>"April",
                                           "05"=>"May","06"=>"June","07"=>"July","08"=>"August",
                                           "09"=>"September","10"=>"October","11"=>"November","12"=>"December"];
                                foreach ($months as $k => $m):
                                ?>
                                    <option value="<?= $k ?>" <?= $v_mo == $k ? "selected" : "" ?>><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-error" id="err-month"></span>
                        </div>
                        <div class="bday-cell">
                            <select name="bday_day" id="bday_day" class="reg-select" required>
                                <option value="">— Day —</option>
                                <?php for ($d = 1; $d <= 31; $d++): $dd = str_pad($d, 2, "0", STR_PAD_LEFT); ?>
                                    <option value="<?= $dd ?>" <?= $v_dy == $dd ? "selected" : "" ?>><?= $d ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="field-error" id="err-day"></span>
                        </div>
                    </div>

                    <!-- Age (auto-compute, maliit na box) -->
                    <div class="age-row">
                        <label for="age">Age</label>
                        <input type="number" id="age" name="age" readonly placeholder="—" value="<?= ($v_yr && $v_mo && $v_dy) ? (new DateTime("$v_yr-$v_mo-$v_dy"))->diff(new DateTime())->y : '' ?>">
                    </div>

                    <!-- NAME (3 fields) -->
                    <div class="form-section">Full Name</div>

                    <!-- Salutation + First Name side by side -->
                    <div class="field">
                        <label>Salutation &amp; First Name</label>
                        <div class="name-row">
                            <select name="salutation" id="salutation" class="reg-select sal-select">
                                <option value="">Title</option>
                                <option value="Mr."  <?= $v_sal === "Mr."   ? "selected" : "" ?>>Mr.</option>
                                <option value="Mrs." <?= $v_sal === "Mrs."  ? "selected" : "" ?>>Mrs.</option>
                                <option value="Ms."  <?= $v_sal === "Ms."   ? "selected" : "" ?>>Ms.</option>
                                <option value="Dr."  <?= $v_sal === "Dr."   ? "selected" : "" ?>>Dr.</option>
                                <option value="Engr." <?= $v_sal === "Engr." ? "selected" : "" ?>>Engr.</option>
                                <option value="Atty." <?= $v_sal === "Atty." ? "selected" : "" ?>>Atty.</option>
                            </select>
                            <input type="text" id="first_name" name="first_name"
                                   placeholder="Ex. JUAN, MARIA (inc. JR, III)"
                                   value="<?= $v_first ?>" required class="name-input">
                        </div>
                        <span class="field-error" id="err-first"></span>
                    </div>

                    <div class="field">
                        <label for="middle_name">Middle Name <small class="muted">(optional)</small></label>
                        <input type="text" id="middle_name" name="middle_name" placeholder="Middle Name" value="<?= $v_mid ?>">
                    </div>

                    <div class="field">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" placeholder="Surname" value="<?= $v_last ?>" required>
                        <span class="field-error" id="err-last"></span>
                    </div>

                    <!-- Sex & Civil Status side by side -->
                    <div class="row two">
                        <div class="field full-mobile">
                            <label for="sex">Sex / Gender</label>
                            <select name="sex" id="sex" class="reg-select" required>
                                <option value="">— Select —</option>
                                <option value="Male"   <?= $v_sex === "Male"   ? "selected" : "" ?>>Male</option>
                                <option value="Female" <?= $v_sex === "Female" ? "selected" : "" ?>>Female</option>
                            </select>
                            <span class="field-error" id="err-sex"></span>
                        </div>
                        <div class="field full-mobile">
                            <label for="civil_status">Civil Status</label>
                            <select name="civil_status" id="civil_status" class="reg-select" required>
                                <option value="">— Select —</option>
                                <option value="Single"    <?= $v_civil === "Single"    ? "selected" : "" ?>>Single</option>
                                <option value="Married"   <?= $v_civil === "Married"   ? "selected" : "" ?>>Married</option>
                                <option value="Widowed"   <?= $v_civil === "Widowed"   ? "selected" : "" ?>>Widowed</option>
                                <option value="Separated" <?= $v_civil === "Separated" ? "selected" : "" ?>>Separated</option>
                                <option value="Annulled"  <?= $v_civil === "Annulled"  ? "selected" : "" ?>>Annulled</option>
                            </select>
                            <span class="field-error" id="err-civil"></span>
                        </div>
                    </div>

                    <!-- ADDRESS -->
                    <div class="form-section">Address</div>

                    <div class="field">
                        <label for="street">Street / House No. / Purok</label>
                        <input type="text" id="street" name="street" value="<?= $v_street ?>" required>
                        <span class="field-error" id="err-street"></span>
                    </div>

                    <div class="field">
                        <label for="region">Region</label>
                        <input type="text" id="region" name="region" value="<?= $v_region ?>" required>
                        <span class="field-error" id="err-region"></span>
                    </div>

                    <div class="field">
                        <label for="province">Province</label>
                        <input type="text" id="province" name="province" value="<?= $v_prov ?>" required>
                        <span class="field-error" id="err-province"></span>
                    </div>

                    <div class="field">
                        <label for="city">City / Municipality</label>
                        <input type="text" id="city" name="city" value="<?= $v_city ?>" required>
                        <span class="field-error" id="err-city"></span>
                    </div>

                    <div class="field">
                        <label for="barangay">Barangay</label>
                        <input type="text" id="barangay" name="barangay" value="<?= $v_bgy ?>" required>
                        <span class="field-error" id="err-barangay"></span>
                    </div>

                    <!-- ACCOUNT -->
                    <div class="form-section">Account Details</div>

                    <div class="field">
                        <label for="mobile">Mobile Number</label>
                        <input type="tel" id="mobile" name="mobile"
                               placeholder="09XXXXXXXXX"
                               value="<?= $v_mobile ?>"
                               maxlength="11" required>
                        <span class="field-error" id="err-mobile"></span>
                    </div>

                    <div class="field">
                        <label for="email">Email Address <small class="muted">(dapat ACTIVE)</small></label>
                        <input type="email" id="email" name="email" placeholder="Enter new Email Address" value="<?= $v_email ?>" required>
                        <span class="field-error" id="err-email"></span>
                    </div>

                    <!-- Password + Confirm (2-column) -->
                    <div class="row two">
                        <div class="field full-mobile">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Enter new Password" required>
                            <span class="field-error" id="err-password"></span>
                        </div>
                        <div class="field full-mobile">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new Password" required>
                            <span class="field-error" id="err-confirm"></span>
                        </div>
                    </div>

                    <!-- TERMS OF SERVICE CHECKBOX -->
                    <div class="tos-wrapper">
                        <label class="tos-label">
                            <input type="checkbox" id="agree" name="agree" value="1">
                            <span>READ and ACCEPT <a href="#" class="tos-link">TERMS OF SERVICES</a></span>
                        </label>
                        <span class="field-error" id="err-agree"></span>
                    </div>

                    <div class="actions" style="margin-top:20px;">
                        <button type="submit">REGISTER</button>
                    </div>

                </form>
            </div>
        </div>
    </main>

</div>

<script>
// ---- Auto-compute Age ----
function computeAge() {
    const y = document.getElementById('bday_year').value;
    const m = document.getElementById('bday_month').value;
    const d = document.getElementById('bday_day').value;
    if (y && m && d) {
        const dob = new Date(`${y}-${m}-${d}`);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const mo = today.getMonth() - dob.getMonth();
        if (mo < 0 || (mo === 0 && today.getDate() < dob.getDate())) age--;
        document.getElementById('age').value = (age >= 0 && age < 130) ? age : '';
    } else {
        document.getElementById('age').value = '';
    }
}
document.getElementById('bday_year').addEventListener('change', computeAge);
document.getElementById('bday_month').addEventListener('change', computeAge);
document.getElementById('bday_day').addEventListener('change', computeAge);

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
        markError(input, errId, `Ilagay ang ${label}.`);
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
    { id: 'region',      err: 'err-region',   label: 'Region' },
    { id: 'province',    err: 'err-province', label: 'Province' },
    { id: 'city',        err: 'err-city',     label: 'City' },
    { id: 'barangay',    err: 'err-barangay', label: 'Barangay' },
    { id: 'email',       err: 'err-email',    label: 'Email' },
    { id: 'mobile',      err: 'err-mobile',   label: 'Mobile Number' },
];

fields.forEach(f => {
    const el = document.getElementById(f.id);
    el.addEventListener('blur', () => validateField(el, f.err, f.label));
    el.addEventListener('input', () => {
        if (el.value.trim()) markOk(el, f.err);
    });
});

// Email format
document.getElementById('email').addEventListener('blur', function() {
    if (this.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value)) {
        markError(this, 'err-email', 'Hindi valid ang format ng email.');
    } else if (this.value) {
        markOk(this, 'err-email');
    }
});

// Password validation
document.getElementById('password').addEventListener('blur', function() {
    if (!this.value) {
        markError(this, 'err-password', 'Ilagay ang password.');
    } else if (this.value.length < 8) {
        markError(this, 'err-password', 'Dapat 8 characters pataas ang password.');
    } else {
        markOk(this, 'err-password');
    }
});

// Confirm password
document.getElementById('confirm_password').addEventListener('blur', function() {
    const pw = document.getElementById('password').value;
    if (!this.value) {
        markError(this, 'err-confirm', 'Kumpirmahin ang password.');
    } else if (this.value !== pw) {
        markError(this, 'err-confirm', 'Hindi magkatugma ang password.');
    } else {
        markOk(this, 'err-confirm');
    }
});

// Birthday selects
['bday_year','bday_month','bday_day'].forEach((id, i) => {
    const errIds = ['err-year','err-month','err-day'];
    const labels = ['Taon', 'Buwan', 'Araw'];
    const el = document.getElementById(id);
    el.addEventListener('change', function() {
        if (!this.value) {
            el.classList.add('input-error');
            document.getElementById(errIds[i]).textContent = `Pumili ng ${labels[i]}.`;
        } else {
            el.classList.remove('input-error');
            el.classList.add('input-ok');
            document.getElementById(errIds[i]).textContent = '';
        }
    });
});

// Sex & Civil Status selects
['sex','civil_status'].forEach((id) => {
    const errIds = { sex: 'err-sex', civil_status: 'err-civil' };
    const el = document.getElementById(id);
    el.addEventListener('change', function() {
        if (!this.value) {
            el.classList.add('input-error');
            document.getElementById(errIds[id]).textContent = 'Pumili ng opsyon.';
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
        markError(this, 'err-mobile', 'Ilagay ang mobile number.');
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
        if (!validateField(el, f.err, f.label)) valid = false;
    });

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
            document.getElementById(errIds[id]).textContent = 'Pumili ng opsyon.';
            valid = false;
        }
    });

    // Birthday
    ['bday_year','bday_month','bday_day'].forEach((id, i) => {
        const errIds = ['err-year','err-month','err-day'];
        const labels = ['Taon', 'Buwan', 'Araw'];
        const el = document.getElementById(id);
        if (!el.value) {
            el.classList.add('input-error');
            document.getElementById(errIds[i]).textContent = `Pumili ng ${labels[i]}.`;
            valid = false;
        }
    });

    // Password
    const pw = document.getElementById('password');
    if (!pw.value || pw.value.length < 8) {
        markError(pw, 'err-password', 'Dapat 8 characters pataas ang password.');
        valid = false;
    }
    const cp = document.getElementById('confirm_password');
    if (cp.value !== pw.value) {
        markError(cp, 'err-confirm', 'Hindi magkatugma ang password.');
        valid = false;
    }

    // TOS
    const agree = document.getElementById('agree');
    if (!agree.checked) {
        document.getElementById('err-agree').textContent = 'Kailangan mong tanggapin ang Terms of Service.';
        agree.closest('.tos-wrapper').classList.add('tos-error');
        valid = false;
    } else {
        document.getElementById('err-agree').textContent = '';
        agree.closest('.tos-wrapper').classList.remove('tos-error');
    }

    if (!valid) e.preventDefault();
});

document.getElementById('agree').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('err-agree').textContent = '';
        this.closest('.tos-wrapper').classList.remove('tos-error');
    }
});
</script>

</body>
</html>
