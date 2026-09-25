(function () {
  const stage = document.getElementById("pptStage");
  if (!stage) return;

  document.getElementById("pptFullscreen")?.addEventListener("click", async () => {
    try {
      if (!document.fullscreenElement && !document.webkitFullscreenElement) {
        if (stage.requestFullscreen) await stage.requestFullscreen();
        else if (stage.webkitRequestFullscreen) stage.webkitRequestFullscreen();
      } else if (document.exitFullscreen) {
        await document.exitFullscreen();
      } else if (document.webkitExitFullscreen) {
        document.webkitExitFullscreen();
      }
    } catch (_) {
      /* ignore */
    }
  });
})();
