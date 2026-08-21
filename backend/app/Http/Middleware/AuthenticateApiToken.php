<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Récupère le token envoyé dans l'en-tête HTTP : Authorization: Bearer xxxxxxxxx
        $plainToken = $request->bearerToken();

        /// Si aucun token n'est envoyé, l'utilisateur n'est pas authentifié.
        if (! $plainToken) {
            return response()->json([
                'message' => 'Token manquant.',
            ], 401);
        }

        // Le token brut n'est jamais stocké dans la base.
        // On calcule donc son hash SHA-256 pour rechercher le token correspondant.

        $token = ApiToken::query()->with('user')->where('token_hash',hash('sha256', $plainToken))->first();

        /* Le token est invalide si :
            - il n'existe pas ;
            - aucun utilisateur ne lui est associé ;
            - l'utilisateur est désactivé.
         */
        if (! $token || ! $token->user || ! $token->user->is_active){
            return response()->json([
                'message' => 'Token invalide.',
            ], 401);
        }

        // Vérifie maintenant sa date d'expiration.
        // Un ancien token sans expires_at est également considéré comme invalide.

        if (! $token->expires_at || $token->expires_at->isPast()){
            // Suppression du token expiré pour éviter d'accumuler des tokens inutiles dans la base.
            $token->delete();

            return response()->json([
                'message' => 'Session expiree. Veuillez vous reconnecter.',
            ], 401);
        }

        /// Enregistre la dernière utilisation du token.
        // Cela pourra servir plus tard à connaître la dernière activité d'une session.
        $token->forceFill([
            'last_used_at' => now(),
        ])->save();

        // Indique à Laravel quel utilisateur correspond à cette requête.
        // Ainsi, dans nos contrôleurs :
        // $request->user() retournera l'utilisateur authentifié.
        $request->setUserResolver(
            fn () => $token->user
        );

        // Continue vers la route demandée.
        return $next($request);
    }
}
