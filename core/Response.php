<?php

declare(strict_types=1);

namespace Nano;

final class Response
{
    private int $status = 200;
    private array $headers = [];
    private string $body = '';

    public function status(int $code): Response
    {
        $this->status = $code;
        return $this;
    }

    public function header(string $name, string $value): Response
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function body(string $content): Response
    {
        $this->body = $content;
        return $this;
    }

    public static function html(string $body, int $status = 200): Response
    {
        return (new self())
            ->status($status)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->body($body);
    }

    public static function json(mixed $data, int $status = 200): Response
    {
        return (new self())
            ->status($status)
            ->header('Content-Type', 'application/json; charset=UTF-8')
            ->body((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function redirect(string $url, int $status = 302): Response
    {
        return (new self())
            ->status($status)
            ->header('Location', $url);
    }

    public static function notFound(string $message = 'Not Found'): Response
    {
        return self::html('<h1>404</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>', 404);
    }

    public static function serverError(string $message = 'Server Error'): Response
    {
        return self::html('<h1>500</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>', 500);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
