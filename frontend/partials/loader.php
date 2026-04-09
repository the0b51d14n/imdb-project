<?php
if (!isset($basePath)) {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $basePath  = str_ends_with($scriptDir, '/pages') ? dirname($scriptDir) : $scriptDir;
}
?>
<div class="page-loader" id="page-loader">
  <div class="loader-logo">
    <img src="<?= $basePath ?>/assets/images/brand/logo-blanc.png" alt="Supinfo.TV">
  </div>

  <div class="loader-shapes">

    <div class="loader-shape">
      <svg viewBox="0 0 80 80">
        <circle cx="40" cy="40" r="32"></circle>
      </svg>
    </div>

    <div class="loader-shape triangle">
      <svg viewBox="0 0 86 80">
        <polygon points="43 8 79 72 7 72"></polygon>
      </svg>
    </div>

    <div class="loader-shape">
      <svg viewBox="0 0 80 80">
        <rect x="8" y="8" width="64" height="64"></rect>
      </svg>
    </div>
  </div>

  <div class="loader-bar">
    <div class="loader-bar-inner"></div>
  </div>
</div>