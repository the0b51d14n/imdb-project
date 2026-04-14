#!/usr/bin/env php
<?php
// ══════════════════════════════════════════════════════════════════════════════
//  build.php — Supinfo.TV
//  Script de build : concatène et minifie CSS + JS pour la production.
//  Usage : php build.php [--watch] [--verbose]
//
//  Sortie :
//    frontend/assets/dist/app.min.css
//    frontend/assets/dist/app.min.js
//    frontend/assets/dist/components.min.js
// ══════════════════════════════════════════════════════════════════════════════

define('ROOT',     dirname(__FILE__));
define('FRONTEND', ROOT . '/frontend');
define('DIST',     FRONTEND . '/assets/dist');

$watch   = in_array('--watch',   $argv ?? []);
$verbose = in_array('--verbose', $argv ?? []);

// ── Création du répertoire dist ───────────────────────────────────────────────
if (!is_dir(DIST)) {
    mkdir(DIST, 0755, true);
}

// ── Ordre de concaténation CSS ────────────────────────────────────────────────
$cssFiles = [
    // Base
    FRONTEND . '/assets/css/base/variables.css',
    FRONTEND . '/assets/css/base/reset.css',
    FRONTEND . '/assets/css/base/layout.css',
    FRONTEND . '/assets/css/base/typography.css',
    // Composants
    FRONTEND . '/assets/css/components/navbar.css',
    FRONTEND . '/assets/css/components/loader.css',
    FRONTEND . '/assets/css/components/movie-card.css',
    FRONTEND . '/assets/css/components/buttons.css',
    FRONTEND . '/assets/css/components/forms.css',
    FRONTEND . '/assets/css/components/logout-button.css',
    FRONTEND . '/assets/css/components/order-button.css',
    FRONTEND . '/assets/css/components/footer.css',
    FRONTEND . '/assets/css/components/advanced-filters.css',
    FRONTEND . '/assets/css/components/watchlist.css',
];

// ── Ordre de concaténation JS (composants réutilisables) ─────────────────────
$jsComponents = [
    FRONTEND . '/assets/js/app.js',
    FRONTEND . '/assets/js/components/loader.js',
    FRONTEND . '/assets/js/components/navbar.js',
    FRONTEND . '/assets/js/components/logout-button.js',
    FRONTEND . '/assets/js/components/movie-card.js',
    FRONTEND . '/assets/js/components/order-button.js',
    FRONTEND . '/assets/js/components/blur-up.js',
    FRONTEND . '/assets/js/components/search-autocomplete.js',
    FRONTEND . '/assets/js/components/cart-ajax.js',
    FRONTEND . '/assets/js/components/watchlist-button.js',
    FRONTEND . '/assets/js/components/rating-widget.js',
];

function minify_css(string $css): string
{
    // Supprimer les commentaires CSS
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    // Supprimer les espaces autour de la ponctuation
    $css = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $css);
    $css = preg_replace('/\s+/', ' ', $css);
    $css = str_replace([' {', '{ ', ' }', '} ', ': ', ' :', '; ', ' ;', ', ', ' ,'], ['{', '{', '}', '}', ':', ':', ';', ';', ',', ','], $css);
    // Supprimer les derniers ";" avant "}"
    $css = str_replace(';}', '}', $css);
    return trim($css);
}

function minify_js(string $js): string
{
    // Minification basique : supprimer les commentaires de ligne et espaces superflus
    // Note : pour une vraie production, utiliser UglifyJS ou Terser
    $js = preg_replace('!/\*.*?\*/!s', '', $js);         // Commentaires /* */
    $js = preg_replace('/^\s*\/\/[^\n]*$/m', '', $js);    // Commentaires //
    $js = preg_replace('/^\s+$/m', '', $js);               // Lignes vides
    $js = preg_replace('/\n{2,}/', "\n", $js);             // Lignes multiples
    return trim($js);
}

function build(array $cssFiles, array $jsComponents, bool $verbose): array
{
    $startTime = microtime(true);
    $stats     = ['css_in' => 0, 'css_out' => 0, 'js_in' => 0, 'js_out' => 0, 'errors' => []];

    // ── Build CSS ─────────────────────────────────────────────────────────────
    $cssOut = "/* Supinfo.TV — Built " . date('Y-m-d H:i:s') . " */\n";
    foreach ($cssFiles as $file) {
        if (!file_exists($file)) {
            $stats['errors'][] = "CSS manquant : $file";
            continue;
        }
        $content = file_get_contents($file);
        // Ignorer les @import (déjà gérés par la concaténation)
        $content = preg_replace('/@import\s+[\'"][^\'"]+[\'"]\s*;/', '', $content);
        $stats['css_in'] += strlen($content);
        $cssOut .= $content . "\n";
        if ($verbose) echo "  CSS: " . basename($file) . "\n";
    }

    $cssMinified = minify_css($cssOut);
    $stats['css_out'] = strlen($cssMinified);
    file_put_contents(DIST . '/app.min.css', $cssMinified);

    // ── Build JS composants ───────────────────────────────────────────────────
    $jsOut = "/* Supinfo.TV — Built " . date('Y-m-d H:i:s') . " */\n";
    foreach ($jsComponents as $file) {
        if (!file_exists($file)) {
            $stats['errors'][] = "JS manquant : $file";
            continue;
        }
        $content = file_get_contents($file);
        $stats['js_in'] += strlen($content);
        $jsOut .= "\n/* === " . basename($file) . " === */\n" . $content . "\n";
        if ($verbose) echo "  JS: " . basename($file) . "\n";
    }

    $jsMinified = minify_js($jsOut);
    $stats['js_out'] = strlen($jsMinified);
    file_put_contents(DIST . '/components.min.js', $jsMinified);

    // ── Manifeste de build ────────────────────────────────────────────────────
    $manifest = [
        'built_at'      => date('c'),
        'css_files'     => count($cssFiles),
        'js_files'      => count($jsComponents),
        'css_reduction' => $stats['css_in'] > 0 ? round((1 - $stats['css_out'] / $stats['css_in']) * 100, 1) : 0,
        'js_reduction'  => $stats['js_in'] > 0  ? round((1 - $stats['js_out']  / $stats['js_in'])  * 100, 1) : 0,
        'duration_ms'   => round((microtime(true) - $startTime) * 1000, 1),
        'errors'        => $stats['errors'],
    ];
    file_put_contents(DIST . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return array_merge($stats, $manifest);
}

// ── Exécution ─────────────────────────────────────────────────────────────────
echo "\n🔨 Supinfo.TV Build\n";
echo str_repeat('─', 40) . "\n";

do {
    if ($verbose) echo "\nFichiers CSS :\n";
    $result = build($cssFiles, $jsComponents, $verbose);

    echo "\n✅ Build terminé en {$result['duration_ms']}ms\n";
    echo "   CSS : " . number_format($result['css_in'] / 1024, 1) . "KB → " . number_format($result['css_out'] / 1024, 1) . "KB (-{$result['css_reduction']}%)\n";
    echo "   JS  : " . number_format($result['js_in'] / 1024, 1) . "KB → " . number_format($result['js_out'] / 1024, 1) . "KB (-{$result['js_reduction']}%)\n";
    echo "   Dist: " . DIST . "/\n";

    if (!empty($result['errors'])) {
        echo "\n⚠️  Avertissements :\n";
        foreach ($result['errors'] as $err) {
            echo "   - $err\n";
        }
    }

    if ($watch) {
        echo "\n👁  Mode watch actif — Ctrl+C pour arrêter\n";
        sleep(3);
    }

} while ($watch);

echo "\n";
exit(empty($result['errors']) ? 0 : 1);
