<?php
declare(strict_types=1);
?>
      </main>
    </div>
  </div>
  <script>
    window.AROWIN_BASE_PATH = <?= json_encode((string) (parse_url($BASE, PHP_URL_PATH) ?: ''), JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="<?= e(admin_asset('js/admin.js')) ?>" defer></script>
</body>
</html>
