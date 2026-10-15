(() => {
  const theme = new URLSearchParams(location.search).get('palette');
  if (!['teal', 'burgundy'].includes(theme)) return;
  document.documentElement.dataset.palette = theme;
})();
