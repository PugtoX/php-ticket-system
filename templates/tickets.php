<?php
use App\TicketStatus;
/** @var array $user @var array $data @var array $filters @var array $stats @var string $csrf @var ?string $error */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ticket Desk</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="bar">
  <strong>Ticket Desk</strong>
  <span class="muted"><?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8') ?></span>
  <form method="post" action="/logout" class="inline">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <button class="link" type="submit">Sign out</button>
  </form>
</header>

<main>
  <section class="stats" id="stats">
    <div class="stat"><b data-status="total"><?= (int) $stats['total'] ?></b><span>total</span></div>
    <div class="stat"><b data-status="open"><?= (int) $stats['open'] ?></b><span>open</span></div>
    <div class="stat"><b data-status="in_progress"><?= (int) $stats['in_progress'] ?></b><span>in progress</span></div>
    <div class="stat"><b data-status="closed"><?= (int) $stats['closed'] ?></b><span>closed</span></div>
  </section>

  <?php if (!empty($error)): ?>
    <p class="alert"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>

  <form class="filters" method="get" action="/tickets">
    <input type="search" name="q" placeholder="Search subject or body" value="<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>">
    <select name="status">
      <option value="">All statuses</option>
      <?php foreach (['open', 'in_progress', 'closed'] as $s): ?>
        <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= str_replace('_', ' ', $s) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Filter</button>
  </form>

  <table class="tickets">
    <thead><tr><th>ID</th><th>Subject</th><th>Customer</th><th>Status</th><th>Created</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($data['items'] as $t): ?>
      <tr data-id="<?= (int) $t['id'] ?>">
        <td>#<?= (int) $t['id'] ?></td>
        <td><?= htmlspecialchars((string) $t['subject'], ENT_QUOTES, 'UTF-8') ?></td>
        <td class="muted"><?= htmlspecialchars((string) $t['customer_email'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><span class="badge" data-status="<?= htmlspecialchars((string) $t['status'], ENT_QUOTES, 'UTF-8') ?>"><?= str_replace('_', ' ', (string) $t['status']) ?></span></td>
        <td class="muted"><?= htmlspecialchars((string) $t['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
        <td class="actions">
          <?php foreach (TicketStatus::nextStates((string) $t['status']) as $next): ?>
            <button data-action="status" data-to="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>"><?= $next === 'in_progress' ? 'Start' : ($next === 'closed' ? 'Close' : 'Reopen') ?></button>
          <?php endforeach; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <nav class="pager">
    <?php if ($data['page'] > 1): ?>
      <a href="/tickets?page=<?= $data['page'] - 1 ?>&status=<?= urlencode($filters['status']) ?>&q=<?= urlencode($filters['q']) ?>">Previous</a>
    <?php endif; ?>
    <span class="muted">Page <?= (int) $data['page'] ?> of <?= (int) $data['pages'] ?> · <?= (int) $data['total'] ?> tickets</span>
    <?php if ($data['page'] < $data['pages']): ?>
      <a href="/tickets?page=<?= $data['page'] + 1 ?>&status=<?= urlencode($filters['status']) ?>&q=<?= urlencode($filters['q']) ?>">Next</a>
    <?php endif; ?>
  </nav>

  <details class="new">
    <summary>New ticket</summary>
    <form method="post" action="/tickets">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <label>Subject<input name="subject" required maxlength="200"></label>
      <label>Customer email<input type="email" name="customer_email" required></label>
      <label>Body<textarea name="body" rows="4" required maxlength="5000"></textarea></label>
      <button type="submit">Create</button>
    </form>
  </details>
</main>

<script>window.CSRF_TOKEN = <?= json_encode($csrf) ?>;</script>
<script src="/assets/app.js"></script>
</body>
</html>
