<?php
session_start();
include "db.php";
require_once __DIR__ . "/functions.php";

// Kailangang naka-login
if (!isset($_SESSION["resident_id"])) {
    header("Location: login.php");
    exit();
}

$service_key  = "barangay_clearance";
$service_name = "Barangay Clearance";

$purposes = ["Employment", "School / Scholarship", "Business", "Travel / Passport", "Medical Assistance", "Loan", "Other"];
$id_types = ["School ID", "National ID (PhilSys)", "Driver's License", "UMID"];

$error         = "";
$purpose       = "";
$purpose_other = "";
$id_type       = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $purpose       = is_string($_POST["purpose"] ?? null) ? $_POST["purpose"] : "";
    $purpose_other = is_string($_POST["purpose_other"] ?? null) ? trim($_POST["purpose_other"]) : "";
    $id_type       = is_string($_POST["id_type"] ?? null) ? $_POST["id_type"] : "";
    $agree         = isset($_POST["agree"]);

    if (!in_array($purpose, $purposes, true)) {
        $error = "Please select a purpose.";
    } elseif ($purpose == "Other" && ($purpose_other == "" || strlen($purpose_other) > 100)) {
        $error = "Please specify your purpose (up to 100 characters).";
    } elseif (!in_array($id_type, $id_types, true)) {
        $error = "Please select an ID type.";
    } elseif (!$agree) {
        $error = "You must agree to the terms before submitting.";
    } elseif (!isset($conn)) {
        $error = "No database connection available.";
    } else {
        [$ok1, $front]  = save_upload("id_front", "ID (Front)", true);
        [$ok2, $back]   = save_upload("id_back",  "ID (Back)", true);
        [$ok3, $selfie] = save_upload("selfie",   "Selfie holding ID", true);

        if (!$ok1) {
            $error = $front;
        } elseif (!$ok2) {
            $error = $back;
        } elseif (!$ok3) {
            $error = $selfie;
        } else {
            $final_purpose = ($purpose == "Other") ? "Other: " . $purpose_other : $purpose;
            $resident_id   = $_SESSION["resident_id"];

            try {
                $stmt = mysqli_prepare($conn, "INSERT INTO requests (resident_id, service, purpose, id_type, id_front, id_back, selfie, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "issssss", $resident_id, $service_key, $final_purpose, $id_type, $front, $back, $selfie);
                    if (mysqli_stmt_execute($stmt)) {
                        header("Location: request_submitted.php");
                        exit();
                    } else {
                        $error = "Failed to submit request. Please try again.";
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
    <?php $active_nav = 'services'; include 'resident_nav.php'; ?>

    <main class="page-main top">
        <a href="services.php" class="back-link">&larr; Back</a>
        <div class="request">
            <h1 class="request-heading">How can the barangay help you today?</h1>
            <?php $active_service = 'clearance'; include 'service_tabs.php'; ?>

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

                    <p class="form-section">Verify Your Identity</p>

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
                        <p class="hint">JPG, PNG or WebP, up to 5 MB.</p>
                    </div>

                    <div class="field">
                        <label for="id_back">ID (Back)</label>
                        <input type="file" id="id_back" name="id_back" accept="image/jpeg,image/png,image/webp" required>
                        <span class="field-error" id="err-id_back"></span>
                        <p class="hint">JPG, PNG or WebP, up to 5 MB.</p>
                    </div>

                    <div class="field">
                        <label for="selfie">Selfie holding your ID</label>
                        <input type="file" id="selfie" name="selfie" accept="image/jpeg,image/png,image/webp" required>
                        <span class="field-error" id="err-selfie"></span>
                        <p class="hint">Ensure your face and ID are clearly visible.</p>
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

// Validation helpers
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

// Selects (change + blur)
["purpose", "id_type"].forEach(function(id) {
    var el = document.getElementById(id);
    function checkSelect() {
        if (!el.value) {
            markError(el, "err-" + id, "Please select an option from the list.");
        } else {
            markOk(el, "err-" + id);
        }
    }
    el.addEventListener("change", checkSelect);
    el.addEventListener("blur", checkSelect);
});

// Other purpose text
otherInput.addEventListener("input", function() {
    if (purpose.value === "Other") {
        if (!this.value.trim()) {
            markError(this, "err-purpose_other", "Please specify the purpose.");
        } else {
            markOk(this, "err-purpose_other");
        }
    }
});

// File inputs (change + blur)
["id_front", "id_back", "selfie"].forEach(function(id) {
    var el = document.getElementById(id);
    function checkFile() {
        if (!el.files || el.files.length === 0) {
            markError(el, "err-" + id, "Please select a file to upload.");
        } else {
            markOk(el, "err-" + id);
        }
    }
    el.addEventListener("change", checkFile);
    el.addEventListener("blur", checkFile);
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
        markError(purpose, "err-purpose", "Please select a purpose.");
        valid = false;
    }
    if (purpose.value === "Other" && !otherInput.value.trim()) {
        markError(otherInput, "err-purpose_other", "Please specify the purpose.");
        valid = false;
    }
    var idType = document.getElementById("id_type");
    if (!idType.value) {
        markError(idType, "err-id_type", "Please select an ID type.");
        valid = false;
    }
    var idFront = document.getElementById("id_front");
    if (!idFront.files || idFront.files.length === 0) {
        markError(idFront, "err-id_front", "Please upload the front of your ID.");
        valid = false;
    }
    var idBack = document.getElementById("id_back");
    if (!idBack.files || idBack.files.length === 0) {
        markError(idBack, "err-id_back", "Please upload the back of your ID.");
        valid = false;
    }
    var selfie = document.getElementById("selfie");
    if (!selfie.files || selfie.files.length === 0) {
        markError(selfie, "err-selfie", "Please upload a selfie holding your ID.");
        valid = false;
    }
    if (!agree.checked) {
        agreeWrap.classList.add("check-error");
        document.getElementById("err-agree").textContent = "You must agree to the terms before submitting.";
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