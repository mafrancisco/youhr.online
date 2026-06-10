<?php

namespace App\Models;

use Carbon\Carbon;
class Schedule extends TenantModel
{
    protected $table = 'schedule';
    public    $timestamps = false;

    protected $fillable = [
        'schedulename',
        'm_timein','m_breakout','m_breakin','m_timeout','m_crossday',
        't_timein','t_breakout','t_breakin','t_timeout','t_crossday',
        'w_timein','w_breakout','w_breakin','w_timeout','w_crossday',
        'th_timein','th_breakout','th_breakin','th_timeout','th_crossday',
        'f_timein','f_breakout','f_breakin','f_timeout','f_crossday',
        'sat_timein','sat_breakout','sat_breakin','sat_timeout','sat_crossday',
        'sun_timein','sun_breakout','sun_breakin','sun_timeout','sun_crossday',
    ];

    protected $casts = [
        'm_crossday'=>'boolean','t_crossday'=>'boolean','w_crossday'=>'boolean',
        'th_crossday'=>'boolean','f_crossday'=>'boolean',
        'sat_crossday'=>'boolean','sun_crossday'=>'boolean',
    ];

    public function slotsFor(Carbon $date): array
    {
        $prefix = match (strtolower($date->format('l'))) {
            'monday'    => 'm',
            'tuesday'   => 't',
            'wednesday' => 'w',
            'thursday'  => 'th',
            'friday'    => 'f',
            'saturday'  => 'sat',
            'sunday'    => 'sun',
        };

        return [
            'timein'   => $this->{"{$prefix}_timein"},
            'breakout' => $this->{"{$prefix}_breakout"},
            'breakin'  => $this->{"{$prefix}_breakin"},
            'timeout'  => $this->{"{$prefix}_timeout"},
            'crossday' => $this->{"{$prefix}_crossday"},
            'prefix'   => $prefix,
        ];
    }
}
