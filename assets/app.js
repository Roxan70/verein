'use strict';

document.querySelectorAll('.alert').forEach((el) => {
  setTimeout(() => {
    el.style.opacity = '0.95';
  }, 10);
});
