<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FollowUpHistory extends Model
{
    use HasFactory;

    protected $table = 'follow_up_histories';

    protected $fillable = [
        'lead_id',
        'sales_id',
        'follow_up_date',
        'status_before',
        'status_after',
        'method',
        'note',
    ];

    protected $casts = [
        'follow_up_date' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(CustomerLead::class, 'lead_id');
    }

    public function salesProfile()
    {
        return $this->belongsTo(SalesProfile::class, 'sales_id');
    }
}
