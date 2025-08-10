<?php

namespace App\Models;

use App\Enums\ChampType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Option extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'champ' => ChampType::class,
    ];

    public function scopeForChamp2(Builder $query): Builder
    {
        return $query->where('champ', ChampType::CHAMP2);
    }
    public function scopeForChamp3(Builder $query): Builder
    {
        return $query->where('champ', ChampType::CHAMP3);
    }
}
