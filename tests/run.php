<?php
/**
 * Dependency-free test runner for the parts that do not need MySQL.
 * Run: php tests/run.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$passed = 0;
$failed = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  ok  " . $label . PHP_EOL;
        return;
    }

    $failed++;
    echo "FAIL  " . $label . ($detail !== '' ? ' (' . $detail . ')' : '') . PHP_EOL;
}

echo "Money + input" . PHP_EOL;

check('decimal round trip', decimal_from_cents(cents_from_decimal('1234.50')) === '1234.50');
check('Bangla money', money_lang(123456, 'bn') === '৳১,২৩৪.৫৬', money_lang(123456, 'bn'));
check('English money', money_lang(123456, 'en') === '৳1,234.56', money_lang(123456, 'en'));
check('zero money', money_lang(0, 'en') === '৳0', money_lang(0, 'en'));
check('Bangla numerals parse', parse_money_input('১২৩.৫০') === 12350);
check('comma is rejected', parse_money_input('1,200') === null);
check('negative is rejected', parse_money_input('-5') === null);
check('three decimals rejected', parse_money_input('10.005') === null);

echo PHP_EOL . "Billing" . PHP_EOL;

$lines = [
    ['unit_price_cents' => 10000, 'quantity' => 2],
    ['unit_price_cents' => 2500, 'quantity' => 1],
];
$bill = compute_bill($lines, [
    'discount_cents' => 500,
    'delivery_fee_cents' => 3000,
    'tax_basis_points' => 500,
]);

check('subtotal', $bill['subtotal'] === 22500, (string) $bill['subtotal']);
check('discount applied', $bill['discount'] === 500);
check('tax on discounted subtotal', $bill['tax'] === 1100, (string) $bill['tax']);
check('grand total', $bill['grand_total'] === 26100, (string) $bill['grand_total']);
check('item count', $bill['item_count'] === 3, (string) $bill['item_count']);

$over = compute_bill($lines, ['discount_cents' => 999999]);
check('discount capped', $over['discount'] === 22500);
check('over-discount flagged', in_array('discount_exceeds_subtotal', $over['errors'], true));

$empty = compute_bill([]);
check('empty bill rejected', $empty['ok'] === false && in_array('empty', $empty['errors'], true));

$badQty = compute_bill([['unit_price_cents' => 100, 'quantity' => 0]]);
check('invalid quantity rejected', $badQty['ok'] === false);

check('change due', change_due(10000, 15000) === 5000);
check('short payment has no change', change_due(10000, 7000) === 0);

echo PHP_EOL . "Payment + status rules" . PHP_EOL;

check('unpaid', payment_status_for(10000, 0) === 'unpaid');
check('partial', payment_status_for(10000, 5000) === 'partial');
check('paid', payment_status_for(10000, 10000) === 'paid');
check('overpaid counts as paid', payment_status_for(10000, 12000) === 'paid');
check('pending may confirm', in_array('confirmed', allowed_status_transitions('pending'), true));
check('pending may cancel', in_array('cancelled', allowed_status_transitions('pending'), true));
check('pending cannot be delivered', !in_array('delivered', allowed_status_transitions('pending'), true));
check('delivered is terminal', allowed_status_transitions('delivered') === []);

echo PHP_EOL . "Validation helpers" . PHP_EOL;

check('phone normalization', normalize_bd_phone('01712-345678') === '01712345678', normalize_bd_phone('01712-345678'));
check('slugify', slugify('Beef Tehari  Special') === 'beef-tehari-special', slugify('Beef Tehari  Special'));
check('clean text strips control chars', clean_text("Hi\x00 there", 20) === 'Hi there', clean_text("Hi\x00 there", 20));
check('clean text truncates', clean_text('abcdef', 3) === 'abc', clean_text('abcdef', 3));

echo PHP_EOL . "Bilingual dictionaries" . PHP_EOL;

$bn = require DH_ROOT . '/includes/languages/bn.php';
$en = require DH_ROOT . '/includes/languages/en.php';

check('same keys in both languages', array_keys($bn) === array_keys($en));
$emptyTranslations = [];
foreach ($bn as $key => $value) {
    if (trim((string) $value) === '' || trim((string) ($en[$key] ?? '')) === '') {
        $emptyTranslations[] = $key;
    }
}
check('no empty translations', $emptyTranslations === [], implode(', ', $emptyTranslations));
check('Bangla is not a copy of English', $bn['pos.title'] !== $en['pos.title']);

$missing = [];
$used = 0;
$root = DH_ROOT;
$paths = [
    'admin', 'api', 'includes', 'scripts', 'tests',
];
$scan = [];
foreach ($paths as $path) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $path));
    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php')
            && !str_contains($file->getPathname(), '/languages/')) {
            $scan[] = $file->getPathname();
        }
    }
}
foreach (glob($root . '/*.php') as $file) {
    $scan[] = $file;
}

foreach ($scan as $file) {
    $source = (string) file_get_contents($file);
    if (preg_match_all("/__\(\s*['\"]([^'\"]+)['\"]/", $source, $matches)) {
        foreach (array_unique($matches[1]) as $key) {
            $used++;
            if (!isset($bn[$key]) || !isset($en[$key])) {
                $missing[$key] = true;
            }
        }
    }
}
check('every __() key exists', $missing === [], implode(', ', array_keys($missing)));
check('dictionary has a useful size', count($bn) >= 700, (string) count($bn));

echo PHP_EOL . "Result: {$passed} passed, {$failed} failed" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
