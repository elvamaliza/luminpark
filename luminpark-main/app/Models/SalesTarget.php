<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesTarget extends Model
{
    use HasFactory;

    protected $table = 'sales_targets';

    protected $fillable = [
        'sales_id',
        'period_start',
        'period_end',
        'target_units',
        'target_amount',
        'achieved_units',
        'achieved_amount',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'target_amount' => 'decimal:2',
        'achieved_amount' => 'decimal:2',
    ];

    public function sales()
    {
        return $this->belongsTo(SalesProfile::class, 'sales_id');
    }

    public function getUnitProgressAttribute(): float
    {
        if ((int) $this->target_units === 0) {
            return 0.0;
        }

        return round(((int) $this->achieved_units / (int) $this->target_units) * 100, 2);
    }

    public function getAmountProgressAttribute(): float
    {
        if ((float) $this->target_amount === 0.0) {
            return 0.0;
        }

        return round(((float) $this->achieved_amount / (float) $this->target_amount) * 100, 2);
    }
}
