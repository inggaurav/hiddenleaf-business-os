<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'name',
        'file_name',
        'mime_type',
        'disk',
        'size',
        'path',
        'directory_id',
        'workspace_id',
        'created_by',
    ];

    public function directory()
    {
        return $this->belongsTo(MediaDirectory::class, 'directory_id');
    }
}
