<?php

declare(strict_types=1);

namespace Nano\Admin\Controllers;

use Nano\Models\Media;
use Nano\Request;
use Nano\Response;
use Nano\Services\MediaService;

final class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;

        $picker = !empty($request->query['picker']);
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = 60;
        $offset = ($page - 1) * $perPage;

        $media = Media::all($perPage, $offset);
        $total = (int) $this->app->db->fetchColumn('SELECT COUNT(*) FROM media');
        $totalPages = max(1, (int) ceil($total / $perPage));

        return $this->render('media/index', [
            'media' => $media,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'picker' => $picker,
        ], $picker ? 'layout/embed' : 'layout/main');
    }

    public function upload(Request $request): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;
        $csrf = $this->verifyCsrfOrFail($request);
        if ($csrf !== null) return $csrf;

        $file = $request->file('file');
        if ($file === null) {
            return Response::json(['error' => 'Nenhum arquivo enviado.'], 400);
        }

        try {
            $media = (new MediaService())->upload($file);
            return Response::json($this->mediaPayload($media));
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, array $params): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;
        $csrf = $this->verifyCsrfOrFail($request);
        if ($csrf !== null) return $csrf;

        $id = (int) ($params['id'] ?? 0);
        $media = Media::find($id);
        if ($media === null) {
            return Response::json(['error' => 'Mídia não encontrada.'], 404);
        }

        $alt = trim((string) ($request->post['alt'] ?? ''));
        $this->app->db->update('media', ['alt' => $alt === '' ? null : $alt], ['id' => $media->id]);

        return Response::json(['ok' => true]);
    }

    public function delete(Request $request, array $params): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;
        $csrf = $this->verifyCsrfOrFail($request);
        if ($csrf !== null) return $csrf;

        $id = (int) ($params['id'] ?? 0);
        $media = Media::find($id);
        if ($media === null) {
            if ($request->isAjax()) {
                return Response::json(['error' => 'Mídia não encontrada.'], 404);
            }
            return Response::redirect(admin_url('media'));
        }

        (new MediaService())->delete($media);

        if ($request->isAjax()) {
            return Response::json(['ok' => true]);
        }

        $this->app->session->flash('success', 'Arquivo excluído.');
        return Response::redirect(admin_url('media'));
    }

    public function show(Request $request, array $params): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;

        $id = (int) ($params['id'] ?? 0);
        $media = Media::find($id);
        if ($media === null) {
            return Response::json(['error' => 'Mídia não encontrada.'], 404);
        }
        return Response::json($this->mediaPayload($media));
    }

    private function mediaPayload(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url('full'),
            'thumb_url' => $media->isImage() ? $media->url('thumb') : null,
            'filename' => $media->filename,
            'original_name' => $media->originalName,
            'mime' => $media->mime,
            'width' => $media->width,
            'height' => $media->height,
            'size' => $media->size,
            'human_size' => $media->humanSize(),
            'alt' => $media->alt,
            'is_image' => $media->isImage(),
            'created_at' => $media->createdAt,
        ];
    }
}
