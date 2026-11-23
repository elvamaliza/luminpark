<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReport extends Model
{
    use HasFactory;

    protected $table = 'sales_reports';

    protected $fillable = [
        'period_start',
        'period_end',
        'units_sold',
        'total_revenue',
        'total_leads',
        'total_closing',
        'generated_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_revenue' => 'decimal:2',
        'total_closing' => 'decimal:2',
    ];

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
