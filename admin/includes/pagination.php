<?php
/**
 * Admin list pagination — 10 rows per page (shared).
 */
declare(strict_types=1);

if (!function_exists('arowin_admin_per_page')) {
    function arowin_admin_per_page(): int
    {
        return 10;
    }
}

if (!function_exists('arowin_admin_page_num')) {
    function arowin_admin_page_num(string $param = 'page'): int
    {
        return max(1, (int) ($_GET[$param] ?? 1));
    }
}

/**
 * Paginate an in-memory list.
 *
 * @template T
 * @param list<T> $all
 * @return array{
 *   rows: list<T>,
 *   page: int,
 *   pages: int,
 *   total: int,
 *   per_page: int,
 *   from: int,
 *   to: int
 * }
 */
if (!function_exists('arowin_admin_paginate')) {
    function arowin_admin_paginate(array $all, ?int $page = null, ?int $perPage = null): array
    {
        $perPage = $perPage ?? arowin_admin_per_page();
        $perPage = max(1, min(100, $perPage));
        $total = count($all);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = $page ?? arowin_admin_page_num();
        $page = max(1, min($pages, $page));
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($all, $offset, $perPage);
        $from = $total === 0 ? 0 : $offset + 1;
        $to = $total === 0 ? 0 : min($total, $offset + count($slice));

        return [
            'rows'     => $slice,
            'page'     => $page,
            'pages'    => $pages,
            'total'    => $total,
            'per_page' => $perPage,
            'from'     => $from,
            'to'       => $to,
        ];
    }
}

/**
 * Build pager URL preserving current query string.
 */
if (!function_exists('arowin_admin_page_url')) {
    function arowin_admin_page_url(int $page, string $param = 'page'): string
    {
        $qs = $_GET;
        if (!is_array($qs)) {
            $qs = [];
        }
        $qs[$param] = $page;
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        if ($path === '') {
            $path = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        }
        return $path . '?' . http_build_query($qs);
    }
}

/**
 * Render pagination controls. Returns empty string if only one page.
 *
 * @param array{page:int,pages:int,total:int,from?:int,to?:int,per_page?:int} $pager
 */
if (!function_exists('arowin_admin_pagination_html')) {
    function arowin_admin_pagination_html(array $pager, string $param = 'page'): string
    {
        $total = (int) ($pager['total'] ?? 0);
        $pages = (int) ($pager['pages'] ?? 1);
        $page = (int) ($pager['page'] ?? 1);
        $from = (int) ($pager['from'] ?? 0);
        $to = (int) ($pager['to'] ?? 0);

        if ($total < 1) {
            return '';
        }

        $meta = '<p class="admin-pager-meta">Showing <strong>' . $from . '–' . $to . '</strong> of <strong>' . $total . '</strong></p>';
        if ($pages <= 1) {
            return '<nav class="admin-pager" aria-label="Pagination">' . $meta . '</nav>';
        }

        $html = '<nav class="admin-pager" aria-label="Pagination">';
        $html .= $meta;
        $html .= '<div class="admin-pager-links">';

        if ($page > 1) {
            $html .= '<a class="admin-pager-btn" href="' . htmlspecialchars(arowin_admin_page_url($page - 1, $param), ENT_QUOTES, 'UTF-8') . '" rel="prev">Previous</a>';
        } else {
            $html .= '<span class="admin-pager-btn is-disabled" aria-disabled="true">Previous</span>';
        }

        $window = 2;
        $start = max(1, $page - $window);
        $end = min($pages, $page + $window);
        if ($start > 1) {
            $html .= '<a class="admin-pager-num" href="' . htmlspecialchars(arowin_admin_page_url(1, $param), ENT_QUOTES, 'UTF-8') . '">1</a>';
            if ($start > 2) {
                $html .= '<span class="admin-pager-ellipsis">…</span>';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            if ($i === $page) {
                $html .= '<span class="admin-pager-num is-active" aria-current="page">' . $i . '</span>';
            } else {
                $html .= '<a class="admin-pager-num" href="' . htmlspecialchars(arowin_admin_page_url($i, $param), ENT_QUOTES, 'UTF-8') . '">' . $i . '</a>';
            }
        }
        if ($end < $pages) {
            if ($end < $pages - 1) {
                $html .= '<span class="admin-pager-ellipsis">…</span>';
            }
            $html .= '<a class="admin-pager-num" href="' . htmlspecialchars(arowin_admin_page_url($pages, $param), ENT_QUOTES, 'UTF-8') . '">' . $pages . '</a>';
        }

        if ($page < $pages) {
            $html .= '<a class="admin-pager-btn" href="' . htmlspecialchars(arowin_admin_page_url($page + 1, $param), ENT_QUOTES, 'UTF-8') . '" rel="next">Next</a>';
        } else {
            $html .= '<span class="admin-pager-btn is-disabled" aria-disabled="true">Next</span>';
        }

        $html .= '</div></nav>';
        return $html;
    }
}
