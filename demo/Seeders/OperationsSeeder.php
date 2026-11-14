<?php

namespace Demo\Seeders;

use Demo\Registre;

/**
 * L'exploitation : ménage, fournisseurs, stock, dépôts, mini-bars, maintenance,
 * approvisionnements (DM/DA/BC), fiches de coûts, charges, partenaires, budget
 * et discussions internes.
 *
 * Tous les montants saisis dans hotel.json / operations.json sont en FCFA et
 * convertis en centimes (* 100) lors de l'insertion en base.
 */
class OperationsSeeder extends AbstractModuleSeeder
{
    public const REPERE_MENAGE = '[demo] Affectation de démonstration';
    public const REPERE_CHARGE = '[demo] Charge de démonstration';

    public function module(): ?string
    {
        return null;
    }

    public function label(): string
    {
        return 'Exploitation : ménage, stock, mini-bars, maintenance, achats, coûts, budget et discussions';
    }

    public function seed(): int
    {
        $o = $this->data['operations'];

        $crees = 0;
        $crees += $this->menage($o);
        $crees += $this->achats($o);
        $crees += $this->depotsEtMinibars($o);
        $crees += $this->barBouteillesEtFiches();
        $crees += $this->maintenance();
        $crees += $this->approvisionnements($o);
        $crees += $this->fichesCoutsChambres();
        $crees += $this->charges($o);
        $crees += $this->partenaires($o);
        $crees += $this->budgetEtDiscussions($o);

        return $crees;
    }

    // ── Ménage ───────────────────────────────────────────────────────────────

    /** Équipes de ménage, états initiaux des chambres et affectations. */
    private function menage(array $o): int
    {
        if (!$this->tableExists('housekeeping_teams')) {
            return 0;
        }

        $crees   = 0;
        $equipes = [];

        foreach ($o['housekeeping_teams'] as $equipe) {
            $avant    = $this->findId('housekeeping_teams', ['name' => $equipe['name']]);
            $leaderId = !empty($equipe['leader_email'])
                ? $this->userIdByEmail($equipe['leader_email'], false)
                : null;

            $id = $this->insertOnce(
                'housekeeping_teams',
                ['name' => $equipe['name']],
                array_merge([
                    'code'      => $equipe['code'] ?? null,
                    'leader_id' => $leaderId,
                    'notes'     => $equipe['notes'] ?? null,
                    'is_active' => true,
                ], $this->timestamps())
            );

            if ($avant === null) {
                $crees++;
            } elseif (Registre::contient($this->pdo, 'housekeeping_teams', $id)) {
                $this->pdo->prepare(
                    'UPDATE housekeeping_teams
                     SET code = COALESCE(code, ?), notes = COALESCE(notes, ?), leader_id = COALESCE(leader_id, ?)
                     WHERE id = ?'
                )->execute([$equipe['code'] ?? null, $equipe['notes'] ?? null, $leaderId, $id]);
            }

            $equipes[$equipe['name']] = $id;
        }

        if (!$this->tableExists('housekeeping_assignments') || $equipes === []) {
            return $crees;
        }

        $chambresRows = $this->pdo->query('SELECT id, number FROM rooms WHERE is_active = true ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        if ($chambresRows === []) {
            return $crees;
        }

        $chambresParNum = [];
        foreach ($chambresRows as $c) {
            $chambresParNum[(string) $c['number']] = (int) $c['id'];
        }

        $deja = $this->count('housekeeping_assignments', 'notes = ?', [self::REPERE_MENAGE]);
        if ($deja === 0) {
            $this->synchroniserStatutsChambres($chambresParNum);
        }

        $vise = $this->volume['housekeeping_assignments'] ?? 20;
        if ($deja >= $vise) {
            return $crees;
        }

        $odile     = $this->userIdByEmail('odile.manga@' . StaffSeeder::DOMAINE) ?? $this->anyUserId();
        $bernad    = $this->userIdByEmail('bernadette.atangana@' . StaffSeeder::DOMAINE) ?? $odile;
        $rose      = $this->userIdByEmail('rose.eyenga@' . StaffSeeder::DOMAINE) ?? $odile;
        $paul      = $this->userIdByEmail('paul.biyong@' . StaffSeeder::DOMAINE) ?? $odile;
        $pelagie   = $this->userIdByEmail('pelagie.mvondo@' . StaffSeeder::DOMAINE) ?? $odile;

        $listeEquipes  = array_values($equipes);
        $listeChambres = array_values($chambresParNum);

        // Les 5 premières affectations posent des situations exploitables dès J0
        // (1 seule affectation active par chambre pour activeHousekeepingAssignment()).
        $tachesJourZero = [
            0 => [
                'room'         => '205',
                'team'         => 'Équipe Étage 2',
                'status'       => 'blocked',
                'kind'         => 'depart',
                'started_by'   => $pelagie,
                'reported_by'  => $pelagie,
                'reported_at'  => $this->jours(0, '08:15:00'),
                'issue_type'   => 'technique',
                'issue_notes'  => 'Climatiseur fuit au plafond — disjonction au démarrage',
                'assigned_at'  => $this->jours(0, '07:50:00'),
                'started_at'   => $this->jours(0, '08:00:00'),
            ],
            1 => [
                'room'         => '304',
                'team'         => 'Équipe Étages supérieurs',
                'status'       => 'pending',
                'kind'         => 'depart',
                'assigned_at'  => $this->jours(0, '07:45:00'),
            ],
            2 => [
                'room'         => '104',
                'team'         => 'Équipe Étage 1',
                'status'       => 'pending',
                'kind'         => 'depart',
                'assigned_at'  => $this->jours(0, '08:00:00'),
            ],
            3 => [
                'room'         => '103',
                'team'         => 'Équipe Étage 1',
                'status'       => 'in_progress',
                'kind'         => 'depart',
                'started_by'   => $paul,
                'assigned_at'  => $this->jours(0, '08:10:00'),
                'started_at'   => $this->jours(0, '08:25:00'),
            ],
            4 => [
                'room'         => '101',
                'team'         => 'Équipe Étage 1',
                'status'       => 'completed',
                'kind'         => 'depart',
                'started_by'   => $rose,
                'completed_by' => $rose,
                'assigned_at'  => $this->jours(0, '07:30:00'),
                'started_at'   => $this->jours(0, '07:40:00'),
                'completed_at' => $this->jours(0, '08:55:00'),
                // Volontairement ni inspectée ni libérée : attend le contrôle de la gouvernante.
            ],
        ];

        for ($i = $deja; $i < $vise; $i++) {
            if (isset($tachesJourZero[$i])) {
                $t       = $tachesJourZero[$i];
                $roomId  = $chambresParNum[$t['room']] ?? $this->cycle($listeChambres, $i);
                $teamId  = $equipes[$t['team']] ?? $this->cycle($listeEquipes, $i);
                $aff     = $t['assigned_at'];

                $this->insert('housekeeping_assignments', array_merge([
                    'housekeeping_team_id' => $teamId,
                    'room_id'              => $roomId,
                    'kind'                 => $t['kind'],
                    'assigned_by'          => $odile,
                    'status'               => $t['status'],
                    'notes'                => self::REPERE_MENAGE,
                    'assigned_at'          => $aff,
                    'started_at'           => $t['started_at'] ?? null,
                    'completed_at'         => $t['completed_at'] ?? null,
                    'started_by'           => $t['started_by'] ?? null,
                    'completed_by'         => $t['completed_by'] ?? null,
                    'reported_by'          => $t['reported_by'] ?? null,
                    'reported_at'          => $t['reported_at'] ?? null,
                    'issue_type'           => $t['issue_type'] ?? null,
                    'issue_notes'          => $t['issue_notes'] ?? null,
                ], $this->timestamps($aff)));
            } else {
                // Historique terminé, contrôlé et libéré sur les 1..9 derniers jours (inclut des recouches).
                $recul   = (($i - 4) % 9) + 1;
                $aff     = $this->jours(-$recul, '08:00:00');
                $deb     = $this->jours(-$recul, '08:20:00');
                $fin     = $this->jours(-$recul, '09:30:00');
                $ctrl    = $this->jours(-$recul, '10:00:00');
                $estRec  = in_array($i, [5, 6], true);
                $roomId  = $estRec
                    ? ($chambresParNum[$i === 5 ? '204' : '403'] ?? $this->cycle($listeChambres, $i))
                    : $this->cycle($listeChambres, $i);

                $this->insert('housekeeping_assignments', array_merge([
                    'housekeeping_team_id' => $this->cycle($listeEquipes, $i),
                    'room_id'              => $roomId,
                    'kind'                 => $estRec ? 'recouche' : 'depart',
                    'assigned_by'          => $odile,
                    'status'               => 'completed',
                    'notes'                => self::REPERE_MENAGE,
                    'assigned_at'          => $aff,
                    'started_at'           => $deb,
                    'completed_at'         => $fin,
                    'started_by'           => $rose,
                    'completed_by'         => $rose,
                    'inspected_by'         => $bernad,
                    'inspected_at'         => $ctrl,
                    'released_by'          => $odile,
                    'released_at'          => $ctrl,
                ], $this->timestamps($aff)));
            }

            $crees++;
        }

        return $crees;
    }

    /** Aligne les statuts des chambres du scénario avec les séjours et le planning du jour. */
    private function synchroniserStatutsChambres(array $chambresParNum): void
    {
        $occupees = array_map(
            'intval',
            $this->pdo->query("SELECT DISTINCT room_id FROM bookings WHERE status = 'checked_in'")->fetchAll(\PDO::FETCH_COLUMN)
        );

        $etats = [
            '201' => 'occupied',
            '305' => 'occupied',
            '204' => 'occupied',
            '403' => 'occupied',
            '205' => 'maintenance',
            '304' => 'dirty',
            '104' => 'dirty',
            '103' => 'cleaning',
            '101' => 'clean',
            '102' => 'available',
            '402' => 'available',
            '401' => 'available',
            '105' => 'available',
            '106' => 'available',
            '203' => 'available',
            '206' => 'available',
        ];

        $stmt = $this->pdo->prepare('UPDATE rooms SET status = ? WHERE id = ?');
        foreach ($etats as $num => $statut) {
            if (!isset($chambresParNum[$num])) {
                continue;
            }
            $roomId = $chambresParNum[$num];
            // Ne jamais écraser un état d'occupation réel, ni marquer occupied sans séjour.
            if ($statut === 'occupied' && !in_array($roomId, $occupees, true)) {
                continue;
            }
            if ($statut !== 'occupied' && in_array($roomId, $occupees, true)) {
                continue;
            }
            $stmt->execute([$statut, $roomId]);
        }
    }

    // ── Fournisseurs, familles, zones et articles ────────────────────────────

    private function achats(array $o): int
    {
        $crees  = 0;
        $parNom = [];

        if ($this->tableExists('suppliers')) {
            foreach ($o['suppliers'] as $f) {
                $avant = $this->findId('suppliers', ['name' => $f['name']]);

                $parNom[$f['name']] = $this->insertOnce(
                    'suppliers',
                    ['name' => $f['name']],
                    array_merge([
                        'contact_name'  => $f['contact'],
                        'contact_phone' => $f['phone'],
                        'contact_email' => $f['email'],
                        'phone'         => $f['phone'],
                        'email'         => $f['email'],
                        'category'      => $f['category'],
                        'city'          => $f['city'] ?? null,
                        'payment_terms' => $f['payment_terms'] ?? null,
                        'is_active'     => true,
                    ], $this->timestamps())
                );

                if ($avant === null) {
                    $crees++;
                } elseif (Registre::contient($this->pdo, 'suppliers', $parNom[$f['name']])) {
                    $this->pdo->prepare('UPDATE suppliers SET category = ? WHERE id = ?')
                        ->execute([$f['category'], $parNom[$f['name']]]);
                }
            }
        }

        if (!$this->tableExists('stock_items')) {
            return $crees;
        }

        $familles = [];

        foreach ($o['stock_categories'] as $rang => $cat) {
            $avant = $this->findId('stock_categories', ['name' => $cat['name']]);

            $familles[$cat['name']] = $this->insertOnce(
                'stock_categories',
                ['name' => $cat['name']],
                array_merge(['sort_order' => $rang, 'is_active' => true], $this->timestamps())
            );

            if ($avant === null) {
                $crees++;
            }
        }

        $zoneDeFamille = [];

        if ($this->tableExists('storage_zones')) {
            foreach ($o['storage_zones'] ?? [] as $rang => $zone) {
                $avant  = $this->findId('storage_zones', ['name' => $zone['name']]);
                $zoneId = $avant ?? $this->insert('storage_zones', array_merge(
                    ['name' => $zone['name'], 'sort_order' => $rang, 'is_active' => true],
                    $this->timestamps()
                ));

                foreach ($zone['categories'] as $famille) {
                    $zoneDeFamille[$famille] = $zoneId;
                }

                if ($avant === null) {
                    $crees++;
                }
            }
        }

        foreach ($o['stock_items'] as $rang => $article) {
            $tarifs = array_values(array_filter(
                $article['suppliers'] ?? [],
                fn (array $t) => isset($parNom[$t[0]])
            ));
            $existant     = $this->findId('stock_items', ['name' => $article['name']]);
            $prixCentimes = (int) $article['price'] * 100;
            $qteStock     = isset($article['stock']) ? (int) $article['stock'] : (int) $article['min'] * 2;
            $unite        = $this->unite($article['unit']);

            if ($existant !== null) {
                if (Registre::contient($this->pdo, 'stock_items', $existant)) {
                    $this->tarifs($existant, $tarifs, $parNom);

                    $this->pdo->prepare(
                        'UPDATE stock_items
                         SET stock_category_id   = COALESCE(stock_category_id, ?),
                             storage_zone_id     = COALESCE(storage_zone_id, ?),
                             average_cost        = CASE WHEN average_cost > 0 AND average_cost < 50000 THEN ? ELSE average_cost END,
                             last_purchase_price = CASE WHEN last_purchase_price > 0 AND last_purchase_price < 50000 THEN ? ELSE last_purchase_price END
                         WHERE id = ?'
                    )->execute([
                        $familles[$article['category']] ?? null,
                        $zoneDeFamille[$article['category']] ?? null,
                        $prixCentimes,
                        $prixCentimes,
                        $existant,
                    ]);
                }
                continue;
            }

            $itemId = $this->insert('stock_items', array_merge([
                'stock_category_id'   => $familles[$article['category']] ?? null,
                'storage_zone_id'     => $zoneDeFamille[$article['category']] ?? null,
                'name'                => $article['name'],
                'reference'           => 'DEMO-S' . str_pad((string) ($rang + 1), 4, '0', STR_PAD_LEFT),
                'unit'                => $unite,
                'current_stock'       => $qteStock,
                'min_stock'           => $article['min'],
                'average_cost'        => $prixCentimes,
                'last_purchase_price' => $prixCentimes,
                'supplier_id'         => isset($tarifs[0]) ? $parNom[$tarifs[0][0]] : null,
                'is_active'           => true,
            ], $this->timestamps()));

            $this->tarifs($itemId, $tarifs, $parNom);
            $crees++;
        }

        // Complète le lien stock_item_id sur les articles du garde-manger créés avant OperationsSeeder.
        $this->lierGardeMangerAuStock();

        return $crees;
    }

    private function lierGardeMangerAuStock(): void
    {
        if (!$this->tableExists('restaurant_pantry_items') || !$this->columnExists('restaurant_pantry_items', 'stock_item_id')) {
            return;
        }

        $stockParNom = [];
        foreach ($this->pdo->query('SELECT id, name FROM stock_items')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $stockParNom[$row['name']] = (int) $row['id'];
        }

        $upd = $this->pdo->prepare(
            'UPDATE restaurant_pantry_items SET stock_item_id = ? WHERE name = ? AND stock_item_id IS NULL'
        );
        foreach ($this->data['restaurant']['pantry_items'] ?? [] as $item) {
            if (!empty($item['stock_item']) && isset($stockParNom[$item['stock_item']])) {
                $upd->execute([$stockParNom[$item['stock_item']], $item['name']]);
            }
        }
    }

    /** @var array<string, string> unité du jeu => symbole de l'établissement */
    private array $unites = [];

    private function unite(string $unite): string
    {
        if (isset($this->unites[$unite]) || !$this->tableExists('stock_units')) {
            return $this->unites[$unite] ?? $unite;
        }

        $avecSymbole = $this->columnExists('stock_units', 'short_name');
        $colonnes    = $avecSymbole ? "name, COALESCE(NULLIF(short_name, ''), name)" : 'name, name';
        $condition   = $avecSymbole ? 'lower(name) = lower(?) OR lower(short_name) = lower(?)' : 'lower(name) = lower(?)';

        $stmt = $this->pdo->prepare("SELECT {$colonnes} FROM stock_units WHERE {$condition} ORDER BY id LIMIT 1");
        $stmt->execute($avecSymbole ? [$unite, $unite] : [$unite]);
        $trouvee = $stmt->fetch(\PDO::FETCH_NUM);

        if ($trouvee !== false) {
            return $this->unites[$unite] = $trouvee[1];
        }

        $this->insert('stock_units', array_merge([
            'name'       => mb_strtoupper(mb_substr($unite, 0, 1)) . mb_substr($unite, 1),
            'short_name' => $unite,
            'sort_order' => 100,
            'is_active'  => true,
        ], $this->timestamps()));

        return $this->unites[$unite] = $avecSymbole ? $unite : mb_strtoupper(mb_substr($unite, 0, 1)) . mb_substr($unite, 1);
    }

    private function tarifs(int $itemId, array $tarifs, array $parNom): void
    {
        if (!$this->tableExists('stock_item_supplier')) {
            return;
        }

        foreach ($tarifs as [$fournisseur, $prix]) {
            $this->insertOnce(
                'stock_item_supplier',
                ['stock_item_id' => $itemId, 'supplier_id' => $parNom[$fournisseur]],
                array_merge(['purchase_price' => (int) $prix * 100, 'priced_at' => $this->jours(-20)], $this->timestamps())
            );
        }
    }

    // ── Dépôts de service & Mini-bars ────────────────────────────────────────

    private function depotsEtMinibars(array $o): int
    {
        $crees       = 0;
        $stockParNom = [];

        if ($this->tableExists('stock_items')) {
            foreach ($this->pdo->query('SELECT id, name, average_cost FROM stock_items')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $stockParNom[$row['name']] = ['id' => (int) $row['id'], 'cost' => (int) $row['average_cost']];
            }
        }

        $depotMinibarId = null;

        $restoPosId = $this->tableExists('points_of_sale')
            ? ($this->pdo->query("SELECT id FROM points_of_sale WHERE kind = 'restauration' AND is_active = true ORDER BY sort_order, id LIMIT 1")->fetchColumn() ?: null)
            : null;

        if ($this->tableExists('service_stores')) {
            foreach ($o['service_stores'] ?? [] as $rang => $store) {
                $isBar = ($store['restaurant_service'] ?? null) === 'bar' || ($store['department'] === 'restaurant' && str_contains(mb_strtolower($store['name']), 'bar'));
                $storeId = null;

                if ($isBar && $restoPosId !== null && $this->columnExists('service_stores', 'point_of_sale_id')) {
                    $storeId = $this->findId('service_stores', [
                        'point_of_sale_id'   => (int) $restoPosId,
                        'restaurant_service' => 'bar',
                    ]);
                }
                if ($isBar && $storeId === null && $this->columnExists('service_stores', 'restaurant_service')) {
                    $storeId = $this->findId('service_stores', ['restaurant_service' => 'bar']);
                }

                if ($storeId === null) {
                    $avant   = $this->findId('service_stores', ['name' => $store['name']]);
                    $storeId = $this->insertOnce(
                        'service_stores',
                        ['name' => $store['name']],
                        array_merge([
                            'department'         => $store['department'],
                            'point_of_sale_id'   => ($store['department'] === 'restaurant' && $restoPosId !== null) ? (int) $restoPosId : null,
                            'restaurant_service' => $store['restaurant_service'] ?? ($isBar ? 'bar' : null),
                            'is_active'          => true,
                            'sort_order'         => $rang,
                        ], $this->timestamps())
                    );

                    if ($avant === null) {
                        $crees++;
                    }
                } elseif ($this->columnExists('service_stores', 'restaurant_service')) {
                    $this->pdo->prepare(
                        "UPDATE service_stores SET restaurant_service = COALESCE(restaurant_service, 'bar'), point_of_sale_id = COALESCE(point_of_sale_id, ?) WHERE id = ?"
                    )->execute([$restoPosId !== null ? (int) $restoPosId : null, $storeId]);
                }

                if ($store['department'] === 'housekeeping' && $depotMinibarId === null) {
                    $depotMinibarId = $storeId;
                }

                if ($this->tableExists('service_store_stocks')) {
                    foreach ($store['stocks'] ?? [] as $ligne) {
                        $info = $stockParNom[$ligne['stock_item']] ?? null;
                        if ($info === null) {
                            continue;
                        }

                        // Si c'est du Rhum au Bar, 1 bouteille est ouverte donc 5 bouteilles fermées restent en stock
                        $qte = ($isBar && $ligne['stock_item'] === 'Rhum ambré 75 cl' && $ligne['quantity'] > 1)
                            ? $ligne['quantity'] - 1
                            : $ligne['quantity'];

                        $avantStock = $this->findId('service_store_stocks', [
                            'service_store_id' => $storeId,
                            'stock_item_id'    => $info['id'],
                        ]);

                        $this->insertOnce(
                            'service_store_stocks',
                            ['service_store_id' => $storeId, 'stock_item_id' => $info['id']],
                            array_merge([
                                'current_stock' => $qte,
                                'average_cost'  => $info['cost'],
                            ], $this->timestamps())
                        );

                        if ($avantStock === null) {
                            $crees++;
                        }
                    }
                }
            }
        }

        if ($depotMinibarId !== null && $this->tableExists('tenants')) {
            $this->configurerDepotMinibar($depotMinibarId);
        }

        if (!$this->tableExists('minibar_articles') || empty($o['minibar'])) {
            return $crees;
        }

        $articlesParNom = [];
        foreach ($o['minibar']['articles'] ?? [] as $rang => $art) {
            $info = $stockParNom[$art['stock_item']] ?? null;
            if ($info === null) {
                continue;
            }

            $avant = $this->findId('minibar_articles', ['stock_item_id' => $info['id']]);
            $artId = $this->insertOnce(
                'minibar_articles',
                ['stock_item_id' => $info['id']],
                array_merge([
                    'name'       => $art['name'],
                    'price'      => (int) $art['price'] * 100,
                    'is_active'  => true,
                    'sort_order' => $rang,
                ], $this->timestamps())
            );

            $articlesParNom[$art['name']] = ['id' => $artId, 'cost' => $info['cost']];
            if ($avant === null) {
                $crees++;
            }
        }

        // Standards de dotation par catégorie (en privilégiant les catégories du jeu sur Zingana).
        $standardsParType = [];

        if ($this->tableExists('minibar_standards')) {
            $typesParCode = $this->roomTypesParCodeDemoFirst();

            foreach ($o['minibar']['standards'] ?? [] as $code => $dotation) {
                $rtId = $typesParCode[$code] ?? null;
                if ($rtId === null) {
                    continue;
                }

                foreach ($dotation as $nomArt => $qte) {
                    $art = $articlesParNom[$nomArt] ?? null;
                    if ($art === null) {
                        continue;
                    }

                    $avantStd = $this->findId('minibar_standards', [
                        'room_type_id'       => $rtId,
                        'minibar_article_id' => $art['id'],
                    ]);

                    $this->insertOnce(
                        'minibar_standards',
                        ['room_type_id' => $rtId, 'minibar_article_id' => $art['id']],
                        array_merge(['quantity' => (int) $qte], $this->timestamps())
                    );

                    if ($avantStd === null) {
                        $crees++;
                    }
                    $standardsParType[$rtId][] = ['art' => $art, 'name' => $nomArt, 'std' => (int) $qte];
                }
            }
        }

        if ($this->tableExists('room_minibar_lines') && $standardsParType !== []) {
            $chambres = $this->pdo->query(
                'SELECT id, number, room_type_id FROM rooms WHERE is_active = true ORDER BY id'
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($chambres as $c) {
                $lignes = $standardsParType[(int) $c['room_type_id']] ?? null;
                if ($lignes === null) {
                    continue;
                }

                $num = (string) $c['number'];
                foreach ($lignes as $l) {
                    $avantLigne = $this->findId('room_minibar_lines', [
                        'room_id'            => (int) $c['id'],
                        'minibar_article_id' => $l['art']['id'],
                    ]);

                    // Ch. 403 : mini-bar vidé pendant le séjour ; Ch. 304 : manque 1 eau à recharger ;
                    // Ch. 305 et autres : plein au standard.
                    $qteReelle = match (true) {
                        $num === '403'                                  => 0,
                        $num === '304' && $l['name'] === 'Eau minérale 50 cl' => max(0, $l['std'] - 1),
                        default                                         => $l['std'],
                    };

                    $this->insertOnce(
                        'room_minibar_lines',
                        ['room_id' => (int) $c['id'], 'minibar_article_id' => $l['art']['id']],
                        array_merge([
                            'standard_quantity' => $l['std'],
                            'quantity'          => $qteReelle,
                            'unit_cost'         => $l['art']['cost'],
                        ], $this->timestamps())
                    );

                    if ($avantLigne === null) {
                        $crees++;
                    }
                }
            }
        }

        return $crees;
    }

    private function configurerDepotMinibar(int $depotId): void
    {
        $row = $this->pdo->query('SELECT id, settings FROM tenants ORDER BY id LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }

        $settings = json_decode((string) ($row['settings'] ?? '{}'), true);
        if (!is_array($settings)) {
            $settings = [];
        }

        if (!empty($settings['minibar']['depot_id'])) {
            return;
        }

        $settings['minibar']['depot_id'] = $depotId;
        $this->pdo->prepare('UPDATE tenants SET settings = ? WHERE id = ?')
            ->execute([$this->json($settings), (int) $row['id']]);
    }

    /** Fiches boissons Bar, bouteille de Rhum ambré ouverte et historique des consommations. */
    private function barBouteillesEtFiches(): int
    {
        $crees = 0;

        $stockParNom = [];
        if ($this->tableExists('stock_items')) {
            foreach ($this->pdo->query('SELECT id, name, average_cost FROM stock_items')->fetchAll(\PDO::FETCH_ASSOC) as $s) {
                $stockParNom[$s['name']] = ['id' => (int) $s['id'], 'cost' => (int) $s['average_cost']];
            }
        }

        // 1. Fiches techniques des boissons (restaurant_bar_drinks)
        if ($this->tableExists('restaurant_bar_drinks') && !empty($this->data['restaurant']['bar_drinks']) && $this->tableExists('restaurant_menu_items')) {
            $menuItemsParNom = [];
            foreach ($this->pdo->query('SELECT id, name FROM restaurant_menu_items')->fetchAll(\PDO::FETCH_ASSOC) as $m) {
                $menuItemsParNom[$m['name']] = (int) $m['id'];
            }

            foreach ($this->data['restaurant']['bar_drinks'] as $bd) {
                $menuItemId = $menuItemsParNom[$bd['menu_item']] ?? null;
                if ($menuItemId === null) {
                    continue;
                }

                $format = $bd['format'];
                $recipe = in_array($format, ['cocktail', 'mocktail'], true);
                $ingredients = [];

                if ($recipe) {
                    foreach ($bd['ingredients'] ?? [] as $ing) {
                        $stockId = $stockParNom[$ing['stock_item']]['id'] ?? null;
                        if ($stockId === null) {
                            continue;
                        }
                        $ingredients[] = [
                            'stock_item_id' => $stockId,
                            'measure'       => $ing['measure'] ?? 'ml',
                            'quantity'      => (float) $ing['quantity'],
                        ];
                    }
                } else {
                    $stockId = isset($bd['stock_item']) ? ($stockParNom[$bd['stock_item']]['id'] ?? null) : null;
                    if ($stockId !== null) {
                        $ingredients[] = [
                            'stock_item_id' => $stockId,
                            'measure'       => $format === 'glass' ? 'ml' : 'stock',
                            'quantity'      => $format === 'glass' ? 50 : 1,
                        ];
                    }
                }

                if ($ingredients === []) {
                    continue;
                }

                $avant = $this->findId('restaurant_bar_drinks', ['restaurant_menu_item_id' => $menuItemId]);
                $this->insertOnce(
                    'restaurant_bar_drinks',
                    ['restaurant_menu_item_id' => $menuItemId],
                    array_merge([
                        'format'       => $format,
                        'ingredients'  => $this->json($ingredients),
                        'instructions' => $bd['instructions'] ?? null,
                    ], $this->timestamps())
                );

                if ($avant === null) {
                    $crees++;
                }
            }
        }

        // 2. Bouteille ouverte au Bar et traçabilité (restaurant_bar_bottles)
        if ($this->tableExists('restaurant_bar_bottles') && $this->tableExists('service_stores')) {
            $barStoreId = $this->pdo->query(
                "SELECT id FROM service_stores WHERE restaurant_service = 'bar' AND is_active = true ORDER BY id LIMIT 1"
            )->fetchColumn();
            if (!$barStoreId) {
                $barStoreId = $this->findId('service_stores', ['name' => 'Dépôt Bar - Restaurant']);
            }

            $rhumInfo = $stockParNom['Rhum ambré 75 cl'] ?? null;
            $barmanId = $this->userIdByEmail('arnaud.simo@' . StaffSeeder::DOMAINE, false) ?? $this->anyUserId();

            if ($barStoreId && $rhumInfo) {
                $storeId  = (int) $barStoreId;
                $rhumId   = (int) $rhumInfo['id'];
                $rhumCost = (int) $rhumInfo['cost'];

                $avantBtl = $this->findId('restaurant_bar_bottles', [
                    'service_store_id' => $storeId,
                    'stock_item_id'    => $rhumId,
                ]);

                if ($avantBtl === null) {
                    $bottleId = $this->insert('restaurant_bar_bottles', array_merge([
                        'service_store_id' => $storeId,
                        'stock_item_id'    => $rhumId,
                        'capacity_ml'      => 750,
                        'remaining_ml'     => 680,
                        'opening_cost'     => $rhumCost,
                        'opened_by'        => $barmanId,
                        'opening_key'      => '00000000-0000-4000-8000-000000000001',
                    ], $this->timestamps($this->jours(0, '08:30:00'))));
                    $crees++;

                    // 3. Mouvements de stock / traçabilité bouteille (selon brainstorming)
                    if ($this->tableExists('service_store_movements')) {
                        $orderId = null;
                        if ($this->tableExists('restaurant_customer_orders')) {
                            $orderId = $this->pdo->query(
                                "SELECT id FROM restaurant_customer_orders WHERE notes = '" . RestaurantSeeder::REPERE_COMMANDE . "' ORDER BY id LIMIT 1"
                            )->fetchColumn() ?: null;
                        }

                        // Mouvement 1 : Ouverture de la bouteille (75 cl)
                        $this->insert('service_store_movements', array_merge([
                            'service_store_id' => $storeId,
                            'stock_item_id'    => $rhumId,
                            'type'             => 'out',
                            'quantity'         => -1,
                            'stock_after'      => 5,
                            'unit_cost'        => $rhumCost,
                            'stock_account'    => '311',
                            'source_type'      => 'bar_open',
                            'source_id'        => $bottleId,
                            'reason'           => 'Ouverture de la bouteille',
                            'details'          => $this->json([
                                'bottle_id'    => $bottleId,
                                'remaining_ml' => 750,
                                'value_delta'  => -$rhumCost,
                            ]),
                            'user_id'          => $barmanId,
                            'occurred_at'      => $this->jours(0, '08:30:00'),
                        ], $this->timestamps($this->jours(0, '08:30:00'))));
                        $crees++;

                        // Mouvement 2 : Vente au verre (5 cl prélevés -> 70 cl restants)
                        $this->insert('service_store_movements', array_merge([
                            'service_store_id' => $storeId,
                            'stock_item_id'    => $rhumId,
                            'type'             => 'out',
                            'quantity'         => 0,
                            'stock_after'      => 5,
                            'unit_cost'        => 0,
                            'stock_account'    => '311',
                            'source_type'      => 'bar_sale',
                            'source_id'        => $orderId !== null ? (int) $orderId : $bottleId,
                            'reason'           => 'Verre de rhum (5 cl)',
                            'details'          => $this->json([
                                'bottle_id'    => $bottleId,
                                'volume_ml'    => -50,
                                'remaining_ml' => 700,
                                'value_delta'  => -80000,
                                'sale_label'   => 'Verre de rhum (5 cl)',
                            ]),
                            'user_id'          => $barmanId,
                            'occurred_at'      => $this->jours(0, '09:15:00'),
                        ], $this->timestamps($this->jours(0, '09:15:00'))));
                        $crees++;

                        // Mouvement 3 : Prélèvement pour un cocktail (2 cl prélevés -> 68 cl restants)
                        $this->insert('service_store_movements', array_merge([
                            'service_store_id' => $storeId,
                            'stock_item_id'    => $rhumId,
                            'type'             => 'out',
                            'quantity'         => 0,
                            'stock_after'      => 5,
                            'unit_cost'        => 0,
                            'stock_account'    => '311',
                            'source_type'      => 'bar_sale',
                            'source_id'        => $orderId !== null ? (int) $orderId : $bottleId,
                            'reason'           => 'Cocktail Daiquiri',
                            'details'          => $this->json([
                                'bottle_id'    => $bottleId,
                                'volume_ml'    => -20,
                                'remaining_ml' => 680,
                                'value_delta'  => -32000,
                                'sale_label'   => 'Cocktail Daiquiri',
                            ]),
                            'user_id'          => $barmanId,
                            'occurred_at'      => $this->jours(0, '09:20:00'),
                        ], $this->timestamps($this->jours(0, '09:20:00'))));
                        $crees++;
                    }
                }
            }
        }

        return $crees;
    }

    // ── Maintenance (DIT) ────────────────────────────────────────────────────

    private function maintenance(): int
    {
        if (!$this->tableExists('maintenance_requests')) {
            return 0;
        }

        $crees  = 0;
        $odile  = $this->userIdByEmail('odile.manga@' . StaffSeeder::DOMAINE) ?? $this->anyUserId();
        $serge  = $this->userIdByEmail('serge.djomo@' . StaffSeeder::DOMAINE) ?? $odile;
        $alain  = $this->userIdByEmail('alain.messi@' . StaffSeeder::DOMAINE) ?? $odile;
        $didier = $this->userIdByEmail('didier.nkwenti@' . StaffSeeder::DOMAINE) ?? $alain;

        $ch205 = $this->findId('rooms', ['number' => '205']);
        $hk205 = $ch205 !== null
            ? $this->pdo->query("SELECT id FROM housekeeping_assignments WHERE room_id = {$ch205} AND status = 'blocked' ORDER BY id DESC LIMIT 1")->fetchColumn()
            : false;

        $demandes = [
            [
                'reference'                  => 'DIT-DEMO-0001',
                'status'                     => 'open',
                'priority'                   => 'urgent',
                'specialty'                  => 'climatisation',
                'room_id'                    => $ch205,
                'location'                   => 'Chambre 205 (2e étage)',
                'title'                      => 'Climatiseur défaillant et fuite d\'eau au plafond',
                'description'                => '[demo] Signalé par Pélagie Mvondo au nettoyage de départ : fuite sur unité murale et déclenchement du disjoncteur.',
                'department'                 => 'housekeeping',
                'source'                     => 'housekeeping',
                'housekeeping_assignment_id' => $hk205 !== false ? (int) $hk205 : null,
                'requested_by'               => $odile,
                'room_blocked'               => true,
                'reopened_count'             => 0,
                'assigned_to'                => null,
                'assigned_by'                => null,
                'assigned_at'                => null,
            ],
            [
                'reference'                  => 'DIT-DEMO-0002',
                'status'                     => 'assigned',
                'priority'                   => 'high',
                'specialty'                  => 'froid',
                'room_id'                    => null,
                'location'                   => 'Cuisine principale — Chambre froide 1',
                'title'                      => 'Sonde de température chambre froide 1 instable',
                'description'                => '[demo] Écart de température constaté à +7°C ce matin au lieu de +3°C.',
                'department'                 => 'restaurant',
                'source'                     => 'manual',
                'housekeeping_assignment_id' => null,
                'requested_by'               => $serge,
                'room_blocked'               => false,
                'reopened_count'             => 0,
                'assigned_to'                => $didier,
                'assigned_by'                => $alain,
                'assigned_at'                => $this->jours(0, '08:40:00'),
            ],
        ];

        foreach ($demandes as $d) {
            if ($this->findId('maintenance_requests', ['reference' => $d['reference']]) !== null) {
                continue;
            }

            $quand = $this->jours(0, '08:20:00');
            $reqId = $this->insert('maintenance_requests', array_merge($d, $this->timestamps($quand)));
            $crees++;

            if ($this->tableExists('maintenance_request_events')) {
                $this->insert('maintenance_request_events', [
                    'maintenance_request_id' => $reqId,
                    'user_id'                => $d['requested_by'],
                    'event'                  => 'created',
                    'notes'                  => $d['description'],
                    'created_at'             => $quand,
                ]);
                $crees++;

                if ($d['assigned_to'] !== null) {
                    $this->insert('maintenance_request_events', [
                        'maintenance_request_id' => $reqId,
                        'user_id'                => $d['assigned_by'],
                        'event'                  => 'assigned',
                        'notes'                  => 'Affectée à Didier Nkwenti (Froid)',
                        'created_at'             => $d['assigned_at'],
                    ]);
                    $crees++;
                }
            }
        }

        return $crees;
    }

    // ── Approvisionnements (DM, DA, BC) ──────────────────────────────────────

    private function approvisionnements(array $o): int
    {
        if (!$this->tableExists('stock_items')) {
            return 0;
        }

        $stock = [];
        foreach ($this->pdo->query('SELECT id, name, average_cost FROM stock_items')->fetchAll(\PDO::FETCH_ASSOC) as $s) {
            $stock[$s['name']] = ['id' => (int) $s['id'], 'cost' => (int) $s['average_cost']];
        }

        $crees    = 0;
        $serge    = $this->userIdByEmail('serge.djomo@' . StaffSeeder::DOMAINE) ?? $this->anyUserId();
        $bernad   = $this->userIdByEmail('bernadette.atangana@' . StaffSeeder::DOMAINE) ?? $serge;
        $roger    = $this->userIdByEmail('roger.essomba@' . StaffSeeder::DOMAINE) ?? $serge;
        $martin   = $this->userIdByEmail('martin.eyoum@' . StaffSeeder::DOMAINE) ?? $serge;
        $gaston   = $this->userIdByEmail('gaston.ewane@' . StaffSeeder::DOMAINE) ?? $serge;

        $restoPosId    = $this->tableExists('points_of_sale')
            ? ($this->pdo->query("SELECT id FROM points_of_sale WHERE kind = 'restauration' AND is_active = true ORDER BY sort_order, id LIMIT 1")->fetchColumn() ?: null)
            : null;
        $depotEtagesId = $this->tableExists('service_stores')
            ? $this->findId('service_stores', ['name' => 'Dépôt Étages & Mini-bars'])
            : null;

        // 1. Demandes de marchandises (DM)
        if ($this->tableExists('stock_requisitions') && $this->tableExists('stock_requisition_lines')) {
            $dms = [
                [
                    'number'              => 'DM-DEMO-0001',
                    'department'          => 'restaurant',
                    'point_of_sale_id'    => $restoPosId !== null ? (int) $restoPosId : null,
                    'service_store_id'    => null,
                    'status'              => 'pending',
                    'purpose'             => '[demo] Réapprovisionnement cuisine midi — rupture huile et café',
                    'requested_by'        => $serge,
                    'requester_signature' => 'S. Djomo',
                    'endorsed_by'         => $serge,
                    'endorsed_at'         => $this->jours(0, '07:30:00'),
                    'lines'               => [['Huile végétale 5 L', 4], ['Café moulu 1 kg', 3]],
                ],
                [
                    'number'              => 'DM-DEMO-0002',
                    'department'          => 'housekeeping',
                    'point_of_sale_id'    => null,
                    'service_store_id'    => $depotEtagesId,
                    'status'              => 'awaiting_endorsement',
                    'purpose'             => '[demo] Dotation étages — savonnettes et shampoings sous seuil',
                    'requested_by'        => $bernad,
                    'requester_signature' => 'B. Atangana',
                    'endorsed_by'         => null,
                    'endorsed_at'         => null,
                    'lines'               => [['Savonnette d\'accueil 25 g', 100], ['Shampoing 30 ml', 80]],
                ],
            ];

            foreach ($dms as $dm) {
                if ($this->findId('stock_requisitions', ['number' => $dm['number']]) !== null) {
                    continue;
                }

                $lines = $dm['lines'];
                unset($dm['lines']);

                $dmId = $this->insert('stock_requisitions', array_merge($dm, $this->timestamps($this->jours(0, '07:30:00'))));
                $crees++;

                foreach ($lines as [$nomArt, $qte]) {
                    if (!isset($stock[$nomArt])) {
                        continue;
                    }
                    $this->insert('stock_requisition_lines', array_merge([
                        'stock_requisition_id' => $dmId,
                        'stock_item_id'        => $stock[$nomArt]['id'],
                        'quantity_requested'   => $qte,
                        'quantity_issued'      => 0,
                    ], $this->timestamps($this->jours(0, '07:30:00'))));
                    $crees++;
                }
            }
        }

        // 2. Demandes d'achat (DA)
        if ($this->tableExists('purchase_requests') && $this->tableExists('purchase_request_lines')) {
            $mboppiId   = $this->findId('suppliers', ['name' => 'Mboppi Distribution']);
            $pecherieId = $this->findId('suppliers', ['name' => 'Pêcherie du Wouri']);

            $das = [
                [
                    'number'      => 'DA-DEMO-0001',
                    'department'  => 'economat',
                    'priority'    => 'urgent',
                    'status'      => 'pending',
                    'supplier_id' => $mboppiId,
                    'purpose'     => '[demo] Réassort d\'urgence ruptures cuisine (huile et café)',
                    'requested_by' => $roger,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_notes' => null,
                    'lines'       => [['Huile végétale 5 L', 12, null], ['Café moulu 1 kg', 10, null]],
                ],
                [
                    'number'      => 'DA-DEMO-0002',
                    'department'  => 'economat',
                    'priority'    => 'normal',
                    'status'      => 'approved',
                    'supplier_id' => $pecherieId,
                    'purpose'     => '[demo] Approvisionnement produits frais et poissons',
                    'requested_by' => $roger,
                    'reviewed_by' => $martin,
                    'reviewed_at' => $this->jours(-1, '16:00:00'),
                    'review_notes' => '[demo] Approuvée par le contrôle de gestion — prête pour bon de commande.',
                    'lines'       => [['Crevettes', 10, 10], ['Bar entier', 15, 15]],
                ],
            ];

            foreach ($das as $da) {
                if ($this->findId('purchase_requests', ['number' => $da['number']]) !== null) {
                    continue;
                }

                $lines = $da['lines'];
                unset($da['lines']);

                $totalEstime = 0;
                foreach ($lines as [$nomArt, $qteReq]) {
                    $totalEstime += (int) (($stock[$nomArt]['cost'] ?? 0) * $qteReq);
                }

                $da['total_estimated_amount'] = $totalEstime;
                $daId = $this->insert('purchase_requests', array_merge($da, $this->timestamps($this->jours(-1, '14:00:00'))));
                $crees++;

                foreach ($lines as [$nomArt, $qteReq, $qteApp]) {
                    if (!isset($stock[$nomArt])) {
                        continue;
                    }
                    $this->insert('purchase_request_lines', array_merge([
                        'purchase_request_id'  => $daId,
                        'stock_item_id'        => $stock[$nomArt]['id'],
                        'quantity_requested'   => $qteReq,
                        'quantity_approved'    => $qteApp,
                        'estimated_unit_price' => $stock[$nomArt]['cost'],
                    ], $this->timestamps($this->jours(-1, '14:00:00'))));
                    $crees++;
                }
            }
        }

        // 3. Bon de commande envoyé (BC) prêt pour réception au magasin central
        if ($this->tableExists('purchase_orders') && $this->tableExists('purchase_order_lines')) {
            $brasseriesId = $this->findId('suppliers', ['name' => 'Brasseries du Cameroun']);
            if ($brasseriesId !== null && $this->findId('purchase_orders', ['number' => 'BC-DEMO-0001']) === null) {
                $lignesBc = [
                    ['Eau minérale 50 cl', 120],
                    ['Jus de mangue 33 cl', 48],
                    ['Bière locale 33 cl', 48],
                ];
                $totalBc = 0;
                foreach ($lignesBc as [$nomArt, $qte]) {
                    $totalBc += (int) (($stock[$nomArt]['cost'] ?? 0) * $qte);
                }

                $sentAt = $this->jours(-1, '11:00:00');
                $bcId   = $this->insert('purchase_orders', array_merge([
                    'number'           => 'BC-DEMO-0001',
                    'supplier_id'      => $brasseriesId,
                    'status'           => 'sent',
                    'expected_at'      => $this->date(0),
                    'sent_at'          => $sentAt,
                    'sent_to_email'    => 'commandes@brasseries.example',
                    'transmission'     => 'email',
                    'total_amount'     => $totalBc,
                    'notes'            => '[demo] Livraison boissons attendue aujourd\'hui au magasin central',
                    'created_by'       => $gaston,
                    'issuer_signature' => 'G. Ewane',
                ], $this->timestamps($sentAt)));
                $crees++;

                foreach ($lignesBc as [$nomArt, $qte]) {
                    if (!isset($stock[$nomArt])) {
                        continue;
                    }
                    $this->insert('purchase_order_lines', array_merge([
                        'purchase_order_id' => $bcId,
                        'stock_item_id'     => $stock[$nomArt]['id'],
                        'quantity_ordered'  => $qte,
                        'quantity_received' => 0,
                        'unit_price'        => $stock[$nomArt]['cost'],
                    ], $this->timestamps($sentAt)));
                    $crees++;
                }
            }
        }

        return $crees;
    }

    // ── Fiches techniques de coût par catégorie de chambre ───────────────────

    private function fichesCoutsChambres(): int
    {
        if (!$this->tableExists('room_cost_sheets') || !$this->tableExists('room_cost_items')) {
            return 0;
        }

        $crees        = 0;
        $typesParCode = $this->roomTypesParCodeDemoFirst();
        $stockIds     = [];
        if ($this->tableExists('stock_items')) {
            foreach ($this->pdo->query('SELECT id, name FROM stock_items')->fetchAll(\PDO::FETCH_ASSOC) as $s) {
                $stockIds[$s['name']] = (int) $s['id'];
            }
        }

        foreach ($this->data['hotel']['room_cost_sheets'] ?? [] as $code => $fiche) {
            $rtId = $typesParCode[$code] ?? null;
            if ($rtId === null) {
                continue;
            }

            $avantSheet = $this->findId('room_cost_sheets', ['room_type_id' => $rtId]);
            $this->insertOnce(
                'room_cost_sheets',
                ['room_type_id' => $rtId],
                array_merge([
                    'reference_occupants'  => $fiche['reference_occupants'] ?? 1,
                    'avg_length_of_stay'   => $fiche['avg_length_of_stay'] ?? 2.0,
                    'fixed_cost_per_night' => (int) ($fiche['fixed_cost_per_night'] ?? 0) * 100,
                    'notes'                => $fiche['notes'] ?? null,
                ], $this->timestamps())
            );
            if ($avantSheet === null) {
                $crees++;
            }

            foreach ($fiche['items'] ?? [] as $rang => $poste) {
                $avantItem = $this->findId('room_cost_items', ['room_type_id' => $rtId, 'label' => $poste['label']]);
                $this->insertOnce(
                    'room_cost_items',
                    ['room_type_id' => $rtId, 'label' => $poste['label']],
                    array_merge([
                        'category'      => $poste['category'] ?? 'other',
                        'basis'         => $poste['basis'] ?? 'per_night',
                        'quantity'      => $poste['quantity'] ?? 1,
                        'unit_cost'     => (int) ($poste['unit_cost'] ?? 0) * 100,
                        'stock_item_id' => !empty($poste['stock_item']) ? ($stockIds[$poste['stock_item']] ?? null) : null,
                        'sort_order'    => $rang,
                        'is_active'     => true,
                    ], $this->timestamps())
                );
                if ($avantItem === null) {
                    $crees++;
                }
            }
        }

        return $crees;
    }

    // ── Charges d'exploitation (en centimes FCFA) ────────────────────────────

    private function charges(array $o): int
    {
        if (!$this->tableExists('expenses')) {
            return 0;
        }

        $par      = $this->userIdByEmail('victor.bilong@' . StaffSeeder::DOMAINE) ?? $this->anyUserId();
        $methodes = ['cash', 'bank_transfer', 'orange_money', 'mtn_momo'];
        $crees    = 0;

        foreach ($o['expenses'] as $i => $charge) {
            $montantCentimes = (int) $charge['amount'] * 100;
            $existant        = $this->findId('expenses', ['label' => $charge['label']]);

            if ($existant !== null) {
                // Normalise les anciennes charges pré-centimes si nécessaire.
                $this->pdo->prepare(
                    'UPDATE expenses SET amount = ? WHERE id = ? AND notes = ? AND amount < 1000000'
                )->execute([$montantCentimes, $existant, self::REPERE_CHARGE]);
                continue;
            }

            $this->insert('expenses', array_merge([
                'occurred_at'    => $this->jours(-($i * 4 + 2), '10:00:00'),
                'category'       => $charge['category'],
                'label'          => $charge['label'],
                'amount'         => $montantCentimes,
                'payment_method' => $this->cycle($methodes, $i),
                'recorded_by'    => $par,
                'notes'          => self::REPERE_CHARGE,
            ], $this->timestamps()));

            $crees++;
        }

        return $crees;
    }

    // ── Organisations partenaires & rattachement clients ─────────────────────

    private function partenaires(array $o): int
    {
        if (!$this->tableExists('partner_organizations')) {
            return 0;
        }

        $crees         = 0;
        $partenairesId = [];

        foreach ($o['partner_organizations'] as $rang => $p) {
            $id = $this->findId('partner_organizations', ['name' => $p['name']]);
            if ($id === null) {
                $id = $this->insert('partner_organizations', array_merge([
                    'name'                        => $p['name'],
                    'code'                        => 'DEMO-P' . str_pad((string) ($rang + 1), 3, '0', STR_PAD_LEFT),
                    'type'                        => $p['type'],
                    'contact_name'                => $p['contact'],
                    'contact_phone'               => $p['phone'],
                    'valid_from'                  => $this->date(-180),
                    'valid_until'                 => $this->date(185),
                    'is_active'                   => true,
                    'has_convention'              => true,
                    'room_discount_type'          => 'percent',
                    'room_discount_value'         => $p['discount'],
                    'restaurant_discount_percent' => max(0, $p['discount'] - 5),
                    'shop_discount_percent'       => max(0, $p['discount'] - 5),
                    'notes'                       => '[demo] Convention de démonstration',
                ], $this->timestamps()));
                $crees++;
            }
            $partenairesId[] = $id;
        }

        // Rattache jusqu'à 5 clients de démonstration (hors clients liés à de vraies réservations) aux partenaires.
        if ($partenairesId !== [] && $this->tableExists('customers')) {
            $where = 'notes = ? AND partner_organization_id IS NULL';
            $params = [CustomerSeeder::MARQUEUR];
            if ($this->tableExists('bookings')) {
                $where .= ' AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.customer_id = customers.id AND b.booking_number NOT LIKE ?)';
                $params[] = BookingSeeder::PREFIXE . '%';
            }

            $clientsSansPart = $this->ids(
                'customers',
                $where,
                $params,
                count($partenairesId)
            );
            $upd = $this->pdo->prepare('UPDATE customers SET partner_organization_id = ? WHERE id = ?');
            foreach ($clientsSansPart as $idx => $cid) {
                if (isset($partenairesId[$idx])) {
                    $upd->execute([$partenairesId[$idx], $cid]);
                }
            }
        }

        return $crees;
    }

    // ── Budget annuel prévisionnel & Discussions internes ────────────────────

    private function budgetEtDiscussions(array $o): int
    {
        $crees = 0;

        if ($this->tableExists('budget_lines') && !empty($o['budget_fcfa'])) {
            $annee      = (int) date('Y');
            $martin     = $this->userIdByEmail('martin.eyoum@' . StaffSeeder::DOMAINE) ?? $this->anyUserId();
            $henriette  = $this->userIdByEmail('henriette.ngono@' . StaffSeeder::DOMAINE) ?? $martin;

            foreach ($o['budget_fcfa'] as $centre => $natures) {
                foreach (['revenue', 'cost'] as $nature) {
                    $serie = $natures[$nature] ?? null;
                    if (!is_array($serie) || count($serie) !== 12) {
                        continue;
                    }

                    $avant = $this->findId('budget_lines', [
                        'year'   => $annee,
                        'center' => $centre,
                        'nature' => $nature,
                    ]);
                    if ($avant !== null) {
                        continue;
                    }

                    $ligne = [
                        'year'         => $annee,
                        'center'       => $centre,
                        'nature'       => $nature,
                        'status'       => 'validated',
                        'validated_by' => $henriette,
                        'validated_at' => $this->jours(-15, '09:00:00'),
                        'updated_by'   => $martin,
                    ];
                    foreach (range(1, 12) as $m) {
                        $ligne['m' . $m] = (int) $serie[$m - 1] * 100;
                    }

                    $this->insert('budget_lines', array_merge($ligne, $this->timestamps()));
                    $crees++;
                }
            }
        }

        if (
            $this->tableExists('discussion_conversations')
            && $this->tableExists('discussion_conversation_user')
            && $this->tableExists('discussion_messages')
        ) {
            $patrick = $this->userIdByEmail('patrick.tchinda@' . StaffSeeder::DOMAINE, false);
            $aurelie = $this->userIdByEmail('aurelie.nkoulou@' . StaffSeeder::DOMAINE, false);
            $odile   = $this->userIdByEmail('odile.manga@' . StaffSeeder::DOMAINE, false);
            $alain   = $this->userIdByEmail('alain.messi@' . StaffSeeder::DOMAINE, false);
            $roger   = $this->userIdByEmail('roger.essomba@' . StaffSeeder::DOMAINE, false);
            $serge   = $this->userIdByEmail('serge.djomo@' . StaffSeeder::DOMAINE, false);

            // Conversation de groupe : Passation Réception ↔ Housekeeping
            if ($patrick !== null && $odile !== null
                && $this->findId('discussion_conversations', ['title' => 'Passation Réception ↔ Housekeeping']) === null
            ) {
                $membres = array_values(array_unique(array_filter([$patrick, $aurelie, $odile, $alain])));
                $quand   = $this->jours(0, '08:25:00');

                $convId = $this->insert('discussion_conversations', array_merge([
                    'title'      => 'Passation Réception ↔ Housekeeping',
                    'is_group'   => true,
                    'created_by' => $patrick,
                ], $this->timestamps($quand)));
                $crees++;

                foreach ($membres as $uid) {
                    $this->insert('discussion_conversation_user', array_merge([
                        'discussion_conversation_id' => $convId,
                        'user_id'                    => $uid,
                        'is_admin'                   => $uid === $patrick,
                        // Laissé à null pour les autres afin d'afficher un non-lu à l'ouverture.
                        'last_read_at'               => $uid === $patrick ? $quand : null,
                    ], $this->timestamps($quand)));
                }

                $this->insert('discussion_messages', array_merge([
                    'conversation_id' => $convId,
                    'user_id'         => $patrick,
                    'body'            => 'Bonjour l\'équipe : Ch. 304 (arrivée DEMO-0011 cet après-midi) est prioritaire au ménage. Ch. 205 bloquée sous DIT-DEMO-0001 (clim).',
                ], $this->timestamps($quand)));
                $crees++;

                if ($odile !== null) {
                    $this->insert('discussion_messages', array_merge([
                        'conversation_id' => $convId,
                        'user_id'         => $odile,
                        'body'            => 'Bien noté Patrick, l\'Équipe Étages supérieurs prend 304 immédiatement. Note également Ch. 403 : mini-bar vidé à contrôler.',
                    ], $this->timestamps($this->jours(0, '08:35:00'))));
                    $crees++;
                }
            }

            // Conversation directe : Roger Essomba ↔ Serge Djomo (rupture huile)
            if ($roger !== null && $serge !== null && !$this->existeConversationDirecteDemo($roger, $serge)) {
                $quand  = $this->jours(0, '07:40:00');
                $convId = $this->insert('discussion_conversations', array_merge([
                    'title'      => null,
                    'is_group'   => false,
                    'created_by' => $serge,
                ], $this->timestamps($quand)));
                $crees++;

                foreach ([$serge, $roger] as $uid) {
                    $this->insert('discussion_conversation_user', array_merge([
                        'discussion_conversation_id' => $convId,
                        'user_id'                    => $uid,
                        'is_admin'                   => false,
                        'last_read_at'               => $uid === $serge ? $quand : null,
                    ], $this->timestamps($quand)));
                }

                $this->insert('discussion_messages', array_merge([
                    'conversation_id' => $convId,
                    'user_id'         => $serge,
                    'body'            => '[demo] Bonjour Roger, la cuisine a posé la DM-DEMO-0001 : nous sommes en rupture sur Huile végétale 5 L pour le service de midi.',
                ], $this->timestamps($quand)));
                $crees++;
            }
        }

        return $crees;
    }

    private function existeConversationDirecteDemo(int $userA, int $userB): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.id
             FROM discussion_conversations c
             JOIN discussion_conversation_user p1 ON p1.discussion_conversation_id = c.id AND p1.user_id = ?
             JOIN discussion_conversation_user p2 ON p2.discussion_conversation_id = c.id AND p2.user_id = ?
             WHERE c.is_group = false
             LIMIT 1"
        );
        $stmt->execute([$userA, $userB]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Retourne code => id des catégories de chambres en privilégiant celles du
     * jeu de démonstration (pour éviter de rattacher SUIP au lieu de SPRES).
     *
     * @return array<string, int>
     */
    private function roomTypesParCodeDemoFirst(): array
    {
        $map = [];
        $rows = $this->pdo->query('SELECT id, code FROM room_types ORDER BY id DESC')->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $code = (string) $row['code'];
            $id   = (int) $row['id'];
            if (!isset($map[$code]) || Registre::contient($this->pdo, 'room_types', $id)) {
                $map[$code] = $id;
            }
        }

        return $map;
    }
}
