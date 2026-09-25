<?php
declare(strict_types=1);

$bnavPage = (string) ($DASHBOARD_PAGE ?? 'dashboard');

/** Same routes / actions — UI layout only (center FAB = Deposit). */
$bnavLeft = [
    [
        'key'   => 'dashboard',
        'label' => 'Home',
        'href'  => url('dashboard'),
        'icon'  => 'home',
    ],
    [
        'key'   => 'transfer',
        'label' => 'Transfer',
        'href'  => '#transfer',
        'modal' => 'transfer',
        'icon'  => 'transfer',
    ],
];
$bnavCenter = [
    'key'   => 'deposit',
    'label' => 'Deposit',
    'href'  => '#deposit',
    'modal' => 'deposit',
    'icon'  => 'deposit',
];
$bnavRight = [
    [
        'key'   => 'withdrawal',
        'label' => 'Withdrawal',
        'href'  => '#withdrawal',
        'modal' => 'withdrawal',
        'icon'  => 'withdrawal',
    ],
    [
        'key'   => 'history',
        'label' => 'History',
        'href'  => url('history'),
        'icon'  => 'history',
    ],
];

/**
 * @param string $name
 */
if (!function_exists('arowin_bnav_icon')) {
function arowin_bnav_icon(string $name): string
{
    switch ($name) {
        case 'home':
            return '<svg class="bnav-ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V20h13V9.5"/><path d="M10 20v-6h4v6"/></svg>';
        case 'deposit':
            return '<svg class="bnav-ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/><path d="m9 13 3 3 3-3"/></svg>';
        case 'transfer':
            return '<svg class="bnav-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 3h5v5"/><path d="m21 3-9 9"/><path d="M8 21H3v-5"/><path d="m3 21 9-9"/></svg>';
        case 'withdrawal':
            return '<svg class="bnav-ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16V8"/><path d="m9 11 3-3 3 3"/></svg>';
        case 'history':
            return '<svg class="bnav-ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/></svg>';
        default:
            return '<svg class="bnav-ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8"/></svg>';
    }
}
}

/**
 * @param array{key:string,label:string,href:string,modal?:string,icon:string} $item
 * @param bool $isFab
 */
if (!function_exists('arowin_bnav_render_item')) {
function arowin_bnav_render_item(array $item, string $bnavPage, bool $isFab = false): void
{
    $isActive = $bnavPage === $item['key'];
    $modalKey = (string) ($item['modal'] ?? '');
    $isModal = $modalKey === 'deposit' || $modalKey === 'transfer' || $modalKey === 'withdrawal';
    $cls = 'bnav-item' . ($isFab ? ' bnav-fab' : '') . ($isActive ? ' active' : '');
    $icon = arowin_bnav_icon((string) ($item['icon'] ?? 'home'));
    $label = e($item['label']);

    if ($isModal) {
        if ($modalKey === 'transfer') {
            $onclick = 'openTransferModal()';
        } elseif ($modalKey === 'withdrawal') {
            $onclick = 'openWithdrawalModal()';
        } else {
            $onclick = 'openDepositModal()';
        }
        echo '<button type="button" class="' . e($cls) . '" onclick="' . $onclick . '">';
        if ($isFab) {
            echo '<span class="bnav-fab-btn" aria-hidden="true">' . $icon . '</span>';
        } else {
            echo $icon;
        }
        echo '<span class="bnav-label">' . $label . '</span>';
        echo '</button>';
        return;
    }

    echo '<a class="' . e($cls) . '" href="' . e($item['href']) . '"' . ($isActive ? ' aria-current="page"' : '') . '>';
    if ($isFab) {
        echo '<span class="bnav-fab-btn" aria-hidden="true">' . $icon . '</span>';
        echo '<span class="bnav-label bnav-fab-label">' . $label . '</span>';
    } else {
        echo $icon;
        echo '<span class="bnav-label">' . $label . '</span>';
    }
    echo '</a>';
}
}
?>
<!-- BOTTOM NAV (mobile only) -->
    <nav class="bottom-nav" aria-label="Funds navigation">
      <div class="bottom-nav-inner">
        <div class="bnav-side bnav-side-left">
          <?php foreach ($bnavLeft as $item): ?>
            <?php arowin_bnav_render_item($item, $bnavPage, false); ?>
          <?php endforeach; ?>
        </div>
        <div class="bnav-center">
          <?php arowin_bnav_render_item($bnavCenter, $bnavPage, true); ?>
        </div>
        <div class="bnav-side bnav-side-right">
          <?php foreach ($bnavRight as $item): ?>
            <?php arowin_bnav_render_item($item, $bnavPage, false); ?>
          <?php endforeach; ?>
        </div>
      </div>
    </nav>

    <?php
    // Ensure $user/$view exist for balance label when possible
    if (!isset($user) && !empty($_SESSION['arowin_uid']) && function_exists('arowin_get_user_by_username')) {
        $user = arowin_get_user_by_username((string) $_SESSION['arowin_uid']);
    }
    if (!isset($view) && !empty($user) && function_exists('arowin_dashboard_view_data')) {
        $view = arowin_dashboard_view_data($user);
    }
    require __DIR__ . '/deposit-modal.php';
    require __DIR__ . '/transfer-modal.php';
    require __DIR__ . '/withdrawal-modal.php';
    if (function_exists('arowin_print_tab_account_boot')) {
        arowin_print_tab_account_boot();
    }
    ?>
