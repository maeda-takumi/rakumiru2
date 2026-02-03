(() => {
  const apiBase = document.body?.dataset.apiBase || '/';
  const toggles = Array.from(document.querySelectorAll('[data-user-toggle]'));

  const setStatus = (row, message, isError = false) => {
    const status = row?.querySelector('[data-toggle-status]');
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('is-error', isError);
  };

  toggles.forEach((toggle) => {
    toggle.addEventListener('change', async () => {
      const row = toggle.closest('[data-user-row]');
      const userId = toggle.dataset.userId;
      const active = toggle.checked ? 1 : 0;

      toggle.disabled = true;
      toggle.setAttribute('aria-checked', toggle.checked ? 'true' : 'false');
      setStatus(row, '更新中...');

      try {
        const response = await fetch(`${apiBase}update_active.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            user_id: userId,
            active: String(active),
          }).toString(),
          credentials: 'same-origin',
        });

        const result = await response.json().catch(() => null);
        if (!response.ok || !result?.ok) {
          throw new Error(result?.error || '更新に失敗しました');
        }

        setStatus(row, '更新しました');
      } catch (error) {
        toggle.checked = !toggle.checked;
        toggle.setAttribute('aria-checked', toggle.checked ? 'true' : 'false');
        setStatus(row, '更新に失敗しました', true);
      } finally {
        toggle.disabled = false;
      }
    });
  });
})();
