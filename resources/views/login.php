<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Sign in — Okyema</title>
<link rel="icon" href="<?= e($base) ?>/assets/favicon/favicon.ico" sizes="32x32">
<link rel="icon" href="<?= e($base) ?>/assets/favicon/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e($base) ?>/css/okyema.css?v=<?= e(config('okyema.app.version')) ?>">
    <?php include resource_path('views/partials/pwa-head.php'); ?>
</head>
<body class="auth-body">
<main class="auth-card">
    <div class="auth-brand">
        <img class="auth-logo" src="<?= e($base) ?>/assets/okyema-mark.svg" alt="Okyema">
        <h1 class="auth-title">Okyema</h1>
        <p class="auth-tagline">Your intelligent chief of staff</p>
    </div>

    <?php if ($errors->any()) { ?>
        <div class="auth-alert" role="alert"><?= e($errors->first()) ?></div>
    <?php } ?>

    <form method="POST" action="<?= e($base) ?>/login" class="auth-form">
        <input type="hidden" name="_token" value="<?= e($csrf) ?>">
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required autocomplete="email" autofocus>
        </label>
        <label class="field">
            <span>Password</span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn btn--primary btn--block">Sign in</button>
    </form>

    <?php if (! empty(config('services.google.client_id'))) { ?>
        <div class="auth-divider">or</div>
        <a class="btn btn--ghost btn--block" href="<?= e($base) ?>/auth/google/redirect">Continue with Google</a>
    <?php } ?>

    <p class="auth-switch">New here? <a href="<?= e($base) ?>/register">Create an account</a></p>
    <p class="auth-credit">Powered by Regno AI</p>
</main>
</body>
</html>
