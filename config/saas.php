<?php

return [
    'landlord_connection' => env('LANDLORD_DB_CONNECTION', 'landlord'),

    'tenant_connection' => env('TENANT_DB_CONNECTION', 'tenant'),

    'tenant_database_prefix' => env('TENANT_DB_PREFIX', 'tenant_'),

    'company_session_key' => 'tenant_company_id',
];
