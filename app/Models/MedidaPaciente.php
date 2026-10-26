<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(AuditObserver::class)]
class MedidaPaciente extends Model
{
    use HasFactory;

    /**
     * Nombre de la tabla asociada.
     *
     * @var string
     */
    protected $table = 'medidas';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'peso',
        'imc',
        'tSuperior',
        'abdomenSuperior',
        'abdomenMedio',
        'abdomenInferior',
        'exSuperior',
        'exInferior',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'peso' => 'float',
            'imc' => 'float',
            'tSuperior' => 'float',
            'abdomenSuperior' => 'float',
            'abdomenMedio' => 'float',
            'abdomenInferior' => 'float',
            'exSuperior' => 'float',
            'exInferior' => 'float',
        ];
    }

    /**
     * Relación Inversa: Esta medida pertenece a un Usuario (Paciente).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
