<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(AuditObserver::class)]
class CatalogItem extends Model
{
    /**
     * Tipos de concepto que maneja el consultorio.
     *
     * @var list<string>
     */
    public const TIPOS = ['consulta', 'producto', 'tratamiento', 'otro'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'nombre',
        'descripcion',
        'tipo',
        'precio',
        'activo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * Doctor dueño del concepto.
     */
    public function bariatra(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Conceptos que se pueden seguir cobrando.
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Nombre del tipo para mostrar en pantalla.
     */
    public function tipoLegible(): string
    {
        return match ($this->tipo) {
            'consulta' => 'Consulta',
            'producto' => 'Producto',
            'tratamiento' => 'Tratamiento',
            default => 'Otro',
        };
    }
}
