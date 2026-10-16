<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Crée le compte administrateur technique d'une console neuve, sans passer par
 * le seeder (qui crée admin/admin) ni par tinker. Lancée sans option, elle pose
 * les questions une à une ; le mot de passe n'apparaît pas à l'écran.
 *
 *     php artisan erp:creer-admin
 *
 * Avec --reinitialiser, la même commande change le mot de passe d'un compte
 * existant (mot de passe oublié, aucun autre administrateur pour le faire).
 */
class CreerAdministrateur extends Command
{
    protected $signature = 'erp:creer-admin
                            {--nom= : Nom affiché}
                            {--email= : Adresse de connexion}
                            {--reinitialiser : Change le mot de passe d\'un compte qui existe déjà}
                            {--mot-de-passe= : Mot de passe (à éviter : il reste dans l\'historique du terminal)}';

    protected $description = 'Crée un compte administrateur technique (accès à tout l\'ERP)';

    public function handle(): int
    {
        $reinit = (bool) $this->option('reinitialiser');

        $nom = $reinit ? null : ($this->option('nom') ?: text('Nom et prénom', required: true));

        $email = $this->option('email') ?: text(
            $reinit ? 'Adresse e-mail du compte' : 'Adresse e-mail (sert à se connecter)',
            required: true,
            validate: fn (string $v) => $this->erreurEmail($v, $reinit),
        );

        if ($erreur = $this->erreurEmail($email, $reinit)) {
            $this->error($erreur);
            return self::FAILURE;
        }

        $motDePasse = $this->option('mot-de-passe');
        if ($motDePasse === null) {
            $motDePasse = password(
                'Mot de passe (12 caractères minimum)',
                required: true,
                validate: fn (string $v) => $this->erreurMotDePasse($v),
            );
            $confirmation = password('Retapez le mot de passe', required: true);
            if ($confirmation !== $motDePasse) {
                $this->error('Les deux mots de passe sont différents. Rien n\'a été créé.');
                return self::FAILURE;
            }
        } elseif ($erreur = $this->erreurMotDePasse($motDePasse)) {
            $this->error($erreur);
            return self::FAILURE;
        }

        if ($reinit) {
            User::where('email', $email)->firstOrFail()->update(['password' => $motDePasse]);
            $this->info("Mot de passe changé pour {$email}.");

            return self::SUCCESS;
        }

        User::create([
            'name' => $nom,
            'email' => $email,
            'password' => $motDePasse,
            'role' => User::ROLE_TECH_ADMIN,
            'is_active' => true,
        ]);

        $this->info("Compte créé : {$email}. Vous pouvez vous connecter.");

        return self::SUCCESS;
    }

    private function erreurEmail(string $valeur, bool $reinit = false): ?string
    {
        if ($reinit) {
            return User::where('email', $valeur)->exists() ? null : 'Aucun compte ne porte cette adresse.';
        }

        $validateur = Validator::make(['email' => $valeur], ['email' => ['email']]);
        if ($validateur->fails()) {
            return 'Adresse e-mail invalide.';
        }

        if (User::where('email', $valeur)->exists()) {
            return 'Cette adresse est déjà utilisée par un autre compte.';
        }

        return null;
    }

    private function erreurMotDePasse(string $valeur): ?string
    {
        return mb_strlen($valeur) < 12 ? 'Le mot de passe doit faire au moins 12 caractères.' : null;
    }
}
