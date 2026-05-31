<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class medidasPaciente extends Model
{
     use HasFactory;
protected $table = 'medidas';
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

    // Relación Inversa: Esta medida pertenece a un Usuario (Paciente)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
