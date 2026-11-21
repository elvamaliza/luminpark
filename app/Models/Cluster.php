<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cluster extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_AKTIF = 'aktif';
    public const STATUS_DIARSIPKAN = 'diarsipkan';
    public const STATUSES = [self::STATUS_AKTIF, self::STATUS_DIARSIPKAN];

    protected $table = 'clusters';

    protected $fillable = ['project_id', 'name', 'description', 'address', 'latitude', 'longitude', 'status'];

    protected $casts = [
        'deleted_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'cluster_id');
    }

    /** Cluster yang masih boleh dipakai pada formulir unit dan filter. */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AKTIF);
    }

    public function isDiarsipkan(): bool
    {
        return $this->status === self::STATUS_DIARSIPKAN;
    }
}
