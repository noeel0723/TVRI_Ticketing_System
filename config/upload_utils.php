<?php
function ensure_upload_dir($dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function is_gd_available() {
    if (extension_loaded('gd')) {
        return true;
    }
    // fallback: check for common GD functions
    return function_exists('imagecreatefromstring') || function_exists('imagecreatefromjpeg') || function_exists('imagecreatefrompng');
}

function detect_mime($path) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $path) : null;
    if ($finfo) {
        finfo_close($finfo);
    }
    return $mime;
}

function detect_mime_from_buffer($data) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_buffer($finfo, $data) : null;
    if ($finfo) {
        finfo_close($finfo);
    }
    return $mime;
}

function create_image_from_path($path, $mime) {
    if (!is_gd_available()) {
        error_log('upload_utils: GD extension not available - enable ext-gd in php.ini');
        return null;
    }
    if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
        return imagecreatefromjpeg($path);
    }
    if ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
        return imagecreatefrompng($path);
    }
    if ($mime === 'image/gif' && function_exists('imagecreatefromgif')) {
        return imagecreatefromgif($path);
    }
    if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        return imagecreatefromwebp($path);
    }
    return null;
}

function create_image_from_string_safe($data, $mime) {
    if (!is_gd_available() || !function_exists('imagecreatefromstring')) {
        error_log('upload_utils: imagecreatefromstring not available (GD missing)');
        return null;
    }
    return imagecreatefromstring($data);
}

function resize_image_resource($src, $max_w, $max_h, $mime) {
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w <= 0 || $h <= 0) {
        return null;
    }
    $ratio = min($max_w / $w, $max_h / $h, 1);
    $new_w = (int) floor($w * $ratio);
    $new_h = (int) floor($h * $ratio);
    if ($new_w === $w && $new_h === $h) {
        return $src;
    }
    $dst = imagecreatetruecolor($new_w, $new_h);
    if ($mime === 'image/png' || $mime === 'image/gif' || $mime === 'image/webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $transparent);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $w, $h);
    return $dst;
}

function save_image_resource($img, $dest, $mime, $quality = 85) {
    if (!is_gd_available()) {
        error_log('upload_utils: cannot save image - GD not available');
        return false;
    }
    if ($mime === 'image/jpeg' && function_exists('imagejpeg')) {
        return imagejpeg($img, $dest, $quality);
    }
    if ($mime === 'image/png' && function_exists('imagepng')) {
        return imagepng($img, $dest, 6);
    }
    if ($mime === 'image/gif' && function_exists('imagegif')) {
        return imagegif($img, $dest);
    }
    if ($mime === 'image/webp' && function_exists('imagewebp')) {
        return imagewebp($img, $dest, $quality);
    }
    return false;
}

function process_uploaded_image($tmp_path, $dest_path, $max_w, $max_h, $allowed_mimes, &$out_mime) {
    $mime = detect_mime($tmp_path);
    if (!$mime || !in_array($mime, $allowed_mimes, true)) {
        return false;
    }
    if (!is_gd_available()) {
        $ok = move_uploaded_file($tmp_path, $dest_path);
        if ($ok) {
            $out_mime = $mime;
        }
        return $ok;
    }
    $img = create_image_from_path($tmp_path, $mime);
    if (!$img) {
        return false;
    }
    $resized = resize_image_resource($img, $max_w, $max_h, $mime);
    if (!$resized) {
        imagedestroy($img);
        return false;
    }
    $ok = save_image_resource($resized, $dest_path, $mime);
    if ($resized !== $img) {
        imagedestroy($resized);
    }
    imagedestroy($img);
    if ($ok) {
        $out_mime = $mime;
    }
    return $ok;
}

function process_base64_image($data, $dest_path, $max_w, $max_h, $allowed_mimes, &$out_mime) {
    $mime = detect_mime_from_buffer($data);
    if (!$mime) {
        return false;
    }
    if (!in_array($mime, $allowed_mimes, true)) {
        return false;
    }
    if (!is_gd_available()) {
        $ok = file_put_contents($dest_path, $data) !== false;
        if ($ok) {
            $out_mime = $mime;
        }
        return $ok;
    }
    $img = create_image_from_string_safe($data, $mime);
    if (!$img) {
        return false;
    }
    $resized = resize_image_resource($img, $max_w, $max_h, $mime);
    if (!$resized) {
        imagedestroy($img);
        return false;
    }
    $ok = save_image_resource($resized, $dest_path, $mime);
    if ($resized !== $img) {
        imagedestroy($resized);
    }
    imagedestroy($img);
    if ($ok) {
        $out_mime = $mime;
    }
    return $ok;
}
?>
