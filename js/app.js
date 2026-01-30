(() => {
  const parentSelect = document.getElementById('parent-select');
  const childSelect = document.getElementById('child-select');
  const form = parentSelect?.closest('form');
  const modal = document.getElementById('description-modal');
  const modalText = document.getElementById('description-modal-text');
  const modalSave = document.getElementById('description-modal-save');
  const modalStatus = document.getElementById('description-modal-status');
  let activeCard = null;

  if (parentSelect && form) {
    parentSelect.addEventListener('change', () => {
      if (childSelect) {
        childSelect.selectedIndex = 0;
      }
      form.submit();
    });
  }

  if (childSelect && form) {
    childSelect.addEventListener('change', () => {
      form.submit();
    });
  }

  const escapeHtml = (value) =>
    value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

  const closeModal = () => {
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    activeCard = null;
  };

  const openModal = (card) => {
    if (!modal || !modalText || !modalStatus) return;
    activeCard = card;
    const descriptionEl = card?.querySelector('.rank-card__description');
    const description = descriptionEl?.dataset.description ?? '';
    modalText.value = description;
    modalStatus.textContent = '';
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    modalText.focus();
  };

  document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const actionButton = target.closest('[data-action]');
    if (actionButton) {
      const action = actionButton.dataset.action;
      const card = actionButton.closest('.rank-card');
      const descriptionEl = card?.querySelector('.rank-card__description');
      if (action === 'edit-description' && card) {
        openModal(card);
        return;
      }
      if (action === 'copy-description' && descriptionEl) {
        const text = (descriptionEl.dataset.description ?? '').trim();
        if (!text) {
          window.alert('コピーする説明文がありません。');
          return;
        }
        if (navigator.clipboard?.writeText) {
          navigator.clipboard.writeText(text).then(
            () => window.alert('説明文をコピーしました。'),
            () => window.alert('コピーに失敗しました。')
          );
        } else {
          const textarea = document.createElement('textarea');
          textarea.value = text;
          document.body.appendChild(textarea);
          textarea.select();
          try {
            document.execCommand('copy');
            window.alert('説明文をコピーしました。');
          } catch (error) {
            window.alert('コピーに失敗しました。');
          } finally {
            document.body.removeChild(textarea);
          }
        }
        return;
      }

      if (action === 'ai-description' && card) {
        const itemCode = card.dataset.itemCode;
        if (!itemCode) {
          window.alert('商品情報が取得できません。');
          return;
        }
        const descriptionEl = card.querySelector('.rank-card__description');
        const previousHtml = descriptionEl?.innerHTML ?? '';
        const previousDescription = descriptionEl?.dataset.description ?? '';
        actionButton.setAttribute('aria-busy', 'true');
        actionButton.disabled = true;
        if (descriptionEl) {
          descriptionEl.innerHTML = '<p class="rank-card__description--empty">AIで説明文を生成中...</p>';
        }
        fetch('description_ai.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ item_code: itemCode }),
        })
          .then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) {
              throw new Error(data.message || 'AI説明の生成に失敗しました。');
            }
            const description = (data.description ?? '').trim();
            if (!description) {
              throw new Error('AI説明が取得できませんでした。');
            }
            if (descriptionEl) {
              descriptionEl.dataset.description = description;
              descriptionEl.innerHTML = `<p>${escapeHtml(description).replace(/\n/g, '<br>')}</p>`;
            }
          })
          .catch((error) => {
            if (descriptionEl) {
              descriptionEl.dataset.description = previousDescription;
              descriptionEl.innerHTML = previousHtml;
            }
            window.alert(error instanceof Error ? error.message : 'AI説明の生成に失敗しました。');
          })
          .finally(() => {
            actionButton.removeAttribute('aria-busy');
            actionButton.disabled = false;
          });
      }
    }

    if (target.closest('[data-modal-close]')) {
      closeModal();
    }
  });

  modalSave?.addEventListener('click', async () => {
    if (!activeCard || !modalText || !modalStatus || !modalSave) return;
    const itemCode = activeCard.dataset.itemCode;
    if (!itemCode) {
      modalStatus.textContent = '商品情報が取得できません。';
      return;
    }
    modalSave.disabled = true;
    modalStatus.textContent = '保存中...';
    const description = modalText.value.trim();
    try {
      const response = await fetch('description_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ item_code: itemCode, description }),
      });
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || '保存に失敗しました。');
      }
      const descriptionEl = activeCard.querySelector('.rank-card__description');
      if (descriptionEl) {
        descriptionEl.dataset.description = description;
        if (description) {
          descriptionEl.innerHTML = `<p>${escapeHtml(description).replace(/\n/g, '<br>')}</p>`;
        } else {
          descriptionEl.innerHTML = '<p class="rank-card__description--empty">商品説明を入力してください</p>';
        }
      }
      modalStatus.textContent = '保存しました。';
      setTimeout(closeModal, 600);
    } catch (error) {
      modalStatus.textContent = error instanceof Error ? error.message : '保存に失敗しました。';
    } finally {
      modalSave.disabled = false;
    }
  });
})();