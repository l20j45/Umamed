<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    /**
     * Sólo existe la fecha de creación.
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'accion',
        'modelo',
        'modelo_id',
        'cambios',
        'ip',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cambios' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Quién realizó el cambio (nulo si fue un proceso automático).
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Registro afectado. Puede ser null si ya se eliminó.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo('auditable', 'modelo', 'modelo_id');
    }

    /**
     * Nombre corto del modelo auditado, para mostrar en pantalla.
     */
    public function modeloCorto(): string
    {
        return class_basename($this->modelo);
    }
}
