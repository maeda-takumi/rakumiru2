  </main>
  <footer class="app-footer">
    <div class="container">
      <p>RAKUMiRU</p>
    </div>
  </footer>
  <div class="modal" id="api-key-modal" aria-hidden="true">
    <div class="modal__overlay" data-api-key-close></div>
    <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="api-key-modal-title">
      <div class="modal__header">
        <h3 id="api-key-modal-title">Gemini APIキー</h3>
        <button type="button" class="modal__close" data-api-key-close aria-label="閉じる">×</button>
      </div>
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