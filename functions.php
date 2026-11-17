<?php
// =====================================================
//  functions.php — Helper functions para sa BarangayGo
// =====================================================

/**
 * Nagse-save ng file upload sa uploads/ folder.
 * Returns array: [bool $success, string $filename_or_error]
 */
function save_upload(string $field_name, string $label, bool $required = false): array {
    if (!isset($_FILES[$field_name]) || $_FILES[$field_name]["error"] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            return [false, "Kailangan i-upload ang $label."];
        }
        return [true, ""];
    }

    $file = $_FILES[$field_name];

    if ($file["error"] !== UPLOAD_ERR_OK) {
        return [false, "May error sa pag-upload ng $label."];
    }

    // Limit to 5MB
    if ($file["size"] > 5 * 1024 * 1024) {
        return [false, "Masyadong malaki ang $label. Maximum 5MB lang."];
    }

    $allowed = ["image/jpeg", "image/png", "image/webp"];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);

    if (!in_array($mime, $allowed, true)) {
        return [false, "JPG, PNG, o WebP lamang ang tinatanggap para sa $label."];
    }

    $ext = match ($mime) {
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp",
        default      => "jpg"
    };

    $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . "uploads";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = bin2hex(random_bytes(16)) . "." . $ext;
    $target   = $upload_dir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file["tmp_name"], $target)) {
        return [false, "Hindi na-save ang $label sa server."];
    }

    return [true, $filename];
}
