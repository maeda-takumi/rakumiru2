(() => {
  const parentSelect = document.getElementById('parent-select');
  const childSelect = document.getElementById('child-select');
  const form = parentSelect?.closest('form');

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
})();