setInterval(() => {
  const auto = document.querySelector('[data-auto-refresh="true"]');
  if (auto) location.reload();
}, 12000);
