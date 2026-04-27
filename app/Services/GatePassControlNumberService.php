<?php

namespace App\Services;

use App\Models\GatePass;

class GatePassControlNumberService
{
    public function generate(): string
    {
        $year  = date('Y');
        $mon   = date('m');
        $last  = GatePass::orderByDesc('id')->value('controlno');

        if ($last) {
            $parts = explode('-', $last);
            $next  = ($parts[0] ?? '') === $year ? (int)($parts[2] ?? 0) + 1 : 1;
        } else {
            $next = 1;
        }

        return $year . '-' . $mon . '-' . sprintf('%04d', $next);
    }
}
