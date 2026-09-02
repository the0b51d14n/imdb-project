<?php
// ══════════════════════════════════════════════════════════════════════════════
//  frontend/pages/404.php — Supinfo.TV
//  Page d'erreur 404 personnalisée avec suggestions de navigation.
//  À inclure depuis nginx : error_page 404 /pages/404.php;
// ══════════════════════════════════════════════════════════════════════════════

http_response_code(404);

require_once __DIR__ . '/../../backend/services/session.php';
app_session_start();
require_once __DIR__ . '/../../backend/services/auth.php';
auth_start_session();

$basePath   = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
$pageTitle  = 'Page introuvable';
$pageCSS    = null;
$pageDesc   = 'La page que vous cherchez n\'existe pas sur Supinfo.TV.';
$activePage = '';

// Suggestions basées sur l'URL demandée
$requestedUri = $_SERVER['REQUEST_URI'] ?? '/';
$suggestions  = [
    ['href' => $basePath . '/index.php',                  'icon' => '🏠', 'label' => 'Accueil'],
    ['href' => $basePath . '/backend/pages/movies.php',   'icon' => '🎬', 'label' => 'Catalogue films'],
    ['href' => $basePath . '/backend/pages/search.php',   'icon' => '🔍', 'label' => 'Recherche'],
];

if (auth_check()) {
    $suggestions[] = ['href' => $basePath . '/backend/pages/profile.php', 'icon' => '👤', 'label' => 'Mon profil'];
    $suggestions[] = ['href' => $basePath . '/backend/pages/cart.php',    'icon' => '🛒', 'label' => 'Mon panier'];
}

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';
?>

<style>
.error-page {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 80px 24px;
}
.error-inner {
    text-align: center;
    max-width: 580px;
}
.error-code {
    font-size: clamp(80px, 15vw, 160px);
    font-weight: 500;
    line-height: 1;
    letter-spacing: -0.04em;
    background: linear-gradient(135deg, var(--deep) 0%, var(--accent) 50%, var(--gold) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 8px;
    position: relative;
    animation: error-flicker 4s ease-in-out infinite;
}
.error-code::after {
    content: '404';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, transparent 0%, rgba(87,204,153,0.15) 50%, transparent 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: error-scan 3s linear infinite;
}
@keyframes error-flicker {
    0%, 95%, 100% { opacity: 1; }
    96% { opacity: 0.85; }
    97% { opacity: 1; }
    98% { opacity: 0.9; }
}
@keyframes error-scan {
    from { background-position: 0% 0%; }
    to   { background-position: 0% 200%; }
}
.error-glitch-bar {
    width: 100%;
    height: 1px;
    background: linear-gradient(to right, transparent, var(--accent), transparent);
    margin: 0 auto 32px;
    opacity: 0.5;
    animation: error-bar-pulse 2s ease-in-out infinite;
}
@keyframes error-bar-pulse {
    0%, 100% { transform: scaleX(0.6); opacity: 0.3; }
    50%       { transform: scaleX(1);   opacity: 0.6; }
}
.error-title {
    font-size: clamp(20px, 3vw, 28px);
    font-weight: 500;
    color: var(--text);
    letter-spacing: -0.02em;
    margin-bottom: 12px;
}
.error-desc {
    font-size: 14px;
    color: var(--text-muted);
    line-height: 1.75;
    margin-bottom: 40px;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}
.error-suggestions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    max-width: 480px;
    margin: 0 auto 32px;
}
.error-suggestion-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 11px 14px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: 13px;
    color: var(--text-muted);
    text-decoration: none;
    transition:
        color var(--transition),
        border-color var(--transition),
        background var(--transition),
        transform var(--transition-snap);
    text-align: left;
}
.error-suggestion-link:hover {
    color: var(--text);
    border-color: var(--accent);
    background: var(--accent-subtle);
    transform: translateY(-2px);
}
.error-suggestion-link .error-icon {
    font-size: 18px;
    flex-shrink: 0;
}
.error-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--text-faint);
    cursor: pointer;
    background: none;
    border: none;
    font-family: var(--font);
    transition: color var(--transition);
    padding: 0;
}
.error-back:hover { color: var(--text-muted); }
.error-static {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: -1;
    opacity: 0.015;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='300' height='300' filter='url(%23n)' opacity='1'/%3E%3C/svg%3E");
    animation: static-flicker 0.1s steps(1) infinite;
}
@keyframes static-flicker {
    0%   { background-position: 0 0; }
    100% { background-position: 100px 100px; }
}
</style>

<main>
<div class="error-static"></div>
<div class="error-page">
  <div class="error-inner">

    <div class="error-code">404</div>
    <div class="error-glitch-bar"></div>

    <h1 class="error-title">Page introuvable</h1>
    <p class="error-desc">
      Le film que vous cherchez a disparu de notre catalogue,
      ou cette URL n'a jamais existé. Essayez une de ces destinations :
    </p>

    <div class="error-suggestions">
      <?php foreach ($suggestions as $s): ?>
      <a href="<?= htmlspecialchars($s['href']) ?>" class="error-suggestion-link">
        <span class="error-icon"><?= $s['icon'] ?></span>
        <?= htmlspecialchars($s['label']) ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if (isset($_SERVER['HTTP_REFERER'])): ?>
    <button class="error-back" onclick="history.back()">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M19 12H5M12 19l-7-7 7-7"/>
      </svg>
      Revenir en arrière
    </button>
    <?php endif; ?>

  </div>
</div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>