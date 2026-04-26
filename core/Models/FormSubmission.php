<?php

declare(strict_types=1);

namespace Nano\Models;

use Nano\App;

final class FormSubmission
{
    public int $id;
    public string $formId;
    public array $data = [];
    public ?string $ip = null;
    public ?string $userAgent = null;
    public ?string $referer = null;
    public ?string $emailStatus = null;   // 'sent' | 'failed' | null
    public ?string $emailError = null;
    public ?string $sentTo = null;
    public string $createdAt;

    public static function fromRow(array $row): self
    {
        $s = new self();
        $s->id = (int) $row['id'];
        $s->formId = (string) $row['form_id'];
        $s->data = is_string($row['data'] ?? null) ? (array) (json_decode($row['data'], true) ?? []) : [];
        $s->ip = $row['ip'] ?? null;
        $s->userAgent = $row['user_agent'] ?? null;
        $s->referer = $row['referer'] ?? null;
        $s->emailStatus = $row['email_status'] ?? null;
        $s->emailError = $row['email_error'] ?? null;
        $s->sentTo = $row['sent_to'] ?? null;
        $s->createdAt = (string) $row['created_at'];
        return $s;
    }

    public static function find(int $id): ?self
    {
        $row = App::instance()->db->fetch('SELECT * FROM form_submissions WHERE id = ? LIMIT 1', [$id]);
        return $row === null ? null : self::fromRow($row);
    }

    public static function create(array $payload): self
    {
        $id = App::instance()->db->insert('form_submissions', [
            'form_id'      => $payload['form_id'],
            'data'         => json_encode($payload['data'] ?? [], JSON_UNESCAPED_UNICODE),
            'ip'           => $payload['ip'] ?? null,
            'user_agent'   => isset($payload['user_agent']) ? mb_substr((string) $payload['user_agent'], 0, 500) : null,
            'referer'      => isset($payload['referer']) ? mb_substr((string) $payload['referer'], 0, 500) : null,
            'email_status' => $payload['email_status'] ?? null,
            'email_error'  => $payload['email_error'] ?? null,
            'sent_to'      => $payload['sent_to'] ?? null,
        ]);
        return self::find($id) ?? throw new \RuntimeException('Failed to record form submission.');
    }

    public function markEmail(string $status, ?string $error = null, ?string $sentTo = null): void
    {
        App::instance()->db->update('form_submissions', [
            'email_status' => $status,
            'email_error'  => $error,
            'sent_to'      => $sentTo,
        ], ['id' => $this->id]);
        $this->emailStatus = $status;
        $this->emailError = $error;
        $this->sentTo = $sentTo;
    }

    /**
     * @return list<self>
     */
    public static function recent(string $formId, int $limit = 100, int $offset = 0): array
    {
        $rows = App::instance()->db->fetchAll(
            'SELECT * FROM form_submissions WHERE form_id = ? ORDER BY created_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            [$formId]
        );
        return array_map(fn($r) => self::fromRow($r), $rows);
    }

    public static function count(string $formId): int
    {
        return (int) App::instance()->db->fetchColumn(
            'SELECT COUNT(*) FROM form_submissions WHERE form_id = ?',
            [$formId]
        );
    }

    public function delete(): void
    {
        App::instance()->db->delete('form_submissions', ['id' => $this->id]);
    }
}
