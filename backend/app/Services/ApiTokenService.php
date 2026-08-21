<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

class ApiTokenService
{
    // Génère un nouveau token API pour un utilisateur.
    // Le token brut est retourné au frontend Angular.
    // Seul son hash SHA-256 est enregistré dans la base de données.

    public function issue(User $user, string $name = 'api'): string
    {
        // Génère un token aléatoire de 80 caractères.
        $plainToken = Str::random(80);

        // Récupère la durée de validité définie dans config/auth.php.
        // Si aucune valeur n'est définie, on utilise 12 heures.
        $ttlHours = (int) config('auth.api_token_ttl_hours', 12);

        ApiToken::create([
            // Utilisateur propriétaire du token.
            'user_id' => $user->id,

            // Nom permettant d'identifier le type de token.
            'name' => $name,

            // On ne stocke jamais le token brut dans la base.
            // On stocke uniquement son hash SHA-256.
            'token_hash' => hash('sha256', $plainToken),

            // Date et heure auxquelles le token deviendra invalide.
            'expires_at' => now()->addHours($ttlHours),
        ]);

        // Seul le token brut est retourné au client.
        return $plainToken;
    }

    // Supprime le token actuellement utilisé.
    // Cette méthode est appelée lors de la déconnexion.

    public function revokeCurrent(string $plainToken): void
    {
        ApiToken::query()
            // On recherche le token grâce au même hash SHA-256.
            ->where('token_hash', hash('sha256', $plainToken))

            // Suppression du token = déconnexion immédiate.
            ->delete();
    }
}