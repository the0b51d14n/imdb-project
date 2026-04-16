<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/pages/profile.php — Supinfo.TV v3
//  Page profil complète façon service VOD premium
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/orders.php';
require_once __DIR__ . '/../services/csrf.php';

auth_start_session();

$basePath = '';
if (preg_match('#^(.+?)/backend/pages/[^/]+$#', str_replace('\\', '/', $_SERVER['SCRIPT_NAME']), $m)) {
    $basePath = rtrim($m[1], '/');
}

if (!auth_check()) {
    header('Location: ' . $basePath . '/pages/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId   = auth_id();
$userName = $_SESSION[SESSION_USER_NAME]  ?? 'Utilisateur';
$userMail = $_SESSION[SESSION_USER_EMAIL] ?? '';
$verified = $_SESSION[SESSION_USER_VERIFIED] ?? false;

$orderHistory    = orders_get_history();
$purchasedMovies = orders_get_purchased_movies();
$orderSuccess    = isset($_GET['order']) && $_GET['order'] === 'success';

$pwdError   = null;
$pwdSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    csrf_verify();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new !== $confirm) {
        $pwdError = "Les nouveaux mots de passe ne correspondent pas.";
    } else {
        $r = auth_change_password($userId, $current, $new);
        if ($r['ok']) { $pwdSuccess = true; }
        else { $pwdError = $r['error']; }
    }
}

$pageTitle  = 'Mon compte';
$pageCSS    = 'pages/profile.css';
$pageDesc   = 'Gérez votre compte Supinfo.TV.';
$activePage = 'profile';

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';

// Avatar initials
$initials = mb_strtoupper(mb_substr($userName, 0, 1));
$totalSpent = array_sum(array_column($orderHistory, 'total_amount'));
$memberSince = !empty($orderHistory) ? date('Y', strtotime(end($orderHistory)['created_at'])) : date('Y');
?>

<style>
/* ══════════════════════════════════════════════════════════
   PROFILE PAGE — Supinfo.TV v3
   Design: dark VOD premium, sidebar navigation
   ══════════════════════════════════════════════════════════ */

.profile-root {
    min-height: 100vh;
    padding-top: var(--navbar-h);
    background: var(--bg);
}

/* ── Hero bannière compte ────────────────────────────────── */
.profile-hero {
    background: linear-gradient(135deg,
        #081820 0%,
        #0d2535 30%,
        #122f42 60%,
        #081820 100%);
    border-bottom: 1px solid var(--border);
    padding: 40px 0 0;
    position: relative;
    overflow: hidden;
}

.profile-hero::before {
    content: '';
    position: absolute;
    top: -60px;
    right: -60px;
    width: 400px;
    height: 400px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(87,204,153,0.07) 0%, transparent 70%);
    pointer-events: none;
}

.profile-hero::after {
    content: '';
    position: absolute;
    bottom: -80px;
    left: 20%;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(34,87,122,0.15) 0%, transparent 70%);
    pointer-events: none;
}

.profile-hero-inner {
    max-width: var(--container);
    margin: 0 auto;
    padding: 0 40px;
    position: relative;
    z-index: 1;
}

.profile-identity {
    display: flex;
    align-items: flex-end;
    gap: 28px;
    padding-bottom: 24px;
}

.profile-avatar-wrap {
    position: relative;
    flex-shrink: 0;
}

.profile-avatar {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    background: linear-gradient(135deg, #22577a 0%, #57cc99 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 34px;
    font-weight: 500;
    color: #fff;
    border: 3px solid var(--border-bright);
    box-shadow: 0 0 0 6px rgba(87,204,153,0.08), 0 8px 32px rgba(0,0,0,0.4);
    letter-spacing: -0.02em;
}

.profile-avatar-status {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--accent);
    border: 3px solid var(--bg);
    box-shadow: 0 0 8px var(--accent-glow);
}

.profile-identity-info {
    flex: 1;
    padding-bottom: 4px;
}

.profile-name-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 6px;
}

.profile-name {
    font-size: clamp(22px, 3vw, 30px);
    font-weight: 500;
    color: var(--text);
    letter-spacing: -0.02em;
    line-height: 1;
}

.profile-plan-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    background: linear-gradient(90deg, rgba(128,237,153,0.15), rgba(87,204,153,0.15));
    border: 1px solid rgba(87,204,153,0.3);
    color: var(--gold);
}

.profile-email-row {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: var(--text-muted);
    flex-wrap: wrap;
}

.profile-verified-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 99px;
    background: rgba(87,204,153,0.1);
    border: 1px solid rgba(87,204,153,0.25);
    color: var(--accent);
}

.profile-unverified-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 99px;
    background: rgba(224,90,106,0.08);
    border: 1px solid rgba(224,90,106,0.25);
    color: var(--danger);
    text-decoration: none;
    transition: background var(--transition);
}

.profile-unverified-chip:hover {
    background: rgba(224,90,106,0.15);
}

/* ── Stats rapides ────────────────────────────────────────── */
.profile-quick-stats {
    display: flex;
    border-top: 1px solid var(--border);
    margin-top: 8px;
}

.profile-stat {
    flex: 1;
    padding: 16px 20px;
    text-align: center;
    border-right: 1px solid var(--border);
    transition: background var(--transition);
    cursor: default;
}

.profile-stat:last-child {
    border-right: none;
}

.profile-stat:hover {
    background: rgba(87,204,153,0.04);
}

.profile-stat-value {
    font-size: 22px;
    font-weight: 500;
    color: var(--text);
    letter-spacing: -0.02em;
    line-height: 1;
    margin-bottom: 4px;
}

.profile-stat-value.accent { color: var(--gold); }
.profile-stat-label {
    font-size: 11px;
    color: var(--text-faint);
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

/* ── Layout principal ─────────────────────────────────────── */
.profile-body {
    max-width: var(--container);
    margin: 0 auto;
    padding: 32px 40px 80px;
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 32px;
    align-items: start;
}

/* ── Sidebar nav ──────────────────────────────────────────── */
.profile-sidenav {
    position: sticky;
    top: calc(var(--navbar-h) + 24px);
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.profile-sidenav-section {
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--text-faint);
    padding: 16px 12px 6px;
}

.profile-sidenav-section:first-child {
    padding-top: 0;
}

.profile-nav-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    border-radius: var(--radius);
    font-size: 13px;
    color: var(--text-muted);
    cursor: pointer;
    background: none;
    border: none;
    font-family: var(--font);
    width: 100%;
    text-align: left;
    text-decoration: none;
    transition: background var(--transition), color var(--transition);
    position: relative;
}

.profile-nav-link:hover {
    background: var(--surface-2);
    color: var(--text);
}

.profile-nav-link.active {
    background: var(--accent-subtle);
    color: var(--accent);
}

.profile-nav-link.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 20%;
    bottom: 20%;
    width: 2px;
    background: var(--accent);
    border-radius: 0 2px 2px 0;
}

.profile-nav-link svg {
    flex-shrink: 0;
    opacity: 0.7;
}

.profile-nav-link.active svg {
    opacity: 1;
}

.profile-nav-badge {
    margin-left: auto;
    background: var(--accent);
    color: #fff;
    font-size: 10px;
    font-weight: 600;
    padding: 1px 6px;
    border-radius: 99px;
    line-height: 16px;
}

.profile-nav-danger {
    color: var(--text-faint);
}

.profile-nav-danger:hover {
    color: var(--danger);
    background: rgba(224,90,106,0.08);
}

/* ── Sections de contenu ──────────────────────────────────── */
.profile-section {
    display: none;
    animation: section-in 0.25s var(--ease-out-expo);
}

.profile-section.active {
    display: block;
}

@keyframes section-in {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}

.profile-section-title {
    font-size: 20px;
    font-weight: 500;
    color: var(--text);
    letter-spacing: -0.01em;
    margin-bottom: 4px;
}

.profile-section-sub {
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: 28px;
    line-height: 1.6;
}

/* ── Cards de section ─────────────────────────────────────── */
.pcard {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 24px;
    margin-bottom: 16px;
}

.pcard-title {
    font-size: 14px;
    font-weight: 500;
    color: var(--text);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.pcard-title svg {
    color: var(--accent);
    flex-shrink: 0;
}

/* ── Champs formulaire profil ─────────────────────────────── */
.pfield {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 0;
    border-bottom: 1px solid var(--border-subtle);
    gap: 16px;
}

.pfield:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.pfield:first-child {
    padding-top: 0;
}

.pfield-label {
    font-size: 12px;
    color: var(--text-faint);
    letter-spacing: 0.04em;
    text-transform: uppercase;
    flex-shrink: 0;
    width: 120px;
}

.pfield-value {
    font-size: 14px;
    color: var(--text);
    flex: 1;
}

.pfield-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: var(--accent);
    cursor: pointer;
    background: none;
    border: none;
    font-family: var(--font);
    padding: 4px 8px;
    border-radius: var(--radius-sm);
    transition: background var(--transition);
    flex-shrink: 0;
}

.pfield-action:hover {
    background: var(--accent-subtle);
}

/* ── Inputs ───────────────────────────────────────────────── */
.p-input {
    width: 100%;
    padding: 11px 14px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    color: var(--text);
    font-family: var(--font);
    font-size: 13px;
    outline: none;
    transition: border-color var(--transition), box-shadow var(--transition);
    box-sizing: border-box;
}

.p-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-subtle);
}

.p-input-label {
    display: block;
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 6px;
}

.p-input-group {
    margin-bottom: 14px;
}

/* ── Alertes ─────────────────────────────────────────────── */
.p-alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 16px;
    border-radius: var(--radius);
    font-size: 13px;
    margin-bottom: 16px;
    line-height: 1.6;
}

.p-alert-success {
    background: rgba(87,204,153,0.1);
    border: 1px solid rgba(87,204,153,0.3);
    color: var(--accent);
}

.p-alert-error {
    background: rgba(224,90,106,0.08);
    border: 1px solid var(--danger);
    color: var(--danger);
}

.p-alert-info {
    background: rgba(34,87,122,0.2);
    border: 1px solid var(--border-bright);
    color: var(--text-muted);
}

/* ── Films achetés ────────────────────────────────────────── */
.library-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 14px;
}

.library-movie {
    position: relative;
    border-radius: var(--radius);
    overflow: hidden;
    border: 1px solid var(--border);
    aspect-ratio: 2/3;
    background: var(--surface-2);
    transition: transform var(--transition-slow), border-color var(--transition);
    cursor: pointer;
}

.library-movie:hover {
    transform: translateY(-4px) scale(1.02);
    border-color: var(--border-bright);
}

.library-movie img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.library-movie-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(8,24,32,0.95) 0%, transparent 60%);
    opacity: 0;
    transition: opacity var(--transition);
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 10px;
}

.library-movie:hover .library-movie-overlay {
    opacity: 1;
}

.library-movie-title {
    font-size: 11px;
    font-weight: 500;
    color: var(--text);
    line-height: 1.3;
    margin-bottom: 6px;
}

.library-movie-play {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
    font-weight: 500;
    color: var(--accent);
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.library-owned-tag {
    position: absolute;
    top: 6px;
    left: 6px;
    background: rgba(87,204,153,0.9);
    color: #fff;
    font-size: 9px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 3px;
    letter-spacing: 0.05em;
    backdrop-filter: blur(4px);
}

/* ── Commandes ────────────────────────────────────────────── */
.order-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 12px;
    transition: border-color var(--transition);
}

.order-card:hover {
    border-color: var(--border-bright);
}

.order-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-subtle);
    gap: 12px;
    flex-wrap: wrap;
}

.order-id {
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-faint);
}

.order-date {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 2px;
}

.order-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 500;
    background: rgba(87,204,153,0.1);
    border: 1px solid rgba(87,204,153,0.25);
    color: var(--accent);
}

.order-total {
    font-size: 18px;
    font-weight: 500;
    color: var(--gold);
    letter-spacing: -0.02em;
}

.order-items-list {
    padding: 14px 20px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.order-item-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    background: var(--surface-2);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius);
    text-decoration: none;
    transition: border-color var(--transition), background var(--transition);
    max-width: 220px;
}

.order-item-chip:hover {
    border-color: var(--accent);
    background: var(--accent-subtle);
}

.order-item-poster {
    width: 30px;
    height: 45px;
    border-radius: 3px;
    object-fit: cover;
    flex-shrink: 0;
    background: var(--surface-3);
}

.order-item-info {
    flex: 1;
    min-width: 0;
}

.order-item-name {
    font-size: 12px;
    font-weight: 500;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.order-item-price {
    font-size: 11px;
    color: var(--gold);
}

/* ── Sécurité ─────────────────────────────────────────────── */
.security-meter {
    height: 6px;
    background: var(--surface-2);
    border-radius: 99px;
    overflow: hidden;
    margin-top: 8px;
}

.security-meter-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--danger) 0%, var(--gold) 50%, var(--accent) 100%);
    border-radius: 99px;
    transition: width 0.8s var(--ease-out-expo);
}

.security-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-subtle);
}

.security-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.security-check {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.security-check.ok {
    background: rgba(87,204,153,0.15);
    color: var(--accent);
}

.security-check.warn {
    background: rgba(224,90,106,0.1);
    color: var(--danger);
}

.security-item-label {
    font-size: 13px;
    color: var(--text);
    flex: 1;
}

.security-item-sub {
    font-size: 11px;
    color: var(--text-faint);
    margin-top: 2px;
}

/* ── Préférences toggles ──────────────────────────────────── */
.pref-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 0;
    border-bottom: 1px solid var(--border-subtle);
    gap: 20px;
}

.pref-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.pref-row:first-child {
    padding-top: 0;
}

.pref-label {
    font-size: 14px;
    color: var(--text);
}

.pref-sub {
    font-size: 12px;
    color: var(--text-faint);
    margin-top: 2px;
}

/* Toggle switch */
.p-toggle {
    position: relative;
    width: 42px;
    height: 24px;
    flex-shrink: 0;
}

.p-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}

.p-toggle-track {
    position: absolute;
    inset: 0;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 99px;
    cursor: pointer;
    transition: background var(--transition), border-color var(--transition);
}

.p-toggle input:checked + .p-toggle-track {
    background: var(--accent);
    border-color: var(--accent);
}

.p-toggle-track::after {
    content: '';
    position: absolute;
    left: 3px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--text-faint);
    transition: transform 0.2s var(--ease-spring), background 0.2s;
}

.p-toggle input:checked + .p-toggle-track::after {
    transform: translateY(-50%) translateX(18px);
    background: #fff;
}

/* ── Danger zone ──────────────────────────────────────────── */
.danger-zone {
    border: 1px solid rgba(224,90,106,0.25);
    border-radius: var(--radius-lg);
    padding: 20px;
    background: rgba(224,90,106,0.04);
}

.danger-zone-title {
    font-size: 13px;
    font-weight: 500;
    color: var(--danger);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-danger {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: var(--radius);
    border: 1px solid rgba(224,90,106,0.4);
    background: rgba(224,90,106,0.08);
    color: var(--danger);
    font-family: var(--font);
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0.05em;
    cursor: pointer;
    transition: all var(--transition);
    text-decoration: none;
}

.btn-danger:hover {
    background: rgba(224,90,106,0.15);
    border-color: var(--danger);
}

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 900px) {
    .profile-body {
        grid-template-columns: 1fr;
        padding: 24px 20px 60px;
        gap: 0;
    }

    .profile-sidenav {
        position: static;
        flex-direction: row;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--border);
        padding-bottom: 16px;
        margin-bottom: 24px;
        gap: 4px;
    }

    .profile-sidenav-section {
        display: none;
    }

    .profile-hero-inner {
        padding: 0 20px;
    }

    .profile-stat {
        padding: 12px 12px;
    }

    .profile-identity {
        gap: 16px;
    }

    .profile-avatar {
        width: 72px;
        height: 72px;
        font-size: 26px;
    }

    .library-grid {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 10px;
    }
}

@media (max-width: 600px) {
    .profile-quick-stats {
        flex-wrap: wrap;
    }

    .profile-stat {
        min-width: 50%;
        border-bottom: 1px solid var(--border);
    }

    .profile-stat:nth-child(even) {
        border-right: none;
    }

    .profile-stat:last-child,
    .profile-stat:nth-last-child(2):nth-child(odd) {
        border-bottom: none;
    }
}
</style>

<main class="profile-root">

  <!-- ── Bannière profil ─────────────────────────────────── -->
  <div class="profile-hero">
    <div class="profile-hero-inner">

      <?php if ($orderSuccess): ?>
      <div class="p-alert p-alert-success" style="margin-bottom:16px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        <span>Commande validée ! Vos films sont maintenant disponibles dans votre bibliothèque.</span>
      </div>
      <?php endif; ?>

      <div class="profile-identity">
        <div class="profile-avatar-wrap">
          <div class="profile-avatar"><?= $initials ?></div>
          <div class="profile-avatar-status"></div>
        </div>

        <div class="profile-identity-info">
          <div class="profile-name-row">
            <h1 class="profile-name"><?= htmlspecialchars($userName) ?></h1>
            <span class="profile-plan-badge">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              Membre
            </span>
          </div>
          <div class="profile-email-row">
            <span><?= htmlspecialchars($userMail) ?></span>
            <?php if ($verified): ?>
            <span class="profile-verified-chip">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              Vérifié
            </span>
            <?php else: ?>
            <a href="<?= $basePath ?>/backend/pages/resend-verification.php" class="profile-unverified-chip">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              Non vérifié — Renvoyer l'email
            </a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="profile-quick-stats">
        <div class="profile-stat">
          <div class="profile-stat-value"><?= count($purchasedMovies) ?></div>
          <div class="profile-stat-label">Films achetés</div>
        </div>
        <div class="profile-stat">
          <div class="profile-stat-value"><?= count($orderHistory) ?></div>
          <div class="profile-stat-label">Commandes</div>
        </div>
        <div class="profile-stat">
          <div class="profile-stat-value accent"><?= number_format($totalSpent, 2, ',', ' ') ?>€</div>
          <div class="profile-stat-label">Total dépensé</div>
        </div>
        <div class="profile-stat">
          <div class="profile-stat-value"><?= date('Y') ?></div>
          <div class="profile-stat-label">Membre depuis</div>
        </div>
      </div>

    </div>
  </div>

  <!-- ── Corps du profil ─────────────────────────────────── -->
  <div class="profile-body">

    <!-- Sidebar navigation -->
    <aside class="profile-sidenav">

      <div class="profile-sidenav-section">Mon compte</div>

      <button class="profile-nav-link active" data-section="library" onclick="switchSection(this, 'library')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <rect x="2" y="3" width="7" height="9" rx="1"/><rect x="9" y="3" width="7" height="5" rx="1"/>
          <rect x="2" y="14" width="7" height="7" rx="1"/><rect x="9" y="10" width="13" height="11" rx="1"/>
        </svg>
        Bibliothèque
        <?php if (count($purchasedMovies) > 0): ?>
        <span class="profile-nav-badge"><?= count($purchasedMovies) ?></span>
        <?php endif; ?>
      </button>

      <button class="profile-nav-link" data-section="orders" onclick="switchSection(this, 'orders')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
          <line x1="3" y1="6" x2="21" y2="6"/>
          <path d="M16 10a4 4 0 0 1-8 0"/>
        </svg>
        Commandes
        <?php if (count($orderHistory) > 0): ?>
        <span class="profile-nav-badge" style="background:var(--surface-3);color:var(--text-muted);"><?= count($orderHistory) ?></span>
        <?php endif; ?>
      </button>

      <button class="profile-nav-link" data-section="watchlist" onclick="switchSection(this, 'watchlist')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
        </svg>
        Ma liste
      </button>

      <div class="profile-sidenav-section">Paramètres</div>

      <button class="profile-nav-link" data-section="account" onclick="switchSection(this, 'account')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
        Informations
      </button>

      <button class="profile-nav-link" data-section="security" onclick="switchSection(this, 'security')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        Sécurité
      </button>

      <button class="profile-nav-link" data-section="preferences" onclick="switchSection(this, 'preferences')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/>
        </svg>
        Préférences
      </button>

      <div class="profile-sidenav-section" style="margin-top:8px;"></div>

      <a href="<?= $basePath ?>/backend/pages/logout.php" class="profile-nav-link profile-nav-danger">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Déconnexion
      </a>

    </aside>

    <!-- Contenu principal -->
    <div class="profile-content">

      <!-- ══ BIBLIOTHÈQUE ══════════════════════════════════════ -->
      <div class="profile-section active" id="section-library">
        <h2 class="profile-section-title">Ma bibliothèque</h2>
        <p class="profile-section-sub">Tous vos films achetés, disponibles à tout moment.</p>

        <?php if (empty($purchasedMovies)): ?>
        <div class="pcard" style="text-align:center;padding:48px 24px;">
          <div style="font-size:48px;margin-bottom:16px;opacity:0.3;">🎬</div>
          <p style="color:var(--text-muted);margin-bottom:20px;">Votre bibliothèque est vide pour l'instant.</p>
          <a href="<?= $basePath ?>/backend/pages/movies.php" class="btn-primary">Explorer le catalogue</a>
        </div>
        <?php else: ?>

        <!-- Filtres bibliothèque -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:12px;flex-wrap:wrap;">
          <span style="font-size:13px;color:var(--text-muted);"><?= count($purchasedMovies) ?> film<?= count($purchasedMovies) > 1 ? 's' : '' ?> dans votre collection</span>
          <input type="text" placeholder="Rechercher dans ma bibliothèque…" oninput="filterLibrary(this.value)"
                 style="padding:8px 14px;background:var(--surface-2);border:1px solid var(--border);
                        border-radius:var(--radius);color:var(--text);font-family:var(--font);font-size:12px;
                        outline:none;width:220px;transition:border-color var(--transition);"
                 onfocus="this.style.borderColor='var(--accent)'"
                 onblur="this.style.borderColor='var(--border)'">
        </div>

        <div class="library-grid" id="library-grid">
          <?php foreach ($purchasedMovies as $pm): ?>
          <a href="<?= $basePath ?>/backend/pages/movie-detail.php?id=<?= (int)$pm['tmdb_id'] ?>"
             class="library-movie" data-title="<?= htmlspecialchars(strtolower($pm['title'])) ?>">
            <div class="library-owned-tag">✓ Acheté</div>
            <?php if (!empty($pm['poster'])): ?>
              <img src="<?= htmlspecialchars($pm['poster']) ?>" alt="<?= htmlspecialchars($pm['title']) ?>" loading="lazy">
            <?php else: ?>
              <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:32px;">🎬</div>
            <?php endif; ?>
            <div class="library-movie-overlay">
              <div class="library-movie-title"><?= htmlspecialchars($pm['title']) ?></div>
              <div class="library-movie-play">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                Voir la fiche
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>

        <?php endif; ?>
      </div>

      <!-- ══ COMMANDES ═════════════════════════════════════════ -->
      <div class="profile-section" id="section-orders">
        <h2 class="profile-section-title">Historique des commandes</h2>
        <p class="profile-section-sub">Retrouvez toutes vos transactions et téléchargez vos factures.</p>

        <?php if (empty($orderHistory)): ?>
        <div class="pcard" style="text-align:center;padding:48px 24px;">
          <div style="font-size:40px;margin-bottom:12px;opacity:0.3;">📋</div>
          <p style="color:var(--text-muted);">Aucune commande pour l'instant.</p>
        </div>
        <?php else: ?>

        <div class="p-alert p-alert-info" style="margin-bottom:20px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span>Total dépensé sur Supinfo.TV : <strong style="color:var(--gold)"><?= number_format($totalSpent, 2, ',', ' ') ?>€</strong></span>
        </div>

        <?php foreach ($orderHistory as $order): ?>
        <div class="order-card">
          <div class="order-card-header">
            <div>
              <div class="order-id">Commande #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></div>
              <div class="order-date"><?= date('d F Y à H\hi', strtotime($order['created_at'])) ?></div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
              <span class="order-status">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Complétée
              </span>
              <span class="order-total"><?= number_format((float)$order['total_amount'], 2, ',', ' ') ?>€</span>
            </div>
          </div>
          <div class="order-items-list">
            <?php foreach ($order['items'] as $item): ?>
            <a href="<?= $basePath ?>/backend/pages/movie-detail.php?id=<?= (int)$item['tmdb_id'] ?>" class="order-item-chip">
              <?php if (!empty($item['poster'])): ?>
              <img src="<?= htmlspecialchars($item['poster']) ?>" class="order-item-poster" alt="">
              <?php else: ?>
              <div class="order-item-poster" style="display:flex;align-items:center;justify-content:center;">🎬</div>
              <?php endif; ?>
              <div class="order-item-info">
                <div class="order-item-name"><?= htmlspecialchars($item['title']) ?></div>
                <div class="order-item-price"><?= number_format((float)$item['price'], 2, ',', '') ?>€</div>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>
      </div>

      <!-- ══ WATCHLIST ══════════════════════════════════════════ -->
      <div class="profile-section" id="section-watchlist">
        <h2 class="profile-section-title">Ma liste</h2>
        <p class="profile-section-sub">Films que vous souhaitez voir ou acheter plus tard.</p>

        <div class="pcard" style="text-align:center;padding:40px 24px;">
          <div style="font-size:40px;margin-bottom:16px;opacity:0.3;">🔖</div>
          <p style="color:var(--text-muted);margin-bottom:20px;">Gérez votre liste de souhaits sur la page dédiée.</p>
          <a href="<?= $basePath ?>/backend/pages/watchlist.php" class="btn-primary">Voir ma liste</a>
        </div>
      </div>

      <!-- ══ INFORMATIONS COMPTE ════════════════════════════════ -->
      <div class="profile-section" id="section-account">
        <h2 class="profile-section-title">Informations du compte</h2>
        <p class="profile-section-sub">Gérez vos informations personnelles et votre adresse e-mail.</p>

        <div class="pcard">
          <div class="pcard-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Profil public
          </div>

          <div class="pfield">
            <span class="pfield-label">Nom d'affichage</span>
            <span class="pfield-value"><?= htmlspecialchars($userName) ?></span>
            <button class="pfield-action" onclick="alert('Modification du nom disponible prochainement.')">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Modifier
            </button>
          </div>

          <div class="pfield">
            <span class="pfield-label">Adresse e-mail</span>
            <span class="pfield-value"><?= htmlspecialchars($userMail) ?></span>
            <button class="pfield-action" onclick="alert('Modification de l\'email disponible prochainement.')">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Modifier
            </button>
          </div>

          <div class="pfield">
            <span class="pfield-label">Statut email</span>
            <span class="pfield-value">
              <?php if ($verified): ?>
              <span style="display:inline-flex;align-items:center;gap:5px;color:var(--accent);font-size:13px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                E-mail vérifié
              </span>
              <?php else: ?>
              <span style="color:var(--danger);font-size:13px;">Non vérifié</span>
              <?php endif; ?>
            </span>
            <?php if (!$verified): ?>
            <form method="POST" action="<?= $basePath ?>/backend/pages/resend-verification.php" style="margin:0;">
              <?= csrf_field() ?>
              <button type="submit" class="pfield-action">Renvoyer l'email</button>
            </form>
            <?php endif; ?>
          </div>

          <div class="pfield">
            <span class="pfield-label">Membre depuis</span>
            <span class="pfield-value"><?= date('Y') ?></span>
          </div>

          <div class="pfield">
            <span class="pfield-label">Type de compte</span>
            <span class="pfield-value" style="display:flex;align-items:center;gap:8px;">
              Standard
              <span style="font-size:11px;color:var(--text-faint);">Accès complet au catalogue</span>
            </span>
          </div>
        </div>

        <div class="pcard" style="margin-top:0;">
          <div class="pcard-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            Moyen de paiement
          </div>
          <div style="display:flex;align-items:center;gap:16px;padding:12px 0;">
            <div style="width:48px;height:32px;background:var(--surface-3);border:1px solid var(--border);
                        border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;
                        font-size:18px;">💳</div>
            <div>
              <div style="font-size:13px;color:var(--text);">Paiement à la commande</div>
              <div style="font-size:12px;color:var(--text-faint);margin-top:2px;">Aucune carte enregistrée — chaque achat est ponctuel</div>
            </div>
          </div>
        </div>

      </div>

      <!-- ══ SÉCURITÉ ═══════════════════════════════════════════ -->
      <div class="profile-section" id="section-security">
        <h2 class="profile-section-title">Sécurité du compte</h2>
        <p class="profile-section-sub">Protégez votre compte avec un mot de passe fort et vérifiez les accès.</p>

        <!-- Score sécurité -->
        <div class="pcard">
          <div class="pcard-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Niveau de sécurité
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <span style="font-size:13px;color:var(--text-muted);">Bon</span>
            <span style="font-size:13px;font-weight:500;color:var(--accent);">75%</span>
          </div>
          <div class="security-meter">
            <div class="security-meter-fill" style="width:75%;"></div>
          </div>
          <p style="font-size:11px;color:var(--text-faint);margin-top:8px;">
            Activez la vérification e-mail pour atteindre 100%.
          </p>

          <div style="margin-top:20px;">
            <div class="security-item">
              <div class="security-check ok">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <div>
                <div class="security-item-label">Mot de passe défini</div>
                <div class="security-item-sub">Votre compte est protégé par un mot de passe</div>
              </div>
            </div>
            <div class="security-item">
              <?php if ($verified): ?>
              <div class="security-check ok">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <?php else: ?>
              <div class="security-check warn">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </div>
              <?php endif; ?>
              <div>
                <div class="security-item-label">E-mail vérifié</div>
                <div class="security-item-sub"><?= $verified ? 'Votre adresse email est confirmée' : 'Vérifiez votre adresse email pour sécuriser votre compte' ?></div>
              </div>
            </div>
            <div class="security-item">
              <div class="security-check warn">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </div>
              <div>
                <div class="security-item-label">Double authentification (2FA)</div>
                <div class="security-item-sub">Disponible prochainement</div>
              </div>
              <span style="font-size:10px;color:var(--text-faint);padding:2px 8px;background:var(--surface-2);
                           border:1px solid var(--border);border-radius:99px;letter-spacing:0.06em;">BIENTÔT</span>
            </div>
          </div>
        </div>

        <!-- Changement de mot de passe -->
        <div class="pcard">
          <div class="pcard-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Changer le mot de passe
          </div>

          <?php if ($pwdSuccess): ?>
          <div class="p-alert p-alert-success">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            Mot de passe modifié avec succès.
          </div>
          <?php endif; ?>

          <?php if ($pwdError): ?>
          <div class="p-alert p-alert-error">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($pwdError) ?>
          </div>
          <?php endif; ?>

          <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">

            <div class="p-input-group">
              <label class="p-input-label">Mot de passe actuel</label>
              <input type="password" name="current_password" class="p-input"
                     placeholder="••••••••" required autocomplete="current-password">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div class="p-input-group">
                <label class="p-input-label">Nouveau mot de passe</label>
                <input type="password" name="new_password" id="new-pwd" class="p-input"
                       placeholder="Min. 8 caractères" required autocomplete="new-password" minlength="8">
              </div>
              <div class="p-input-group">
                <label class="p-input-label">Confirmer</label>
                <input type="password" name="confirm_password" id="confirm-pwd" class="p-input"
                       placeholder="Identique" required autocomplete="new-password" minlength="8">
              </div>
            </div>

            <div id="pwd-hint" style="font-size:11px;color:var(--text-faint);margin-bottom:16px;line-height:1.6;">
              8 caractères minimum · 1 majuscule · 1 chiffre
            </div>

            <button type="submit" class="btn-primary">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              Enregistrer le nouveau mot de passe
            </button>
          </form>
        </div>

        <!-- Sessions actives -->
        <div class="pcard">
          <div class="pcard-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            Session active
          </div>
          <div class="security-item" style="padding-top:0;">
            <div class="security-check ok">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="5"/></svg>
            </div>
            <div style="flex:1;">
              <div class="security-item-label">Session actuelle</div>
              <div class="security-item-sub">Connecté maintenant · <?= htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Navigateur inconnu', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <a href="<?= $basePath ?>/backend/pages/logout.php" class="btn-danger" style="font-size:11px;padding:6px 12px;">
              Déconnecter
            </a>
          </div>
        </div>

        <!-- Danger zone -->
        <div class="danger-zone">
          <div class="danger-zone-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Zone dangereuse
          </div>
          <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
            La suppression de votre compte est définitive. Toutes vos données et votre bibliothèque seront perdues.
          </p>
          <button class="btn-danger" onclick="if(confirm('Êtes-vous sûr de vouloir supprimer votre compte ? Cette action est irréversible.')) alert('Fonctionnalité disponible prochainement. Contactez le support.')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
            Supprimer mon compte
          </button>
        </div>

      </div>

      <!-- ══ PRÉFÉRENCES ════════════════════════════════════════ -->
      <div class="profile-section" id="section-preferences">
        <h2 class="profile-section-title">Préférences</h2>
        <p class="profile-section-sub">Personnalisez votre expérience Supinfo.TV.</p>

        <div class="pcard">
          <div class="pcard-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            Notifications
          </div>

          <div class="pref-row">
            <div>
              <div class="pref-label">Nouveaux films disponibles</div>
              <div class="pref-sub">Soyez informé des nouvelles sorties</div>
            </div>
            <label class="p-toggle">
              <input type="checkbox" checked>
              <span class="p-toggle-track"></span>
            </label>
          </div>

          <div class="pref-row">
            <div>
              <div class="pref-label">Offres et promotions</div>
              <div class="pref-sub">Recevez les meilleures offres</div>
            </div>
            <label class="p-toggle">
              <input type="checkbox">
              <span class="p-toggle-track"></span>
            </label>
          </div>

          <div class="pref-row">
            <div>
              <div class="pref-label">Rappels de liste</div>
              <div class="pref-sub">Films sauvegardés depuis longtemps</div>
            </div>
            <label class="p-toggle">
              <input type="checkbox" checked>
              <span class="p-toggle-track"></span>
            </label>
          </div>
        </div>

        <div class="pcard">
          <div class="pcard-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
            Contenu
          </div>

          <div class="pref-row">
            <div>
              <div class="pref-label">Langue préférée</div>
              <div class="pref-sub">Langue des descriptions et métadonnées</div>
            </div>
            <select class="p-input" style="width:auto;padding:7px 30px 7px 12px;min-width:130px;">
              <option selected>Français</option>
              <option>English</option>
              <option>Español</option>
            </select>
          </div>

          <div class="pref-row">
            <div>
              <div class="pref-label">Recommandations</div>
              <div class="pref-sub">Basées sur vos achats précédents</div>
            </div>
            <label class="p-toggle">
              <input type="checkbox" checked>
              <span class="p-toggle-track"></span>
            </label>
          </div>

          <div class="pref-row">
            <div>
              <div class="pref-label">Qualité des affiches</div>
              <div class="pref-sub">Images haute résolution (plus lentes)</div>
            </div>
            <label class="p-toggle">
              <input type="checkbox" checked>
              <span class="p-toggle-track"></span>
            </label>
          </div>
        </div>

        <div class="pcard">
          <div class="pcard-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Affichage
          </div>

          <div class="pref-row">
            <div>
              <div class="pref-label">Animations réduites</div>
              <div class="pref-sub">Moins d'effets de mouvement</div>
            </div>
            <label class="p-toggle">
              <input type="checkbox">
              <span class="p-toggle-track"></span>
            </label>
          </div>

          <div class="pref-row" style="border:none;padding-bottom:0;">
            <div>
              <div class="pref-label">Affichage compact</div>
              <div class="pref-sub">Plus de films à l'écran</div>
            </div>
            <label class="p-toggle">
              <input type="checkbox">
              <span class="p-toggle-track"></span>
            </label>
          </div>
        </div>

        <button class="btn-primary" onclick="showToast && showToast('Préférences enregistrées !', 'success')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          Enregistrer les préférences
        </button>
      </div>

    </div><!-- .profile-content -->
  </div><!-- .profile-body -->
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>

<script>
(function () {
  'use strict';

  // ── Navigation entre sections ─────────────────────────────
  window.switchSection = function(btn, sectionId) {
    // Désactiver tous les liens
    document.querySelectorAll('.profile-nav-link').forEach(l => l.classList.remove('active'));
    document.querySelectorAll('.profile-section').forEach(s => s.classList.remove('active'));

    // Activer la section choisie
    btn.classList.add('active');
    const section = document.getElementById('section-' + sectionId);
    if (section) section.classList.add('active');

    // Scroll vers le contenu sur mobile
    if (window.innerWidth < 900) {
      section?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  // ── Hash navigation ───────────────────────────────────────
  function loadFromHash() {
    const hash = window.location.hash.replace('#', '');
    const validSections = ['library','orders','watchlist','account','security','preferences'];
    if (validSections.includes(hash)) {
      const btn = document.querySelector('[data-section="' + hash + '"]');
      if (btn) switchSection(btn, hash);
    }
  }

  loadFromHash();
  window.addEventListener('hashchange', loadFromHash);

  // Mettre à jour le hash au clic
  document.querySelectorAll('.profile-nav-link[data-section]').forEach(btn => {
    btn.addEventListener('click', () => {
      history.replaceState(null, '', '#' + btn.dataset.section);
    });
  });

  // ── Filtrage bibliothèque ─────────────────────────────────
  window.filterLibrary = function(q) {
    const term = q.toLowerCase().trim();
    document.querySelectorAll('.library-movie').forEach(card => {
      const title = card.dataset.title || '';
      card.style.display = (!term || title.includes(term)) ? '' : 'none';
    });
  };

  // ── Vérification mots de passe en live ───────────────────
  const pwd1 = document.getElementById('new-pwd');
  const pwd2 = document.getElementById('confirm-pwd');
  const hint = document.getElementById('pwd-hint');

  function checkPwdMatch() {
    if (!pwd1 || !pwd2 || !hint) return;
    if (!pwd2.value) {
      hint.style.color = 'var(--text-faint)';
      hint.textContent = '8 caractères minimum · 1 majuscule · 1 chiffre';
      return;
    }
    if (pwd1.value === pwd2.value) {
      hint.style.color = 'var(--accent)';
      hint.textContent = '✓ Les mots de passe correspondent';
    } else {
      hint.style.color = 'var(--danger)';
      hint.textContent = '✗ Les mots de passe ne correspondent pas';
    }
  }

  pwd1?.addEventListener('input', checkPwdMatch);
  pwd2?.addEventListener('input', checkPwdMatch);

  // ── Animation sécurité ────────────────────────────────────
  const meterFill = document.querySelector('.security-meter-fill');
  if (meterFill) {
    const target = meterFill.style.width;
    meterFill.style.width = '0';
    setTimeout(() => { meterFill.style.width = target; }, 400);
  }

})();
</script>

</body>
</html>