<?php

return [
    'admin_emails' => array_values(array_filter(array_map('trim', explode(',', env('LANDLORD_ADMIN_EMAILS', ''))))),
];
