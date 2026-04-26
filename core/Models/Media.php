<?php

declare(strict_types=1);

namespace Nano\Models;

use Nano\App;

final class Media
{
    public int $id;
    public string $filename;
    public string $originalName;
    public string $mime;
    public int $size;
    public ?int $width = null;
    public ?int $height = null;
    public ?string $alt = null;
    public array $meta = [];
    public string $createdAt;

    public static function fromRow(array $row): self
    {
        $m = new self();
        $m->id = (int) $row['id'];
        $m->filename = (string) $row['filename'];
        $m->originalName = (string) $row['original_name'];
        $m->mime = (string) $row['mime'];
        $m->size = (int) $row['size'];
        $m->width = isset($row['width']) ? (int) $row['width'] : null;
        $m->height = isset($row['height']) ? (int) $row['height'] : null;
        $m->alt = $row['alt'] ?? null;
        $m->meta = is_string($row['meta'] ?? null) ? (array) (json_decode($row['meta'], true) ?? []) : [];
        $m->createdAt = (string) $row['created_at'];
        return $m;
    }

    public static function find(int $id): ?self
    {
        $row = App::instance()->db->fetch('SELECT * FROM media WHERE id = ? LIMIT 1', [$id]);
        return $row === null ? null : self::fromRow($row);
    }

    public static function findByFilename(string $filename): ?self
    {
        $row = App::instance()->db->fetch('SELECT * FROM media WHERE filename = ? LIMIT 1', [$filename]);
        return $row === null ? null : self::fromRow($row);
    }

    /**
     * @param list<int> $ids
     * @return array<int, self> Indexed by id
     */
    public static function findMany(array $ids): array
    {
        if ($ids === []) return [];
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = App::instance()->db->fetchAll(
            "SELECT * FROM media WHERE id IN ({$placeholders})",
            $ids
        );
        $out = [];
        foreach ($rows as $row) {
            $m = self::fromRow($row);
            $out[$m->id] = $m;
        }
        return $out;
    }

    public static function all(int $limit = 100, int $offset = 0): array
    {
        $rows = App::instance()->db->fetchAll(
            'SELECT * FROM media ORDER BY created_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        return array_map(fn($r) => self::fromRow($r), $rows);
    }

    public function url(string $size = 'full'): string
    {
        $base = App::instance()->basePath;
        if ($size === '' || $size === 'full' || !str_starts_with($this->mime, 'image/') || $this->mime === 'image/svg+xml') {
            return $base . '/uploads/' . $this->filename;
        }
        return $base . '/uploads/' . $size . '/' . $this->filename;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function humanSize(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 1) . ' KB';
        return number_format($bytes / 1024 / 1024, 1) . ' MB';
    }

    public static function create(array $data): self
    {
        $id = App::instance()->db->insert('media', [
            'filename' => $data['filename'],
            'original_name' => $data['original_name'],
            'mime' => $data['mime'],
            'size' => $data['size'],
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'alt' => $data['alt'] ?? null,
            'meta' => json_encode($data['meta'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
        return self::find($id) ?? throw new \RuntimeException('Failed to create media.');
    }
}
