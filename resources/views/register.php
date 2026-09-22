<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Create account — Okyema</title>
<link rel="icon" href="<?= e($base) ?>/assets/favicon.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e($base) ?>/css/okyema.css?v=<?= e(config('okyema.app.version')) ?>">
    <?php include resource_path('views/partials/pwa-head.php'); ?>
</head>
<body class="auth-body">
<main class="auth-card">
    <div class="auth-brand">
        <div class="auth-logo">O</div>
        <h1 class="auth-title">Okyema</h1>
        <p class="auth-tagline">Your intelligent chief of staff</p>
    </div>

    <?php if ($errors->any()) { ?>
        <div class="auth-alert" role="alert"><?= e($errors->first()) ?></div>
    <?php } ?>

    <form method="POST" action="<?= e($base) ?>/register" class="auth-form">
        <input type="hidden" name="_token" value="<?= e($csrf) ?>">
        <label class="field">
            <span>Name</span>
            <input type="text" name="name" value="<?= e($old['name'] ?? '') ?>" required autocomplete="name" autofocus>
        </label>
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required autocomplete="email">
        </label>
        <label class="field">
            <span>Password</span>
            <input type="password" name="password" required autocomplete="new-password">
        </label>
        <label class="field">
            <span>Confirm password</span>
            <input type="password" name="password_confirmation" required autocomplete="new-password">
        </label>
        <button type="submit" class="btn btn--primary btn--block">Create account</button>
    </form>

    <p class="auth-switch">Already have an account? <a href="<?= e($base) ?>/login">Sign in</a></p>
    <p class="auth-credit">Powered by Regno AI</p>
</main>
</body>
</html>
