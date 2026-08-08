<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');

$filters = application_filters_from_request($_GET);
$listLimit = application_list_limit_from_request($_GET, 10);
$currentPage = application_page_from_request($_GET);
$offset = $listLimit > 0 ? ($currentPage - 1) * $listLimit : 0;
$rows = fetch_admin_applications(db(), $filters, $listLimit > 0 ? $listLimit + 1 : 0, $offset);
$hasNextPage = $listLimit > 0 && count($rows) > $listLimit;
if ($hasNextPage) {
    $rows = array_slice($rows, 0, $listLimit);
}

json_response([
    'rows' => render_admin_application_rows($rows),
    'pagination' => render_admin_application_pagination($currentPage, $listLimit, $hasNextPage),
]);
