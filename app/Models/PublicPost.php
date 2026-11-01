<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PublicPost extends Model
{
    use SoftDeletes;
    protected $table = 'public_posts';

    protected $fillable = ['title', 'content', 'posted_on', 'image', 'is_published'];

    protected $casts = [
        'posted_on'    => 'date',
        'is_published' => 'boolean',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . ltrim($this->image, '/')) : null;
    }
}
