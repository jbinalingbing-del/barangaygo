<?php
session_start();
include "db.php";

// Admin access check
if (!isset($_SESSION["admin_id"])) {
    header("Location: admin.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: admin_residents.php");
    exit();
}

$resident_id = isset($_POST["resident_id"]) ? (int)$_POST["resident_id"] : 0;
if ($resident_id <= 0) {
    header("Location: admin_residents.php?error=invalid_id");
    exit();
}

try {
    // 1. Kunin ang impormasyon ng residente bago burahin para malinis ang files
    $res = null;
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM residents WHERE resident_id = ?");
        $stmt->execute([$resident_id]);
        $res = $stmt->fetch();
    } elseif ($conn) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM residents WHERE resident_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $resident_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }

    if (!$res) {
        header("Location: admin_residents.php?error=not_found");
        exit();
    }

    // 2. Linisin ang profile picture kung mayroon
    if (!empty($res["profile_pic"])) {
        $pic_path = __DIR__ . "/uploads/" . $res["profile_pic"];
        if (file_exists($pic_path) && is_file($pic_path)) {
            @unlink($pic_path);
        }
    }

    // 3. Linisin ang mga naka-upload na files sa requests ng residenteng ito
    $req_files = [];
    if ($pdo) {
        $rf_stmt = $pdo->prepare("SELECT id_front, id_back, selfie, document_file FROM requests WHERE resident_id = ?");
        $rf_stmt->execute([$resident_id]);
        $req_files = $rf_stmt->fetchAll();
    } elseif ($conn) {
        $rf_stmt = mysqli_prepare($conn, "SELECT id_front, id_back, selfie, document_file FROM requests WHERE resident_id = ?");
        mysqli_stmt_bind_param($rf_stmt, "i", $resident_id);
        mysqli_stmt_execute($rf_stmt);
        $rf_res = mysqli_stmt_get_result($rf_stmt);
        while ($row = mysqli_fetch_assoc($rf_res)) {
            $req_files[] = $row;
        }
    }

    foreach ($req_files as $rf) {
        foreach (["id_front", "id_back", "selfie"] as $f) {
            if (!empty($rf[$f])) {
                $path = __DIR__ . "/uploads/" . $rf[$f];
                if (file_exists($path) && is_file($path)) @unlink($path);
            }
        }
        if (!empty($rf["document_file"])) {
            $doc_path = __DIR__ . "/uploads/documents/" . $rf["document_file"];
            if (file_exists($doc_path) && is_file($doc_path)) @unlink($doc_path);
        }
    }

    // 4. Burahin sa database (notifications, requests, at residents)
    if ($pdo) {
        $pdo->prepare("DELETE FROM notifications WHERE resident_id = ?")->execute([$resident_id]);
        $pdo->prepare("DELETE FROM requests WHERE resident_id = ?")->execute([$resident_id]);
        $pdo->prepare("DELETE FROM residents WHERE resident_id = ?")->execute([$resident_id]);
    } elseif ($conn) {
        $d1 = mysqli_prepare($conn, "DELETE FROM notifications WHERE resident_id = ?");
        mysqli_stmt_bind_param($d1, "i", $resident_id);
        mysqli_stmt_execute($d1);

        $d2 = mysqli_prepare($conn, "DELETE FROM requests WHERE resident_id = ?");
        mysqli_stmt_bind_param($d2, "i", $resident_id);
        mysqli_stmt_execute($d2);

        $d3 = mysqli_prepare($conn, "DELETE FROM residents WHERE resident_id = ?");
        mysqli_stmt_bind_param($d3, "i", $resident_id);
        mysqli_stmt_execute($d3);
    }

    header("Location: admin_residents.php?msg=deleted");
    exit();

} catch (Exception $e) {
    header("Location: admin_residents.php?error=" . urlencode($e->getMessage()));
    exit();
}
