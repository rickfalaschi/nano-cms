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
     *
     * Note: this engine version does not generate image variants. Originals
     * are served as-is. If you need responsive images, use srcset/<picture>
     * in the theme with externally-prepared assets, or reintroduce a sizes
     * subsystem in a future revision.
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

        // Sanitize SVG before any reference reaches the DB.
        //
        // SVG files served with Content-Type: image/svg+xml render as
        // active content in the browser — any <script>, on* event
        // handler, or javascript: URI inside would execute in the site's
        // origin (stored XSS). We strip dangerous elements/attributes
        // server-side so the file on disk is safe to serve. If the SVG
        // can't be parsed (broken XML, malicious payload disguised as
        // SVG), the upload is rejected entirely.
        if ($mime === 'image/svg+xml') {
            if (!$this->sanitizeSvg($destPath)) {
                @unlink($destPath);
                throw new \RuntimeException(
                    'SVG inválido ou contém conteúdo não permitido (script, eventos, URIs javascript:).'
                );
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

        App::instance()->db->delete('media', ['id' => $media->id]);
    }

    /**
     * Sanitize an SVG file in place. Removes scripts, event handlers,
     * external resource references and other vectors that could turn an
     * uploaded SVG into a stored XSS when rendered in the browser.
     *
     * Returns false if the file can't be parsed as XML, in which case
     * the caller should reject the upload. Returns true after the file
     * has been overwritten with the sanitized version.
     *
     * Strategy:
     *   1. Parse with DOMDocument using LIBXML_NONET (no external DTDs)
     *      and without LIBXML_NOENT (so entities are not expanded — XXE
     *      protection). PHP 8+ disables external entity loading by
     *      default; we don't reactivate it.
     *   2. Drop dangerous elements wholesale: <script>, <foreignObject>
     *      (can host arbitrary HTML/JS), and a few others.
     *   3. For every remaining element, drop attributes that could
     *      execute code: any on* event handler, href/xlink:href values
     *      starting with javascript:/vbscript:/data:, and `style` values
     *      mentioning expression()/javascript:/behavior:.
     *   4. Save back to disk.
     */
    private function sanitizeSvg(string $path): bool
    {
        $content = @file_get_contents($path);
        if (!is_string($content) || $content === '') {
            return false;
        }

        $previousErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        // LIBXML_NONET prevents fetching external resources during parsing;
        // we deliberately don't pass LIBXML_NOENT so internal entities are
        // left as-is rather than expanded (entity expansion is a known XXE
        // and DoS vector).
        $loaded = $dom->loadXML($content, LIBXML_NONET);

        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (!$loaded) {
            return false;
        }

        // 0. Strip the DOCTYPE entirely. SVG doesn't need one in practice,
        //    and a DOCTYPE that declares external entities (`<!ENTITY xxe
        //    SYSTEM "file://...">`) is a vector even when entity expansion
        //    is disabled — some parsers downstream may still resolve it.
        if ($dom->doctype !== null) {
            $dom->removeChild($dom->doctype);
        }

        // 1. Drop entire elements that are dangerous regardless of attrs.
        //    `foreignObject` can embed arbitrary HTML (including <script>).
        //    `handler`/`listener` are deprecated SVG event-binding elements
        //    but historically supported in some renderers.
        $dangerousTags = [
            'script', 'foreignObject', 'iframe', 'object', 'embed',
            'handler', 'listener', 'set',
        ];
        $xpath = new \DOMXPath($dom);
        foreach ($dangerousTags as $tag) {
            // local-name() makes the match case-insensitive across XML namespaces.
            foreach ($xpath->query("//*[local-name()='{$tag}']") ?: [] as $node) {
                if ($node->parentNode !== null) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        // 2. Walk every remaining element and strip dangerous attributes.
        foreach ($xpath->query('//*') ?: [] as $element) {
            if (!$element instanceof \DOMElement) continue;

            // Snapshot attribute list since modifying it during iteration
            // is undefined behavior in DOM.
            $attrs = [];
            foreach ($element->attributes as $attr) {
                $attrs[] = $attr;
            }

            foreach ($attrs as $attr) {
                $localName = strtolower($attr->localName ?? $attr->name);
                $value = trim($attr->value);
                $valueLower = strtolower($value);

                $strip = false;

                // 2a. Any event handler (onclick, onload, onerror, …).
                if (str_starts_with($localName, 'on')) {
                    $strip = true;
                }

                // 2b. href/xlink:href pointing at code-execution URI schemes.
                //     `data:` is also stripped — data URIs can carry SVG with
                //     embedded scripts, defeating our sanitization.
                if (in_array($localName, ['href', 'xlink:href'], true)) {
                    if (
                        str_starts_with($valueLower, 'javascript:')
                        || str_starts_with($valueLower, 'vbscript:')
                        || str_starts_with($valueLower, 'data:')
                    ) {
                        $strip = true;
                    }
                }

                // 2c. style attribute with code-execution patterns.
                if ($localName === 'style') {
                    if (
                        str_contains($valueLower, 'javascript:')
                        || str_contains($valueLower, 'expression(')
                        || str_contains($valueLower, 'behavior:')
                    ) {
                        $strip = true;
                    }
                }

                if ($strip) {
                    if ($attr->namespaceURI !== null && $attr->namespaceURI !== '') {
                        $element->removeAttributeNS($attr->namespaceURI, $attr->localName);
                    } else {
                        $element->removeAttribute($attr->name);
                    }
                }
            }
        }

        // 3. Save back. saveXML() returns false on failure (very rare).
        $sanitized = $dom->saveXML();
        if (!is_string($sanitized) || $sanitized === '') {
            return false;
        }

        return @file_put_contents($path, $sanitized) !== false;
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
