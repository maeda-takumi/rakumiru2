  </main>
  <footer class="app-footer">
    <div class="container">
      <!-- Rakuten Web Services Attribution Snippet FROM HERE -->
      <a href="https://developers.rakuten.com/" target="_blank">Supported by Rakuten Developers</a>
      <!-- Rakuten Web Services Attribution Snippet TO HERE -->

    </div>
  </footer>
  <button class="scroll-top-button" id="scroll-top-button" aria-label="ページトップへ">
    <img src="img/top.png" alt="" />
  </button>
  <div class="modal" id="api-key-modal" aria-hidden="true">
    <div class="modal__overlay" data-api-key-close></div>
    <div class="modal__panel modal__panel--password" role="dialog" aria-modal="true" aria-labelledby="api-key-modal-title">
      <div class="modal__header">
        <h3 id="api-key-modal-title">Gemini APIキー</h3>
        <button type="button" class="modal__close" data-api-key-close aria-label="閉じる">×</button>
      </div>
      <input type="hidden" id="api-key-user-id" value="<?= htmlspecialchars((string) ($userId ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <label class="modal__field">
        <span>APIキー</span>
        <input type="password" id="api-key-input" placeholder="Gemini APIキーを入力" autocomplete="off">
      </label>
      <div class="modal__actions">
        <button type="button" class="modal__button modal__button--ghost" data-api-key-close>キャンセル</button>
        <button type="button" class="modal__button" id="api-key-save">保存</button>
      </div>
      <p class="modal__status" id="api-key-status" aria-live="polite"></p>
    </div>
  </div>
  <script src="js/app.js?v=<?= time() ?>"></script>
</body>
</html>