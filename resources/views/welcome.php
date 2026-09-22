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
    <p style="color:#F5F7FB;font-size:2rem;font-weight:800;margin:0;letter-spacing:-.02em">Okyema</p>
    <p style="color:#9BA6C2;margin:8px 0 0">Your intelligent chief of staff</p>
    <a class="splash__cta" href="<?= e($base) ?>/login" aria-label="Continue">→</a>
    <p class="splash__credit">Powered by Regno AI</p>
</main>
</body>
</html>
