<?php

declare(strict_types=1);

namespace Nano\Admin\Controllers;

use Nano\Models\Item;
use Nano\Request;
use Nano\Response;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) {
            return $auth;
        }

        $itemTypes = $this->app->config->itemTypes();
        $stats = [];
        foreach ($itemTypes as $type => $def) {
            $count = (int) $this->app->db->fetchColumn(
                'SELECT COUNT(*) FROM items WHERE type = ?',
                [$type]
            );
            $stats[(string) $type] = [
                'label' => (string) ($def['label'] ?? $type),
                'count' => $count,
            ];
        }

        return $this->render('pages/dashboard', [
            'stats' => $stats,
            'pages' => $this->app->config->pages(),
            'taxonomies' => $this->app->config->taxonomies(),
        ]);
    }
}
