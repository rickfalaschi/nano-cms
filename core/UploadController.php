<?php

declare(strict_types=1);

namespace Nano;

use Nano\Models\Media;
use Nano\Services\MediaService;

final class UploadController
{
    public function serve(Request $request, array $params): Response
    {
        $path = (string) ($params['path'] ?? '');
        if ($path === '' || str_contains($path, '..')) {
            return Response::notFound();
        }

        $uploadsPath = App::instance()->config->path('uploads');
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn($s) => $s !== ''));

        if (count($segments) === 2) {
            $sizeKey = $segments[0];
            $filename = $segments[1];
            $sizeDef = App::instance()->config->site('image_sizes.' . $sizeKey);
            if (is_array($sizeDef)) {
                $media = Media::findByFilename($filename);
                if ($media !== null) {
                    $generated = (new MediaService())->generateSize($media, $sizeKey);
                    if ($generated !== null) {
                        return $this->serveFile($generated);
                    }
                }
                return Response::notFound();
            }
        }

        $file = realpath($uploadsPath . '/' . $path);
        $uploadsReal = realpath($uploadsPath);
        if ($file === false || $uploadsReal === false || !str_starts_with($file, $uploadsReal . DIRECTORY_SEPARATOR)) {
            return Response::notFound();
        }
        if (!is_file($file)) {
            return Response::notFound();
        }

        return $this->serveFile($file);
    }

    private function serveFile(string $file): Response
    {
        $mime = function_exists('mime_content_type')
            ? (mime_content_type($file) ?: 'application/octet-stream')
            : 'application/octet-stream';

        return (new Response())
            ->status(200)
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'public, max-age=2592000')
            ->body((string) file_get_contents($file));
    }
}
