<?php
/**
 * GET /php/api/faqs.php
 * ---------------------
 * Active FAQs with search, category filter, sorting and pagination.
 *
 * Query parameters (all optional):
 *   q          search question / answer
 *   category   category name
 *   sort       order | question
 *   page       1..
 *   per_page   1-200 (default 10)
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../serializers.php';

require_login();

try {
    [$page, $perPage, $offset] = pagination(10, 200);

    $where = ['f.is_active = 1'];
    $types = '';
    $params = [];

    $q = query_string('q');
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(f.question LIKE ? OR f.answer LIKE ?)';
        $types .= 'ss';
        array_push($params, $like, $like);
    }

    $category = query_string('category');
    if ($category !== '' && strcasecmp($category, 'All') !== 0) {
        $where[] = 'c.name = ?';
        $types .= 's';
        $params[] = $category;
    }

    $sortMap = [
        'order'    => 'f.display_order ASC, f.id ASC',
        'question' => 'f.question ASC',
    ];
    $sort = query_string('sort', 'order');
    $orderBy = $sortMap[$sort] ?? $sortMap['order'];

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $total = (int) db_value(
        'SELECT COUNT(*) FROM faqs f LEFT JOIN faq_categories c ON c.id = f.category_id' . $whereSql,
        $types,
        $params
    );

    $rows = db_query(
        'SELECT f.id, f.question, f.answer, f.display_order, c.name AS category
         FROM faqs f
         LEFT JOIN faq_categories c ON c.id = f.category_id' . $whereSql . '
         ORDER BY ' . $orderBy . '
         LIMIT ? OFFSET ?',
        $types . 'ii',
        array_merge($params, [$perPage, $offset])
    );

    json_success(
        array_map('faq_to_api', $rows),
        'FAQs loaded successfully.',
        pagination_meta($page, $perPage, $total)
    );
} catch (Throwable $error) {
    json_handle_exception($error);
}
