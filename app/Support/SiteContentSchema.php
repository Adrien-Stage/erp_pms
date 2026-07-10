<?php

namespace App\Support;

/**
 * Schéma du contenu marketing du site vitrine (template_site), organisé en
 * pages -> sections -> champs. Source de vérité unique partagée entre le
 * formulaire admin (onglet "Contenu du site"), la persistance
 * (AdminAuditController::updateSiteContent) et l'API publique
 * (publicSiteContent) — les trois itèrent ce même schéma.
 *
 * Chaque section porte un drapeau "enabled" : le site n'affiche que les
 * sections actives (ex. un hôtel sans vidéo désactive la section vidéo).
 *
 * Types de champs :
 *  - text      : input court (255 max)
 *  - textarea  : texte long (5000 max)
 *  - image     : upload d'une image unique (chemin storage "site/...")
 *  - images    : galerie multi-upload (tableau de chemins)
 *  - items     : liste structurée saisie en textarea, un élément par ligne,
 *                colonnes séparées par " | " selon 'keys' (ex. Titre | Description)
 */
class SiteContentSchema
{
    public static function pages(): array
    {
        return [
            'home' => [
                'label' => 'Accueil',
                'sections' => [
                    'hero' => [
                        'label' => 'Hero (bannière principale)',
                        'description' => 'Premier écran du site : titre, accroche et bouton d\'action.',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'subtitle' => ['type' => 'textarea', 'label' => 'Sous-titre'],
                            'cta_label' => ['type' => 'text', 'label' => 'Libellé du bouton'],
                            'background_image' => ['type' => 'image', 'label' => 'Image de fond'],
                        ],
                    ],
                    'philosophy' => [
                        'label' => 'Notre philosophie',
                        'description' => 'Présentation de l\'établissement et de ses valeurs.',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'body' => ['type' => 'textarea', 'label' => 'Texte de présentation'],
                            'image' => ['type' => 'image', 'label' => 'Image d\'illustration'],
                            'values' => ['type' => 'items', 'label' => 'Piliers / valeurs', 'keys' => ['title', 'description'], 'placeholder' => "Éco-responsabilité | Gestion durable des ressources\nPanorama exceptionnel | Vue imprenable sur les collines"],
                        ],
                    ],
                    'services' => [
                        'label' => 'Nos équipements',
                        'description' => 'Services et équipements mis en avant (Wi-Fi, parking, sécurité…).',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'subtitle' => ['type' => 'textarea', 'label' => 'Sous-titre'],
                            'items' => ['type' => 'items', 'label' => 'Équipements', 'keys' => ['title', 'description'], 'placeholder' => "Wi-Fi haut débit | Connexion fibre dans tout l'établissement\nParking privé | Gratuit et sécurisé"],
                        ],
                    ],
                    'rooms' => [
                        'label' => 'Nos hébergements',
                        'description' => 'Les 3 types de chambres les plus réservés, récupérés automatiquement depuis l\'application — seuls le titre et le sous-titre se personnalisent ici.',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'subtitle' => ['type' => 'textarea', 'label' => 'Sous-titre'],
                        ],
                    ],
                    'testimonials' => [
                        'label' => 'Témoignages',
                        'description' => 'Avis de clients affichés en carrousel.',
                        'fields' => [
                            'items' => ['type' => 'items', 'label' => 'Témoignages', 'keys' => ['author', 'text'], 'placeholder' => "Marie K. | Un séjour inoubliable, cadre magnifique et personnel aux petits soins.\nJean T. | La meilleure table de la région, nous reviendrons !"],
                        ],
                    ],
                    'video' => [
                        'label' => 'Vidéo de présentation',
                        'description' => 'Bannière vidéo — à désactiver si l\'établissement n\'a pas de vidéo.',
                        'default_enabled' => false,
                        'fields' => [
                            'url' => ['type' => 'text', 'label' => 'URL de la vidéo (mp4 ou YouTube)'],
                        ],
                    ],
                    'offers' => [
                        'label' => 'Nos offres',
                        'description' => 'Offres et forfaits spéciaux (séjour romantique, forfait famille…).',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'subtitle' => ['type' => 'textarea', 'label' => 'Sous-titre'],
                            'items' => ['type' => 'items', 'label' => 'Offres', 'keys' => ['title', 'description', 'price'], 'placeholder' => "Escapade romantique | Nuit + dîner aux chandelles + spa | 85 000 FCFA\nForfait famille | 2 chambres communicantes + petits-déjeuners | 120 000 FCFA"],
                        ],
                    ],
                    'restaurant' => [
                        'label' => 'Restaurant',
                        'description' => 'Aperçu du restaurant sur la page d\'accueil (la carte complète vit sur la page Restaurant).',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'body' => ['type' => 'textarea', 'label' => 'Texte de présentation'],
                            'image' => ['type' => 'image', 'label' => 'Image'],
                        ],
                    ],
                    'discovery' => [
                        'label' => 'Découverte',
                        'description' => 'Activités et excursions autour de l\'établissement.',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'subtitle' => ['type' => 'textarea', 'label' => 'Sous-titre'],
                            'items' => ['type' => 'items', 'label' => 'Activités', 'keys' => ['title', 'description'], 'placeholder' => "Randonnées guidées | Sentiers panoramiques avec guide local\nRoute des chefferies | Circuit culturel d'une journée"],
                        ],
                    ],
                    'instagram' => [
                        'label' => 'Galerie Instagram',
                        'description' => 'Mosaïque de photos façon feed Instagram.',
                        'fields' => [
                            'handle' => ['type' => 'text', 'label' => 'Compte Instagram (ex. @villaboutanga)'],
                            'images' => ['type' => 'images', 'label' => 'Photos'],
                        ],
                    ],
                    'newsletter' => [
                        'label' => 'Newsletter',
                        'description' => 'Bloc d\'inscription à la newsletter.',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'subtitle' => ['type' => 'textarea', 'label' => 'Sous-titre'],
                        ],
                    ],
                    'contact_form' => [
                        'label' => 'Formulaire de contact',
                        'description' => 'Bloc contact en bas de page (adresse, téléphone et email viennent de l\'onglet Informations).',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'intro' => ['type' => 'textarea', 'label' => 'Texte d\'introduction'],
                        ],
                    ],
                ],
            ],

            'heb' => [
                'label' => 'Hébergements',
                'sections' => [
                    'banner' => [
                        'label' => 'Bannière',
                        'description' => 'En-tête de la page Hébergements. La grille des chambres est alimentée automatiquement par l\'application.',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'subtitle' => ['type' => 'textarea', 'label' => 'Sous-titre'],
                            'background_image' => ['type' => 'image', 'label' => 'Image de fond'],
                        ],
                    ],
                ],
            ],

            'resto' => [
                'label' => 'Restaurant',
                'sections' => [
                    'banner' => [
                        'label' => 'Bannière',
                        'description' => 'En-tête de la page Restaurant.',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'subtitle' => ['type' => 'textarea', 'label' => 'Sous-titre'],
                            'background_image' => ['type' => 'image', 'label' => 'Image de fond'],
                        ],
                    ],
                    'experience' => [
                        'label' => 'L\'expérience',
                        'description' => 'Présentation de l\'expérience culinaire (la carte vient de l\'application).',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'body' => ['type' => 'textarea', 'label' => 'Texte de présentation'],
                            'image' => ['type' => 'image', 'label' => 'Image'],
                        ],
                    ],
                    'gallery' => [
                        'label' => 'Galerie photos',
                        'description' => 'Photos du restaurant et des plats.',
                        'fields' => [
                            'images' => ['type' => 'images', 'label' => 'Photos'],
                        ],
                    ],
                ],
            ],

            'about' => [
                'label' => 'À propos',
                'sections' => [
                    'banner' => [
                        'label' => 'Bannière',
                        'description' => 'En-tête de la page À propos.',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'subtitle' => ['type' => 'textarea', 'label' => 'Sous-titre'],
                            'background_image' => ['type' => 'image', 'label' => 'Image de fond'],
                        ],
                    ],
                    'welcome' => [
                        'label' => 'Bienvenue',
                        'description' => 'Histoire et présentation détaillée de l\'établissement.',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'body' => ['type' => 'textarea', 'label' => 'Texte'],
                            'image' => ['type' => 'image', 'label' => 'Image'],
                        ],
                    ],
                    'facilities' => [
                        'label' => 'Nos installations',
                        'description' => 'Liste des installations (piscine, spa, salle de réunion…).',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'items' => ['type' => 'items', 'label' => 'Installations', 'keys' => ['title', 'description'], 'placeholder' => "Piscine extérieure | Chauffée, ouverte de 8h à 20h\nSpa & massages | Sur réservation"],
                        ],
                    ],
                ],
            ],

            'contact' => [
                'label' => 'Contact',
                'sections' => [
                    'banner' => [
                        'label' => 'Bannière',
                        'description' => 'En-tête de la page Contact.',
                        'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Titre'],
                            'subtitle' => ['type' => 'textarea', 'label' => 'Sous-titre'],
                            'background_image' => ['type' => 'image', 'label' => 'Image de fond'],
                        ],
                    ],
                    'info' => [
                        'label' => 'Infos pratiques',
                        'description' => 'Adresse, téléphone et email viennent de l\'onglet Informations — ici uniquement l\'intro et les horaires.',
                        'fields' => [
                            'intro' => ['type' => 'textarea', 'label' => 'Texte d\'introduction'],
                            'hours' => ['type' => 'textarea', 'label' => 'Horaires'],
                        ],
                    ],
                    'map' => [
                        'label' => 'Carte',
                        'description' => 'Localisation sur la page Contact.',
                        'fields' => [
                            'embed_url' => ['type' => 'text', 'label' => 'URL d\'intégration Google Maps (iframe src)'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Structure complète pages -> sections -> champs, hydratée depuis le JSON
     * stocké (Tenant::site_content['pages']) avec valeurs par défaut, et
     * migration douce depuis l'ancien format à plat (hero/about/contact/
     * gallery au premier niveau) tant que "pages" n'a jamais été enregistré.
     */
    public static function hydrate(?array $siteContent): array
    {
        $stored = $siteContent['pages'] ?? null;
        $legacy = $stored === null ? self::legacyDefaults($siteContent ?? []) : [];

        $result = [];
        foreach (self::pages() as $pageKey => $page) {
            foreach ($page['sections'] as $sectionKey => $section) {
                $current = $stored[$pageKey][$sectionKey]
                    ?? $legacy[$pageKey][$sectionKey]
                    ?? [];

                $out = ['enabled' => (bool) ($current['enabled'] ?? ($section['default_enabled'] ?? true))];

                foreach ($section['fields'] as $fieldKey => $field) {
                    $out[$fieldKey] = match ($field['type']) {
                        'images' => array_values(array_filter((array) ($current[$fieldKey] ?? []))),
                        'items'  => array_values(array_filter((array) ($current[$fieldKey] ?? []), 'is_array')),
                        default  => $current[$fieldKey] ?? null,
                    };
                }

                $result[$pageKey][$sectionKey] = $out;
            }
        }

        return $result;
    }

    /**
     * Pré-remplissage depuis l'ancien format (formulaire générique d'avant
     * les onglets) pour ne pas perdre le contenu déjà saisi.
     */
    private static function legacyDefaults(array $old): array
    {
        return [
            'home' => [
                'hero' => [
                    'title' => $old['hero']['title'] ?? null,
                    'subtitle' => $old['hero']['subtitle'] ?? null,
                    'cta_label' => $old['hero']['cta_label'] ?? null,
                    'background_image' => $old['hero']['background_image'] ?? null,
                ],
                'philosophy' => [
                    'title' => $old['about']['title'] ?? null,
                    'body' => $old['about']['body'] ?? null,
                ],
                'instagram' => [
                    'images' => $old['gallery'] ?? [],
                ],
            ],
            'contact' => [
                'info' => [
                    'intro' => $old['contact']['intro'] ?? null,
                    'hours' => $old['contact']['hours'] ?? null,
                ],
            ],
        ];
    }

    /**
     * "A | B" par ligne -> [['title' => 'A', 'description' => 'B'], ...]
     */
    public static function parseItems(?string $raw, array $keys): array
    {
        $items = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $item = [];
            foreach ($keys as $i => $key) {
                $item[$key] = $parts[$i] ?? null;
            }
            $items[] = $item;
        }
        return $items;
    }

    /**
     * Inverse de parseItems, pour pré-remplir la textarea du formulaire.
     */
    public static function itemsToRaw(array $items, array $keys): string
    {
        return collect($items)
            ->map(fn ($item) => implode(' | ', array_map(fn ($k) => $item[$k] ?? '', $keys)))
            ->implode("\n");
    }
}
