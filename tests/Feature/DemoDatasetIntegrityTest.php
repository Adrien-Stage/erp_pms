<?php

/**
 * Contrôle d'intégrité structurelle et monétaire du jeu de démonstration officiel
 * dans demo/ : cohérence du manifest, des fichiers JSON et des seeders PDO.
 */

test('le manifest du jeu de démonstration référence des seeders valides dans l ordre attendu', function () {
    $manifestPath = base_path('demo/manifest.json');
    expect(file_exists($manifestPath))->toBeTrue();

    $manifest = json_decode(file_get_contents($manifestPath), true);
    expect($manifest)->toBeArray()
        ->and($manifest['version'])->toBe(2)
        ->and($manifest['steps'])->toBeArray()->not->toBeEmpty();

    $attendus = [
        'StaffSeeder',
        'CustomerSeeder',
        'HotelSeeder',
        'BookingSeeder',
        'RestaurantSeeder',
        'ShopSeeder',
        'OperationsSeeder',
    ];

    $recus = array_column($manifest['steps'], 'seeder');
    expect($recus)->toBe($attendus);

    foreach ($recus as $seeder) {
        $fichier = base_path("demo/Seeders/{$seeder}.php");
        expect(file_exists($fichier))->toBeTrue();
        expect(class_exists("Demo\\Seeders\\{$seeder}"))->toBeTrue();
    }
});

test('tous les fichiers JSON de demo/data sont valides et couvrent le contrat métier', function () {
    $fichiers = ['people', 'hotel', 'restaurant', 'shop', 'operations', 'staff'];
    $donnees = [];

    foreach ($fichiers as $nom) {
        $chemin = base_path("demo/data/{$nom}.json");
        expect(file_exists($chemin))->toBeTrue();

        $decode = json_decode(file_get_contents($chemin), true);
        expect(json_last_error())->toBe(JSON_ERROR_NONE)
            ->and($decode)->toBeArray();
        $donnees[$nom] = $decode;
    }

    // Personnel : 45 comptes @demo.wetchah.test, >= 2 par équipe, aucun rôle privilégié/retiré.
    expect($donnees['staff']['password'])->toBe('Test@2026')
        ->and($donnees['staff']['staff'])->toHaveCount(45);

    $rolesInterdits = ['admin', 'support', 'customer_guest', 'quality_auditor', 'rh_manager', 'it_support'];
    foreach ($donnees['staff']['staff'] as $membre) {
        expect($membre['email'])->toEndWith('@demo.wetchah.test')
            ->and($membre['role'])->not->toBeIn($rolesInterdits);
    }

    // Hôtel : catégories, chambres (dont 204, 205, 304, 305, 401, 402, 403), prestations, formules et fiches coûts.
    $codesTypes = array_column($donnees['hotel']['room_types'], 'code');
    expect($codesTypes)->toContain('STD', 'SUP', 'SJR', 'SPRES');
    $numerosChambres = array_column($donnees['hotel']['rooms'], 'number');
    expect($numerosChambres)->toContain('101', '102', '204', '205', '304', '305', '401', '402', '403');
    expect($donnees['hotel']['room_cost_sheets'])->toHaveKeys(['STD', 'SUP', 'SJR', 'SPRES']);

    // Restaurant : 6 catégories, 26 articles (dont 4 boissons bar), 12 articles garde-manger, 5 recettes, 2 banquets, fiches bar.
    expect($donnees['restaurant']['categories'])->toHaveCount(6)
        ->and($donnees['restaurant']['items'])->toHaveCount(26)
        ->and($donnees['restaurant']['pantry_items'])->toHaveCount(12)
        ->and($donnees['restaurant']['recipes'])->toHaveCount(5)
        ->and($donnees['restaurant']['banquets'])->toHaveCount(2)
        ->and($donnees['restaurant']['bar_drinks'])->toHaveCount(5);

    foreach ($donnees['restaurant']['banquets'] as $banquet) {
        expect($banquet['reference'])->toStartWith('DEMO-BQ-')
            ->and($banquet['source'])->toBeIn(['sur_place', 'telephone', 'restaurant', 'site_web'])
            ->and($banquet['menu_items'])->toBeArray()->not->toBeEmpty();
    }

    // Boutique : 20 articles et au moins 2 articles sous seuil (Masque bamiléké, Tabouret royal).
    expect($donnees['shop']['products'])->toHaveCount(20);
    $alertesBoutique = array_values(array_filter(
        $donnees['shop']['products'],
        fn ($p) => ($p['stock'] ?? 0) <= ($p['reorder_level'] ?? 0)
    ));
    expect(count($alertesBoutique))->toBeGreaterThanOrEqual(2);

    // Exploitation : 79 articles stock (dont Rhum et Whisky bar) dont les alertes attendues + 20 charges alignées sur Expense::CATEGORIES + 5 partenaires.
    expect($donnees['operations']['stock_items'])->toHaveCount(79)
        ->and($donnees['operations']['expenses'])->toHaveCount(20)
        ->and($donnees['operations']['partner_organizations'])->toHaveCount(5);

    $alertesStock = [];
    foreach ($donnees['operations']['stock_items'] as $art) {
        if (isset($art['stock']) && $art['stock'] <= $art['min']) {
            $alertesStock[] = $art['name'];
        }
    }
    expect($alertesStock)->toContain(
        'Huile végétale 5 L',
        'Café moulu 1 kg',
        'Crevettes',
        "Savonnette d'accueil 25 g"
    );

    $categoriesCharges = ['purchase', 'salary', 'maintenance', 'electricity', 'water', 'marketing', 'transport', 'other'];
    foreach ($donnees['operations']['expenses'] as $charge) {
        expect($charge['category'])->toBeIn($categoriesCharges)
            ->and($charge['amount'])->toBeInt()->toBeGreaterThan(0);
    }
});

test('les seeders et le purgeur respectent les correctifs de schéma et le contrat centimes', function () {
    $staffSeeder = file_get_contents(base_path('demo/Seeders/StaffSeeder.php'));
    expect(strtolower($staffSeeder))->toContain("where mode = 'fixed'")
        ->and(strtolower($staffSeeder))->not->toContain("where kind = 'fixed'")
        ->and($staffSeeder)->toContain("date('N') - 1");

    $bookingSeeder = file_get_contents(base_path('demo/Seeders/BookingSeeder.php'));
    expect($bookingSeeder)->toContain("'booker_id'              => null");

    $restaurantSeeder = file_get_contents(base_path('demo/Seeders/RestaurantSeeder.php'));
    expect($restaurantSeeder)->toContain('FROM spaces')
        ->and($restaurantSeeder)->toContain('balance_due + ?')
        ->and($restaurantSeeder)->not->toContain('restaurant_spaces');

    $shopSeeder = file_get_contents(base_path('demo/Seeders/ShopSeeder.php'));
    expect($shopSeeder)->toContain('balance_due + ?')
        ->and($shopSeeder)->toContain("'cash', 'mobile_money', 'card'");

    $operationsSeeder = file_get_contents(base_path('demo/Seeders/OperationsSeeder.php'));
    expect($operationsSeeder)->toContain('COALESCE(stock_category_id, ?)')
        ->and($operationsSeeder)->toContain('COALESCE(storage_zone_id, ?)')
        ->and($operationsSeeder)->toContain("'reopened_count'             => 0");

    // Les JSON stockent des FCFA entiers, jamais des centimes pré-multipliés.
    $hotelJson = json_decode(file_get_contents(base_path('demo/data/hotel.json')), true);
    $preises = array_column($hotelJson['room_types'], 'base_price', 'code');
    expect($preises['STD'])->toBe(25000)
        ->and($preises['SUP'])->toBe(40000)
        ->and($preises['SJR'])->toBe(65000)
        ->and($preises['SPRES'])->toBe(120000);
});
