<?php
const UPLOAD_DIR = __DIR__ . '/../uploads/tenancy/';
const UPLOAD_MAX = 8 * 1024 * 1024; // 8 MB
const UPLOAD_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf'];

/**
 * $_FILES[$field] (single ya multiple) ko save karta hai. Kitni files save huin wo return.
 */
function save_tenancy_files(int $tenancyId, string $field, string $title): int
{
    if (empty($_FILES[$field])) return 0;
    $f = $_FILES[$field];
    $names = (array)$f['name'];
    $saved = 0;
    foreach ($names as $i => $orig) {
        $err = ((array)$f['error'])[$i];
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        $tmp = ((array)$f['tmp_name'])[$i];
        $size = ((array)$f['size'])[$i];
        if ($err !== UPLOAD_ERR_OK || $size > UPLOAD_MAX || !is_uploaded_file($tmp)) {
            flash("File \"$orig\" upload nahi hui (max 8MB).", 'danger');
            continue;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset(UPLOAD_TYPES[$mime])) {
            flash("File \"$orig\": sirf JPG, PNG, WEBP, GIF ya PDF allowed hai.", 'danger');
            continue;
        }
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        $name = $tenancyId . '_' . bin2hex(random_bytes(10)) . '.' . UPLOAD_TYPES[$mime];
        if (move_uploaded_file($tmp, UPLOAD_DIR . $name)) {
            $t = $title !== '' ? $title : pathinfo($orig, PATHINFO_FILENAME);
            if (count($names) > 1 && $title !== '') $t .= ' (' . ($i + 1) . ')';
            db_exec('INSERT INTO tenancy_files (tenancy_id, title, file_name, original_name, mime) VALUES (?,?,?,?,?)',
                [$tenancyId, mb_substr($t, 0, 120), $name, mb_substr($orig, 0, 255), $mime]);
            $saved++;
        }
    }
    return $saved;
}
