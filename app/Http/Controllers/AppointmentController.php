<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAppointmentRequest;
use App\Jobs\SendAppointmentReminder;
use App\Models\Appointment;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    /**
     * Agenda mensual del consultorio.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Appointment::class);

        $zona = Appointment::zonaHoraria();
        $mes = $this->mesValido($request->query('mes'), $zona);

        $inicioMes = Carbon::createFromFormat('Y-m-d', $mes.'-01', $zona)->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();

        $usuario = $request->user();
        $bariatraFiltro = $request->integer('bariatra_id') ?: null;

        $consulta = Appointment::query()
            ->with(['paciente:id,name,last_name,phone', 'bariatra:id,name', 'charges'])
            ->enRango($inicioMes, $finMes)
            ->orderBy('inicia_en');

        // Un bariatra sólo ve su propia agenda; administración ve todo.
        if ($usuario->hasRole('bariatra') && ! $usuario->hasAnyRole(['admin', 'recepcionista'])) {
            $consulta->where('bariatra_id', $usuario->getKey());
        } elseif ($bariatraFiltro) {
            $consulta->where('bariatra_id', $bariatraFiltro);
        }

        return Inertia::render('Agenda/Index', [
            'citas' => $consulta->get()->map(fn (Appointment $cita) => $this->paraJs($cita))->values(),
            'mes' => $inicioMes->format('Y-m'),
            'hoy' => now($zona)->format('Y-m-d'),
            'bariatras' => User::role('bariatra')->orderBy('name')->get(['id', 'name']),
            'pacientes' => User::role('paciente')->orderBy('name')->get(['id', 'name', 'last_name']),
            'filtros' => ['bariatra_id' => $bariatraFiltro],
            'whatsapp' => app(WhatsAppService::class)->modo(),
        ]);
    }

    /**
     * Agenda del paciente autenticado.
     */
    public function misCitas(Request $request): Response
    {
        $citas = Appointment::query()
            ->with(['paciente:id,name,last_name,phone', 'bariatra:id,name', 'charges'])
            ->where('user_id', $request->user()->getKey())
            ->where('inicia_en', '>=', now()->subHours(12))
            ->orderBy('inicia_en')
            ->limit(50)
            ->get()
            ->map(fn (Appointment $cita) => $this->paraJs($cita))
            ->values();

        return Inertia::render('Patients/MisCitas', [
            'citas' => $citas,
            'recibir_recordatorios' => (bool) $request->user()->recibir_recordatorios,
        ]);
    }

    /**
     * El paciente decide si quiere recibir recordatorios por WhatsApp.
     */
    public function actualizarRecordatorios(Request $request): RedirectResponse
    {
        $activar = $request->boolean('recibir_recordatorios');

        $request->user()->update(['recibir_recordatorios' => $activar]);

        return back()->with(
            'success',
            $activar
                ? 'Seguirás recibiendo recordatorios por WhatsApp.'
                : 'Listo: ya no te enviaremos recordatorios por WhatsApp.'
        );
    }

    public function store(SaveAppointmentRequest $request): RedirectResponse
    {
        $cita = DB::transaction(function () use ($request) {
            $datos = $this->conFechasUtc($request->validated());

            $this->bloquearAgendaDelDoctor((int) $datos['bariatra_id']);

            // Se vuelve a comprobar dentro de la transacción: la validación
            // pudo pasar antes de que otra persona agendara el mismo horario.
            if (Appointment::hayChoque((int) $datos['bariatra_id'], $datos['inicia_en'], $datos['termina_en'])) {
                throw ValidationException::withMessages([
                    'inicia_en' => 'Ese horario se acaba de ocupar. Actualiza la agenda e intenta de nuevo.',
                ]);
            }

            return Appointment::create($datos);
        });

        return back()->with(
            'success',
            'Cita agendada para el '.$cita->inicioLocal()->format('d/m/Y').' a las '.$cita->inicioLocal()->format('H:i').'.'
        );
    }

    public function update(SaveAppointmentRequest $request, Appointment $cita): RedirectResponse
    {
        $this->authorize('update', $cita);

        DB::transaction(function () use ($request, $cita) {
            $datos = $this->conFechasUtc($request->validated());

            $this->bloquearAgendaDelDoctor((int) $datos['bariatra_id']);

            if (Appointment::hayChoque((int) $datos['bariatra_id'], $datos['inicia_en'], $datos['termina_en'], $cita->getKey())) {
                throw ValidationException::withMessages([
                    'inicia_en' => 'Ese horario se cruza con otra cita del doctor.',
                ]);
            }

            // Si la cita cambió de horario, el recordatorio vuelve a estar pendiente.
            if (! $cita->inicia_en->equalTo($datos['inicia_en'])) {
                $datos['recordatorio_enviado_en'] = null;
            }

            $cita->update($datos);
        });

        return back()->with('success', 'Cita actualizada.');
    }

    /**
     * Cancelar conserva la cita en el historial (no se borra).
     */
    public function cancelar(Appointment $cita): RedirectResponse
    {
        $this->authorize('cancel', $cita);

        if ($cita->estado === 'cancelada') {
            return back()->with('error', 'La cita ya estaba cancelada.');
        }

        $cita->update(['estado' => 'cancelada']);

        return back()->with('success', 'Cita cancelada. Se conserva en el historial del paciente.');
    }

    public function destroy(Appointment $cita): RedirectResponse
    {
        $this->authorize('delete', $cita);

        $cita->delete();

        return back()->with('success', 'Cita eliminada de la agenda.');
    }

    /**
     * Encola el recordatorio de una cita (envío manual desde la agenda).
     */
    public function enviarRecordatorio(Appointment $cita): RedirectResponse
    {
        $this->authorize('update', $cita);

        if (! $cita->estaVigente()) {
            return back()->with('error', 'La cita está cancelada: no se envía recordatorio.');
        }

        if (blank($cita->paciente?->phone)) {
            return back()->with('error', 'El paciente no tiene un teléfono registrado.');
        }

        if ($cita->paciente && ! $cita->paciente->recibir_recordatorios) {
            return back()->with('error', 'El paciente pidió no recibir recordatorios por WhatsApp.');
        }

        SendAppointmentReminder::dispatch($cita->id, forzar: true);

        if (app(WhatsAppService::class)->modo() === 'cloud') {
            return back()->with('success', 'Recordatorio en cola de envío por WhatsApp.');
        }

        return back()->with(
            'success',
            'Modo desarrollo: el recordatorio se registró en el log. Configura WHATSAPP_DRIVER=cloud para enviarlo de verdad.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function paraJs(Appointment $cita): array
    {
        $whatsapp = app(WhatsAppService::class);
        $paciente = $cita->paciente;

        return [
            'id' => $cita->id,
            'fecha' => $cita->inicioLocal()->format('Y-m-d'),
            'inicia' => $cita->inicioLocal()->format('H:i'),
            'termina' => $cita->finLocal()->format('H:i'),
            'inicia_local' => $cita->inicioLocal()->format('Y-m-d\TH:i'),
            'termina_local' => $cita->finLocal()->format('Y-m-d\TH:i'),
            'estado' => $cita->estado,
            'tipo' => $cita->tipo,
            'notas' => $cita->notas,
            'paciente' => $paciente ? [
                'id' => $paciente->id,
                'nombre' => $cita->nombrePaciente(),
                'telefono' => $paciente->phone,
            ] : null,
            'bariatra' => [
                'id' => $cita->bariatra_id,
                'nombre' => $cita->bariatra?->name,
            ],
            'whatsapp_url' => $paciente ? $whatsapp->enlace($paciente->phone, $cita->mensaje('recordatorio')) : null,
            'recordatorio_enviado' => $cita->recordatorio_enviado_en !== null,
            'total' => $cita->total(),
            'pagado' => $cita->totalPagado(),
            'saldo' => $cita->saldo(),
            'estado_pago' => $cita->estadoPago(),
        ];
    }

    /**
     * Serializa la agenda de un doctor dentro de la transacción.
     *
     * En MySQL bloquea su fila hasta el commit, así dos personas no pueden
     * apartar el mismo horario a la vez. En SQLite el bloqueo no aplica.
     */
    private function bloquearAgendaDelDoctor(int $bariatraId): void
    {
        User::query()->whereKey($bariatraId)->lockForUpdate()->first();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function conFechasUtc(array $datos): array
    {
        $zona = Appointment::zonaHoraria();

        $datos['inicia_en'] = Carbon::parse((string) $datos['inicia_en'], $zona)->utc();
        $datos['termina_en'] = Carbon::parse((string) $datos['termina_en'], $zona)->utc();

        return $datos;
    }

    private function mesValido(mixed $valor, string $zona): string
    {
        if (is_string($valor) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor) === 1) {
            return $valor;
        }

        return now($zona)->format('Y-m');
    }
}
