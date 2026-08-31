<?php

namespace Demo\Seeders;

/**
 * Catégories et chambres.
 *
 * Le parc est le socle physique de l'établissement : sans chambre, aucune
 * réservation n'est possible. Les catégories sont créées avant, les chambres y
 * étant rattachées par code.
 */
class HotelSeeder extends AbstractModuleSeeder
{
    public function module(): ?string
    {
        return null;
    }

    public function label(): string
    {
        return 'Catégories et chambres';
    }

    public function seed(): int
    {
        $h     = $this->data['hotel'];
        $crees = 0;

        // --- Catégories, indexées par code pour rattacher les chambres ---
        $categories = [];

        foreach ($h['room_types'] as $rang => $type) {
            $avant = $this->findId('room_types', ['code' => $type['code']]);

            $categories[$type['code']] = $this->insertOnce(
                'room_types',
                ['code' => $type['code']],
                array_merge([
                    'name'          => $type['name'],
                    'description'   => $type['description'],
                    'base_capacity' => $type['base_capacity'],
                    'max_capacity'  => $type['max_capacity'],
                    'base_price'    => $type['base_price'],
                    'amenities'     => json_encode($type['amenities'], JSON_UNESCAPED_UNICODE),
                    'sort_order'    => $rang,
                    'is_active'     => true,
                ], $this->timestamps())
            );

            if ($avant === null) {
                $crees++;
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

        return $crees;
    }
}
