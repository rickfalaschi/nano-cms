<?php

declare(strict_types=1);

namespace Nano\Services;

use Nano\App;
use Nano\Models\Media;

final class MediaService
{
    /** mime → extension (only mimes in this map are accepted) */
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'application/pdf' => 'pdf',
    ];

    private const MAX_SIZE = 25 * 1024 * 1024;

    /**
     * Process a $_FILES entry, validate, persist file + DB row.
     */
    public function upload(array $uploaded): Media
    {
        $error = (int) ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(self::uploadErrorMessage($error));
        }

        $size = (int) ($uploaded['size'] ?? 0);
        if ($size <= 0) {
            throw new \RuntimeException('Arquivo vazio.');
        }
        if ($size > self::MAX_SIZE) {
            throw new \RuntimeException(sprintf(
                'Arquivo muito grande (máximo %d MB).',
                (int) round(self::MAX_SIZE / 1024 / 1024)
            ));
        }

        $tmp = (string) ($uploaded['tmp_name'] ?? '');
        if ($tmp === '' || (!is_uploaded_file($tmp) && !is_file($tmp))) {
            throw new \RuntimeException('Upload inválido.');
        }

        $mime = self::detectMime($tmp);
        if ($mime === null || !isset(self::ALLOWED_MIMES[$mime])) {
            // When detection fails, surface what each layer saw — this is
            // far more useful than "desconhecido" for diagnosing weird files
            // (HEIC from iPhones, PSD, corrupted images, etc.). The file is
            // already rejected, so there's no information leak.
            $diag = self::diagnoseMime($tmp);
            throw new \RuntimeException(sprintf(
                'Tipo de arquivo não permitido. Detecção retornou: %s · Primeiros bytes: %s',
                $diag['summary'],
                $diag['hex']
            ));
        }

        $originalName = (string) ($uploaded['name'] ?? 'arquivo');
        $ext = self::ALLOWED_MIMES[$mime];

        $base = preg_replace('/\.[^.]+$/', '', $originalName) ?? $originalName;
        $base = \slugify($base);
        $filename = $this->uniqueFilename($base . '.' . $ext);

        $uploadsPath = App::instance()->config->path('uploads');
        if (!is_dir($uploadsPath)) {
            @mkdir($uploadsPath, 0775, true);
            @chmod($uploadsPath, 0775);
        }
        $destPath = $uploadsPath . '/' . $filename;

        if (is_uploaded_file($tmp)) {
            if (!move_uploaded_file($tmp, $destPath)) {
                throw new \RuntimeException('Não foi possível salvar o arquivo.');
            }
        } else {
            if (!copy($tmp, $destPath)) {
                throw new \RuntimeException('Não foi possível salvar o arquivo.');
            }
        }

        $width = null;
        $height = null;
        if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') {
            $info = @getimagesize($destPath);
            if (is_array($info)) {
                $width = (int) $info[0];
                $height = (int) $info[1];
            }
        }

        return Media::create([
            'filename' => $filename,
            'original_name' => $originalName,
            'mime' => $mime,
            'size' => $size,
            'width' => $width,
            'height' => $height,
        ]);
    }

    public function delete(Media $media): void
    {
        $uploadsPath = App::instance()->config->path('uploads');

        $original = $uploadsPath . '/' . $media->filename;
        if (is_file($original)) {
            @unlink($original);
        }

        $sizes = App::instance()->config->site('image_sizes');
        if (is_array($sizes)) {
            foreach (array_keys($sizes) as $sizeKey) {
                $sized = $uploadsPath . '/' . $sizeKey . '/' . $media->filename;
                if (is_file($sized)) {
                    @unlink($sized);
                }
            }
        }

        App::instance()->db->delete('media', ['id' => $media->id]);
    }

    /**
     * Generate (or return cached) sized variant. Returns absolute file path.
     */
    public function generateSize(Media $media, string $size): ?string
    {
        $sizeDef = App::instance()->config->site('image_sizes.' . $size);
        if (!is_array($sizeDef)) {
            return null;
        }

        $uploadsPath = App::instance()->config->path('uploads');
        $sourcePath = $uploadsPath . '/' . $media->filename;
        if (!is_file($sourcePath)) {
            return null;
        }

        if (!str_starts_with($media->mime, 'image/') || $media->mime === 'image/svg+xml') {
            return $sourcePath;
        }

        $sizeDir = $uploadsPath . '/' . $size;
        if (!is_dir($sizeDir)) {
            @mkdir($sizeDir, 0775, true);
            @chmod($sizeDir, 0775);
        }
        $destPath = $sizeDir . '/' . $media->filename;

        if (is_file($destPath) && filemtime($destPath) >= filemtime($sourcePath)) {
            return $destPath;
        }

        $width = (int) ($sizeDef['width'] ?? 0);
        $height = (int) ($sizeDef['height'] ?? 0);
        $crop = (bool) ($sizeDef['crop'] ?? false);

        if ($width <= 0 && $height <= 0) {
            return $sourcePath;
        }

        $info = @getimagesize($sourcePath);
        if (!is_array($info)) {
            return $sourcePath;
        }

        [$srcW, $srcH, $type] = $info;

        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => @imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            default => null,
        };
        if (!$src) {
            return $sourcePath;
        }

        if ($crop && $width > 0 && $height > 0) {
            $srcRatio = $srcW / max(1, $srcH);
            $destRatio = $width / max(1, $height);
            if ($srcRatio > $destRatio) {
                $cropH = $srcH;
                $cropW = (int) round($srcH * $destRatio);
                $cropX = (int) round(($srcW - $cropW) / 2);
                $cropY = 0;
            } else {
                $cropW = $srcW;
                $cropH = (int) round($srcW / $destRatio);
                $cropX = 0;
                $cropY = (int) round(($srcH - $cropH) / 2);
            }
            $dst = imagecreatetruecolor($width, $height);
            $this->preserveTransparency($dst, $type);
            imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $width, $height, $cropW, $cropH);
            $newW = $width;
            $newH = $height;
        } else {
            $maxW = $width > 0 ? $width : PHP_INT_MAX;
            $maxH = $height > 0 ? $height : PHP_INT_MAX;
            $ratio = min($maxW / $srcW, $maxH / $srcH, 1.0);
            $newW = (int) max(1, round($srcW * $ratio));
            $newH = (int) max(1, round($srcH * $ratio));
            if ($newW === $srcW && $newH === $srcH) {
                imagedestroy($src);
                copy($sourcePath, $destPath);
                return $destPath;
            }
            $dst = imagecreatetruecolor($newW, $newH);
            $this->preserveTransparency($dst, $type);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        }

        match ($type) {
            IMAGETYPE_PNG => imagepng($dst, $destPath, 9),
            IMAGETYPE_GIF => imagegif($dst, $destPath),
            IMAGETYPE_WEBP => imagewebp($dst, $destPath, 85),
            default => imagejpeg($dst, $destPath, 85),
        };

        imagedestroy($src);
        imagedestroy($dst);

        return $destPath;
    }

    private function preserveTransparency(\GdImage $dst, int $type): void
    {
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP || $type === IMAGETYPE_GIF) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefilledrectangle($dst, 0, 0, imagesx($dst), imagesy($dst), $transparent);
            }
        }
    }

    private function uniqueFilename(string $name): string
    {
        $uploadsPath = App::instance()->config->path('uploads');
        if (!is_file($uploadsPath . '/' . $name)) {
            return $name;
        }
        $info = pathinfo($name);
        $base = $info['filename'];
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        $i = 1;
        while (true) {
            $candidate = $base . '-' . $i . $ext;
            if (!is_file($uploadsPath . '/' . $candidate)) {
                return $candidate;
            }
            $i++;
        }
    }

    /**
     * Detect a file's real MIME type via three layers, in order:
     *   1. `getimagesize()` — gold standard for images, parses real header.
     *   2. Magic-byte sniffing — catches PNG/JPEG/GIF/WebP/PDF/SVG even when
     *      finfo's database is broken.
     *   3. `finfo` extension — last resort.
     *
     * Returns null when nothing recognizes it. The browser-supplied mime type
     * is never trusted (it's user-controlled).
     */
    private static function detectMime(string $path): ?string
    {
        // 1. getimagesize — works reliably for raster images.
        $info = @getimagesize($path);
        if (is_array($info) && !empty($info['mime'])) {
            return (string) $info['mime'];
        }

        // 2. Magic bytes / signature sniffing.
        $sig = self::sniffMagic($path);
        if ($sig !== null) {
            return $sig;
        }

        // 3. finfo (may return 'text/plain' or 'application/octet-stream' on
        //    some PHP versions for valid binary files; we treat those as
        //    unrecognized).
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = @$finfo->file($path);
            if (is_string($detected) && $detected !== ''
                && !in_array($detected, ['text/plain', 'application/octet-stream'], true)) {
                return $detected;
            }
        }

        return null;
    }

    private static function sniffMagic(string $path): ?string
    {
        $head = @file_get_contents($path, false, null, 0, 32);
        if (!is_string($head) || strlen($head) < 4) {
            return null;
        }

        // PNG
        if (str_starts_with($head, "\x89PNG\r\n\x1a\n")) return 'image/png';
        // JPEG
        if (str_starts_with($head, "\xFF\xD8\xFF"))     return 'image/jpeg';
        // GIF
        if (str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a')) return 'image/gif';
        // WebP — RIFF....WEBP
        if (str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP') return 'image/webp';
        // PDF
        if (str_starts_with($head, '%PDF-')) return 'application/pdf';
        // BMP
        if (str_starts_with($head, 'BM')) return 'image/bmp';
        // ICO
        if (str_starts_with($head, "\x00\x00\x01\x00")) return 'image/x-icon';
        // TIFF (little-endian II*\0  or big-endian MM\0*)
        if (str_starts_with($head, "II*\x00") || str_starts_with($head, "MM\x00*")) return 'image/tiff';
        // HEIC / HEIF / AVIF — ISO BMFF: 4-byte big-endian size + 'ftyp' + brand at offset 8
        if (strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp') {
            $brand = substr($head, 8, 4);
            return match ($brand) {
                'heic', 'heix', 'hevc', 'heim', 'heis', 'heiv', 'mif1', 'msf1' => 'image/heic',
                'avif', 'avis' => 'image/avif',
                default => 'application/octet-stream',
            };
        }
        // Photoshop PSD
        if (str_starts_with($head, '8BPS')) return 'image/vnd.adobe.photoshop';

        // SVG: <svg ...> or <?xml ... <svg ...>
        $longerHead = @file_get_contents($path, false, null, 0, 2048);
        if (is_string($longerHead)) {
            $trimmed = ltrim($longerHead);
            if (str_starts_with($trimmed, '<svg') ||
                (str_starts_with($trimmed, '<?xml') && str_contains($longerHead, '<svg'))) {
                return 'image/svg+xml';
            }
        }

        return null;
    }

    /**
     * Run all detection methods and report their results, plus the file's
     * first 16 bytes in hex. Used to produce actionable error messages.
     *
     * @return array{summary:string, hex:string}
     */
    private static function diagnoseMime(string $path): array
    {
        $bytes = @file_get_contents($path, false, null, 0, 16) ?: '';
        $size = @filesize($path);

        $img = @getimagesize($path);
        $imgMime = is_array($img) && !empty($img['mime']) ? $img['mime'] : 'fail';

        $magic = self::sniffMagic($path) ?? 'no match';

        $finfoMime = 'fail';
        if (class_exists(\finfo::class)) {
            $f = new \finfo(FILEINFO_MIME_TYPE);
            $detected = @$f->file($path);
            if (is_string($detected) && $detected !== '') {
                $finfoMime = $detected;
            }
        }

        $summary = sprintf(
            'getimagesize=%s, magic=%s, finfo=%s, size=%s',
            $imgMime,
            $magic,
            $finfoMime,
            $size === false ? 'fail' : ($size . ' bytes')
        );

        return [
            'summary' => $summary,
            'hex'     => bin2hex(substr($bytes, 0, 16)),
        ];
    }

    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o tamanho máximo permitido.',
            UPLOAD_ERR_PARTIAL => 'Upload incompleto.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado no servidor.',
            UPLOAD_ERR_CANT_WRITE => 'Não foi possível salvar o arquivo.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão PHP.',
            default => 'Erro desconhecido no upload.',
        };
    }
}
