<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ImageWebpService
{
    /**
     * Convert JPEG/PNG to WebP (resized) and return the relative storage path + bytes.
     * Returns null when conversion is not supported or fails.
     *
     * Notes:
     * - Uses GD functions (lightweight, no external binaries).
     * - Only targets JPEG/PNG to avoid breaking animated GIFs.
     */
    public function convertToWebpBytes(
        UploadedFile $file,
        int $maxWidth = 512,
        int $maxHeight = 512,
        int $quality = 80
    ): ?array {
        if (!function_exists('imagewebp')) {
            return null;
        }

        $tmpPath = $file->getRealPath();
        if (!$tmpPath) {
            return null;
        }

        $imageInfo = @getimagesize($tmpPath);
        if (!$imageInfo || !isset($imageInfo[0], $imageInfo[1], $imageInfo[2])) {
            return null;
        }

        [$width, $height, $type] = $imageInfo;

        // Only JPEG / PNG
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return null;
        }

        $src = null;
        if ($type === IMAGETYPE_JPEG) {
            if (!function_exists('imagecreatefromjpeg')) {
                return null;
            }
            $src = @imagecreatefromjpeg($tmpPath);
        } elseif ($type === IMAGETYPE_PNG) {
            if (!function_exists('imagecreatefrompng')) {
                return null;
            }
            $src = @imagecreatefrompng($tmpPath);
        }

        if (!$src) {
            return null;
        }

        // Resize (only shrink, never upscale)
        $scale = 1.0;
        if ($width > 0 && $height > 0) {
            $scale = min($maxWidth / $width, $maxHeight / $height, 1.0);
        }

        $targetW = (int) max(1, (int) round($width * $scale));
        $targetH = (int) max(1, (int) round($height * $scale));

        $dst = imagecreatetruecolor($targetW, $targetH);
        if (!$dst) {
            imagedestroy($src);
            return null;
        }

        // Preserve alpha for PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $transparent);
        }

        $resampled = imagecopyresampled(
            $dst,
            $src,
            0,
            0,
            0,
            0,
            $targetW,
            $targetH,
            $width,
            $height
        );

        imagedestroy($src);

        if (!$resampled) {
            imagedestroy($dst);
            return null;
        }

        ob_start();
        $ok = imagewebp($dst, null, max(0, min(100, $quality)));
        imagedestroy($dst);
        $bytes = ob_get_clean();

        if (!$ok || !is_string($bytes) || $bytes === '') {
            return null;
        }

        $filename = (string) Str::uuid() . '.webp';
        $relativePath = 'profile-photos/' . $filename;

        return [
            'path' => $relativePath,
            'bytes' => $bytes,
        ];
    }
}
