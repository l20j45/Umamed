<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[ObservedBy(AuditObserver::class)]
class Appointment extends Model
{
    /**
     * Estados posibles de una cita.
     *
     * @var list<string>
     */
    public const ESTADOS = ['pendiente', 'confirmada', 'atendida', 'cancelada', 'no_asistio'];

    /**
     * Tipos de consulta que maneja la clínica.
     *
     * @var list<string>
     */
    public const TIPOS = ['primera_vez', 'seguimiento', 'valoracion', 'otro'];

    /**
     * Estados que ya no ocupan horario en la agenda.
     *
     * @var list<string>
     */
    public const ESTADOS_LIBERAN_AGENDA = ['cancelada', 'no_asistio'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'bariatra_id',
        'user_id',
        'tipo',
        'inicia_en',
        'termina_en',
        'estado',
        'notas',
        'recordatorio_enviado_en',
        'pagado',
        'pagado_en',
        'metodo_pago',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inicia_en' => 'datetime',
            'termina_en' => 'datetime',
            'recordatorio_enviado_en' => 'datetime',
        ];
    }

    /**
     * Conceptos cobrados en la cita.
     */
    public function charges(): HasMany
    {
        return $this->hasMany(AppointmentCharge::class);
    }

    /**
     * Abonos recibidos para la cita.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(AppointmentPayment::class);
    }

    /**
     * Total cobrado en la cita (requiere la relación cargada o la consulta).
     */
    public function total(): float
    {
        return round(
            $this->charges->sum(fn (AppointmentCharge $cargo): float => $cargo->subtotal),
            2
        );
    }

    /**
     * Total abonado a la cita.
     */
    public function totalPagado(): float
    {
        return round(
            $this->payments->sum(fn (AppointmentPayment $abono): float => (float) $abono->monto),
            2
        );
    }

    /**
     * Lo que falta por cobrar de la cita.
     */
    public function saldo(): float
    {
        return max(0, round($this->total() - $this->totalPagado(), 2));
    }

    public function getPagadoAttribute(): bool
    {
        return $this->estaPagada();
    }

    public function getPagadoEnAttribute(): ?Carbon
    {
        $ultimoPago = $this->payments()->latest('pagado_en')->latest('id')->first();

        if (! $ultimoPago || ! $ultimoPago->pagado_en) {
            return null;
        }

        return $ultimoPago->pagado_en instanceof Carbon
            ? $ultimoPago->pagado_en
            : Carbon::parse($ultimoPago->pagado_en);
    }

    public function getMetodoPagoAttribute(): ?string
    {
        $ultimoPago = $this->payments()->latest('pagado_en')->latest('id')->first();

        return $ultimoPago?->metodo_pago;
    }

    public function setPagadoAttribute(mixed $value): void
    {
        $this->attributes['pagado'] = (bool) $value ? 1 : 0;
    }

    public function setPagadoEnAttribute(mixed $value): void
    {
        $this->attributes['pagado_en'] = $value;
    }

    public function setMetodoPagoAttribute(mixed $value): void
    {
        $this->attributes['metodo_pago'] = $value;
    }

    public function save(array $options = []): bool
    {
        if (array_key_exists('pagado', $this->attributes) || array_key_exists('pagado_en', $this->attributes) || array_key_exists('metodo_pago', $this->attributes)) {
            $this->sincronizarPagoLegacy();
            unset($this->attributes['pagado'], $this->attributes['pagado_en'], $this->attributes['metodo_pago']);
        }

        return parent::save($options);
    }

    /**
     * Estado del pago: pagado, parcial o pendiente.
     */
    public function estadoPago(): string
    {
        $total = $this->total();
        $pagado = $this->totalPagado();

        if ($total > 0 && $pagado >= $total - 0.005) {
            return 'pagado';
        }

        return $pagado > 0 ? 'parcial' : 'pendiente';
    }

    /**
     * ¿La cita ya quedó pagada por completo?
     */
    public function estaPagada(): bool
    {
        return $this->estadoPago() === 'pagado';
    }

    /**
     * Zona horaria de la clínica (las horas se guardan en UTC).
     */
    public static function zonaHoraria(): string
    {
        return (string) config('clinic.zona_horaria', 'UTC');
    }

    /**
     * El paciente de la cita (puede ser nulo si el horario está bloqueado).
     */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * El profesional que atiende la cita.
     */
    public function bariatra(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bariatra_id');
    }

    /**
     * Citas dentro de un rango (en UTC).
     */
    public function scopeEnRango(Builder $query, Carbon $desde, Carbon $hasta): Builder
    {
        return $query
            ->where('inicia_en', '>=', $desde->copy()->utc())
            ->where('inicia_en', '<=', $hasta->copy()->utc());
    }

    /**
     * Citas que siguen ocupando un horario.
     */
    public function scopeOcupanAgenda(Builder $query): Builder
    {
        return $query->whereNotIn('estado', self::ESTADOS_LIBERAN_AGENDA);
    }

    private function sincronizarPagoLegacy(): void
    {
        $pagado = (bool) ($this->attributes['pagado'] ?? false);
        if (! $pagado) {
            return;
        }

        $this->loadMissing('charges', 'payments');
        $total = $this->total();
        if ($total <= 0) {
            return;
        }

        $pagadoActual = $this->totalPagado();
        if ($pagadoActual >= $total - 0.005) {
            return;
        }

        $monto = round(max(0, $total - $pagadoActual), 2);
        $metodo = (string) ($this->attributes['metodo_pago'] ?? 'efectivo');
        $fecha = $this->attributes['pagado_en'] ?? now()->toDateString();

        $this->payments()->create([
            'monto' => $monto,
            'metodo_pago' => $metodo,
            'pagado_en' => $fecha,
            'nota' => 'Pago legacy sincronizado',
        ]);
    }

    /**
     * ¿La cita todavía se puede atender / recordar?
     */
    public function estaVigente(): bool
    {
        return ! in_array($this->estado, self::ESTADOS_LIBERAN_AGENDA, true);
    }

    /**
     * Hora de inicio ya convertida a la zona de la clínica.
     */
    public function inicioLocal(): Carbon
    {
        return $this->inicia_en->copy()->timezone(self::zonaHoraria());
    }

    /**
     * Hora de fin ya convertida a la zona de la clínica.
     */
    public function finLocal(): Carbon
    {
        return $this->termina_en->copy()->timezone(self::zonaHoraria());
    }

    /**
     * Nombre visible del paciente (o el motivo del bloqueo).
     */
    public function nombrePaciente(): string
    {
        if (! $this->paciente) {
            return 'Horario bloqueado';
        }

        return trim($this->paciente->name.' '.$this->paciente->last_name);
    }

    /**
     * ¿El doctor ya tiene una cita que se cruza con ese rango? (fechas en UTC)
     */
    public static function hayChoque(int $bariatraId, Carbon $inicio, Carbon $fin, ?int $ignorarId = null): bool
    {
        return static::query()
            ->where('bariatra_id', $bariatraId)
            ->whereNotIn('estado', self::ESTADOS_LIBERAN_AGENDA)
            ->when($ignorarId !== null, fn (Builder $query) => $query->whereKeyNot($ignorarId))
            ->where('inicia_en', '<', $fin)
            ->where('termina_en', '>', $inicio)
            ->exists();
    }

    /**
     * Parámetros que espera la plantilla aprobada de WhatsApp, en orden:
     * 1) nombre del paciente, 2) fecha, 3) hora.
     *
     * @return list<string>
     */
    public function parametrosPlantilla(): array
    {
        return [
            (string) ($this->paciente?->name ?? ''),
            $this->inicioLocal()->format('d/m/Y'),
            $this->inicioLocal()->format('H:i'),
        ];
    }

    /**
     * Arma un mensaje de WhatsApp con los datos de la cita.
     */
    public function mensaje(string $plantilla = 'recordatorio'): string
    {
        $texto = (string) config("whatsapp.plantillas.{$plantilla}", '');

        return strtr($texto, [
            ':paciente' => $this->paciente?->name ?? '',
            ':doctor' => $this->bariatra?->name ?? '',
            ':clinica' => (string) config('app.name'),
            ':fecha' => $this->inicioLocal()->translatedFormat('l j \d\e F'),
            ':hora' => $this->inicioLocal()->format('H:i'),
        ]);
    }
}
