<?php

namespace App\Session;

use Illuminate\Session\DatabaseSessionHandler;

/**
 * Database session handler that records which company (tenant) each session belongs to.
 *
 * All companies share one `sessions` table, and `user_id` holds the username (see
 * User::getAuthIdentifierName()), which is only unique within a company. Storing the
 * company id alongside it makes every session attributable, so sessions can be found
 * or removed per company (e.g. `WHERE company_id = ? AND user_id = ?`).
 *
 * Requires the `sessions.company_id` column (migration 2026_11_16_000003). If the column
 * is missing, session inserts fail and Laravel silently drops the session, so run that
 * migration before deploying this handler.
 */
class TenantAwareDatabaseSessionHandler extends DatabaseSessionHandler
{
    protected function getDefaultPayload($data)
    {
        $payload = parent::getDefaultPayload($data);

        if ($this->container) {
            // Bound by TenantManager::switchToCompany() once the request's tenant is resolved
            // (from the session, or at login). Null for landlord and guest-only requests.
            $payload['company_id'] = $this->container->bound('currentCompany')
                ? $this->container->make('currentCompany')->getKey()
                : null;
        }

        return $payload;
    }
}
