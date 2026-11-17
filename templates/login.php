<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in — Ticket Desk</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="auth">
<main class="card">
  <h1>Ticket Desk</h1>
  <p class="muted">Internal support queue</p>
  <?php if (!empty($error)): ?>
    <p class="alert"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>
  <form method="post" action="/login">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <label>Email<input type="email" name="email" required autocomplete="username"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit">Sign in</button>
  </form>
</main>
</body>
</html>
