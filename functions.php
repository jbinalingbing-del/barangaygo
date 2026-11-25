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
        $msg = match ($file["error"]) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "$label is too large. It exceeds the server upload limit. Please upload a smaller file.",
            UPLOAD_ERR_PARTIAL   => "Upload for $label was interrupted. Please try again.",
            UPLOAD_ERR_NO_TMP_DIR=> "Missing temporary folder for $label on the server.",
            UPLOAD_ERR_CANT_WRITE=> "Failed to write $label to disk.",
            default              => "Upload error for $label (Error code: " . $file["error"] . ")."
        };
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
        return [false, "Failed to save $label to the server."];
    }

    return [true, $filename];
}
