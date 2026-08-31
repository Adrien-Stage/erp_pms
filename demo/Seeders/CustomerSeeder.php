<?php

namespace Demo\Seeders;

/**
 * Les clients — socle de tout le reste.
 *
 * Réservations, folios, commandes restaurant et ventes boutique pointent tous
 * vers eux : cette étape passe donc en premier.
 */
class CustomerSeeder extends AbstractModuleSeeder
{
    /** Marqueur porté par chaque client fictif, pour les reconnaître ensuite. */
    public const MARQUEUR = '[demo] Client de démonstration';

    public function module(): ?string
    {
        return null;
    }

    public function label(): string
    {
        return 'Clients';
    }

    public function seed(): int
    {
        $p       = $this->data['people'];
        $vise    = $this->volume['customers'] ?? 20;
        $crees   = 0;

        for ($i = 0; $i < $vise; $i++) {
            $prenom = $this->cycle($p['first_names'], $i);
            $nom    = $this->cycle($p['last_names'], $i * 3 + 1);
            $email  = $this->emailDe($prenom, $nom, $i);

            // L'adresse est la clé naturelle : deux homonymes restent deux
            // clients, mais relancer l'installation n'en recrée aucun.
            if ($this->findId('customers', ['email' => $email]) !== null) {
                continue;
            }

            $this->insert('customers', array_merge([
                'first_name'     => $prenom,
                'last_name'      => $nom,
                'email'          => $email,
                'phone'          => $this->telephone($i),
                'city'           => $this->cycle($p['cities'], $i * 2),
                'country'        => $this->cycle($p['countries'], $i),
                'nationality'    => $this->cycle($p['countries'], $i),
                'id_type'        => $this->cycle($p['id_types'], $i),
                'id_number'      => 'DEMO' . str_pad((string) (100000 + $i * 7), 6, '0', STR_PAD_LEFT),
                'loyalty_level'  => $this->cycle($p['loyalty_levels'], $i),
                'loyalty_points' => ($i * 137) % 5000,
                'is_vip'         => $i % 9 === 0,
                'notes'          => self::MARQUEUR,
                'created_at'     => $this->jours(-180 + $i * 5),
                'updated_at'     => $this->now(),
            ]));

            $crees++;
        }

        return $crees;
    }

    private function emailDe(string $prenom, string $nom, int $i): string
    {
        $normalise = fn(string $s) => strtolower(preg_replace(
            '/[^a-z]/i',
            '',
            iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s
        ));

        return $normalise($prenom) . '.' . $normalise($nom) . ($i + 1) . '@demo.invalid';
    }

    private function telephone(int $i): string
    {
        return '+237 6' . str_pad((string) (55000000 + $i * 111111), 8, '0', STR_PAD_LEFT);
    }
}
