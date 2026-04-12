<?php

session_start();
 
require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/orders.php';
require_once __DIR__ . '/../services/csrf.php';
 
auth_start_session();
 
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
auth_require($basePath . '/pages/profile.php');
 
$userId   = auth_id();
$userName = $_SESSION[SESSION_USER_NAME]  ?? 'Utilisateur';
$userMail = $_SESSION[SESSION_USER_EMAIL] ?? '';

// ── Variables manquantes corrigées ────────────────────────────────────────────
$verified    = $_SESSION[SESSION_USER_VERIFIED] ?? false;
$orderHistory = orders_get_history();
 
$pwdError   = null;
$pwdSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    csrf_verify();
 
    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';
 
    if ($new !== $confirm) {
        $pwdError = "Les nouveaux mots de passe ne correspondent pas.";
    } else {
        $r = auth_change_password($userId, $current, $new);
        if ($r['ok']) {
            $pwdSuccess = true;
        } else {
            $pwdError = $r['error'];
        }
    }
}
 
$purchasedMovies = orders_get_purchased_movies();
$orderSuccess    = isset($_GET['order']) && $_GET['order'] === 'success';
 
$pageTitle  = 'Mon profil';
$pageCSS    = 'pages/profile.css';
$pageDesc   = 'Gérez votre compte Supinfo.TV — films achetés et paramètres.';
$activePage = 'profile';
 
include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';

?>

<main>
<div class="container" style="padding-top:48px;padding-bottom:80px;">
 
  <?php if ($orderSuccess): ?>
  <div style="padding:16px 20px;background:rgba(87,204,153,0.1);border:1px solid rgba(87,204,153,0.3);
              border-radius:var(--radius-lg);margin-bottom:32px;display:flex;align-items:center;gap:12px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2">
      <polyline points="20 6 9 17 4 12"/>
    </svg>
    <span style="font-size:14px;color:var(--accent);font-weight:500;">Commande validée ! Vos films sont disponibles ci-dessous.</span>
  </div>
  <?php endif; ?>
 
  <!-- Header profil -->
  <div style="display:flex;align-items:center;gap:24px;margin-bottom:48px;flex-wrap:wrap;">
    <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--deep),var(--accent));
                display:flex;align-items:center;justify-content:center;flex-shrink:0;
                font-size:28px;font-weight:500;color:#fff;border:2px solid var(--border-bright);
                box-shadow:0 0 24px var(--accent-glow);">
      <?= mb_strtoupper(mb_substr($userName, 0, 1)) ?>
    </div>
    <div>
      <h1 style="font-size:28px;font-weight:500;letter-spacing:-0.02em;color:var(--text);margin-bottom:4px;">
        <?= htmlspecialchars($userName) ?>
      </h1>
      <p style="font-size:14px;color:var(--text-muted);margin:0;display:flex;align-items:center;gap:8px;">
        <?= htmlspecialchars($userMail) ?>
        <?php if ($verified): ?>
        <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;color:var(--accent);
                     border:1px solid rgba(87,204,153,0.3);border-radius:99px;padding:2px 8px;">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
          Vérifié
        </span>
        <?php else: ?>
        <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;color:var(--text-faint);
                     border:1px solid var(--border);border-radius:99px;padding:2px 8px;">
          Non vérifié
        </span>
        <?php endif; ?>
      </p>
    </div>
    <?php if (!$verified): ?>
    <form method="POST" action="<?= $basePath ?>/pages/resend-verification.php" style="margin-left:auto;">
      <?= csrf_field() ?>
      <button type="submit" class="btn-more">Renvoyer l'e-mail de vérification</button>
    </form>
    <?php endif; ?>
  </div>
 
  <div style="display:grid;grid-template-columns:1fr 340px;gap:40px;align-items:start;">
 
    <!-- Colonne principale -->
    <div>
 
      <!-- Mes films -->
      <section style="margin-bottom:48px;">
        <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:24px;">
          <div>
            <div style="font-size:10px;font-weight:500;letter-spacing:0.12em;text-transform:uppercase;color:var(--accent);margin-bottom:6px;">Bibliothèque</div>
            <h2 style="font-size:22px;font-weight:500;color:var(--text);letter-spacing:-0.01em;">
              Mes films
              <?php if (!empty($purchasedMovies)): ?>
              <span style="font-size:14px;color:var(--text-muted);font-weight:400;margin-left:8px;"><?= count($purchasedMovies) ?> film<?= count($purchasedMovies) > 1 ? 's' : '' ?></span>
              <?php endif; ?>
            </h2>
          </div>
        </div>
 
        <?php if (empty($purchasedMovies)): ?>
        <div style="padding:48px 24px;background:var(--surface);border:1px solid var(--border);
                    border-radius:var(--radius-lg);text-align:center;">
          <div style="font-size:40px;margin-bottom:16px;opacity:0.4;">🎬</div>
          <p style="color:var(--text-muted);font-size:14px;margin-bottom:20px;">Vous n'avez pas encore acheté de films.</p>
          <a href="<?= $basePath ?>/pages/movies.php" class="btn-primary">Explorer le catalogue</a>
        </div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;">
          <?php foreach ($purchasedMovies as $pm):
            $movie = [
              'id'     => $pm['tmdb_id'],
              'title'  => $pm['title'],
              'poster' => $pm['poster'],
              'year'   => substr($pm['purchased_at'] ?? '', 0, 4),
              'note'   => null,
              'price'  => ['unit' => $pm['price']],
            ];
          ?>
            <?php include __DIR__ . '/../../frontend/partials/movie-card.php'; ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>
 
      <!-- Historique commandes -->
      <?php if (!empty($orderHistory)): ?>
      <section>
        <div style="margin-bottom:24px;">
          <div style="font-size:10px;font-weight:500;letter-spacing:0.12em;text-transform:uppercase;color:var(--accent);margin-bottom:6px;">Historique</div>
          <h2 style="font-size:22px;font-weight:500;color:var(--text);letter-spacing:-0.01em;">Mes commandes</h2>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <?php foreach ($orderHistory as $order): ?>
          <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px 24px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
              <div>
                <span style="font-size:12px;color:var(--text-faint);">Commande #<?= (int)$order['id'] ?></span>
                <div style="font-size:13px;color:var(--text-muted);margin-top:2px;">
                  <?= date('d/m/Y à H\hi', strtotime($order['created_at'])) ?>
                </div>
              </div>
              <span style="font-size:17px;font-weight:500;color:var(--gold);">
                <?= number_format((float)$order['total_amount'], 2, ',', '') ?>€
              </span>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <?php foreach ($order['items'] as $item): ?>
              <a href="<?= $basePath ?>/pages/movie-detail.php?id=<?= (int)$item['tmdb_id'] ?>"
                 style="display:flex;align-items:center;gap:8px;padding:6px 10px;
                        background:var(--surface-2);border:1px solid var(--border-subtle);
                        border-radius:var(--radius);transition:border-color var(--transition);"
                 onmouseover="this.style.borderColor='var(--accent)'"
                 onmouseout="this.style.borderColor='var(--border-subtle)'">
                <?php if (!empty($item['poster'])): ?>
                <img src="<?= htmlspecialchars($item['poster']) ?>" alt=""
                     style="width:28px;border-radius:3px;flex-shrink:0;">
                <?php endif; ?>
                <span style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($item['title']) ?></span>
                <span style="font-size:12px;color:var(--gold);margin-left:auto;"><?= number_format((float)$item['price'], 2, ',', '') ?>€</span>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
 
    </div>
 
    <!-- Colonne droite : sécurité -->
    <aside>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);
                  padding:24px;position:sticky;top:calc(var(--navbar-h) + 24px);">
        <div style="font-size:10px;font-weight:500;letter-spacing:0.12em;text-transform:uppercase;
                    color:var(--text-faint);margin-bottom:16px;">Sécurité</div>
        <h3 style="font-size:17px;font-weight:500;color:var(--text);margin-bottom:20px;">Changer le mot de passe</h3>
 
        <?php if ($pwdSuccess): ?>
        <div style="padding:12px 14px;background:rgba(87,204,153,0.1);border:1px solid rgba(87,204,153,0.3);
                    border-radius:var(--radius);margin-bottom:16px;font-size:13px;color:var(--accent);">
          ✅ Mot de passe modifié avec succès.
        </div>
        <?php endif; ?>
        <?php if ($pwdError): ?>
        <div style="padding:12px 14px;background:rgba(224,90,106,0.08);border:1px solid var(--danger);
                    border-radius:var(--radius);margin-bottom:16px;font-size:13px;color:var(--danger);">
          <?= htmlspecialchars($pwdError) ?>
        </div>
        <?php endif; ?>
 
        <form method="POST" action="">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">
 
          <?php
          $inputStyle = "width:100%;padding:11px 14px;background:var(--surface-2);
                         border:1px solid var(--border);border-radius:var(--radius);
                         color:var(--text);font-family:var(--font);font-size:13px;outline:none;
                         margin-bottom:12px;box-sizing:border-box;";
          ?>
 
          <input type="password" name="current_password" placeholder="Mot de passe actuel"
                 required autocomplete="current-password" style="<?= $inputStyle ?>">
          <input type="password" name="new_password" placeholder="Nouveau mot de passe"
                 required autocomplete="new-password" minlength="8" style="<?= $inputStyle ?>">
          <input type="password" name="confirm_password" placeholder="Confirmer le nouveau mot de passe"
                 required autocomplete="new-password" minlength="8"
                 style="<?= $inputStyle ?>margin-bottom:16px;">
 
          <p style="font-size:11px;color:var(--text-faint);margin-bottom:16px;line-height:1.6;">
            8 caractères minimum · 1 majuscule · 1 chiffre
          </p>
 
          <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">
            Mettre à jour
          </button>
        </form>
 
        <div style="margin-top:24px;padding-top:24px;border-top:1px solid var(--border);">
          <a href="<?= $basePath ?>/pages/logout.php"
             style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-muted);
                    padding:10px 14px;border:1px solid var(--border);border-radius:var(--radius);
                    transition:all var(--transition);background:transparent;"
             onmouseover="this.style.color='var(--danger)';this.style.borderColor='var(--danger)';this.style.background='rgba(224,90,106,0.06)'"
             onmouseout="this.style.color='var(--text-muted)';this.style.borderColor='var(--border)';this.style.background='transparent'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Se déconnecter
          </a>
        </div>
      </div>
    </aside>
 
  </div>
</div>
</main>
 
<?php include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= $basePath ?>/assets/js/pages/profile.js"></script>
</body>
</html>