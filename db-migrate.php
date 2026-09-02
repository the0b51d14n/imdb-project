#!/usr/bin/env php
<?php
// ══════════════════════════════════════════════════════════════════════════════
//  db-migrate.php — Supinfo.TV
//  Applique les scripts SQL du projet sur la base configurée dans .env.
//
//  Utile face à une base MySQL 8 managée (Clever Cloud, Aiven…) quand le client
//  `mysql` local est un client MariaDB : celui-ci ne sait pas s'authentifier en
//  caching_sha2_password et refuse la connexion. PDO/mysqlnd, lui, la gère.
//
//  Usage :
//    php db-migrate.php              # applique les scripts
//    php db-migrate.php --dry-run    # analyse les fichiers sans se connecter
//
//  La configuration est lue depuis .env, ou depuis les variables
//  d'environnement si elles sont déjà définies :
//    DB_HOST=xxx DB_USER=xxx DB_PASS=xxx DB_NAME=xxx php db-migrate.php
// ══════════════════════════════════════════════════════════════════════════════

$dryRun = in_array('--dry-run', $argv ?? [], true);

$files = [
    __DIR__ . '/backend/Database.sql',
    __DIR__ . '/backend/Database_patch.sql',
    __DIR__ . '/backend/Database_sessions.sql',
];

// ── Découpage d'un script SQL en requêtes ─────────────────────────────────────
// Respecte les chaînes ('…', "…") et les identifiants (`…`) pour ne pas couper
// sur un point-virgule qui se trouverait à l'intérieur.
function sql_split(string $sql): array
{
    $statements = [];
    $current    = '';
    $quote      = null;
    $len        = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];

        if ($quote !== null) {
            $current .= $char;
            if ($char === '\\' && $i + 1 < $len) {      // séquence échappée
                $current .= $sql[++$i];
            } elseif ($char === $quote) {
                $quote = null;
            }
            continue;
        }

        // Commentaire « -- » jusqu'à la fin de ligne
        if ($char === '-' && substr($sql, $i, 2) === '--') {
            $eol = strpos($sql, "\n", $i);
            if ($eol === false) break;
            $i = $eol;
            $current .= "\n";
            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $current .= $char;
            continue;
        }

        if ($char === ';') {
            if (trim($current) !== '') $statements[] = trim($current);
            $current = '';
            continue;
        }

        $current .= $char;
    }

    if (trim($current) !== '') $statements[] = trim($current);

    return $statements;
}

// ── Analyse des fichiers ──────────────────────────────────────────────────────
$plan  = [];
$total = 0;

foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "✗ Fichier introuvable : $file\n");
        exit(1);
    }

    $statements   = sql_split((string)file_get_contents($file));
    $plan[$file]  = $statements;
    $total       += count($statements);

    printf("  %-40s %2d requête(s)\n", basename($file), count($statements));
}

echo "\n$total requête(s) au total.\n";

if ($dryRun) {
    echo "Mode --dry-run : aucune connexion, aucune écriture.\n";
    exit(0);
}

// ── Connexion ─────────────────────────────────────────────────────────────────
require_once __DIR__ . '/backend/config/database.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$name = getenv('DB_NAME') ?: 'supinfotv';

echo "\nConnexion à {$name} sur {$host}…\n";

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, "✗ " . $e->getMessage() . "\n");
    fwrite(STDERR, "  Vérifiez DB_HOST, DB_PORT, DB_NAME, DB_USER et DB_PASS.\n");
    exit(1);
}

// ── Exécution ─────────────────────────────────────────────────────────────────
$applied = 0;

foreach ($plan as $file => $statements) {
    echo "\n" . basename($file) . "\n";

    foreach ($statements as $statement) {
        // Première ligne utile de la requête, pour l'affichage
        $label = preg_replace('/\s+/', ' ', substr($statement, 0, 60));

        try {
            $pdo->exec($statement);
            $applied++;
            echo "  ✓ {$label}…\n";

        } catch (PDOException $e) {
            fwrite(STDERR, "  ✗ {$label}…\n     " . $e->getMessage() . "\n");
            exit(1);
        }
    }
}

echo "\n✓ {$applied} requête(s) appliquée(s).\n";

// ── Vérification ──────────────────────────────────────────────────────────────
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "Tables présentes (" . count($tables) . ") : " . implode(', ', $tables) . "\n";

foreach (['users', 'sessions', 'tmdb_cache'] as $required) {
    if (!in_array($required, $tables, true)) {
        fwrite(STDERR, "⚠ Table manquante : {$required}\n");
    }
}
