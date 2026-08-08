<?php

/*
|--------------------------------------------------------------------------
| On-premise biometric sync agent
|--------------------------------------------------------------------------
|
| Only used when this installation is running as an agent at a tenant's site,
| polling a biometric device on the local network and forwarding punches to the
| central server. Leave these unset on the server itself.
|
| Issue a token with:  php artisan biometric:agent-token {company-slug}
|
*/

return [
    'server_url'   => env('AGENT_SERVER_URL'),
    'company_slug' => env('AGENT_COMPANY_SLUG'),
    'token'        => env('AGENT_TOKEN'),
];
