<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(AuditObserver::class)]
class Expense extends Model
{
    /**
     * Categorías de gasto del consultorio.
     *
     * @var list<string>
     */
    public const CATEGORIAS = ['insumos', 'renta', 'servicios', 'publicidad', 'personal', 'otro'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'concepto',
        'categoria',
        'monto',
        'fecha',
        'notas',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    /**
     * Doctor al que se carga el gasto.
     */
    public function bariatra(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Nombre de la categoría para mostrar.
     */
    public function categoriaLegible(): string
    {
        return ucfirst($this->categoria);
    }
}
