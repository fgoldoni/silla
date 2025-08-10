<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Document extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'tags' => AsArrayObject::class,
        'metadata' => AsArrayObject::class,
        'deleted_at' => 'datetime',
    ];

    public function storagePath(): string
    {
        return $this->file_path;
    }


    public function setFileNameAttribute(string $value): void
    {
        $ext = pathinfo($value, PATHINFO_EXTENSION);
        $name = pathinfo($value, PATHINFO_FILENAME);
        $safe = Str::slug($name);
        $this->attributes['file_name'] = $ext ? $safe . '.' . strtolower($ext) : $safe;
    }

    #[Scope]
    public function ownedBy(Builder $query, int|string|null $userId)
    {
        return $query->where('user_id', $userId);
    }
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Générer l'ULID si pas déjà défini
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::ulid();
            }

            // Calcul UID incrémental
            $lastUid = static::withTrashed()->max('uid');
            $model->uid = $lastUid ? $lastUid + 1 : 1;
        });
    }

    public function getStatusAttribute(): string
    {
        return $this->deleted_at ? 'trashed' : 'active';
    }

}
