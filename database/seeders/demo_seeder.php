<?php
/**
 * Repeatable demo data. Every insert is keyed so running the seeder twice
 * never duplicates records and never overwrites data the owner has edited.
 */

declare(strict_types=1);

function seed_demo_data(): array
{
    $summary = [];

    // ---------------------------------------------------------------- settings
    $settings = [
        'name_bn' => 'ঢাকা হোটেল',
        'name_en' => 'Dhaka Hotel',
        'tagline_bn' => 'ঘরোয়া স্বাদের বিশ্বস্ত ঠিকানা',
        'tagline_en' => 'Home-style Bangladeshi flavours, delivered warm',
        'description_bn' => 'ঢাকা হোটেল ছোট পরিসরে ঘরোয়া বাংলাদেশি খাবার রান্না করে। বিরিয়ানি, ভাত-তরকারি থেকে শুরু করে হালকা নাস্তা ও মিষ্টান্ন—সবই অর্ডার পাওয়ার পর তৈরি হয়। আমরা প্রতিটি অর্ডার ফোনে নিশ্চিত করে গরম গরম ডেলিভারি দিই।',
        'description_en' => 'Dhaka Hotel cooks home-style Bangladeshi food in small batches. From biryani and rice plates to light snacks and desserts, everything is prepared after you order. We confirm every order by phone and deliver it hot.',
        'address_bn' => '',
        'address_en' => '',
        'phone' => '',
        'whatsapp_number' => '',
        'owner_email' => '',
        'hours_bn' => 'প্রতিদিন সকাল ১১:০০ – রাত ১১:০০',
        'hours_en' => 'Daily, 11:00 AM – 11:00 PM',
        'delivery_fee' => '60',
        'minimum_order' => '150',
        'currency' => 'BDT',
        'tax_percent' => '0',
        'notification_language' => 'bn',
        'receipt_footer_bn' => 'আমাদের সঙ্গে থাকার জন্য ধন্যবাদ।',
        'receipt_footer_en' => 'Thank you for dining with us.',
        'demo_mode' => '1',
    ];

    $insertedSettings = 0;
    foreach ($settings as $key => $value) {
        // INSERT IGNORE: existing (possibly owner-edited) values are preserved.
        $statement = q('INSERT IGNORE INTO restaurant_settings (setting_key, setting_value) VALUES (?, ?)', [$key, $value]);
        $insertedSettings += $statement->rowCount();
    }
    $summary[] = sprintf('Settings: %d inserted, %d kept', $insertedSettings, count($settings) - $insertedSettings);

    // ------------------------------------------------------------------- admin
    $adminEmail = 'admin@dhakahotel.demo';
    $existingAdmin = fetch_one('SELECT id FROM admins WHERE email = ?', [$adminEmail]);
    if ($existingAdmin === null) {
        insert_row('admins', [
            'name' => 'Demo Owner',
            'email' => $adminEmail,
            'password_hash' => password_hash('DhakaDemo@2026!', PASSWORD_DEFAULT),
            'preferred_language' => 'bn',
            'is_active' => 1,
        ]);
        $summary[] = 'Admin: created admin@dhakahotel.demo';
    } else {
        $summary[] = 'Admin: exists, password left untouched';
    }

    $adminId = (int) fetch_value('SELECT id FROM admins WHERE email = ?', [$adminEmail], 0);

    // -------------------------------------------------------------- categories
    $categories = [
        ['biryani', 'বিরিয়ানি', 'Biryani', 'সুগন্ধি চালে রান্না বিরিয়ানি ও তেহারি।', 'Fragrant rice dishes, slow cooked.', 1],
        ['rice-curry', 'ভাত ও তরকারি', 'Rice & Curry', 'গরম ভাতের সঙ্গে ঘরোয়া তরকারি।', 'Everyday rice plates with homestyle curries.', 2],
        ['chicken', 'চিকেন', 'Chicken', 'মুরগির বিভিন্ন পদ।', 'Chicken favourites from the kitchen.', 3],
        ['burgers-snacks', 'বার্গার ও নাস্তা', 'Burgers & Snacks', 'হালকা খাবার ও নাস্তা।', 'Light bites and snacks.', 4],
        ['drinks', 'পানীয়', 'Drinks', 'ঠান্ডা ও তাজা পানীয়।', 'Fresh and chilled drinks.', 5],
        ['desserts', 'মিষ্টান্ন', 'Desserts', 'ঘরোয়া মিষ্টান্ন।', 'Homestyle sweets.', 6],
    ];

    foreach ($categories as [$slug, $nameBn, $nameEn, $descBn, $descEn, $sort]) {
        q(
            'INSERT IGNORE INTO categories (name_bn, name_en, description_bn, description_en, slug, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, 1, ?)',
            [$nameBn, $nameEn, $descBn, $descEn, $slug, $sort]
        );
    }

    $categoryIds = [];
    foreach (fetch_all('SELECT id, slug FROM categories') as $row) {
        $categoryIds[$row['slug']] = (int) $row['id'];
    }
    $summary[] = 'Categories: ' . count($categoryIds) . ' available';

    // -------------------------------------------------------------- menu items
    $items = [
        ['chicken-biryani', 'biryani', 'চিকেন বিরিয়ানি', 'Chicken Biryani',
            'সুগন্ধি বাসমতি চাল, নরম মুরগির মাংস আর মশলার সুবাস—সঙ্গে সালাদ।',
            'Fragrant basmati rice layered with tender chicken and warm spices, served with salad.',
            '220.00', 'chicken-biryani.svg', 1],
        ['beef-tehari', 'biryani', 'বিফ তেহারি', 'Beef Tehari',
            'গরুর মাংসের তেহারি, আলু আর মসলার ঘ্রাণে ভরপুর।',
            'Aromatic tehari rice cooked with beef and potato.',
            '180.00', 'beef-tehari.svg', 0],
        ['kacchi-biryani', 'biryani', 'কাচ্চি বিরিয়ানি', 'Kacchi Biryani',
            'ধীরে রান্না করা কাচ্চি বিরিয়ানি, নরম মাংস ও আলুসহ।',
            'Slow-cooked kacchi biryani with soft mutton-style beef and potato.',
            '280.00', 'kacchi-biryani.svg', 1],
        ['plain-rice', 'rice-curry', 'সাদা ভাত', 'Plain Rice',
            'গরম সাদা ভাত, যেকোনো তরকারির সঙ্গে দারুণ।',
            'Steamed white rice, perfect with any curry.',
            '40.00', 'plain-rice.svg', 0],
        ['chicken-curry', 'chicken', 'মুরগির ঝোল', 'Chicken Curry',
            'ঘরোয়া মসলায় রান্না মুরগির ঝোল, আলু দিয়ে।',
            'Home-style chicken curry cooked with potato and everyday spices.',
            '140.00', 'chicken-curry.svg', 0],
        ['beef-bhuna', 'rice-curry', 'গরুর ভুনা', 'Beef Bhuna',
            'ধীরে ভুনা করা গরুর মাংস, ঘন মসলার স্বাদে।',
            'Slow-bhuna beef in a thick, deeply spiced gravy.',
            '190.00', 'beef-bhuna.svg', 1],
        ['chicken-roast', 'chicken', 'চিকেন রোস্ট', 'Chicken Roast',
            'মশলা মাখিয়ে রান্না মুরগির রোস্ট, হালকা মিষ্টি-ঝাল স্বাদে।',
            'Marinated chicken roast with a gently sweet and spicy glaze.',
            '160.00', 'chicken-roast.svg', 1],
        ['chicken-burger', 'burgers-snacks', 'চিকেন বার্গার', 'Chicken Burger',
            'গ্রিল করা চিকেন প্যাটি, সস ও সবজি দিয়ে বার্গার।',
            'Grilled chicken patty with sauce and fresh vegetables in a soft bun.',
            '180.00', 'chicken-burger.svg', 1],
        ['french-fries', 'burgers-snacks', 'ফ্রেঞ্চ ফ্রাই', 'French Fries',
            'মুচমুচে সোনালি আলু ভাজি, সঙ্গে টমেটো সস।',
            'Golden crispy fries served with tomato sauce.',
            '100.00', 'french-fries.svg', 0],
        ['chicken-sandwich', 'burgers-snacks', 'চিকেন স্যান্ডউইচ', 'Chicken Sandwich',
            'নরম পাউরুটিতে চিকেন, সবজি ও মেয়োনিজ।',
            'Soft bread filled with chicken, vegetables and mayonnaise.',
            '130.00', 'chicken-sandwich.svg', 0],
        ['vegetable-fried-rice', 'rice-curry', 'ভেজিটেবল ফ্রাইড রাইস', 'Vegetable Fried Rice',
            'মিশ্র সবজি দিয়ে ভাজা ফ্রাইড রাইস।',
            'Wok-tossed fried rice with mixed vegetables.',
            '150.00', 'vegetable-fried-rice.svg', 0],
        ['egg-fried-rice', 'rice-curry', 'এগ ফ্রাইড রাইস', 'Egg Fried Rice',
            'ডিম দিয়ে ভাজা ফ্রাইড রাইস, হালকা মসলায়।',
            'Fried rice with egg and a light touch of spice.',
            '120.00', 'egg-fried-rice.svg', 0],
        ['borhani', 'drinks', 'বোরহানি', 'Borhani',
            'দই ও পুদিনা দিয়ে তৈরি ঠান্ডা বোরহানি।',
            'Chilled yoghurt drink with mint.',
            '60.00', 'borhani.svg', 1],
        ['lime-juice', 'drinks', 'তাজা লেবুর শরবত', 'Fresh Lime Juice',
            'তাজা লেবু, চিনি ও বরফ দিয়ে শরবত।',
            'Fresh lime juice with sugar and ice.',
            '70.00', 'lime-juice.svg', 0],
        ['firni', 'desserts', 'ফিরনি', 'Firni',
            'দুধ ও চালের গুঁড়ায় তৈরি ঐতিহ্যবাহী ফিরনি।',
            'Traditional rice pudding made with milk.',
            '80.00', 'firni.svg', 1],
    ];

    $sort = 0;
    foreach ($items as [$key, $slug, $nameBn, $nameEn, $descBn, $descEn, $price, $image, $featured]) {
        if (!isset($categoryIds[$slug])) {
            continue;
        }
        $sort++;
        q(
            'INSERT IGNORE INTO menu_items
                (category_id, name_bn, name_en, description_bn, description_en, price, image_path,
                 is_available, is_featured, is_demo, demo_key, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 1, ?, ?)',
            [
                $categoryIds[$slug], $nameBn, $nameEn, $descBn, $descEn, $price,
                'menu/' . $image, $featured, $key, $sort,
            ]
        );
    }

    $menuIds = [];
    foreach (fetch_all('SELECT demo_key, id FROM menu_items WHERE demo_key IS NOT NULL') as $row) {
        $menuIds[$row['demo_key']] = (int) $row['id'];
    }
    $summary[] = 'Menu items: ' . count($menuIds) . ' demo items available';

    // ----------------------------------------------------------------- reviews
    if ((int) fetch_value('SELECT COUNT(*) FROM reviews WHERE is_demo = 1', [], 0) === 0) {
        $reviews = [
            ['Demo Reviewer Aisha', 5, 'বিরিয়ানি খুব সুস্বাদু ছিল, গরম গরম পেয়েছি। ডেলিভারি সময়মতো এসেছে।', 'approved'],
            ['Demo Reviewer Rakib', 4, 'Borhani was great and the burger was fresh. Packaging could be a little better.', 'approved'],
            ['Demo Reviewer Nusrat', 5, 'ফিরনি দারুণ ছিল, আবার অর্ডার করব।', 'pending'],
            ['Demo Reviewer Tanvir', 3, 'খাবার ঠিকঠাক ছিল, তবে ডেলিভারিতে একটু দেরি হয়েছে।', 'rejected'],
        ];
        foreach ($reviews as [$name, $rating, $text, $status]) {
            insert_row('reviews', [
                'display_name' => $name,
                'rating' => $rating,
                'review_text' => $text,
                'status' => $status,
                'is_demo' => 1,
            ]);
        }
        $summary[] = 'Reviews: 4 demo reviews created';
    } else {
        $summary[] = 'Reviews: demo reviews already present';
    }

    // ------------------------------------------------------------------ orders
    $customers = [
        ['Demo Customer Rahima', '01712345678', 'rahima.demo@example.com'],
        ['Demo Customer Karim', '01812345678', null],
        ['Demo Customer Nabila', '01912345678', null],
    ];

    $customerIds = [];
    foreach ($customers as [$name, $phone, $email]) {
        q(
            'INSERT INTO customers (name, phone, email, is_demo) VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = VALUES(name), is_demo = 1',
            [$name, $phone, $email]
        );
        $customerIds[$phone] = (int) db()->lastInsertId();
    }

    $demoOrders = [
        [
            'number' => 'DH-DEMO01',
            'customer' => '01712345678',
            'customer_name' => 'Demo Customer Rahima',
            'customer_phone' => '01712345678',
            'address' => 'Demo address: House 12, Road 4 (fictional)',
            'type' => 'delivery',
            'table' => null,
            'status' => 'delivered',
            'payment_status' => 'paid',
            'method' => 'cash',
            'source' => 'web',
            'created_interval' => '2 DAY',
            'items' => ['chicken-biryani' => 2, 'borhani' => 1],
            'delivery_fee' => 60,
            'payments' => [['cash', 560, null]],
        ],
        [
            'number' => 'DH-DEMO02',
            'customer' => '01812345678',
            'customer_name' => 'Demo Customer Karim',
            'customer_phone' => '01812345678',
            'address' => 'Demo address: Flat 3B, Mirpur (fictional)',
            'type' => 'delivery',
            'table' => null,
            'status' => 'preparing',
            'payment_status' => 'unpaid',
            'method' => 'cod',
            'source' => 'web',
            'created_interval' => '3 HOUR',
            'items' => ['kacchi-biryani' => 1, 'firni' => 1],
            'delivery_fee' => 60,
            'payments' => [],
        ],
        [
            'number' => 'DH-DEMO03',
            'customer' => '01912345678',
            'customer_name' => 'Demo Customer Nabila',
            'customer_phone' => '01912345678',
            'address' => null,
            'type' => 'takeaway',
            'table' => null,
            'status' => 'confirmed',
            'payment_status' => 'partial',
            'method' => 'bkash',
            'source' => 'web',
            'created_interval' => '5 HOUR',
            'items' => ['beef-bhuna' => 1, 'plain-rice' => 1],
            'delivery_fee' => 0,
            'payments' => [['bkash', 100, 'DEMO-BKASH-0001']],
        ],
        [
            'number' => 'DH-DEMO04',
            'customer' => null,
            'customer_name' => 'Walk-in customer (demo)',
            'customer_phone' => null,
            'address' => null,
            'type' => 'dine_in',
            'table' => '03',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'method' => 'cash',
            'source' => 'pos',
            'created_interval' => '1 HOUR',
            'items' => ['chicken-burger' => 2, 'french-fries' => 2],
            'delivery_fee' => 0,
            'payments' => [],
        ],
        [
            'number' => 'DH-DEMO05',
            'customer' => '01712345678',
            'customer_name' => 'Demo Customer Rahima',
            'customer_phone' => '01712345678',
            'address' => 'Demo address: House 12, Road 4 (fictional)',
            'type' => 'delivery',
            'table' => null,
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
            'method' => 'cod',
            'source' => 'web',
            'created_interval' => '1 DAY',
            'items' => ['vegetable-fried-rice' => 1, 'egg-fried-rice' => 1],
            'delivery_fee' => 60,
            'payments' => [],
        ],
    ];

    $createdOrders = 0;
    foreach ($demoOrders as $demo) {
        if (fetch_value('SELECT 1 FROM orders WHERE order_number = ?', [$demo['number']]) !== null) {
            continue;
        }

        $lines = [];
        foreach ($demo['items'] as $key => $quantity) {
            if (!isset($menuIds[$key])) {
                continue;
            }
            $row = fetch_one('SELECT name_bn, name_en, price FROM menu_items WHERE id = ?', [$menuIds[$key]]);
            if ($row === null) {
                continue;
            }
            $unit = cents_from_decimal($row['price']);
            $lines[] = [
                'menu_item_id' => $menuIds[$key],
                'name_bn' => $row['name_bn'],
                'name_en' => $row['name_en'],
                'unit_price_cents' => $unit,
                'quantity' => $quantity,
                'line_total_cents' => $unit * $quantity,
            ];
        }

        if ($lines === []) {
            continue;
        }

        $totals = compute_bill($lines, [
            'delivery_fee_cents' => (int) $demo['delivery_fee'] * 100,
            'tax_basis_points' => 0,
        ]);

        $paid = 0;
        foreach ($demo['payments'] as [, $amount]) {
            $paid += (int) $amount * 100;
        }

        $orderId = insert_row('orders', [
            'order_number' => $demo['number'],
            'customer_id' => $demo['customer'] !== null ? ($customerIds[$demo['customer']] ?? null) : null,
            'customer_name' => $demo['customer_name'],
            'customer_phone' => $demo['customer_phone'],
            'delivery_address' => $demo['address'],
            'order_type' => $demo['type'],
            'table_number' => $demo['table'],
            'status' => $demo['status'],
            'payment_status' => $demo['payment_status'],
            'subtotal' => decimal_from_cents($totals['subtotal']),
            'discount' => '0.00',
            'delivery_fee' => decimal_from_cents($totals['delivery_fee']),
            'tax' => '0.00',
            'grand_total' => decimal_from_cents($totals['grand_total']),
            'amount_paid' => decimal_from_cents($paid),
            'payment_method' => $demo['method'],
            'notes' => null,
            'source' => $demo['source'],
            'created_by' => $demo['source'] === 'pos' ? $adminId : null,
            'idempotency_key' => 'seed-' . $demo['number'],
            'is_demo' => 1,
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . $demo['created_interval'])),
        ]);

        foreach ($lines as $line) {
            insert_row('order_items', [
                'order_id' => $orderId,
                'menu_item_id' => $line['menu_item_id'],
                'name_bn' => $line['name_bn'],
                'name_en' => $line['name_en'],
                'unit_price' => decimal_from_cents($line['unit_price_cents']),
                'quantity' => $line['quantity'],
                'line_total' => decimal_from_cents($line['line_total_cents']),
            ]);
        }

        insert_row('order_status_history', [
            'order_id' => $orderId,
            'status' => $demo['status'] === 'cancelled' ? 'pending' : $demo['status'],
            'note' => 'Demo data',
            'admin_id' => $demo['source'] === 'pos' ? $adminId : null,
        ]);

        if ($demo['status'] === 'cancelled') {
            insert_row('order_status_history', [
                'order_id' => $orderId,
                'status' => 'cancelled',
                'note' => 'Demo cancellation',
                'admin_id' => null,
            ]);
        }

        $paymentIndex = 0;
        foreach ($demo['payments'] as [$method, $amount, $reference]) {
            $paymentIndex++;
            insert_row('payments', [
                'order_id' => $orderId,
                'method' => $method,
                'amount' => decimal_from_cents((int) $amount * 100),
                'status' => 'confirmed',
                'reference' => $reference,
                'recorded_by' => $adminId,
                'idempotency_key' => 'seed-' . $demo['number'] . '-pay-' . $paymentIndex,
                'confirmed_at' => date('Y-m-d H:i:s', strtotime('-' . $demo['created_interval'] . ' + 20 MINUTE')),
            ]);
        }

        $createdOrders++;
    }

    $summary[] = $createdOrders === 0
        ? 'Orders: demo orders already present'
        : "Orders: {$createdOrders} demo orders created";

    return $summary;
}
