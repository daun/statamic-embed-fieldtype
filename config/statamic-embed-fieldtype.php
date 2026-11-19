<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | Cache store for embed data. Defaults to a file store that survives
    | `cache:clear`. Set to a store name from config/cache.php if storage
    | isn't persisted between deployments.
    |
    */

    'cache_store' => null,

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | Seconds until embed data is refreshed in the background. Failed
    | refreshes keep the previous data. Set to null to never refresh.
    |
    */

    'cache_ttl' => 60 * 60 * 24 * 30,

];
