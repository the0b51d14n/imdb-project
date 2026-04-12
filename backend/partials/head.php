<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/partials/head.php — Supinfo.TV
//  Correction : basePath recalculé depuis backend/pages/ pour pointer
//  vers la racine du projet (pas /backend).
// ══════════════════════════════════════════════════════════════════════════════

if (!isset($basePath)) {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    // backend/pages/ → dirname x1 = /backend → dirname x2 = racine
    if (str_ends_with($scriptDir, '/pages')) {
        $basePath = dirname(dirname($scriptDir));
    } else {
        $basePath = dirname($scriptDir);
    }
}

include __DIR__ . '/../../frontend/partials/head.php';