<?php

use function Cake\Core\env;

/*
 * Local configuration file to provide any overrides to your app.php configuration.
 * Copy and save this file as app_local.php and make changes as required.
 * Note: It is not recommended to commit files with credentials such as app_local.php
 * into source code version control.
 */
return [
    /*
     * Debug Level:
     *
     * Production Mode:
     * false: No error messages, errors, or warnings shown.
     *
     * Development Mode:
     * true: Errors and warnings shown.
     */
    'debug' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN),

    'Error' => [
        'errorLevel' => E_ALL & ~E_USER_DEPRECATED,
    ],

    /*
     * Security and encryption configuration
     *
     * - salt - A random string used in security hashing methods.
     *   The salt value is also used as the encryption key.
     *   You should treat it as extremely sensitive data.
     */
    'Security' => [
        'salt' => env('SECURITY_SALT', '8e367f0c2c16e5179e1720da4e18b791b11b5e3c1bc156592f8298d042d8367f'),
    ],

    /*
     * Connection information used by the ORM to connect
     * to your application's datastores.
     *
     * See app.php for more configuration options.
     */
    'Datasources' => [
        'default' => [
            'driver' => \Cake\Database\Driver\Postgres::class,
            'host' => env('DATABASE_HOST', 'localhost'),
            'port' => env('DATABASE_PORT', '5432'),
            'username' => env('DATABASE_USER', 'app'),
            'password' => env('DATABASE_PASSWORD', ''),
            'database' => env('DATABASE_NAME', 'bbs'),
            'encoding' => 'utf8',
            'sslmode' => env('DATABASE_SSLMODE', 'prefer'),
            'url' => env('DATABASE_URL', null),
        ],

        'test' => [
            'driver' => \Cake\Database\Driver\Postgres::class,
            'host' => env('DATABASE_HOST', 'localhost'),
            'port' => env('DATABASE_PORT', '5432'),
            'username' => env('DATABASE_USER', 'app'),
            'password' => env('DATABASE_PASSWORD', ''),
            'database' => env('DATABASE_NAME', 'bbs_test'),
            'encoding' => 'utf8',
            'sslmode' => env('DATABASE_SSLMODE', 'prefer'),
            'url' => env('DATABASE_TEST_URL', null),
        ],
    ],

    /*
     * Email configuration.
     *
     * Host and credential configuration in case you are using SmtpTransport
     *
     * See app.php for more configuration options.
     */
    'EmailTransport' => [
        'default' => [
            'host' => 'localhost',
            'port' => 25,
            'username' => null,
            'password' => null,
            'client' => null,
            'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
        ],
    ],
];
