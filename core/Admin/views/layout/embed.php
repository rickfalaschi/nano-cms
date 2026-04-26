<?php
/**
 * Minimal layout for embedded admin pages (picker iframe, etc).
 * No sidebar, no chrome, just the content.
 *
 * @var string $_content
 * @var \Nano\Config $config
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mídia — Picker</title>
    <link rel="stylesheet" href="<?= e(admin_url('__static/css/admin.css')) ?>">
</head>
<body class="embed">
    <main class="embed__main">
        <?= $_content ?>
    </main>
    <script type="module" src="<?= e(admin_url('__static/js/admin.js')) ?>"></script>
</body>
</html>
