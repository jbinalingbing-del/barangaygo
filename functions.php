<?php
// =====================================================
//  functions.php — Helper functions para sa BarangayGo
// =====================================================

/**
 * Saves uploaded file to uploads/ directory.
 * Returns array: [bool $success, string $filename_or_error]
 */
function save_upload(string $field_name, string $label, bool $required = false): array {
    if (!isset($_FILES[$field_name]) || $_FILES[$field_name]["error"] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            return [false, "Please upload $label."];
        }
        return [true, ""];
    }

    $file = $_FILES[$field_name];

    if ($file["error"] !== UPLOAD_ERR_OK) {
        switch ($file["error"]) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $msg = "$label is too large. It exceeds the server upload limit. Please upload a smaller file.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $msg = "Upload for $label was interrupted. Please try again.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $msg = "Missing temporary folder for $label on the server.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $msg = "Failed to write $label to disk.";
                break;
            default:
                $msg = "Upload error for $label (Error code: " . $file["error"] . ").";
                break;
        }
        return [false, $msg];
    }

    // Limit to 5MB
    if ($file["size"] > 5 * 1024 * 1024) {
        return [false, "$label is too large. Maximum size is 5 MB."];
    }

    $allowed = ["image/jpeg", "image/png", "image/webp"];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);

    if (!in_array($mime, $allowed, true)) {
        return [false, "Only JPG, PNG, or WebP files are accepted for $label."];
    }

    switch ($mime) {
        case "image/jpeg":
            $ext = "jpg";
            break;
        case "image/png":
            $ext = "png";
            break;
        case "image/webp":
            $ext = "webp";
            break;
        default:
            $ext = "jpg";
            break;
    }

    $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . "uploads";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = bin2hex(random_bytes(16)) . "." . $ext;
    $target   = $upload_dir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file["tmp_name"], $target)) {
        return [false, "Failed to save $label to the server."];
    }

    return [true, $filename];
}
