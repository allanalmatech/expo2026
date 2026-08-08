<?php
declare(strict_types=1);

function tent_types(): array
{
    return [
        '50' => [
            'name' => '50-Seater Tent',
            'canopy' => 'Single Canopy',
            'min_stalls' => 1,
            'recommended_stalls' => 4,
            'max_stalls' => 5,
            'footprint_sqm' => 54,
            'hire_setup_cost' => 450000,
            'normal_assumption' => 'Use 4 stalls per tent for standard planning unless exhibitor category requires otherwise.',
            'hard_rule' => 'Never allocate more than 5 stalls in one 50-seater tent.',
            'arrangements' => [
                ['key' => 'exclusive_50', 'name' => 'Exclusive tent', 'stalls' => 1, 'suitable' => 'Medium corporate exhibitor or sponsor', 'stall_class' => 'exclusive_50', 'walkway_ratio' => 0.16, 'setup_extra' => 160000],
                ['key' => 'large_50', 'name' => 'Large stalls', 'stalls' => 2, 'suitable' => 'Established businesses or service providers', 'stall_class' => 'large', 'walkway_ratio' => 0.20, 'setup_extra' => 140000],
                ['key' => 'standard_50', 'name' => 'Standard stalls', 'stalls' => 4, 'suitable' => 'SMEs, NGOs and retailers', 'stall_class' => 'standard', 'walkway_ratio' => 0.25, 'setup_extra' => 110000],
                ['key' => 'small_50', 'name' => 'Small stalls', 'stalls' => 5, 'suitable' => 'Student businesses, startups and small vendors', 'stall_class' => 'small', 'walkway_ratio' => 0.30, 'setup_extra' => 90000],
            ],
        ],
        '100' => [
            'name' => '100-Seater Tent',
            'canopy' => 'Double Canopy',
            'min_stalls' => 1,
            'recommended_stalls' => 8,
            'max_stalls' => 10,
            'footprint_sqm' => 108,
            'hire_setup_cost' => 850000,
            'normal_assumption' => 'Use 8 stalls per 100-seater tent for standard exhibitors.',
            'hard_rule' => 'The tent must not exceed 10 stalls.',
            'arrangements' => [
                ['key' => 'exclusive_100', 'name' => 'Exclusive corporate pavilion', 'stalls' => 1, 'suitable' => 'Headline sponsor, telecom, bank or major brand', 'stall_class' => 'exclusive_100', 'walkway_ratio' => 0.14, 'setup_extra' => 320000],
                ['key' => 'mega_100', 'name' => 'Mega premium stalls', 'stalls' => 2, 'suitable' => 'Two large corporate exhibitors', 'stall_class' => 'premium', 'walkway_ratio' => 0.18, 'setup_extra' => 260000],
                ['key' => 'large_100', 'name' => 'Large stalls', 'stalls' => 4, 'suitable' => 'Banks, insurance companies and established brands', 'stall_class' => 'large', 'walkway_ratio' => 0.22, 'setup_extra' => 220000],
                ['key' => 'medium_100', 'name' => 'Medium stalls', 'stalls' => 6, 'suitable' => 'Corporate exhibitors and government agencies', 'stall_class' => 'medium', 'walkway_ratio' => 0.25, 'setup_extra' => 185000],
                ['key' => 'standard_100', 'name' => 'Standard stalls', 'stalls' => 8, 'suitable' => 'Mixed SMEs, NGOs and service providers', 'stall_class' => 'standard', 'walkway_ratio' => 0.28, 'setup_extra' => 160000],
                ['key' => 'small_100', 'name' => 'Small stalls', 'stalls' => 10, 'suitable' => 'Student businesses, startups and small retailers', 'stall_class' => 'small', 'walkway_ratio' => 0.32, 'setup_extra' => 140000],
            ],
        ],
    ];
}

function tent_pricing_model(): array
{
    return [
        'small' => ['label' => 'Small stall price', 'price' => 250000],
        'standard' => ['label' => 'Standard stall price', 'price' => 400000],
        'medium' => ['label' => 'Medium stall price', 'price' => 550000],
        'large' => ['label' => 'Large stall price', 'price' => 750000],
        'premium' => ['label' => 'Premium stall price', 'price' => 1200000],
        'half_tent' => ['label' => 'Half-tent price', 'price' => 1800000],
        'exclusive_50' => ['label' => 'Exclusive 50-seater tent price', 'price' => 2200000],
        'exclusive_100' => ['label' => 'Exclusive 100-seater tent price', 'price' => 4200000],
        'sponsor_pavilion' => ['label' => 'Sponsor pavilion price', 'price' => 7500000],
    ];
}

function venue_layout_zones(): array
{
    return [
        'corporate_sponsors' => [
            'name' => 'Corporate & Sponsors',
            'u_position' => 'Business distribution zone',
            'traffic' => 'Not specified',
            'multiplier' => 1.00,
            'notes' => 'Tents 1, 2, 3, 12, 13, 14. Banks, telecoms, insurance, universities, government agencies, and internet providers.',
        ],
        'retail_commercial' => [
            'name' => 'Retail & Commercial',
            'u_position' => 'Business distribution zone',
            'traffic' => 'Not specified',
            'multiplier' => 1.00,
            'notes' => 'Tents 4, 5, 6, 7, 8. Electronics, furniture, cosmetics, fashion, phones, printing, supermarkets, and hardware.',
        ],
        'students_innovation' => [
            'name' => 'Students & Innovation',
            'u_position' => 'Business distribution zone',
            'traffic' => 'Not specified',
            'multiplier' => 1.00,
            'notes' => 'Tents 9, 10, 11. Student businesses, clubs, innovation projects, startups, and campus entrepreneurs.',
        ],
        'food_beverage' => [
            'name' => 'Food & Beverage',
            'u_position' => 'Business distribution zone',
            'traffic' => 'Not specified',
            'multiplier' => 1.00,
            'notes' => 'Tent 15 mandatory beer garden with approved breweries, spirits and beverage promotions, lounge seating, and responsible consumption area.',
        ],
    ];
}

function exhibitor_categories(): array
{
    return [
        'student_startup' => [
            'name' => 'Student and startup tents',
            'description' => 'Small vendors, student businesses and early-stage startups.',
            'preferred' => ['50' => 5, '100' => 10],
            'default_zone' => 'students_innovation',
            'electricity' => false,
            'furniture' => true,
            'branding' => false,
            'reason' => 'Higher stall count keeps prices accessible while preserving safe walkways.',
        ],
        'sme_retail' => [
            'name' => 'SME and retail tents',
            'description' => 'Retailers, SMEs, campus services and product sellers.',
            'preferred' => ['50' => 4, '100' => 8],
            'default_zone' => 'retail_commercial',
            'electricity' => false,
            'furniture' => true,
            'branding' => true,
            'reason' => 'Standard planning assumption balances affordability, visibility and movement.',
        ],
        'ngo_government' => [
            'name' => 'NGO and government tents',
            'description' => 'Public agencies, NGOs, outreach programs and information desks.',
            'preferred' => ['50' => 4, '100' => 6],
            'default_zone' => 'corporate_sponsors',
            'electricity' => false,
            'furniture' => true,
            'branding' => true,
            'reason' => 'Fewer exhibitors allow consultation tables and slower visitor engagement.',
        ],
        'corporate' => [
            'name' => 'Corporate tents',
            'description' => 'Banks, telecoms, insurers and established brands.',
            'preferred' => ['50' => 2, '100' => 4],
            'default_zone' => 'corporate_sponsors',
            'electricity' => true,
            'furniture' => true,
            'branding' => true,
            'reason' => 'Large stalls support demonstrations, consultation areas and stronger branding.',
        ],
        'sponsor_exclusive' => [
            'name' => 'Sponsor-exclusive tents',
            'description' => 'Headline sponsors, major brands and exclusive pavilions.',
            'preferred' => ['50' => 1, '100' => 1],
            'default_zone' => 'corporate_sponsors',
            'electricity' => true,
            'furniture' => true,
            'branding' => true,
            'reason' => 'Exclusive use must be charged as a pavilion, not an ordinary stall.',
        ],
        'food_beverage' => [
            'name' => 'Food and beverage tents',
            'description' => 'Food vendors, drink sellers and snack businesses.',
            'preferred' => ['50' => 4, '100' => 6],
            'default_zone' => 'food_beverage',
            'electricity' => true,
            'furniture' => true,
            'branding' => false,
            'reason' => 'Food vendors need service space, queue control, hygiene spacing and power access.',
        ],
    ];
}

function tent_money(float $amount): string
{
    return 'UGX ' . number_format((int) round($amount));
}

function internal_walkway_label(int $stalls): string
{
    if ($stalls === 1) {
        return 'Perimeter visitor access and private pavilion frontage';
    }
    if ($stalls === 2) {
        return 'Central aisle with two large frontage zones';
    }
    if ($stalls <= 5) {
        return 'Central aisle with front-facing stall line';
    }
    if ($stalls <= 8) {
        return 'Main aisle plus short cross-access gaps';
    }
    return 'Main aisle with tighter small-stall circulation gaps';
}

function arrangement_stall_size(array $tent, array $arrangement): string
{
    $usable = (float) $tent['footprint_sqm'] * (1 - (float) $arrangement['walkway_ratio']);
    $sqm = $usable / max(1, (int) $arrangement['stalls']);
    return 'Approx. ' . number_format($sqm, 1) . ' sqm per stall';
}

function simulate_tent_arrangement(string $tentCode, array $arrangement, string $zoneKey, bool $electricity, bool $furniture, bool $branding): array
{
    $tents = tent_types();
    $pricing = tent_pricing_model();
    $zones = venue_layout_zones();
    $tent = $tents[$tentCode];
    $zone = $zones[$zoneKey] ?? reset($zones);
    $stalls = (int) $arrangement['stalls'];

    if ($stalls < (int) $tent['min_stalls'] || $stalls > (int) $tent['max_stalls']) {
        throw new InvalidArgumentException('Tent arrangement violates fixed capacity rules.');
    }

    $stallClass = (string) $arrangement['stall_class'];
    $basePrice = (float) ($pricing[$stallClass]['price'] ?? $pricing['standard']['price']);
    if ($stallClass === 'exclusive_100' && $zoneKey === 'corporate_sponsors') {
        $basePrice = max($basePrice, (float) $pricing['sponsor_pavilion']['price']);
    }

    $pricePerStall = $basePrice * (float) $zone['multiplier'];
    if ($electricity) {
        $pricePerStall += $stalls === 1 ? 250000 : 70000;
    }
    if ($furniture) {
        $pricePerStall += $stalls === 1 ? 180000 : 45000;
    }
    if ($branding) {
        $pricePerStall += $stalls === 1 ? 400000 : 90000;
    }

    $revenue = $pricePerStall * $stalls;
    $cost = (float) $tent['hire_setup_cost'] + (float) $arrangement['setup_extra'];
    if ($electricity) {
        $cost += 150000 + (35000 * $stalls);
    }
    if ($furniture) {
        $cost += 30000 * $stalls;
    }
    if ($branding) {
        $cost += 50000 * $stalls;
    }

    return [
        'tent' => $tent,
        'zone' => $zone,
        'arrangement' => $arrangement,
        'stalls' => $stalls,
        'stall_size' => arrangement_stall_size($tent, $arrangement),
        'walkways' => internal_walkway_label($stalls),
        'price_per_stall' => $pricePerStall,
        'revenue' => $revenue,
        'cost' => $cost,
        'profit' => $revenue - $cost,
        'electricity' => $electricity,
        'furniture' => $furniture,
        'branding' => $branding,
    ];
}

function recommended_arrangement_key(string $categoryKey, string $tentCode): string
{
    $categories = exhibitor_categories();
    $target = (int) ($categories[$categoryKey]['preferred'][$tentCode] ?? tent_types()[$tentCode]['recommended_stalls']);
    foreach (tent_types()[$tentCode]['arrangements'] as $arrangement) {
        if ((int) $arrangement['stalls'] === $target) {
            return (string) $arrangement['key'];
        }
    }
    return (string) tent_types()[$tentCode]['arrangements'][0]['key'];
}

function simulate_category(string $categoryKey, string $tentCode, ?string $zoneKey = null): array
{
    $categories = exhibitor_categories();
    $category = $categories[$categoryKey];
    $zoneKey = $zoneKey ?: (string) $category['default_zone'];
    $recommendedKey = recommended_arrangement_key($categoryKey, $tentCode);
    $simulations = [];

    foreach (tent_types()[$tentCode]['arrangements'] as $arrangement) {
        $simulation = simulate_tent_arrangement(
            $tentCode,
            $arrangement,
            $zoneKey,
            (bool) $category['electricity'],
            (bool) $category['furniture'],
            (bool) $category['branding']
        );
        $simulation['recommended'] = $arrangement['key'] === $recommendedKey;
        $simulations[] = $simulation;
    }

    return $simulations;
}
