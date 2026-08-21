<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chemins concernés par CORS
    |--------------------------------------------------------------------------
    |
    | Toutes nos routes API commencent par /api.
    |
    */
    'paths' => [
        'api/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Méthodes HTTP autorisées
    |--------------------------------------------------------------------------
    |
    | Angular devra pouvoir envoyer :
    | GET, POST, PUT, PATCH, DELETE, OPTIONS...
    |
    */
    'allowed_methods' => [
        '*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontends autorisés
    |--------------------------------------------------------------------------
    |
    | Pendant le développement Angular tourne normalement
    | sur le port 4200.
    |
    */
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:4200'),
        'http://127.0.0.1:4200',
    ],

    /*
     * Aucun pattern supplémentaire pour le moment.
     */
    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Headers autorisés
    |--------------------------------------------------------------------------
    |
    | Le * permet notamment à Angular d'envoyer :
    |
    | Authorization: Bearer TOKEN
    | Content-Type: application/json
    |
    */
    'allowed_headers' => [
        '*',
    ],

    /*
     * Aucun header particulier n'a besoin d'être exposé
     * au navigateur pour le moment.
     */
    'exposed_headers' => [],

    /*
     * Durée pendant laquelle le navigateur peut mettre
     * en cache la réponse CORS.
     */
    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | Nous utilisons un Bearer token personnalisé et non
    | les cookies de session Laravel / Sanctum.
    |
    | Nous laissons donc cette option à false.
    |
    */
    'supports_credentials' => false,

];
