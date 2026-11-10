<?php

namespace Demo\Seeders;

use Demo\Registre;

/**
 * Catégories, chambres, prestations annexes et formules tarifaires.
 *
 * Tous les prix sont saisis en FCFA dans hotel.json et convertis en centimes
 * (* 100) lors de l'insertion en base, conformément à la convention de
 * wetchah_app.
 */
class HotelSeeder extends AbstractModuleSeeder
{
    public function module(): ?string
    {
        return null;
    }

    public function label(): string
    {
        return 'Catégories, chambres, prestations et formules';
    }

    public function seed(): int
    {
        $h     = $this->data['hotel'];
        $crees = 0;

        // --- Catégories, indexées par code pour rattacher les chambres ---
        $categories = [];

        foreach ($h['room_types'] as $rang => $type) {
            $avant           = $this->findId('room_types', ['code' => $type['code']]);
            $prixCentimes    = (int) $type['base_price'] * 100;
            $litSuppCentimes = (int) ($type['extra_bed_price'] ?? 0) * 100;

            if ($avant === null) {
                $categories[$type['code']] = $this->insert('room_types', array_merge([
                    'code'               => $type['code'],
                    'name'               => $type['name'],
                    'description'        => $type['description'],
                    'base_capacity'      => $type['base_capacity'],
                    'max_capacity'       => $type['max_capacity'],
                    'base_price'         => $prixCentimes,
                    'size_sqm'           => $type['size_sqm'] ?? null,
                    'bed_configuration'  => $type['bed_configuration'] ?? null,
                    'includes_breakfast' => (bool) ($type['includes_breakfast'] ?? false),
                    'allows_extra_bed'   => (bool) ($type['allows_extra_bed'] ?? false),
                    'extra_bed_price'    => $litSuppCentimes,
                    'amenities'          => $this->json($type['amenities']),
                    'sort_order'         => $rang,
                    'is_active'          => true,
                ], $this->timestamps()));
                $crees++;
            } else {
                $categories[$type['code']] = $avant;
                // Si la catégorie appartient au jeu (ou porte une ancienne insertion
                // pré-centimes), on normalise son tarif en centimes et ses attributs.
                if (Registre::contient($this->pdo, 'room_types', $avant)) {
                    $this->pdo->prepare(
                        'UPDATE room_types
                         SET base_price = ?, extra_bed_price = ?, includes_breakfast = ?, allows_extra_bed = ?,
                             size_sqm = COALESCE(size_sqm, ?), bed_configuration = COALESCE(bed_configuration, ?)
                         WHERE id = ?'
                    )->execute([
                        $prixCentimes,
                        $litSuppCentimes,
                        (int) (bool) ($type['includes_breakfast'] ?? false),
                        (int) (bool) ($type['allows_extra_bed'] ?? false),
                        $type['size_sqm'] ?? null,
                        $type['bed_configuration'] ?? null,
                        $avant,
                    ]);
                } else {
                    // Catégorie préexistante de l'établissement (ex. STD sur Zingana) :
                    // on complète seulement d'éventuelles métadonnées nulles sans toucher au tarif.
                    $this->pdo->prepare(
                        'UPDATE room_types
                         SET base_price = CASE WHEN base_price > 0 AND base_price < 500000 AND name = ? THEN ? ELSE base_price END,
                             size_sqm = COALESCE(size_sqm, ?),
                             bed_configuration = COALESCE(bed_configuration, ?)
                         WHERE id = ?'
                    )->execute([
                        $type['name'],
                        $prixCentimes,
                        $type['size_sqm'] ?? null,
                        $type['bed_configuration'] ?? null,
                        $avant,
                    ]);
                }
            }
        }

        // --- Chambres ---
        foreach ($h['rooms'] as $i => $chambre) {
            if ($this->findId('rooms', ['number' => $chambre['number']]) !== null) {
                continue;
            }

            $categorie = $categories[$chambre['type']] ?? null;
            if ($categorie === null) {
                continue;
            }

            $this->insert('rooms', array_merge([
                'room_type_id' => $categorie,
                'number'       => $chambre['number'],
                'floor'        => $chambre['floor'],
                'view_type'    => $this->cycle($h['views'], $i),
                'status'       => 'available',
                'is_active'    => true,
            ], $this->timestamps()));

            $crees++;
        }

        // --- Prestations annexes (folio) ---
        $servicesParNom = [];
        if ($this->tableExists('service_items')) {
            foreach ($h['service_items'] ?? [] as $rang => $svc) {
                $avant = $this->findId('service_items', ['category' => $svc['category'], 'name' => $svc['name']]);
                $id    = $avant ?? $this->insert('service_items', array_merge([
                    'category'         => $svc['category'],
                    'name'             => $svc['name'],
                    'description'      => $svc['description'] ?? null,
                    'price'            => (int) $svc['price'] * 100,
                    'duration_minutes' => $svc['duration_minutes'] ?? null,
                    'sort_order'       => $rang,
                    'is_active'        => true,
                ], $this->timestamps()));

                $servicesParNom[$svc['name']] = $id;
                if ($avant === null) {
                    $crees++;
                }
            }
        }

        // --- Formules tarifaires (RoomPackages) ---
        if ($this->tableExists('room_packages')) {
            foreach ($h['room_packages'] ?? [] as $rang => $pkg) {
                $avant = $this->findId('room_packages', ['name' => $pkg['name']])
                    ?? (!empty($pkg['code']) ? $this->findId('room_packages', ['code' => $pkg['code']]) : null);

                if ($avant !== null) {
                    continue;
                }

                $serviceIds = array_values(array_filter(array_map(
                    fn (string $nom) => $servicesParNom[$nom] ?? null,
                    $pkg['services'] ?? []
                )));

                $remiseType  = $pkg['room_discount_type'] ?? 'none';
                $remiseVal   = (int) ($pkg['room_discount_value'] ?? 0);
                if ($remiseType === 'amount') {
                    $remiseVal *= 100;
                }

                $this->insert('room_packages', array_merge([
                    'name'                => $pkg['name'],
                    'code'                => $pkg['code'] ?? null,
                    'description'         => $pkg['description'] ?? null,
                    'meals'               => $this->json($pkg['meals'] ?? []),
                    'service_item_ids'    => $this->json($serviceIds),
                    'pricing_mode'        => $pkg['pricing_mode'] ?? 'per_person_night',
                    'price'               => (int) $pkg['price'] * 100,
                    'room_discount_type'  => $remiseType,
                    'room_discount_value' => $remiseVal,
                    'room_type_ids'       => $this->json([]),
                    'sort_order'          => $rang,
                    'is_active'           => true,
                ], $this->timestamps()));

                $crees++;
            }
        }

        return $crees;
    }
}
