<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PacienteObservacion extends Model
{
    /**
     * Nombre de la tabla asociada.
     *
     * @var string
     */
    protected $table = 'paciente_observaciones';

    /**
     * Los atributos asignables.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'created_by',
        'titulo',
        'observaciones',
        'dieta',
        'recomendaciones',
        'formato',
        'plantilla',
    ];

    /**
     * Relación inversa: la observación pertenece a un paciente.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación inversa: la observación fue creada por un bariatra.
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
