<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

#[ObservedBy(AuditObserver::class)]
class ProgressPhoto extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORIAS = [
        'frente',
        'espalda',
        'lateral_derecha',
        'lateral_izquierda',
    ];

    /**
     * Al eliminar la foto (aunque sea borrado lógico) también se borra el
     * archivo del disco: si no, las imágenes se acumulan para siempre y el
     * paciente cree que ya no existen.
     */
    protected static function booted(): void
    {
        static::deleted(function (self $foto): void {
            $disco = Storage::disk('local');

            if (filled($foto->image_path) && $disco->exists($foto->image_path)) {
                $disco->delete($foto->image_path);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'image_path',
        'taken_at',
        'notes',
        'categoria',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['photo_url'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_at' => 'date',
        ];
    }

    /**
     * URL pública del endpoint que sirve la foto (valida la propiedad).
     *
     * Se protege con Route::has() para que el modelo pueda serializarse en
     * consola, colas o tests donde las rutas web aún no están registradas.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->getKey() || ! Route::has('patient.photos.show')) {
            return null;
        }

        return route('patient.photos.show', ['id' => $this->getKey()]);
    }

    public function categoriaLegible(): string
    {
        return match ($this->categoria) {
            'frente' => 'Frente',
            'espalda' => 'Espalda',
            'lateral_derecha' => 'Lateral derecha',
            'lateral_izquierda' => 'Lateral izquierda',
            default => ucfirst((string) ($this->categoria ?? 'frente')),
        };
    }

    /**
     * Relación: La foto pertenece a un paciente/usuario específico.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
