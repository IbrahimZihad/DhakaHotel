<?php
/**
 * Public language preference endpoint (used by progressive enhancement).
 * The plain `?lang=` link works without JavaScript; this keeps an
 * already-rendered page in sync without a reload.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (!is_post()) {
    json_response(['ok' => false, 'message' => __('error.method_not_allowed')], 405);
}

require_csrf();

$lang = strtolower(trim((string) ($_POST['lang'] ?? '')));
if (!in_array($lang, available_languages(), true)) {
    json_response(['ok' => false, 'message' => __('error.invalid_request')], 422);
}

set_language($lang, false);

json_response([
    'ok' => true,
    'lang' => $lang,
    'dict' => dictionary($lang),
]);
