<?php

return [
    'landlord_connection' => env('LANDLORD_DB_CONNECTION', 'landlord'),

    'tenant_connection' => env('TENANT_DB_CONNECTION', 'tenant'),

    'tenant_database_prefix' => env('TENANT_DB_PREFIX', 'tenant_'),

    'company_session_key' => 'tenant_company_id',

    /*
    |--------------------------------------------------------------------------
    | ADMS (biometric device push) endpoints
    |--------------------------------------------------------------------------
    |
    | The /iclock/* routes are how ZKTeco push-mode devices deliver attendance.
    | They cannot be authenticated in the normal sense: the device only presents
    | its serial number and cannot send headers, tokens or CSRF values. Anyone who
    | knows a registered serial could therefore post attendance for that tenant.
    |
    | They are disabled by default so an internet-facing deployment does not expose
    | them unintentionally. Enable with ADMS_ENABLED=true only where a device is
    | actually in use, and prefer restricting access to the device's address at the
    | web server or security group level.
    |
    */
    'adms_enabled' => env('ADMS_ENABLED', false),
];
