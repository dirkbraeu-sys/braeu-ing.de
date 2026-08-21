(function () {
  // Datenschutzfreundliches Eigen-Tracking: keine IP-Speicherung, kein
  // Cross-Site-Tracking, Cookie nur 1 Tag gültig. Respektiert Do-Not-Track.
  if (navigator.doNotTrack === '1' || window.doNotTrack === '1') { return; }
  try {
    fetch('/stats/track.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        path: location.pathname,
        ref: document.referrer
      }),
      credentials: 'same-origin',
      keepalive: true
    }).catch(function () {});
  } catch (e) {}
})();
