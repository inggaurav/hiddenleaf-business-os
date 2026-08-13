<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditObserver
{
    public function created(Model $model)
    {
        $this->log($model, 'created');
    }

    public function updated(Model $model)
    {
        $this->log($model, 'updated');
    }

    public function deleted(Model $model)
    {
        $this->log($model, 'deleted');
    }

    protected function log(Model $model, $action)
    {
        AuditLog::create([
            'id' => Str::uuid(),
            'actor_id' => Auth::id() ?? 1,
            'organization_id' => $model->organization_id ?? 1,
            'workspace_id' => $model->workspace_id ?? null,
            'entity_type' => get_class($model),
            'entity_id' => $model->id,
            'action' => $action,
            'metadata' => [
                'old' => $model->getOriginal(),
                'new' => $model->getAttributes(),
            ],
            'created_at' => now(),
        ]);
    }
}
