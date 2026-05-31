<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgressPhoto extends Model
{

use SoftDeletes; // 2. Usar el Trait dentro de la clase

    protected $dates = ['deleted_at']; // Opcional en versiones recientes
    protected $fillable = [
        'user_id',
        'image_path',
        'taken_at',
        'notes'
    ];

    protected $casts = [
        'taken_at' => 'date',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute()
    {
        // 🛠️ CORRECCIÓN: Usamos $this->id en lugar de acceder al array de atributos crudos
        return route('patient.photos.show', ['id' => $this->id]);
    }

    /**
     * Relación: La foto pertenece a un paciente/usuario específico.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
