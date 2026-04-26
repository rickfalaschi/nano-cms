<?php

declare(strict_types=1);

namespace Nano\Models;

use Nano\App;

final class Item
{
    public int $id;
    public string $type;
    public string $slug;
    public string $title;
    public ?string $template = null;
    public string $status = 'draft';
    public array $fields = [];
    public ?string $publishedAt = null;
    public string $createdAt;
    public string $updatedAt;

    public static function fromRow(array $row): self
    {
        $i = new self();
        $i->id = (int) $row['id'];
        $i->type = (string) $row['type'];
        $i->slug = (string) $row['slug'];
        $i->title = (string) $row['title'];
        $i->template = $row['template'] !== null ? (string) $row['template'] : null;
        $i->status = (string) $row['status'];
        $i->fields = is_string($row['fields'] ?? null) ? (array) (json_decode($row['fields'], true) ?? []) : [];
        $i->publishedAt = $row['published_at'] ?? null;
        $i->createdAt = (string) $row['created_at'];
        $i->updatedAt = (string) $row['updated_at'];
        return $i;
    }

    public static function find(int $id): ?self
    {
        $row = App::instance()->db->fetch('SELECT * FROM items WHERE id = ? LIMIT 1', [$id]);
        return $row === null ? null : self::fromRow($row);
    }

    public static function findBySlug(string $type, string $slug): ?self
    {
        $row = App::instance()->db->fetch(
            'SELECT * FROM items WHERE type = ? AND slug = ? LIMIT 1',
            [$type, $slug]
        );
        return $row === null ? null : self::fromRow($row);
    }

    /**
     * @return list<self>
     */
    public static function published(string $type, int $limit = 100): array
    {
        $rows = App::instance()->db->fetchAll(
            'SELECT * FROM items WHERE type = ? AND status = ? ORDER BY published_at DESC, created_at DESC LIMIT ' . (int) $limit,
            [$type, 'published']
        );
        return array_map(fn($r) => self::fromRow($r), $rows);
    }

    /**
     * @param array{limit?:int, offset?:int, status?:string, term?:int, order?:string} $args
     * @return list<self>
     */
    public static function query(string $type, array $args = []): array
    {
        $db = App::instance()->db;
        $limit = (int) ($args['limit'] ?? 100);
        $offset = (int) ($args['offset'] ?? 0);
        $status = (string) ($args['status'] ?? 'published');
        $order = (string) ($args['order'] ?? 'published_at DESC, created_at DESC');

        $params = [$type];
        $where = ['type = ?'];

        if ($status !== 'any') {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        if (isset($args['term'])) {
            $where[] = 'id IN (SELECT item_id FROM item_term WHERE term_id = ?)';
            $params[] = (int) $args['term'];
        }

        $sql = sprintf(
            'SELECT * FROM items WHERE %s ORDER BY %s LIMIT %d OFFSET %d',
            implode(' AND ', $where),
            $order,
            $limit,
            $offset
        );

        $rows = $db->fetchAll($sql, $params);
        return array_map(fn($r) => self::fromRow($r), $rows);
    }

    /**
     * @return list<self>
     */
    public static function byTerm(int $termId, int $limit = 100): array
    {
        $rows = App::instance()->db->fetchAll(
            'SELECT i.* FROM items i INNER JOIN item_term it ON it.item_id = i.id
             WHERE it.term_id = ? AND i.status = ?
             ORDER BY i.published_at DESC, i.created_at DESC LIMIT ' . (int) $limit,
            [$termId, 'published']
        );
        return array_map(fn($r) => self::fromRow($r), $rows);
    }

    public static function listAdmin(string $type, ?string $status = null): array
    {
        $params = [$type];
        $where = ['type = ?'];
        if ($status !== null && $status !== 'all') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $rows = App::instance()->db->fetchAll(
            'SELECT * FROM items WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC',
            $params
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
        $type = $config->itemType($this->type);
        $slug = (string) ($type['slug'] ?? $this->type);
        return App::instance()->basePath . '/' . trim($slug, '/') . '/' . $this->slug;
    }

    /**
     * @return list<Term>
     */
    public function terms(?string $taxonomy = null): array
    {
        $sql = 'SELECT t.* FROM terms t INNER JOIN item_term it ON it.term_id = t.id WHERE it.item_id = ?';
        $params = [$this->id];
        if ($taxonomy !== null) {
            $sql .= ' AND t.taxonomy = ?';
            $params[] = $taxonomy;
        }
        $sql .= ' ORDER BY t.name ASC';
        $rows = App::instance()->db->fetchAll($sql, $params);
        return array_map(fn($r) => Term::fromRow($r), $rows);
    }

    public function setTerms(string $taxonomy, array $termIds): void
    {
        $db = App::instance()->db;
        $db->transaction(function ($db) use ($taxonomy, $termIds) {
            $db->query(
                'DELETE it FROM item_term it INNER JOIN terms t ON t.id = it.term_id
                 WHERE it.item_id = ? AND t.taxonomy = ?',
                [$this->id, $taxonomy]
            );
            foreach ($termIds as $termId) {
                $db->query(
                    'INSERT IGNORE INTO item_term (item_id, term_id) VALUES (?, ?)',
                    [$this->id, (int) $termId]
                );
            }
        });
    }

    public static function create(array $data): self
    {
        $payload = [
            'type' => $data['type'],
            'slug' => $data['slug'],
            'title' => $data['title'],
            'template' => $data['template'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'fields' => json_encode($data['fields'] ?? [], JSON_UNESCAPED_UNICODE),
            'published_at' => $data['published_at'] ?? null,
        ];
        $id = App::instance()->db->insert('items', $payload);
        return self::find($id) ?? throw new \RuntimeException('Failed to create item.');
    }

    public function save(array $data): void
    {
        $update = [];
        if (array_key_exists('title', $data)) $update['title'] = $data['title'];
        if (array_key_exists('slug', $data)) $update['slug'] = $data['slug'];
        if (array_key_exists('template', $data)) $update['template'] = $data['template'];
        if (array_key_exists('status', $data)) $update['status'] = $data['status'];
        if (array_key_exists('fields', $data)) $update['fields'] = json_encode($data['fields'], JSON_UNESCAPED_UNICODE);
        if (array_key_exists('published_at', $data)) $update['published_at'] = $data['published_at'];

        if ($update !== []) {
            App::instance()->db->update('items', $update, ['id' => $this->id]);
        }
    }

    public function delete(): void
    {
        App::instance()->db->delete('items', ['id' => $this->id]);
    }
}
