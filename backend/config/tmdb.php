<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/config/tmdb.php — Supinfo.TV
//  Alias vers frontend/config/tmdb.php pour éviter la duplication.
//  Toute la logique TMDB (constantes, calcul/vérif prix HMAC) est centralisée
//  dans frontend/config/tmdb.php qui est le fichier de référence.
// ══════════════════════════════════════════════════════════════════════════════

$_frontendTmdb = dirname(__DIR__, 2) . '/frontend/config/tmdb.php';

if (file_exists($_frontendTmdb)) {
    require_once $_frontendTmdb;
} else {
    // Fallback si la structure de dossiers diffère
    require_once __DIR__ . '/../../frontend/config/tmdb.php';
}

unset($_frontendTmdb);
