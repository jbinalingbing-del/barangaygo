<?php
session_start();
include "db.php";

// Auth check: Resident o Admin lang ang puwede
$is_admin    = isset($_SESSION["admin_id"]);
$resident_id = isset($_SESSION["resident_id"]) ? (int)$_SESSION["resident_id"] : 0;

if (!$is_admin && $resident_id <= 0) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // Hindi pinapayagan ang direct GET access para sa seguridad
    if ($is_admin) {
        header("Location: admin_history.php");
    } else {
        header("Location: my_requests.php");
    }
    exit();
}

$request_id = isset($_POST["request_id"]) ? (int)$_POST["request_id"] : 0;
if ($request_id <= 0) {
    if ($is_admin) {
        header("Location: admin_history.php?error=invalid_id");
    } else {
        header("Location: my_requests.php?error=invalid_id");
    }
    exit();
}

try {
    // 1. Kunin ang detalye ng request
    $req = null;
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM requests WHERE request_id = ?");
        $stmt->execute([$request_id]);
        $req = $stmt->fetch();
    } elseif ($conn) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM requests WHERE request_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $request_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $req = mysqli_fetch_assoc($res);
    }

    if (!$req) {
        $target = $is_admin ? "admin_history.php?error=not_found" : "my_requests.php?error=not_found";
        header("Location: " . $target);
        exit();
    }

    // 2. Seguridad para sa Resident:
    // - Dapat sa kanya ang request
    // - Dapat 'Pending' pa ang status
    if (!$is_admin) {
        if ((int)$req["resident_id"] !== $resident_id) {
            header("Location: my_requests.php?error=unauthorized");
            exit();
        }
        if ($req["status"] !== "Pending") {
            header("Location: my_requests.php?error=already_processed");
            exit();
        }
    }

    // 3. Opsyonal: Linisin ang mga naka-save na uploaded files kung mayroon
    $upload_fields = ["id_front", "id_back", "selfie"];
    foreach ($upload_fields as $f) {
        if (!empty($req[$f])) {
            $file_path = __DIR__ . "/uploads/" . $req[$f];
            if (file_exists($file_path) && is_file($file_path)) {
                @unlink($file_path);
            }
        }
    }
    if (!empty($req["document_file"])) {
        $doc_path = __DIR__ . "/uploads/documents/" . $req["document_file"];
        if (file_exists($doc_path) && is_file($doc_path)) {
            @unlink($doc_path);
        }
    }

    // 4. Burahin ang mga kaugnay na notifications
    if ($pdo) {
        $pdo->prepare("DELETE FROM notifications WHERE request_id = ?")->execute([$request_id]);
        $pdo->prepare("DELETE FROM requests WHERE request_id = ?")->execute([$request_id]);
    } elseif ($conn) {
        $dn = mysqli_prepare($conn, "DELETE FROM notifications WHERE request_id = ?");
        mysqli_stmt_bind_param($dn, "i", $request_id);
        mysqli_stmt_execute($dn);

        $dr = mysqli_prepare($conn, "DELETE FROM requests WHERE request_id = ?");
        mysqli_stmt_bind_param($dr, "i", $request_id);
        mysqli_stmt_execute($dr);
    }

    // Redirect na may tagumpay na mensahe
    if ($is_admin) {
        header("Location: admin_history.php?msg=deleted&req_id=" . $request_id);
    } else {
        header("Location: my_requests.php?msg=deleted&req_id=" . $request_id);
    }
    exit();

} catch (Exception $e) {
    if ($is_admin) {
        header("Location: admin_history.php?error=" . urlencode($e->getMessage()));
    } else {
        header("Location: my_requests.php?error=" . urlencode($e->getMessage()));
    }
    exit();
}
