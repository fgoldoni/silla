<?php

namespace App\Models;

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
}
