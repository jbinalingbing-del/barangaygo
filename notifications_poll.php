<?php
// Live polling endpoint para sa unread notification count at bagong notifications
session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// Ibalik ang 0 kung hindi naka-login ang resident
if (!isset($_SESSION["resident_id"])) {
    echo json_encode(["unread" => 0, "items" => []]);
    exit();
}

include_once __DIR__ . "/db.php";
$resident_id = (int)$_SESSION["resident_id"];
$unread = 0;
$items = [];
$with_list = !empty($_GET["with_list"]);

// Ayusin ang icon, mensahe, at oras ng bawat notification bago ipadala sa browser
function format_notif_item(array $n): array {
    $msg = $n["message"] ?? "";
    $icon = "📋";
    if (strpos($msg, "Approved") !== false)  $icon = "✅";
    if (strpos($msg, "Ready") !== false)     $icon = "📄";
    if (strpos($msg, "Released") !== false)  $icon = "🤝";
    if (strpos($msg, "Rejected") !== false)  $icon = "❌";

    $clean_msg = preg_replace('/^[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\s]+/u', '', $msg);
    $clean_msg = preg_replace('/Handa na ang iyong dokumento para sa (.+?)[!.]* Maaari mo na itong i-download at i-print\.?/i', 'Your document for $1 is ready! You can now download and print it.', $clean_msg);
    $clean_msg = preg_replace('/Ang iyong request para sa (.+?) ay naging [\'"]?([^\'"]+)[\'"]?\.?/i', 'Your request for $1 has been marked as \'$2\'.', $clean_msg);
    $clean_msg = preg_replace('/Nai-upload na ang iyong dokumento para sa (.+?)\.?/i', 'Your document for $1 has been uploaded.', $clean_msg);
    $clean_msg = preg_replace('/Ang iyong request #?(\d+) ay naaprubahan na\.?/i', 'Your request #$1 has been approved.', $clean_msg);
    $clean_msg = preg_replace('/Ang iyong request #?(\d+) ay nai-release na\.?/i', 'Your request #$1 has been released.', $clean_msg);
    $clean_msg = preg_replace('/Ang iyong request #?(\d+) ay tinanggihan\.?/i', 'Your request #$1 was rejected.', $clean_msg);
    $clean_msg = str_ireplace(['Dahilan/Paalala:', 'Paalala:', 'Dahilan:'], ['Remarks:', 'Reminder:', 'Reason:'], $clean_msg);

    $is_doc = (stripos($msg, "dokumento") !== false || stripos($msg, "document") !== false);
    return [
        "notif_id"   => (int)$n["notif_id"],
        "request_id" => !empty($n["request_id"]) ? (int)$n["request_id"] : 0,
        "is_doc"     => $is_doc,
        "is_unread"  => empty($n["is_read"]),
        "icon"       => $icon,
        "message"    => $clean_msg,
        "time"       => date("F j, Y · g:i A", strtotime($n["created_at"])),
    ];
}

try {
    // Kunin ang bilang ng hindi pa nababasang notifications at (kung hiniling) ang buong listahan
    if (!empty($pdo)) {
        $ns = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE resident_id = ? AND is_read = 0");
        $ns->execute([$resident_id]);
        $unread = (int)$ns->fetchColumn();

        if ($with_list) {
            $ls = $pdo->prepare("SELECT notif_id, request_id, message, is_read, created_at FROM notifications WHERE resident_id = ? ORDER BY created_at DESC");
            $ls->execute([$resident_id]);
            foreach ($ls->fetchAll() as $row) {
                $items[] = format_notif_item($row);
            }
        }
    } elseif (!empty($conn)) {
        $ns = mysqli_prepare($conn, "SELECT COUNT(*) FROM notifications WHERE resident_id = ? AND is_read = 0");
        if ($ns) {
            mysqli_stmt_bind_param($ns, "i", $resident_id);
            mysqli_stmt_execute($ns);
            mysqli_stmt_bind_result($ns, $unread);
            mysqli_stmt_fetch($ns);
            mysqli_stmt_close($ns);
        }
        if ($with_list) {
            $ls = mysqli_prepare($conn, "SELECT notif_id, request_id, message, is_read, created_at FROM notifications WHERE resident_id = ? ORDER BY created_at DESC");
            if ($ls) {
                mysqli_stmt_bind_param($ls, "i", $resident_id);
                mysqli_stmt_execute($ls);
                $res = mysqli_stmt_get_result($ls);
                while ($row = mysqli_fetch_assoc($res)) {
                    $items[] = format_notif_item($row);
                }
                mysqli_stmt_close($ls);
            }
        }
    }
} catch (Exception $e) {}

echo json_encode(["unread" => (int)$unread, "items" => $items]);
