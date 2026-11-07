import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { activarPush, desactivarPush, pushDisponible } from '@/lib/push';
import { useState } from 'react';

type Estado = 'pendiente' | 'confirmada' | 'atendida' | 'cancelada' | 'no_asistio';

interface Cita {
    id: number;
    fecha: string;
    inicia: string;
    termina: string;
    estado: Estado;
    tipo: string;
    notas: string | null;
    bariatra: { id: number; nombre: string | null };
}

interface MisCitasProps {
    citas: Cita[];
    recibir_recordatorios: boolean;
}

const ESTADOS: Record<Estado, { texto: string; clase: string }> = {
    pendiente: {
        texto: 'Pendiente',
        clase: 'border-amber-200 bg-amber-50 text-amber-900',
    },
    confirmada: {
        texto: 'Confirmada',
        clase: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    },
    atendida: {
        texto: 'Atendida',
        clase: 'border-sky-200 bg-sky-50 text-sky-900',
    },
    cancelada: {
        texto: 'Cancelada',
        clase: 'border-gray-200 bg-gray-100 text-gray-500 line-through',
    },
    no_asistio: {
        texto: 'No asistió',
        clase: 'border-red-200 bg-red-50 text-red-800',
    },
};

const TIPOS: Record<string, string> = {
    primera_vez: 'Primera vez',
    seguimiento: 'Seguimiento',
    valoracion: 'Valoración',
    otro: 'Consulta',
};

const fechaLarga = (fecha: string) =>
    new Date(`${fecha}T12:00:00`).toLocaleDateString('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

export default function MisCitas({ citas, recibir_recordatorios }: MisCitasProps) {
    const proximas = citas.filter(
        (cita) => !['cancelada', 'atendida', 'no_asistio'].includes(cita.estado),
    );
    const anteriores = citas.filter((cita) =>
        ['cancelada', 'atendida', 'no_asistio'].includes(cita.estado),
    );

    const [estadoPush, setEstadoPush] = useState<NotificationPermission | 'no-disponible'>(
        () => (!pushDisponible() ? 'no-disponible' : Notification.permission),
    );
    const [cargandoPush, setCargandoPush] = useState(false);

    const alternarPush = async () => {
        setCargandoPush(true);

        try {
            if (estadoPush === 'granted') {
                await desactivarPush();
                setEstadoPush('default');
            } else {
                const resultado = await activarPush();
                setEstadoPush(resultado === 'error' ? 'denied' : resultado);
            }
        } finally {
            setCargandoPush(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Mis citas
                </h2>
            }
        >
            <Head title="Mis citas" />

            <div className="py-10">
                <div className="mx-auto max-w-3xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <section className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h3 className="border-b border-gray-100 px-5 py-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Próximas
                        </h3>

                        {proximas.length === 0 ? (
                            <p className="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No tienes citas programadas. Escríbenos para agendar una.
                            </p>
                        ) : (
                            <ul className="divide-y divide-gray-100 dark:divide-gray-700">
                                {proximas.map((cita) => (
                                    <li key={cita.id} className="px-5 py-4">
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <p className="text-sm font-semibold capitalize text-gray-900 dark:text-gray-100">
                                                    {fechaLarga(cita.fecha)}
                                                </p>
                                                <p className="text-sm text-gray-600 dark:text-gray-300">
                                                    {cita.inicia}–{cita.termina} ·{' '}
                                                    {TIPOS[cita.tipo] ?? cita.tipo}
                                                </p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                    Con {cita.bariatra.nombre}
                                                </p>
                                            </div>
                                            <span
                                                className={`rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${ESTADOS[cita.estado].clase}`}
                                            >
                                                {ESTADOS[cita.estado].texto}
                                            </span>
                                        </div>
                                        {cita.notas && (
                                            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                                {cita.notas}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                            Recordatorios por WhatsApp
                        </h3>
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Te enviamos un recordatorio el día antes de cada cita al número
                            registrado en tu expediente.
                        </p>
                        <label className="mt-3 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={recibir_recordatorios}
                                onChange={(e) =>
                                    router.patch(
                                        route('patient.recordatorios'),
                                        { recibir_recordatorios: e.target.checked },
                                        { preserveScroll: true },
                                    )
                                }
                                className="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                            />
                            Quiero recibir recordatorios por WhatsApp
                        </label>
                    </section>

                    <section className="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                            Notificaciones en tu teléfono
                        </h3>
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Un aviso llega al teléfono 24 horas antes de cada cita y
                            cuando la clínica registra algo nuevo en tu expediente
                            (medición, notas o recibo). En iPhone se necesita la app
                            instalada desde "Agregar a la pantalla de inicio"; en Android
                            también funciona desde Chrome.
                        </p>
                        {estadoPush === 'no-disponible' ? (
                            <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">
                                Este navegador no soporta notificaciones push o no se está
                                viendo con una conexión segura.
                            </p>
                        ) : estadoPush === 'denied' ? (
                            <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">
                            Las notificaciones están bloqueadas. Actívalas para este
                            sitio en la configuración del navegador.
                            </p>
                        ) : estadoPush === 'granted' ? (
                            <>
                                <p className="mt-3 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                                    Notificaciones activadas.
                                </p>
                                <button
                                    type="button"
                                    disabled={cargandoPush}
                                    onClick={alternarPush}
                                    className="mt-2 text-sm text-gray-500 underline hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    Desactivarlas
                                </button>
                            </>
                        ) : (
                            <button
                                type="button"
                                disabled={cargandoPush}
                                onClick={alternarPush}
                                className="mt-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                            >
                                {cargandoPush ? 'Activando...' : 'Activar notificaciones'}
                            </button>
                        )}
                    </section>

                    {anteriores.length > 0 && (
                        <section className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <h3 className="border-b border-gray-100 px-5 py-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                Historial
                            </h3>
                            <ul className="divide-y divide-gray-100 dark:divide-gray-700">
                                {anteriores.map((cita) => (
                                    <li
                                        key={cita.id}
                                        className="flex flex-wrap items-center justify-between gap-2 px-5 py-3"
                                    >
                                        <div>
                                            <p className="text-sm capitalize text-gray-700 dark:text-gray-200">
                                                {fechaLarga(cita.fecha)} · {cita.inicia}
                                            </p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {TIPOS[cita.tipo] ?? cita.tipo} con{' '}
                                                {cita.bariatra.nombre}
                                            </p>
                                        </div>
                                        <span
                                            className={`rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${ESTADOS[cita.estado].clase}`}
                                        >
                                            {ESTADOS[cita.estado].texto}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
