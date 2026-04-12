<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/partials/head.php — Supinfo.TV
//  Alias vers frontend/partials/head.php.
//  Les pages backend utilisent les mêmes assets CSS/JS que le frontend.
// ══════════════════════════════════════════════════════════════════════════════

// Recalculer basePath pour pointer vers le frontend
if (!isset($basePath)) {
    // backend/pages/ → dirname x1 = backend/ → dirname x2 = racine/
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if (str_ends_with($scriptDir, '/pages')) {
        $basePath = dirname($scriptDir);
    } else {
        $basePath = $scriptDir;
    }
}

include __DIR__ . '/../../frontend/partials/head.php';
