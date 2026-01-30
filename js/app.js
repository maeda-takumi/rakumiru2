const ghostButtons = document.querySelectorAll('.ghost-button');

ghostButtons.forEach((button) => {
  button.addEventListener('click', () => {
    button.classList.toggle('is-active');
  });
});