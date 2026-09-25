<?php
/**
 * Admin — settings — /admin/dashboard/settings
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'settings';
$ADMIN_TITLE = 'Settings';

$appName = (string) ($APP['name'] ?? 'Arowin Trading Portal');
$usdtAddress = (string) env('AROWIN_USDT_ADDRESS', '');
$usdtNetwork = (string) env('AROWIN_USDT_NETWORK', 'TRC20');
$depositMode = (string) env('DEPOSIT_MODE', 'test');
$adminUser = (string) env('ADMIN_USER', '');

require dirname(__DIR__, 2) . '/includes/layout-start.php';
?>

<section class="admin-panel">
  <div class="admin-panel-head">
    <h2>Portal configuration</h2>
  </div>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <tbody>
        <tr>
          <th style="width:220px;">App name</th>
          <td><?= e($appName) ?></td>
        </tr>
        <tr>
          <th>Admin user</th>
          <td><?= e($adminUser !== '' ? $adminUser : '—') ?></td>
        </tr>
        <tr>
          <th>USDT network</th>
          <td><?= e($usdtNetwork) ?></td>
        </tr>
        <tr>
          <th>USDT address</th>
          <td><code><?= e($usdtAddress !== '' ? $usdtAddress : 'Not set') ?></code></td>
        </tr>
        <tr>
          <th>Deposit mode</th>
          <td><?= e($depositMode) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</section>

<?php require dirname(__DIR__, 2) . '/includes/layout-end.php'; ?>
