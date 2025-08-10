<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Liste des emails Super Admin
    |--------------------------------------------------------------------------
    |
    | Séparez les emails par des virgules dans le .env :
    | ADMIN_EMAIL_LIST=email1@email.com,email2@email.com
    |
    */

    'emails' => array_map(
        'trim',
        explode(',', env('ADMIN_EMAIL_LIST', ''))
    ),

];
