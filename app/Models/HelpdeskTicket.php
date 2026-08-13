<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpdeskTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'name',
        'email',
        'category_id',
        'subject',
        'status',
        'priority',
        'description',
        'attachments',
        'organization_id',
        'workspace_id',
        'created_by',
    ];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(HelpdeskCategory::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function replies()
    {
        return $this->hasMany(HelpdeskReply::class, 'ticket_id');
    }
}
