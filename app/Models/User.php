<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

#[ObservedBy(AuditObserver::class)]
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'email',
        'password',
        'phone',
        'avatar',
        'estatura',
        'recibir_recordatorios',
        'treatment_start_date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['avatar_url'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'estatura' => 'float',
            'recibir_recordatorios' => 'boolean',
            'treatment_start_date' => 'date',
        ];
    }

    /**
     * URL pública del avatar o null si el usuario no tiene uno.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (blank($this->avatar)) {
            return null;
        }

        return Storage::disk('avatars')->url($this->avatar);
    }

    /**
     * Estatura en metros usada para calcular el IMC.
     */
    public function estaturaParaImc(): float
    {
        $estatura = (float) ($this->estatura ?: config('clinic.estatura_por_defecto', 1.70));

        // Evita una división por cero si la configuración quedara mal capturada.
        return $estatura > 0.5 ? $estatura : 1.70;
    }

    /**
     * Relación: Un Usuario (Paciente) tiene muchas Medidas.
     */
    public function medidas(): HasMany
    {
        return $this->hasMany(MedidaPaciente::class);
    }

    /**
     * Relación: Un Usuario (Paciente) tiene muchas fotos de progreso.
     */
    public function progressPhotos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class);
    }
}
