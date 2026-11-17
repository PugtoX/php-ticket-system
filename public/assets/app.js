document.addEventListener('click', async (ev) => {
  const btn = ev.target.closest('button[data-action="status"]');
  if (!btn) return;
  const row = btn.closest('tr');
  const id = row.dataset.id;
  const to = btn.dataset.to;
  btn.disabled = true;
  try {
    const res = await fetch(`/api/tickets/${id}/status`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
      body: JSON.stringify({ status: to })
    });
    const payload = await res.json();
    if (!res.ok) {
      const badge = row.querySelector('.badge');
      badge.classList.add('shake');
      setTimeout(() => badge.classList.remove('shake'), 400);
      console.warn('update rejected', payload);
      return;
    }
    const badge = row.querySelector('.badge');
    badge.dataset.status = payload.status;
    badge.textContent = payload.status.replace('_', ' ');
    await refreshStats();
  } finally {
    btn.disabled = false;
  }
});

async function refreshStats() {
  const res = await fetch('/api/stats');
  if (!res.ok) return;
  const s = await res.json();
  document.querySelectorAll('b[data-status]').forEach((el) => {
    const key = el.dataset.status;
    if (key in s) el.textContent = s[key];
  });
}
