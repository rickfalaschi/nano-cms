<?php

declare(strict_types=1);

namespace Nano;

final class Config
{
    private array $env;
    private array $site;
    private string $rootPath;
    private bool $themeInstalled = false;

    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
        $envFile = $rootPath . '/config/env.php';

        if (!is_file($envFile)) {
            throw new \RuntimeException("Config file not found: {$envFile}");
        }

        // env.php reads from environment variables (loaded via Nano\Env::load).
        $this->env = require $envFile;

        // The theme is a separate package — it brings its own site.json.
        // When no theme is installed yet, we boot with an empty schema so the
        // setup page can render and tell the user what to do.
        $themePath = $this->env['paths']['theme'] ?? ($rootPath . '/theme');
        $siteFile = $themePath . '/site.json';

        if (!is_file($siteFile)) {
            $this->site = [];
            $this->themeInstalled = false;
            return;
        }

        $rawSite = file_get_contents($siteFile);
        $decoded = json_decode((string) $rawSite, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid site.json: ' . json_last_error_msg());
        }

        $this->site = $decoded;
        $this->themeInstalled = true;
    }

    public function themeInstalled(): bool
    {
        return $this->themeInstalled;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->env;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function site(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->site;
        }

        $segments = explode('.', $key);
        $value = $this->site;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function pages(): array
    {
        return $this->site['pages'] ?? [];
    }

    public function page(string $key): ?array
    {
        return $this->site['pages'][$key] ?? null;
    }

    public function itemTypes(): array
    {
        return $this->site['item_types'] ?? [];
    }

    public function itemType(string $type): ?array
    {
        return $this->site['item_types'][$type] ?? null;
    }

    public function taxonomies(): array
    {
        return $this->site['taxonomies'] ?? [];
    }

    public function taxonomy(string $taxonomy): ?array
    {
        return $this->site['taxonomies'][$taxonomy] ?? null;
    }

    public function fieldGroup(string $group): ?array
    {
        return $this->site['field_groups'][$group] ?? null;
    }

    /**
     * Built-in SEO field set. Automatically attached to pages and to item
     * types with `has_page: true`. Stored in the same `fields` JSON as
     * regular content fields, but rendered as a separate block in the
     * admin so editors find them in a predictable place.
     *
     * @return list<array<string,mixed>>
     */
    public function seoFields(): array
    {
        return [
            [
                'name'  => 'meta_title',
                'type'  => 'text',
                'label' => 'Meta título',
                'help'  => 'Aparece na aba do navegador e nos resultados de busca. Se vazio, usa o título do conteúdo.',
            ],
            [
                'name'  => 'meta_description',
                'type'  => 'textarea',
                'label' => 'Meta descrição',
                'help'  => 'Resumo curto (até ~160 caracteres) usado em buscas e em previews ao compartilhar.',
            ],
            [
                'name'  => 'og_image',
                'type'  => 'image',
                'label' => 'Imagem de compartilhamento',
                'help'  => 'Mostrada quando o link é compartilhado em redes sociais e WhatsApp. Recomendado: 1200×630.',
            ],
        ];
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function path(string $key): string
    {
        return (string) $this->get("paths.{$key}");
    }

    public function resolveFields(array $fields): array
    {
        $resolved = [];
        foreach ($fields as $field) {
            if (isset($field['group'])) {
                $group = $this->fieldGroup($field['group']);
                if ($group !== null && isset($group['fields'])) {
                    foreach ($group['fields'] as $subField) {
                        $resolved[] = $subField;
                    }
                }
                continue;
            }
            $resolved[] = $field;
        }
        return $resolved;
    }
}
