<?php
/**
 * Admin sidebar navigation map.
 *
 * @return list<array{id:string, label:string, href:string, icon:string}>
 */
declare(strict_types=1);

return [
    [
        'id'    => 'dashboard',
        'label' => 'Dashboard',
        'href'  => admin_url('/dashboard'),
        'icon'  => 'grid',
    ],
    [
        'id'    => 'users',
        'label' => 'Users',
        'href'  => admin_url('/dashboard/users'),
        'icon'  => 'users',
    ],
    [
        'id'    => 'purchases',
        'label' => 'Purchases',
        'href'  => admin_url('/dashboard/purchases'),
        'icon'  => 'package',
    ],
    [
        'id'    => 'withdrawals',
        'label' => 'Withdrawals',
        'href'  => admin_url('/dashboard/withdrawals'),
        'icon'  => 'package',
    ],
    [
        'id'    => 'withdrawal_permissions',
        'label' => 'WD Permissions',
        'href'  => admin_url('/dashboard/withdrawal-permissions'),
        'icon'  => 'lock',
    ],
    [
        'id'    => 'support',
        'label' => 'Support',
        'href'  => admin_url('/dashboard/support'),
        'icon'  => 'support',
    ],
    [
        'id'    => 'ppt',
        'label' => 'PPT upload',
        'href'  => admin_url('/dashboard/ppt'),
        'icon'  => 'ppt',
    ],
    [
        'id'    => 'guests',
        'label' => 'Guests',
        'href'  => admin_url('/dashboard/guests'),
        'icon'  => 'guest',
    ],
    [
        'id'    => 'cms',
        'label' => 'CMS',
        'href'  => admin_url('/dashboard/cms'),
        'icon'  => 'ppt',
    ],
    [
        'id'    => 'seo',
        'label' => 'SEO',
        'href'  => admin_url('/dashboard/seo'),
        'icon'  => 'settings',
    ],
    [
        'id'    => 'settings',
        'label' => 'Settings',
        'href'  => admin_url('/dashboard/settings'),
        'icon'  => 'settings',
    ],
];
