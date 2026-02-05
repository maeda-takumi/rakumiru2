(() => {
  const genreSelect = document.getElementById('genre-select');
  const form = genreSelect?.closest('form');
  const modal = document.getElementById('description-modal');
  const modalText = document.getElementById('description-modal-text');
  const modalSave = document.getElementById('description-modal-save');
  const modalStatus = document.getElementById('description-modal-status');
  const settingsModal = document.getElementById('settings-modal');
  const settingsOpen = document.getElementById('settings-open');
  const apiKeyModal = document.getElementById('api-key-modal');
  const apiKeyOpen = document.getElementById('api-key-open');
  const apiKeyInput = document.getElementById('api-key-input');
  const apiKeySave = document.getElementById('api-key-save');
  const apiKeyStatus = document.getElementById('api-key-status');
  const scrollTopButton = document.getElementById('scroll-top-button');
  const priceFilterToggle = document.getElementById('price-filter-enabled');
  const priceInputs = Array.from(document.querySelectorAll('[data-price-input]'));
  const priceFilterNote = settingsModal?.querySelector('[data-price-filter-note]');
  const genreSlider = document.querySelector('[data-genre-slider]');
  const apiBase = document.body?.dataset.apiBase || '/';
  const tutorialOverlay = document.getElementById('tutorial-overlay');
  const tutorialHighlight = tutorialOverlay?.querySelector('[data-tutorial-highlight]');
  const tutorialStepLabel = tutorialOverlay?.querySelector('[data-tutorial-step]');
  const tutorialText = tutorialOverlay?.querySelector('[data-tutorial-text]');
  const tutorialNext = tutorialOverlay?.querySelector('[data-tutorial-next]');
  const tutorialSkip = tutorialOverlay?.querySelector('[data-tutorial-skip]');
  const tutorialStart = document.getElementById('tutorial-start');
  const menuToggle = document.getElementById('menu-toggle');
  const globalDrawer = document.getElementById('global-drawer');
  const termsModal = document.getElementById('terms-modal');
  const termsModalContents = Array.from(document.querySelectorAll('#terms-modal [data-modal-content]'));
  let activeCard = null;
  let activeTutorialIndex = 0;
  let activeTutorialSteps = [];
  let activeGenreSlide = null;



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
  const showCooldownPopup = (message) => {
    window.alert(message);
  };
  const openDrawer = () => {
    if (!globalDrawer || !menuToggle) return;
    if (document.activeElement instanceof HTMLElement && globalDrawer.contains(document.activeElement)) {
      menuToggle.focus();
    }
    globalDrawer.classList.add('is-open');
    globalDrawer.setAttribute('aria-hidden', 'false');
    menuToggle.setAttribute('aria-expanded', 'true');
  };

  const closeDrawer = () => {
    if (!globalDrawer || !menuToggle) return;
    globalDrawer.classList.remove('is-open');
    globalDrawer.setAttribute('aria-hidden', 'true');
    menuToggle.setAttribute('aria-expanded', 'false');
  };

  const openTermsModal = (target) => {
    if (!termsModal || termsModalContents.length === 0) return;
    termsModalContents.forEach((section) => {
      section.hidden = section.dataset.modalContent !== target;
    });
    termsModal.classList.add('is-open');
    termsModal.setAttribute('aria-hidden', 'false');
    const closeButton = termsModal.querySelector('[data-terms-modal-close]');
    if (closeButton instanceof HTMLElement) {
      closeButton.focus();
    }
  };

  const closeTermsModal = () => {
    if (!termsModal) return;
    termsModal.classList.remove('is-open');
    termsModal.setAttribute('aria-hidden', 'true');
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

  const closeApiKeyModal = () => {
    if (!apiKeyModal || !apiKeyInput || !apiKeyStatus) return;
    apiKeyModal.classList.remove('is-open');
    apiKeyModal.setAttribute('aria-hidden', 'true');
    apiKeyStatus.textContent = '';
    apiKeyInput.value = '';
  };
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const tutorialStorageKey = 'rakumiruTutorialSeen';
  const canUseStorage = (() => {
    try {
      const testKey = '__rakumiru_tutorial__';
      localStorage.setItem(testKey, '1');
      localStorage.removeItem(testKey);
      return true;
    } catch (error) {
      return false;
    }
  })();
  const updateScrollTopButton = () => {
    if (!scrollTopButton) return;
    scrollTopButton.classList.toggle('is-visible', window.scrollY > 200);
  };

  scrollTopButton?.addEventListener('click', () => {
    const behavior = prefersReducedMotion.matches ? 'auto' : 'smooth';
    window.scrollTo({ top: 0, behavior });
  });
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
  apiKeyOpen?.addEventListener('click', () => {
    if (!apiKeyModal || !apiKeyInput) return;
    apiKeyModal.classList.add('is-open');
    apiKeyModal.setAttribute('aria-hidden', 'false');
    apiKeyInput.focus();
  });

  menuToggle?.addEventListener('click', () => {
    if (!globalDrawer) return;
    if (globalDrawer.classList.contains('is-open')) {
      closeDrawer();
      return;
    }
    openDrawer();
  });
  const syncPriceFilterFields = () => {
    if (!priceFilterToggle || priceInputs.length === 0) return;
    const enabled = priceFilterToggle.checked;
    priceInputs.forEach((input) => {
      input.disabled = !enabled;
      input.required = enabled;
    });
  };

  const buildTutorialSteps = () => [
    {
      selector: '#settings-open',
      text: '⚙設定ボタンからジャンルや絞り込み条件を変更できます。',
      scope: 'document',
    },
    {
      selector: '.genre-slider__controls',
      text: 'ジャンルスライダーで表示するジャンルを切り替えられます。',
      scope: 'document',
    },
    {
      selector: '.rank-card__title',
      text: '商品名をクリックすると楽天Roomの投稿ページが開きます。',
      scope: 'active',
    },
    {
      selector: '[data-action="ai-description"]',
      text: 'AIボタンでAIが投稿文を自動生成します。',
      scope: 'active',
    },
    {
      selector: '[data-action="edit-description"]',
      text: '編集ボタンで投稿文を入力・編集します。',
      scope: 'active',
    },
    {
      selector: '[data-action="copy-description"]',
      text: 'コピーボタンで投稿文をコピーできます。',
      scope: 'active',
    },
  ];

  const resolveTutorialSteps = () => {
    const activeSlide =
      activeGenreSlide ?? genreSlider?.querySelector('[data-genre-slide].is-active');
    return buildTutorialSteps()
      .map((step) => {
        const root =
          step.scope === 'active' && activeSlide instanceof Element ? activeSlide : document;
        const element = root.querySelector(step.selector);
        if (!element && step.scope === 'active') {
          const fallback = document.querySelector(step.selector);
          if (!fallback) return null;
          return { ...step, element: fallback };
        }
        if (!element) return null;
        return { ...step, element };
      })
      .filter(Boolean);
  };

  const updateTutorialPosition = (element) => {
    if (!tutorialHighlight || !tutorialOverlay) return;
    const padding = 8;
    const rect = element.getBoundingClientRect();
    tutorialHighlight.style.width = `${rect.width + padding * 2}px`;
    tutorialHighlight.style.height = `${rect.height + padding * 2}px`;
    tutorialHighlight.style.left = `${Math.max(rect.left - padding, 8)}px`;
    tutorialHighlight.style.top = `${Math.max(rect.top - padding, 8)}px`;
  };

  const updateTutorialCardPosition = (element) => {
    if (!tutorialOverlay) return;
    const card = tutorialOverlay.querySelector('.tutorial-card');
    if (!card) return;
    const rect = element.getBoundingClientRect();
    const maxWidth = Math.min(320, window.innerWidth - 32);
    card.style.maxWidth = `${maxWidth}px`;
    card.style.width = `${maxWidth}px`;
    const cardRect = card.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const preferredTop =
      spaceBelow > cardRect.height + 24
        ? rect.bottom + 16
        : rect.top - cardRect.height - 16;
    const top = Math.min(
      Math.max(preferredTop, 16),
      window.innerHeight - cardRect.height - 16
    );
    const left = Math.min(
      Math.max(rect.left, 16),
      window.innerWidth - cardRect.width - 16
    );
    card.style.top = `${top}px`;
    card.style.left = `${left}px`;
  };

  const repositionActiveTutorial = () => {
    if (!tutorialOverlay?.classList.contains('is-active')) return;
    const step = activeTutorialSteps[activeTutorialIndex];
    if (!step) return;
    updateTutorialPosition(step.element);
    updateTutorialCardPosition(step.element);
  };

  let tutorialRepositionRaf = null;
  const scheduleTutorialReposition = () => {
    if (tutorialRepositionRaf) return;
    tutorialRepositionRaf = window.requestAnimationFrame(() => {
      tutorialRepositionRaf = null;
      repositionActiveTutorial();
    });
  };
  const showTutorialStep = (index) => {
    if (!tutorialOverlay || !tutorialStepLabel || !tutorialText || !tutorialNext) return;
    const step = activeTutorialSteps[index];
    if (!step) return;
    activeTutorialIndex = index;
    const behavior = prefersReducedMotion.matches ? 'auto' : 'smooth';
    step.element.scrollIntoView({ behavior, block: 'center' });
    tutorialStepLabel.textContent = `ステップ ${index + 1} / ${activeTutorialSteps.length}`;
    tutorialText.textContent = step.text;
    tutorialNext.textContent =
      index === activeTutorialSteps.length - 1 ? '終了' : 'OK';
    tutorialOverlay.classList.add('is-active');
    tutorialOverlay.setAttribute('aria-hidden', 'false');
    window.setTimeout(() => {
      updateTutorialPosition(step.element);
      updateTutorialCardPosition(step.element);
    }, 200);
  };

  const endTutorial = () => {
    if (!tutorialOverlay) return;
    tutorialOverlay.classList.remove('is-active');
    tutorialOverlay.setAttribute('aria-hidden', 'true');
    if (canUseStorage) {
      localStorage.setItem(tutorialStorageKey, '1');
    }
  };

  const startTutorial = () => {
    if (!tutorialOverlay) return;
    activeTutorialSteps = resolveTutorialSteps();
    if (activeTutorialSteps.length === 0) return;
    showTutorialStep(0);
  };
  priceFilterToggle?.addEventListener('change', syncPriceFilterFields);
  syncPriceFilterFields();
  updateScrollTopButton();
  tutorialNext?.addEventListener('click', () => {
    if (activeTutorialIndex >= activeTutorialSteps.length - 1) {
      endTutorial();
      return;
    }
    showTutorialStep(activeTutorialIndex + 1);
  });
  tutorialSkip?.addEventListener('click', endTutorial);
  tutorialStart?.addEventListener('click', () => {
    closeDrawer();
    if (tutorialOverlay?.classList.contains('is-active')) {
      endTutorial();
    }
    startTutorial();
  });
  window.addEventListener('resize', scheduleTutorialReposition);
  document.addEventListener('scroll', scheduleTutorialReposition, true);
  window.visualViewport?.addEventListener('resize', scheduleTutorialReposition);
  window.visualViewport?.addEventListener('scroll', scheduleTutorialReposition);
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (tutorialOverlay?.classList.contains('is-active')) {
      endTutorial();
      return;
    }
    if (termsModal?.classList.contains('is-open')) {
      closeTermsModal();
      return;
    }
    if (globalDrawer?.classList.contains('is-open')) {
      closeDrawer();
    }
  });

  if (genreSlider) {
    const track = genreSlider.querySelector('[data-genre-track]');
    const slides = Array.from(genreSlider.querySelectorAll('[data-genre-slide]'));
    const prevButton = genreSlider.querySelector('[data-genre-prev]');
    const nextButton = genreSlider.querySelector('[data-genre-next]');
    const label = genreSlider.querySelector('[data-genre-label]');
    const countLabel = genreSlider.querySelector('[data-genre-count-label]');
    const dots = Array.from(genreSlider.querySelectorAll('[data-genre-dot]'));
    const sortKeySelect = genreSlider.querySelector('[data-sort-key]');
    const sortOrderSelect = genreSlider.querySelector('[data-sort-order]');
    let currentIndex = 0;

    const parseNumber = (value) => {
      const parsed = Number(value);
      return Number.isFinite(parsed) ? parsed : 0;
    };

    const sortRankCards = () => {
      const sortKey = sortKeySelect?.value ?? 'rank';
      const sortOrder = sortOrderSelect?.value ?? 'asc';
      const multiplier = sortOrder === 'desc' ? -1 : 1;

      slides.forEach((slide) => {
        const list = slide.querySelector('.ranking-list');
        if (!list) return;
        const cards = Array.from(list.querySelectorAll('.rank-card'));
        cards.sort((a, b) => {
          const aValue =
            sortKey === 'reviews'
              ? parseNumber(a.dataset.reviewCount)
              : parseNumber(a.dataset.rankPos);
          const bValue =
            sortKey === 'reviews'
              ? parseNumber(b.dataset.reviewCount)
              : parseNumber(b.dataset.rankPos);
          if (aValue === bValue) {
            return parseNumber(a.dataset.rankPos) - parseNumber(b.dataset.rankPos);
          }
          return (aValue - bValue) * multiplier;
        });
        cards.forEach((card) => list.appendChild(card));
      });
    };
    const updateSlider = () => {
      if (!track || slides.length === 0) return;
      track.style.transform = `translateX(${-currentIndex * 100}%)`;
      const currentSlide = slides[currentIndex];
      activeGenreSlide = currentSlide ?? null;
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
      slides.forEach((slide, index) => {
        const isActive = index === currentIndex;
        slide.classList.toggle('is-active', isActive);
        slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
      });
    };

    const goToIndex = (index) => {
      if (index < 0 || index >= slides.length) return;
      currentIndex = index;
      updateSlider();
      if (tutorialOverlay?.classList.contains('is-active')) {
        activeTutorialSteps = resolveTutorialSteps();
        if (activeTutorialSteps.length === 0) return;
        const nextIndex = Math.min(activeTutorialIndex, activeTutorialSteps.length - 1);
        showTutorialStep(nextIndex);
      }
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
    sortRankCards();

    sortKeySelect?.addEventListener('change', sortRankCards);
    sortOrderSelect?.addEventListener('change', sortRankCards);
  }
  if (!canUseStorage || localStorage.getItem(tutorialStorageKey) !== '1') {
    startTutorial();
  }
  document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const drawerCloseButton = target.closest('[data-drawer-close]');
    if (drawerCloseButton) {
      closeDrawer();
      return;
    }

    const termsModalTrigger = target.closest('[data-modal-target]');
    if (termsModalTrigger) {
      const targetType = termsModalTrigger.dataset.modalTarget;
      if (targetType === 'terms' || targetType === 'privacy') {
        openTermsModal(targetType);
        return;
      }
    }

    const menuAction = target.closest('[data-menu-action]');
    if (menuAction?.dataset.menuAction === 'tutorial' && tutorialStart) {
      tutorialStart.click();
      closeDrawer();
      return;
    }
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
            () => window.alert('投稿文をコピーしました。'),
            () => window.alert('コピーに失敗しました。')
          );
        } else {
          const textarea = document.createElement('textarea');
          textarea.value = text;
          document.body.appendChild(textarea);
          textarea.select();
          try {
            document.execCommand('copy');
            window.alert('投稿文をコピーしました。');
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
          descriptionEl.innerHTML = '<p class="rank-card__description--empty">AIで投稿文を生成中...</p>';
        }
        fetch(`${apiBase}description_ai.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ item_code: itemCode }),
        })
          .then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (response.status === 429 && data.message) {
              const cooldownError = new Error(data.message);
              cooldownError.isCooldown = true;
              throw cooldownError;
            }
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
          })
          .catch((error) => {
            const message =
              error instanceof Error ? error.message : 'AI説明の生成に失敗しました。';
            if (error instanceof Error && error.isCooldown) {
              if (descriptionEl) {
                descriptionEl.dataset.description = previousDescription;
                descriptionEl.innerHTML = previousHtml;
              }
              showCooldownPopup(message);
              return;
            }
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
    if (target.closest('[data-terms-modal-close]')) {
      closeTermsModal();
    }
    if (target.closest('[data-settings-close]')) {
      closeSettingsModal();
    }
    if (target.closest('[data-api-key-close]')) {
      closeApiKeyModal();
    }
  });

  window.addEventListener('scroll', updateScrollTopButton, { passive: true });
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
  apiKeySave?.addEventListener('click', async () => {
    if (!apiKeyInput || !apiKeySave || !apiKeyStatus) return;
    const apiKey = apiKeyInput.value.trim();
    if (!apiKey) {
      apiKeyStatus.textContent = 'APIキーを入力してください。';
      return;
    }
    apiKeySave.disabled = true;
    apiKeyStatus.textContent = '確認中...';
    try {
      const response = await fetch(`${apiBase}gemini_key_save.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ api_key: apiKey }),
      });
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || '保存に失敗しました。');
      }
      apiKeyStatus.textContent = '保存しました。';
      setTimeout(closeApiKeyModal, 600);
    } catch (error) {
      apiKeyStatus.textContent = error instanceof Error ? error.message : '保存に失敗しました。';
    } finally {
      apiKeySave.disabled = false;
    }
  });
})();