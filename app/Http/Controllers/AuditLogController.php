<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\MedidaPaciente;
use App\Models\ProgressPhoto;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Modelos auditados, con el nombre corto que se muestra en el filtro.
     *
     * @var array<string, class-string>
     */
    public const MODELOS = [
        'Appointment' => Appointment::class,
        'MedidaPaciente' => MedidaPaciente::class,
        'ProgressPhoto' => ProgressPhoto::class,
        'User' => User::class,
    ];

    /**
     * Bitácora de cambios del sistema (sólo administración).
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'accion' => (string) $request->query('accion', ''),
            'modelo' => (string) $request->query('modelo', ''),
            'usuario_id' => $request->integer('usuario_id') ?: null,
            'desde' => (string) $request->query('desde', ''),
            'hasta' => (string) $request->query('hasta', ''),
        ];

        $modeloFiltrado = self::MODELOS[$filtros['modelo']] ?? null;

        $registros = AuditLog::query()
            ->with(['usuario:id,name', 'auditable'])
            ->when(
                in_array($filtros['accion'], ['created', 'updated', 'deleted'], true),
                fn ($query) => $query->where('accion', $filtros['accion'])
            )
            ->when(
                $modeloFiltrado !== null,
                fn ($query) => $query->where('modelo', $modeloFiltrado)
            )
            ->when(
                $filtros['usuario_id'] !== null,
                fn ($query) => $query->where('user_id', $filtros['usuario_id'])
            )
            ->when(
                $filtros['desde'] !== '',
                fn ($query) => $query->whereDate('created_at', '>=', $filtros['desde'])
            )
            ->when(
                $filtros['hasta'] !== '',
                fn ($query) => $query->whereDate('created_at', '<=', $filtros['hasta'])
            )
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => [
                'id' => $log->id,
                'accion' => $log->accion,
                'modelo' => $log->modeloCorto(),
                'modelo_id' => $log->modelo_id,
                'referencia' => $this->referencia($log),
                'usuario' => $log->usuario?->name,
                'usuario_id' => $log->user_id,
                'ip' => $log->ip,
                'cambios' => $log->cambios,
                'fecha' => $log->created_at
                    ?->timezone((string) config('clinic.zona_horaria', 'UTC'))
                    ->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Auditoria/Index', [
            'registros' => $registros,
            'filtros' => $filtros,
            'usuarios' => User::query()->orderBy('name')->get(['id', 'name']),
            'acciones' => [
                'created' => 'Alta',
                'updated' => 'Cambio',
                'deleted' => 'Baja',
            ],
            'modelos' => array_keys(self::MODELOS),
            'zona' => (string) config('clinic.zona_horaria', 'UTC'),
        ]);
    }

    /**
     * Texto legible del registro afectado.
     */
    private function referencia(AuditLog $log): string
    {
        $registro = $log->auditable;

        if ($registro === null) {
            return '#'.$log->modelo_id.' (ya no existe)';
        }

        return match (true) {
            $registro instanceof User => $registro->name.' · '.$registro->email,
            $registro instanceof Appointment => 'Cita del '
                .$registro->inicioLocal()->format('d/m/Y H:i'),
            $registro instanceof MedidaPaciente => 'Medición #'.$registro->getKey(),
            $registro instanceof ProgressPhoto => 'Foto #'.$registro->getKey(),
            default => '#'.$log->modelo_id,
        };
    }
}
