<?php
/**
 * Okyema PWA <head> snippet: manifest link, theme-colour, iOS meta tags and
 * service-worker registration.
 *
 * No offline caching by design — the service worker is a network pass-through.
 * Requires $base (app base path) in scope; falls back to the request base path.
 */
$base = $base ?? rtrim(request()->getBasePath(), '/');
$version = config('okyema.app.version', '0.1.0');
?>
<link rel="manifest" href="<?= e($base) ?>/manifest.webmanifest?v=<?= e($version) ?>">
<meta name="theme-color" content="#0B1020">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Okyema">
<link rel="apple-touch-icon" href="<?= e($base) ?>/assets/apple-touch-icon.png?v=<?= e($version) ?>">
<script>
/**
 * Installability, captured as early as possible. Chrome fires
 * `beforeinstallprompt` whenever it decides the app is installable — often
 * before the app's own script has run.
 */
window.__okyemaInstallPrompt = null;
window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    window.__okyemaInstallPrompt = event;
    window.dispatchEvent(new Event('okyema:installable'));
});
window.addEventListener('appinstalled', function () {
    window.__okyemaInstallPrompt = null;
    window.dispatchEvent(new Event('okyema:installed'));
});
(function () {
    if (!('serviceWorker' in navigator)) return;
    if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') return;
    window.addEventListener('load', function () {
        navigator.serviceWorker.register(<?= json_encode($base) ?> + '/sw.js').catch(function () {});
    });
})();
</script>
