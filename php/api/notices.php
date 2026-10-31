<?php
/**
 * GET /php/api/notices.php
 * ------------------------
 * Published notices with search, category filter, sorting and pagination.
 *
 * Query parameters (all optional):
 *   q          search title / content
 *   category   category name
 *   active     1 = hide expired notices
 *   sort       date | title
 *   page       1..
 *   per_page   1-200 (default 10)
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../serializers.php';

require_login();

try {
    [$page, $perPage, $offset] = pagination(10, 200);

    $where = ['n.is_published = 1'];
    $types = '';
    $params = [];

    $q = query_string('q');
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(n.title LIKE ? OR n.content LIKE ?)';
        $types .= 'ss';
        array_push($params, $like, $like);
    }

    $category = query_string('category');
    if ($category !== '' && strcasecmp($category, 'All') !== 0) {
        $where[] = 'c.name = ?';
        $types .= 's';
        $params[] = $category;
    }

    if (query_bool('active')) {
        $where[] = '(n.expires_at IS NULL OR n.expires_at >= NOW())';
    }

    $sortMap = [
        'date'  => 'n.is_pinned DESC, n.published_at DESC, n.id DESC',
        'title' => 'n.title ASC',
    ];
    $sort = query_string('sort', 'date');
    $orderBy = $sortMap[$sort] ?? $sortMap['date'];

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $total = (int) db_value(
        'SELECT COUNT(*) FROM notices n LEFT JOIN notice_categories c ON c.id = n.category_id' . $whereSql,
        $types,
        $params
    );

    $rows = db_query(
        'SELECT n.id, n.title, n.content, n.is_pinned, n.published_at, n.expires_at,
                c.name AS category, c.color AS category_color
         FROM notices n
         LEFT JOIN notice_categories c ON c.id = n.category_id' . $whereSql . '
         ORDER BY ' . $orderBy . '
         LIMIT ? OFFSET ?',
        $types . 'ii',
        array_merge($params, [$perPage, $offset])
    );

    json_success(
        array_map('notice_to_api', $rows),
        'Notices loaded successfully.',
        pagination_meta($page, $perPage, $total)
    );
} catch (Throwable $error) {
    json_handle_exception($error);
}
