<?php

declare(strict_types=1);

namespace Nano\Models;

use Nano\App;

final class Term
{
    public int $id;
    public string $taxonomy;
    public string $slug;
    public string $name;
    public ?int $parentId = null;
    public array $fields = [];

    public static function fromRow(array $row): self
    {
        $t = new self();
        $t->id = (int) $row['id'];
        $t->taxonomy = (string) $row['taxonomy'];
        $t->slug = (string) $row['slug'];
        $t->name = (string) $row['name'];
        $t->parentId = isset($row['parent_id']) ? (int) $row['parent_id'] : null;
        $t->fields = is_string($row['fields'] ?? null) ? (array) (json_decode($row['fields'], true) ?? []) : [];
        return $t;
    }

    public static function find(int $id): ?self
    {
        $row = App::instance()->db->fetch('SELECT * FROM terms WHERE id = ? LIMIT 1', [$id]);
        return $row === null ? null : self::fromRow($row);
    }

    public static function findBySlug(string $taxonomy, string $slug): ?self
    {
        $row = App::instance()->db->fetch(
            'SELECT * FROM terms WHERE taxonomy = ? AND slug = ? LIMIT 1',
            [$taxonomy, $slug]
        );
        return $row === null ? null : self::fromRow($row);
    }

    /**
     * @return list<self>
     */
    public static function all(string $taxonomy): array
    {
        $rows = App::instance()->db->fetchAll(
            'SELECT * FROM terms WHERE taxonomy = ? ORDER BY name ASC',
            [$taxonomy]
        );
        return array_map(fn($r) => self::fromRow($r), $rows);
    }

    public function field(string $name, mixed $default = null): mixed
    {
        return $this->fields[$name] ?? $default;
    }

    public function url(): string
    {
        $config = App::instance()->config;
        $tax = $config->taxonomy($this->taxonomy);
        $slug = (string) ($tax['slug'] ?? $this->taxonomy);
        return App::instance()->basePath . '/' . trim($slug, '/') . '/' . $this->slug;
    }

    public static function create(array $data): self
    {
        $id = App::instance()->db->insert('terms', [
            'taxonomy' => $data['taxonomy'],
            'slug' => $data['slug'],
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'fields' => json_encode($data['fields'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
        return self::find($id) ?? throw new \RuntimeException('Failed to create term.');
    }

    public function save(array $data): void
    {
        $update = [];
        if (array_key_exists('name', $data)) $update['name'] = $data['name'];
        if (array_key_exists('slug', $data)) $update['slug'] = $data['slug'];
        if (array_key_exists('parent_id', $data)) $update['parent_id'] = $data['parent_id'];
        if (array_key_exists('fields', $data)) $update['fields'] = json_encode($data['fields'], JSON_UNESCAPED_UNICODE);

        if ($update !== []) {
            App::instance()->db->update('terms', $update, ['id' => $this->id]);
        }
    }

    public function delete(): void
    {
        App::instance()->db->delete('terms', ['id' => $this->id]);
    }
}
