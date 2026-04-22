<?php

return [

    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role' => Spatie\Permission\Models\Role::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        'role_pivot_key' => null,
        'permission_pivot_key' => null,
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id',
    ],

    'register_permission_check_method' => true,

    'register_octane_reset_listener' => false,

    'events_enabled' => false,

    'teams' => false,

    'team_resolver' => \Spatie\Permission\DefaultTeamResolver::class,

    'use_passport_client_credentials' => false,

    'display_permission_in_exception' => false,

    'display_role_in_exception' => false,

    'enable_wildcard_permission' => false,

    /* Cache-specific settings
    |
    | PERFORMANCE NOTE: The default cache store is 'default', which in this
    | application resolves to the 'database' driver. That means every request
    | whose role/permission cache has expired runs a DB read just to check
    | authorisation. Switching to 'file' eliminates those DB round-trips while
    | keeping cross-request persistence. Override via PERMISSION_CACHE_STORE
    | in .env (e.g. set to 'redis' when Redis is reliably available).
    |
    */
    'cache' => [

        /*
         * By default all permissions are cached for 24 hours to speed up performance.
         * When permissions or roles are updated the cache is flushed automatically.
         */
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),

        /*
         * The cache key used to store all permissions.
         */
        'key' => 'spatie.permission.cache',

        /*
         * Use 'file' instead of 'default' (database) so that role/permission
         * lookups hit the filesystem rather than the DB on cache miss.
         * Set PERMISSION_CACHE_STORE=redis in .env when Redis is available.
         */
        'store' => env('PERMISSION_CACHE_STORE', 'file'),
    ],
];
