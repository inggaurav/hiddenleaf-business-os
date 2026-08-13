<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaDirectory extends Model {
    use HasFactory;
    protected $fillable = ['name', 'path', 'disk', 'workspace_id', 'created_by'];
}
