<?php
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$pageTitle  = 'Connexion';
$pageCSS    = 'pages/login.css';
$pageDesc   = 'Connectez-vous à Supinfo.TV';
$activePage = '';

$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';
?>

<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

<main>
  <div class="auth-page">
    <div class="auth-container" id="auth-container">

      <div class="form-box login">
        <form action="<?= $basePath ?>/pages/login-handler.php" method="POST">
          <h1>Connexion</h1>

          <div class="input-box">
            <input type="email" name="email" placeholder="Adresse e-mail" required autocomplete="email">
            <i class="bx bxs-envelope"></i>
          </div>

          <div class="input-box">
            <input type="password" name="password" placeholder="Mot de passe" required autocomplete="current-password">
            <i class="bx bxs-lock-alt"></i>
          </div>

          <div class="forgot-link">
            <a href="#">Mot de passe oublié ?</a>
          </div>

          <button type="submit" class="auth-btn">Se connecter</button>

          <div class="auth-divider">ou continuer avec</div>

          <div class="social-icons">
            <a href="#" title="Google"><i class="bx bxl-google"></i></a>
            <a href="#" title="GitHub"><i class="bx bxl-github"></i></a>
          </div>
        </form>
      </div>

      <div class="form-box register">
        <form action="<?= $basePath ?>/pages/register-handler.php" method="POST">
          <h1>Inscription</h1>

          <div class="input-box">
            <input type="text" name="username" placeholder="Nom d'utilisateur" required autocomplete="username">
            <i class="bx bxs-user"></i>
          </div>

          <div class="input-box">
            <input type="email" name="email" placeholder="Adresse e-mail" required autocomplete="email">
            <i class="bx bxs-envelope"></i>
          </div>

          <div class="input-box">
            <input type="password" name="password" placeholder="Mot de passe" required autocomplete="new-password">
            <i class="bx bxs-lock-alt"></i>
          </div>

          <button type="submit" class="auth-btn">Créer un compte</button>

          <div class="auth-divider">ou continuer avec</div>

          <div class="social-icons">
            <a href="#" title="Google"><i class="bx bxl-google"></i></a>
            <a href="#" title="GitHub"><i class="bx bxl-github"></i></a>
          </div>
        </form>
      </div>

      <div class="toggle-box">

        <div class="toggle-panel toggle-left">
          <h1>Bienvenue !</h1>
          <p>Pas encore de compte ?</p>
          <button class="toggle-btn register-btn">S'inscrire</button>
        </div>

        <div class="toggle-panel toggle-right">
          <h1>Bon retour !</h1>
          <p>Déjà membre ?</p>
          <button class="toggle-btn login-btn">Se connecter</button>
        </div>

      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>

<script>
  // Animation toggle — conservée intégralement
  const container   = document.getElementById('auth-container');
  const registerBtn = container.querySelector('.register-btn');
  const loginBtn    = container.querySelector('.login-btn');

  registerBtn.addEventListener('click', () => container.classList.add('active'));
  loginBtn.addEventListener('click',    () => container.classList.remove('active'));

  if (new URLSearchParams(location.search).get('mode') === 'register') {
    container.classList.add('active');
  }
</script>

</body>
</html>