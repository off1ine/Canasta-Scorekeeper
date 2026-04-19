<?php
/**
 * Shared <head> contents. Include after opening <head>.
 * Set $pageTitle before including.
 */
?>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover" />
<meta name="color-scheme" content="light" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="default" />
<meta name="apple-mobile-web-app-title" content="Scorekeeper" />
<title>Scorekeeper<?php echo isset($pageTitle) ? ' — ' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') : ''; ?></title>
<link rel="stylesheet" href="assets/app.css?v=<?= filemtime(__DIR__ . '/assets/app.css') ?>" />
