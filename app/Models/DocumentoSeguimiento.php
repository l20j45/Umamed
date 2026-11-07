<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Documentos que el staff sube al expediente fuera de la consulta: menús o
 * planes alimenticios, pedimentos de estudios y notas de seguimiento (PDF).
 *
 * Quedan en el disco 'local' (fuera del alcance del navegador) y se sirven
 * vía endpoint con validación de propiedad.
 */
#[ObservedBy(AuditObserver::class)]
class DocumentoSeguimiento extends Model
{
    use HasFactory;

    protected $table = 'seguimiento';

    public const CATEGORIAS = [
        'menu',
        'pedimento',
        'nota',
        'otro',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'uploaded_by',
        'titulo',
        'categoria',
        'notas',
        'archivo_path',
    ];

    /**
     * Al eliminar el registro también se borra el PDF del disco, igual que
     * las fotos de progreso.
     */
    protected static function booted(): void
    {
        static::deleted(function (self $documento): void {
            $disco = Storage::disk('local');

            if (filled($documento->archivo_path) && $disco->exists($documento->archivo_path)) {
                $disco->delete($documento->archivo_path);
            }
        });
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['tiene_pdf'];

    /**
     * Indicativo para la UI: sólo los menús exigen PDF; el resto puede ser
     * comentario sin archivo.
     */
    public function getTienePdfAttribute(): bool
    {
        return filled($this->archivo_path);
    }

    public function categoriaLegible(): string
    {
        return match ($this->categoria) {
            'menu' => 'Menú / plan alimenticio',
            'pedimento' => 'Pedimento de estudios',
            'nota' => 'Nota o instrucciones',
            'otro' => 'Otro documento',
            default => ucfirst((string) ($this->categoria ?? 'otro')),
        };
    }

    /**
     * Relación: el documento pertenece a un paciente.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación: el staff que lo subió (puede quedar nulo si se borra).
     */
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
