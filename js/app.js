(() => {
  const genreSelect = document.getElementById('genre-select');
  const form = genreSelect?.closest('form');
  const modal = document.getElementById('description-modal');
  const modalText = document.getElementById('description-modal-text');
  const modalSave = document.getElementById('description-modal-save');
  const modalStatus = document.getElementById('description-modal-status');
  const settingsModal = document.getElementById('settings-modal');
  const settingsOpen = document.getElementById('settings-open');
  const priceFilterToggle = document.getElementById('price-filter-enabled');
  const priceInputs = Array.from(document.querySelectorAll('[data-price-input]'));
  const priceFilterNote = settingsModal?.querySelector('[data-price-filter-note]');
  const genreSlider = document.querySelector('[data-genre-slider]');
  const apiBase = document.body?.dataset.apiBase || '/';
  const aiDailyLimit = Number(document.body?.dataset.aiDailyLimit ?? '');
  let aiRemaining = Number(document.body?.dataset.aiRemaining ?? '');
  let activeCard = null;



  const escapeHtml = (value) =>
    value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  const renderAiError = (descriptionEl, previousHtml, previousDescription, message) => {
    if (!descriptionEl) {
      window.alert(message);
      return;
    }
    descriptionEl.dataset.description = previousDescription;
    const safeMessage = escapeHtml(message).replace(/\n/g, '<br>');
    descriptionEl.innerHTML = `${previousHtml}<p class="rank-card__description--error">AI説明の生成に失敗しました。</p><p class="rank-card__description--error-detail">${safeMessage}</p>`;
  };
  const closeModal = () => {
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    activeCard = null;
  };

  const closeSettingsModal = () => {
    if (!settingsModal) return;
    settingsModal.classList.remove('is-open');
    settingsModal.setAttribute('aria-hidden', 'true');
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

  const openSettingsModal = () => {
    if (!settingsModal) return;
    settingsModal.classList.add('is-open');
    settingsModal.setAttribute('aria-hidden', 'false');
    const firstField = settingsModal.querySelector('select, input, button');
    if (firstField instanceof HTMLElement) {
      firstField.focus();
    }
  };

  settingsOpen?.addEventListener('click', openSettingsModal);

  const syncPriceFilterFields = () => {
    if (!priceFilterToggle || priceInputs.length === 0) return;
    const enabled = priceFilterToggle.checked;
    priceInputs.forEach((input) => {
      input.disabled = !enabled;
      input.required = enabled;
    });
  };

  priceFilterToggle?.addEventListener('change', syncPriceFilterFields);
  syncPriceFilterFields();

  if (genreSlider) {
    const track = genreSlider.querySelector('[data-genre-track]');
    const slides = Array.from(genreSlider.querySelectorAll('[data-genre-slide]'));
    const prevButton = genreSlider.querySelector('[data-genre-prev]');
    const nextButton = genreSlider.querySelector('[data-genre-next]');
    const label = genreSlider.querySelector('[data-genre-label]');
    const countLabel = genreSlider.querySelector('[data-genre-count-label]');
    const dots = Array.from(genreSlider.querySelectorAll('[data-genre-dot]'));
    let currentIndex = 0;

    const updateSlider = () => {
      if (!track || slides.length === 0) return;
      track.style.transform = `translateX(${-currentIndex * 100}%)`;
      const currentSlide = slides[currentIndex];
      if (label && currentSlide) {
        label.textContent = currentSlide.dataset.genreName ?? '';
      }
      if (countLabel) {
        countLabel.textContent = `${currentIndex + 1} / ${slides.length}`;
      }
      if (prevButton) {
        prevButton.disabled = currentIndex === 0;
      }
      if (nextButton) {
        nextButton.disabled = currentIndex === slides.length - 1;
      }
      dots.forEach((dot, index) => {
        dot.classList.toggle('is-active', index === currentIndex);
        dot.setAttribute('aria-selected', index === currentIndex ? 'true' : 'false');
      });
    };

    const goToIndex = (index) => {
      if (index < 0 || index >= slides.length) return;
      currentIndex = index;
      updateSlider();
    };

    prevButton?.addEventListener('click', () => {
      goToIndex(currentIndex - 1);
    });

    nextButton?.addEventListener('click', () => {
      goToIndex(currentIndex + 1);
    });

    dots.forEach((dot) => {
      dot.addEventListener('click', () => {
        const index = Number(dot.dataset.genreIndex);
        if (!Number.isNaN(index)) {
          goToIndex(index);
        }
      });
    });

    if (slides.length <= 1) {
      prevButton?.setAttribute('aria-hidden', 'true');
      nextButton?.setAttribute('aria-hidden', 'true');
      dots.forEach((dot) => dot.setAttribute('aria-hidden', 'true'));
      countLabel?.setAttribute('aria-hidden', 'true');
    }

    updateSlider();
  }
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
        const limitText = Number.isFinite(aiDailyLimit) ? String(aiDailyLimit) : '3';
        const remainingText = Number.isFinite(aiRemaining) ? `${aiRemaining}回` : '不明';
        const confirmMessage = `AI説明を生成しますか？\n残り使用回数: ${remainingText}\n※1ユーザ${limitText}回まで`;
        if (!window.confirm(confirmMessage)) {
          return;
        }
        if (Number.isFinite(aiRemaining) && aiRemaining <= 0) {
          window.alert('本日の利用回数が上限に達しています。明日以降に再度お試しください。');
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
        fetch(`${apiBase}description_ai.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ item_code: itemCode }),
        })
          .then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) {
              const baseMessage =
                data.message || `AI説明の生成に失敗しました。(HTTP ${response.status})`;
              const detailMessage = data.detail ? `${baseMessage}\n${data.detail}` : baseMessage;
              throw new Error(detailMessage);
            }
            const description = (data.description ?? '').trim();
            if (!description) {
              throw new Error('AI説明が取得できませんでした。');
            }
            if (descriptionEl) {
              descriptionEl.dataset.description = description;
              descriptionEl.innerHTML = `<p>${escapeHtml(description).replace(/\n/g, '<br>')}</p>`;
            }
            if (Number.isFinite(aiRemaining) && aiRemaining > 0) {
              aiRemaining -= 1;
              document.body.dataset.aiRemaining = String(aiRemaining);
            }
          })
          .catch((error) => {
            const message =
              error instanceof Error ? error.message : 'AI説明の生成に失敗しました。';
            renderAiError(descriptionEl, previousHtml, previousDescription, message);
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
    if (target.closest('[data-settings-close]')) {
      closeSettingsModal();
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
      const response = await fetch(`${apiBase}description_save.php`, {
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