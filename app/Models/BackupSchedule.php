<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupSchedule extends Model
{
    protected $fillable = [
        'tenant_id',
        'enabled',
        'frequency',
        'hour',
        'minute',
        'day_of_week',
        'day_of_month',
        'retention',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Calcule la prochaine occurrence à partir de maintenant selon la
     * fréquence et l'heure configurées.
     */
    public function computeNextRun(?Carbon $from = null): Carbon
    {
        $from = $from ?? now();
        $next = $from->copy()->setTime($this->hour, $this->minute, 0);

        switch ($this->frequency) {
            case 'weekly':
                $dow = $this->day_of_week ?? 1;
                // Prochain jour de semaine voulu (aujourd'hui inclus si l'heure n'est pas passée)
                while ($next->dayOfWeek !== $dow || $next->lessThanOrEqualTo($from)) {
                    $next->addDay()->setTime($this->hour, $this->minute, 0);
                }
                break;

            case 'monthly':
                $dom = $this->day_of_month ?? 1;
                $next->day(min($dom, $next->daysInMonth));
                if ($next->lessThanOrEqualTo($from)) {
                    $next->addMonthNoOverflow()->day(min($dom, $next->copy()->addMonthNoOverflow()->daysInMonth))
                         ->setTime($this->hour, $this->minute, 0);
                }
                break;

            case 'daily':
            default:
                if ($next->lessThanOrEqualTo($from)) {
                    $next->addDay();
                }
                break;
        }

        return $next;
    }

    public function frequencyLabel(): string
    {
        $days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        $time = sprintf('%02dh%02d', $this->hour, $this->minute);

        return match ($this->frequency) {
            'weekly'  => 'Chaque ' . ($days[$this->day_of_week ?? 1] ?? 'Lundi') . ' à ' . $time,
            'monthly' => 'Le ' . ($this->day_of_month ?? 1) . ' de chaque mois à ' . $time,
            default   => 'Chaque jour à ' . $time,
        };
    }
}
