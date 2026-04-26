<?php

declare(strict_types=1);

namespace Nano;

/**
 * Thrown by the boot sequence when the install is incomplete and we cannot
 * complete it automatically (because INITIAL_USER credentials are missing).
 *
 * The caller (`public/index.php`) catches this and renders the setup page.
 */
final class SetupException extends \RuntimeException
{
    /** @var array<string,bool|string> */
    public array $state;

    public function __construct(string $message, array $state = [])
    {
        parent::__construct($message);
        $this->state = $state;
    }
}
