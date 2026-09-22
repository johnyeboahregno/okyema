<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Okyema</title>
<link rel="icon" href="<?= e($base) ?>/assets/favicon.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e($base) ?>/css/okyema.css?v=<?= e(config('okyema.app.version')) ?>">
    <?php include resource_path('views/partials/pwa-head.php'); ?>
</head>
<body class="splash-body">
<main class="splash-image">
    <div class="splash__mark" aria-hidden="true">O</div>
    <h1 class="splash__title">Okyema</h1>
    <p class="splash__tagline">Your intelligent chief of staff</p>
    <a class="splash__cta" href="<?= e($base) ?>/login" aria-label="Continue to sign in"><span aria-hidden="true">→</span></a>
    <p class="splash__credit">Powered by Regno AI</p>
</main>
</body>
</html>
