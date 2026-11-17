<?php
session_start();
include "db.php";
require_once __DIR__ . "/functions.php";

// Kailangang naka-login
if (!isset($_SESSION["resident_id"])) {
    header("Location: login.php");
    exit();
}

$service_key  = "certificate_of_residency";
$service_name = "Certificate of Residency";

$purposes = ["Employment", "School / Scholarship", "Bank / Loan", "Passport / Government ID", "Medical Assistance", "Other"];
$id_types = ["School ID", "National ID (PhilSys)", "Driver's License", "UMID"];

$error         = "";
$purpose       = "";
$purpose_other = "";
$years         = "";
$id_type       = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $purpose       = is_string($_POST["purpose"] ?? null) ? $_POST["purpose"] : "";
    $purpose_other = is_string($_POST["purpose_other"] ?? null) ? trim($_POST["purpose_other"]) : "";
    $years         = is_string($_POST["years"] ?? null) ? trim($_POST["years"]) : "";
    $id_type       = is_string($_POST["id_type"] ?? null) ? $_POST["id_type"] : "";
    $agree         = isset($_POST["agree"]);

    if (!in_array($purpose, $purposes, true)) {
        $error = "Pumili ng purpose.";
    } elseif ($purpose == "Other" && ($purpose_other == "" || strlen($purpose_other) > 100)) {
        $error = "Ilagay ang purpose (hanggang 100 characters).";
    } elseif (filter_var($years, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 120]]) === false) {
        $error = "Ilagay kung ilang taon ka nang nakatira sa barangay (0 hanggang 120).";
    } elseif (!in_array($id_type, $id_types, true)) {
        $error = "Pumili ng uri ng ID.";
    } elseif (!$agree) {
        $error = "Kailangan mong pumayag bago mag-submit.";
    } elseif (!isset($conn)) {
        $error = "Wala pang database connection.";
    } else {
        [$ok1, $front]  = save_upload("id_front", "ID (Front)", true);
        [$ok2, $back]   = save_upload("id_back",  "ID (Back)", false);
        [$ok3, $selfie] = save_upload("selfie",   "selfie na hawak ang ID", true);

        if (!$ok1) {
            $error = $front;
        } elseif (!$ok2) {
            $error = $back;
        } elseif (!$ok3) {
            $error = $selfie;
        } else {
            $final_purpose = ($purpose == "Other") ? "Other: " . $purpose_other : $purpose;
            $resident_id   = $_SESSION["resident_id"];
            $years_value   = (int) $years;

            try {
                $stmt = mysqli_prepare($conn, "INSERT INTO requests (resident_id, service, purpose, years_of_residency, id_type, id_front, id_back, selfie, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ississss", $resident_id, $service_key, $final_purpose, $years_value, $id_type, $front, $back, $selfie);
                    if (mysqli_stmt_execute($stmt)) {
                        header("Location: request_submitted.php");
                        exit();
                    } else {
                        $error = "Hindi na-submit ang request. Subukan ulit.";
                    }
                } else {
                    $error = "Database error: " . mysqli_error($conn);
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
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
    <title><?= htmlspecialchars($service_name, ENT_QUOTES); ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="frame full">
    <nav class="frame-nav">
        <a href="services.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <a href="logout.php">Logout</a>
        </div>
    </nav>

    <main class="page-main top">
        <a href="services.php" class="back-link">&larr; Back</a>
        <div class="request">
            <h1 class="request-heading">Ano ang maitutulong ng barangay sa iyo?</h1>

            <div class="form-box left">
                <h1><?= htmlspecialchars($service_name, ENT_QUOTES); ?></h1>

                <?php if ($error !== ""): ?>
                    <p class="message" role="alert"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" id="serviceForm" novalidate>
                    <div class="field">
                        <label for="purpose">Purpose</label>
                        <select id="purpose" name="purpose" required>
                            <option value="">Select purpose</option>
                            <?php foreach ($purposes as $p): ?>
                                <option value="<?= htmlspecialchars($p, ENT_QUOTES); ?>"<?= $purpose === $p ? " selected" : ""; ?>><?= htmlspecialchars($p, ENT_QUOTES); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-error" id="err-purpose"></span>
                    </div>

                    <div class="field hidden" id="other_field">
                        <label for="purpose_other">Please specify</label>
                        <input type="text" id="purpose_other" name="purpose_other" maxlength="100"
                               value="<?= htmlspecialchars($purpose_other, ENT_QUOTES); ?>">
                        <span class="field-error" id="err-purpose_other"></span>
                    </div>

                    <div class="field">
                        <label for="years">Years of Residency</label>
                        <input type="number" id="years" name="years" min="0" max="120"
                               value="<?= htmlspecialchars($years, ENT_QUOTES); ?>" required>
                        <span class="field-error" id="err-years"></span>
                    </div>

                    <p class="form-section">&mdash; Verify Your Identity &mdash;</p>

                    <div class="field">
                        <label for="id_type">ID Type</label>
                        <select id="id_type" name="id_type" required>
                            <option value="">Select ID type</option>
                            <?php foreach ($id_types as $t): ?>
                                <option value="<?= htmlspecialchars($t, ENT_QUOTES); ?>"<?= $id_type === $t ? " selected" : ""; ?>><?= htmlspecialchars($t, ENT_QUOTES); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-error" id="err-id_type"></span>
                    </div>

                    <div class="field">
                        <label for="id_front">ID (Front)</label>
                        <input type="file" id="id_front" name="id_front" accept="image/jpeg,image/png,image/webp" required>
                        <span class="field-error" id="err-id_front"></span>
                        <p class="hint">JPG, PNG o WebP, hanggang 5 MB.</p>
                    </div>

                    <div class="field">
                        <label for="id_back">ID (Back, optional)</label>
                        <input type="file" id="id_back" name="id_back" accept="image/jpeg,image/png,image/webp">
                    </div>

                    <div class="field">
                        <label for="selfie">Selfie holding your ID</label>
                        <input type="file" id="selfie" name="selfie" accept="image/jpeg,image/png,image/webp" required>
                        <span class="field-error" id="err-selfie"></span>
                        <p class="hint">Siguraduhing malinaw ang mukha mo at ang ID.</p>
                    </div>

                    <label class="check" id="agree_wrap">
                        <input type="checkbox" id="agree" name="agree" value="1" required>
                        <span>I agree that my ID and photo will be used only to verify this request.</span>
                    </label>
                    <span class="field-error" id="err-agree" style="margin-bottom: 12px;"></span>

                    <div class="actions">
                        <button type="submit">SUBMIT</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
var purpose = document.getElementById("purpose");
var otherField = document.getElementById("other_field");
var otherInput = document.getElementById("purpose_other");

function toggleOther() {
    var isOther = purpose.value === "Other";
    otherField.classList.toggle("hidden", !isOther);
    if (!isOther) {
        otherInput.value = "";
        markOk(otherInput, "err-purpose_other");
    }
}

purpose.addEventListener("change", toggleOther);
toggleOther();

function markError(el, errId, msg) {
    el.classList.add("input-error");
    el.classList.remove("input-ok");
    var errEl = document.getElementById(errId);
    if (errEl) errEl.textContent = msg;
}

function markOk(el, errId) {
    el.classList.remove("input-error");
    el.classList.add("input-ok");
    var errEl = document.getElementById(errId);
    if (errEl) errEl.textContent = "";
}

// Selects
["purpose", "id_type"].forEach(function(id) {
    var el = document.getElementById(id);
    el.addEventListener("change", function() {
        if (!this.value) {
            markError(this, "err-" + id, "Pumili ng isa sa listahan.");
        } else {
            markOk(this, "err-" + id);
        }
    });
});

// Years of residency
var yearsInput = document.getElementById("years");
yearsInput.addEventListener("input", function() {
    if (this.value === "" || isNaN(this.value) || Number(this.value) < 0) {
        markError(this, "err-years", "Ilagay ang bilang ng taon ng paninirahan.");
    } else {
        markOk(this, "err-years");
    }
});

// Other purpose text
otherInput.addEventListener("input", function() {
    if (purpose.value === "Other") {
        if (!this.value.trim()) {
            markError(this, "err-purpose_other", "Pakitukoy ang layunin.");
        } else {
            markOk(this, "err-purpose_other");
        }
    }
});

// File inputs
["id_front", "selfie"].forEach(function(id) {
    var el = document.getElementById(id);
    el.addEventListener("change", function() {
        if (!this.files || this.files.length === 0) {
            markError(this, "err-" + id, "Pumili ng file na ia-upload.");
        } else {
            markOk(this, "err-" + id);
        }
    });
});

// Agree checkbox
var agree = document.getElementById("agree");
var agreeWrap = document.getElementById("agree_wrap");
agree.addEventListener("change", function() {
    if (this.checked) {
        agreeWrap.classList.remove("check-error");
        document.getElementById("err-agree").textContent = "";
    }
});

// Form submit check
document.getElementById("serviceForm").addEventListener("submit", function(e) {
    var valid = true;

    if (!purpose.value) {
        markError(purpose, "err-purpose", "Kailangang pumili ng purpose.");
        valid = false;
    }
    if (purpose.value === "Other" && !otherInput.value.trim()) {
        markError(otherInput, "err-purpose_other", "Pakitukoy ang layunin.");
        valid = false;
    }
    if (yearsInput.value === "" || isNaN(yearsInput.value) || Number(yearsInput.value) < 0) {
        markError(yearsInput, "err-years", "Ilagay ang bilang ng taon ng paninirahan.");
        valid = false;
    }
    var idType = document.getElementById("id_type");
    if (!idType.value) {
        markError(idType, "err-id_type", "Pumili ng uri ng ID.");
        valid = false;
    }
    var idFront = document.getElementById("id_front");
    if (!idFront.files || idFront.files.length === 0) {
        markError(idFront, "err-id_front", "I-upload ang harap ng iyong ID.");
        valid = false;
    }
    var selfie = document.getElementById("selfie");
    if (!selfie.files || selfie.files.length === 0) {
        markError(selfie, "err-selfie", "I-upload ang iyong selfie kasama ang ID.");
        valid = false;
    }
    if (!agree.checked) {
        agreeWrap.classList.add("check-error");
        document.getElementById("err-agree").textContent = "Kailangan mong lagyan ng check ang pagsang-ayon.";
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
        var firstErr = document.querySelector(".input-error, .check-error");
        if (firstErr) {
            firstErr.scrollIntoView({ behavior: "smooth", block: "center" });
        }
    }
});
</script>

</body>
</html>