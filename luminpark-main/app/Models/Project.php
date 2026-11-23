<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $table = 'projects';

    protected $fillable = ['name', 'description', 'address', 'status'];

    public function clusters()
    {
        return $this->hasMany(Cluster::class, 'project_id');
    }

    public function units()
    {
        return $this->hasManyThrough(Unit::class, Cluster::class, 'project_id', 'cluster_id');
    }
}
