import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { moneda } from '@/lib/formato';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';
import type { PageProps } from '@/types';

type Estado = 'pendiente' | 'confirmada' | 'atendida' | 'cancelada' | 'no_asistio';

interface Cita {
    id: number;
    fecha: string;
    inicia: string;
    termina: string;
    inicia_local: string;
    termina_local: string;
    estado: Estado;
    tipo: string;
    notas: string | null;
    paciente: { id: number; nombre: string; telefono: string | null } | null;
    bariatra: { id: number; nombre: string | null };
    whatsapp_url: string | null;
    recordatorio_enviado: boolean;
    pagado: boolean;
    total: number;
}

interface Bariatra {
    id: number;
    name: string;
}

interface Paciente {
    id: number;
    name: string;
    last_name: string | null;
}

interface AgendaProps {
    citas: Cita[];
    mes: string;
    hoy: string;
    bariatras: Bariatra[];
    pacientes: Paciente[];
    filtros: { bariatra_id: number | null };
    whatsapp: 'cloud' | 'log';
}

const DIAS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

const ESTADOS: { valor: Estado; texto: string }[] = [
    { valor: 'pendiente', texto: 'Pendiente' },
    { valor: 'confirmada', texto: 'Confirmada' },
    { valor: 'atendida', texto: 'Atendida' },
    { valor: 'cancelada', texto: 'Cancelada' },
    { valor: 'no_asistio', texto: 'No asistió' },
];

const TIPOS = [
    { valor: 'primera_vez', texto: 'Primera vez' },
    { valor: 'seguimiento', texto: 'Seguimiento' },
    { valor: 'valoracion', texto: 'Valoración' },
    { valor: 'otro', texto: 'Otro' },
];

const ESTILO_ESTADO: Record<Estado, string> = {
    pendiente: 'border-amber-200 bg-amber-50 text-amber-900',
    confirmada: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    atendida: 'border-sky-200 bg-sky-50 text-sky-900',
    cancelada: 'border-gray-200 bg-gray-100 text-gray-500 line-through',
    no_asistio: 'border-red-200 bg-red-50 text-red-800',
};

const textoEstado = (estado: Estado) =>
    ESTADOS.find((e) => e.valor === estado)?.texto ?? estado;

const textoTipo = (tipo: string) =>
    TIPOS.find((t) => t.valor === tipo)?.texto ?? tipo;

const nombreMes = (mes: string) => {
    const [anio, numero] = mes.split('-').map(Number);
    const fecha = new Date(anio, numero - 1, 1);

    return fecha.toLocaleDateString('es-MX', { month: 'long', year: 'numeric' });
};

export default function Agenda({
    citas,
    mes,
    hoy,
    bariatras,
    pacientes,
    filtros,
    whatsapp,
}: AgendaProps) {
    const pageProps = usePage().props as unknown as PageProps;
    const roles: string[] = pageProps.auth.user.roles ?? [];
    const esAdmin = roles.includes('admin');
    const esSoloBariatra =
        roles.includes('bariatra') &&
        !roles.includes('admin') &&
        !roles.includes('recepcionista');

    const [diaSeleccionado, setDiaSeleccionado] = useState(hoy);
    const [modalAbierto, setModalAbierto] = useState(false);
    const [citaEditando, setCitaEditando] = useState<Cita | null>(null);

    const form = useForm({
        user_id: '' as string | number,
        bariatra_id: (bariatras[0]?.id ?? '') as string | number,
        tipo: 'seguimiento',
        inicia_en: '',
        termina_en: '',
        estado: 'pendiente' as Estado,
        notas: '',
    });

    const { data, setData, errors, processing, clearErrors } = form;

    const dias = useMemo(() => {
        const [anio, numero] = mes.split('-').map(Number);
        const primero = new Date(anio, numero - 1, 1);
        const ultimo = new Date(anio, numero, 0);
        const huecos = (primero.getDay() + 6) % 7;

        const celdas: (string | null)[] = Array.from({ length: huecos }, () => null);

        for (let dia = 1; dia <= ultimo.getDate(); dia++) {
            celdas.push(
                `${anio}-${String(numero).padStart(2, '0')}-${String(dia).padStart(2, '0')}`,
            );
        }

        while (celdas.length % 7 !== 0) {
            celdas.push(null);
        }

        return celdas;
    }, [mes]);

    const citasPorFecha = useMemo(() => {
        const mapa: Record<string, Cita[]> = {};

        for (const cita of citas) {
            (mapa[cita.fecha] ??= []).push(cita);
        }

        return mapa;
    }, [citas]);

    const citasDelDia = citasPorFecha[diaSeleccionado] ?? [];

    const irAMes = (delta: number) => {
        const [anio, numero] = mes.split('-').map(Number);
        const destino = new Date(anio, numero - 1 + delta, 1);
        const nuevoMes = `${destino.getFullYear()}-${String(destino.getMonth() + 1).padStart(2, '0')}`;

        router.get(
            route('agenda.index'),
            {
                mes: nuevoMes,
                bariatra_id: filtros.bariatra_id ?? undefined,
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    const filtrarPorBariatra = (valor: string) => {
        router.get(
            route('agenda.index'),
            { mes, bariatra_id: valor || undefined },
            { preserveScroll: true, preserveState: true },
        );
    };

    const abrirNueva = (fecha: string) => {
        setCitaEditando(null);
        form.setData({
            user_id: '',
            bariatra_id: bariatras[0]?.id ?? '',
            tipo: 'seguimiento',
            inicia_en: `${fecha}T09:00`,
            termina_en: `${fecha}T10:00`,
            estado: 'pendiente',
            notas: '',
        });
        clearErrors();
        setModalAbierto(true);
    };

    const abrirEditar = (cita: Cita) => {
        setCitaEditando(cita);
        form.setData({
            user_id: cita.paciente?.id ?? '',
            bariatra_id: cita.bariatra.id,
            tipo: cita.tipo,
            inicia_en: cita.inicia_local,
            termina_en: cita.termina_local,
            estado: cita.estado,
            notas: cita.notas ?? '',
        });
        clearErrors();
        setModalAbierto(true);
    };

    const guardar = (evento: FormEvent) => {
        evento.preventDefault();

        const opciones = {
            preserveScroll: true,
            onSuccess: () => setModalAbierto(false),
        };

        if (citaEditando) {
            form.patch(route('agenda.update', citaEditando.id), opciones);
        } else {
            form.post(route('agenda.store'), opciones);
        }
    };

    const cancelar = (cita: Cita) => {
        const nombre = cita.paciente?.nombre ?? 'este bloqueo de horario';

        if (
            !window.confirm(
                `¿Cancelar la cita de ${nombre}? Se conserva en el historial del paciente.`,
            )
        ) {
            return;
        }

        router.post(route('agenda.cancelar', cita.id), {}, { preserveScroll: true });
    };

    const eliminar = (cita: Cita) => {
        const nombre = cita.paciente?.nombre ?? 'este bloqueo de horario';

        if (!window.confirm(`¿Eliminar definitivamente la cita de ${nombre}?`)) {
            return;
        }

        router.delete(route('agenda.destroy', cita.id), { preserveScroll: true });
    };

    const enviarRecordatorio = (cita: Cita) => {
        router.post(route('agenda.recordatorio', cita.id), {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            Agenda del consultorio
                        </h2>
                        <p className="text-sm text-gray-500">
                            {citas.length} {citas.length === 1 ? 'cita' : 'citas'} en {nombreMes(mes)}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {!esSoloBariatra && bariatras.length > 1 && (
                            <select
                                value={filtros.bariatra_id ?? ''}
                                onChange={(e) => filtrarPorBariatra(e.target.value)}
                                className="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            >
                                <option value="">Todos los doctores</option>
                                {bariatras.map((bariatra) => (
                                    <option key={bariatra.id} value={bariatra.id}>
                                        {bariatra.name}
                                    </option>
                                ))}
                            </select>
                        )}

                        <button
                            type="button"
                            onClick={() => abrirNueva(diaSeleccionado)}
                            className="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                        >
                            + Nueva cita
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="Agenda" />

            <div className="py-8">
                <div className="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
                    <div className="lg:col-span-2">
                        <div className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                                <button
                                    type="button"
                                    onClick={() => irAMes(-1)}
                                    aria-label="Mes anterior"
                                    className="rounded-md px-3 py-1 text-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
                                >
                                    ‹
                                </button>
                                <div className="flex items-center gap-3">
                                    <span className="text-base font-semibold first-letter:uppercase text-gray-800 dark:text-gray-100">
                                        {nombreMes(mes)}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.get(
                                                route('agenda.index'),
                                                {},
                                                { preserveScroll: true, preserveState: true },
                                            )
                                        }
                                        className="rounded border border-gray-300 px-2 py-0.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        Hoy
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => irAMes(1)}
                                    aria-label="Mes siguiente"
                                    className="rounded-md px-3 py-1 text-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
                                >
                                    ›
                                </button>
                            </div>

                            <div className="grid grid-cols-7 border-b border-gray-200 bg-gray-50 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                {DIAS.map((dia) => (
                                    <div key={dia} className="px-1 py-2">
                                        {dia}
                                    </div>
                                ))}
                            </div>

                            <div className="grid grid-cols-7">
                                {dias.map((dia, indice) => {
                                    if (dia === null) {
                                        return (
                                            <div
                                                key={`vacio-${indice}`}
                                                className="min-h-24 border-b border-r border-gray-100 bg-gray-50/60 dark:border-gray-700 dark:bg-gray-900/20"
                                            />
                                        );
                                    }

                                    const delDia = citasPorFecha[dia] ?? [];
                                    const esHoy = dia === hoy;
                                    const seleccionado = dia === diaSeleccionado;

                                    return (
                                        <button
                                            type="button"
                                            key={dia}
                                            onClick={() => setDiaSeleccionado(dia)}
                                            className={`min-h-24 border-b border-r border-gray-100 p-1 text-left align-top transition dark:border-gray-700 ${
                                                seleccionado
                                                    ? 'bg-blue-50 dark:bg-blue-900/20'
                                                    : 'hover:bg-gray-50 dark:hover:bg-gray-700/40'
                                            }`}
                                        >
                                            <span
                                                className={`inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold ${
                                                    esHoy
                                                        ? 'bg-blue-600 text-white'
                                                        : 'text-gray-700 dark:text-gray-300'
                                                }`}
                                            >
                                                {Number(dia.slice(-2))}
                                            </span>

                                            <span className="mt-1 block space-y-0.5">
                                                {delDia.slice(0, 3).map((cita) => (
                                                    <span
                                                        key={cita.id}
                                                        className={`block truncate rounded border px-1 py-0.5 text-[10px] font-medium ${ESTILO_ESTADO[cita.estado]}`}
                                                    >
                                                        {cita.inicia} {cita.paciente?.nombre ?? 'Bloqueo'}
                                                    </span>
                                                ))}
                                                {delDia.length > 3 && (
                                                    <span className="block px-1 text-[10px] text-gray-400">
                                                        +{delDia.length - 3} más
                                                    </span>
                                                )}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    </div>

                    <div>
                        <div className="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                                <h3 className="text-sm font-semibold first-letter:uppercase text-gray-800 dark:text-gray-100">
                                    {new Date(`${diaSeleccionado}T12:00:00`).toLocaleDateString(
                                        'es-MX',
                                        {
                                            weekday: 'long',
                                            day: 'numeric',
                                            month: 'long',
                                        },
                                    )}
                                </h3>
                                <button
                                    type="button"
                                    onClick={() => abrirNueva(diaSeleccionado)}
                                    className="text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    Agendar
                                </button>
                            </div>

                            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                                {citasDelDia.length === 0 && (
                                    <p className="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No hay citas este día.
                                    </p>
                                )}

                                {citasDelDia.map((cita) => (
                                    <div key={cita.id} className="space-y-2 px-4 py-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                    {cita.inicia}–{cita.termina} ·{' '}
                                                    {cita.paciente?.nombre ?? 'Horario bloqueado'}
                                                </p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                    {textoTipo(cita.tipo)} · {cita.bariatra.nombre}
                                                </p>
                                            </div>
                                            <span
                                                className={`shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold ${ESTILO_ESTADO[cita.estado]}`}
                                            >
                                                {textoEstado(cita.estado)}
                                            </span>
                                        </div>

                                        {cita.notas && (
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {cita.notas}
                                            </p>
                                        )}

                                        <p className="text-xs">
                                            <span className="tabular font-medium text-ink">
                                                {moneda(cita.total)}
                                            </span>{' '}
                                            <span
                                                className={
                                                    cita.pagado
                                                        ? 'text-pine-700'
                                                        : 'text-arcilla-700'
                                                }
                                            >
                                                · {cita.pagado ? 'Pagado' : 'Pendiente'}
                                            </span>
                                        </p>

                                        <div className="flex flex-wrap items-center gap-2 text-xs">
                                            <Link
                                                href={route('agenda.cobro', cita.id)}
                                                className="rounded-sm bg-pine-700 px-2 py-1 font-semibold text-white transition hover:bg-pine-800"
                                            >
                                                Cobro
                                            </Link>

                                            {cita.whatsapp_url && (
                                                <a
                                                    href={cita.whatsapp_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="rounded-md bg-emerald-600 px-2 py-1 font-semibold text-white transition hover:bg-emerald-700"
                                                >
                                                    WhatsApp
                                                </a>
                                            )}

                                            {whatsapp === 'cloud' && cita.paciente && (
                                                <button
                                                    type="button"
                                                    onClick={() => enviarRecordatorio(cita)}
                                                    className="rounded-md border border-emerald-600 px-2 py-1 font-semibold text-emerald-700 transition hover:bg-emerald-50 dark:text-emerald-400"
                                                >
                                                    {cita.recordatorio_enviado
                                                        ? 'Reenviar recordatorio'
                                                        : 'Enviar recordatorio'}
                                                </button>
                                            )}

                                            <button
                                                type="button"
                                                onClick={() => abrirEditar(cita)}
                                                className="rounded-md border border-gray-300 px-2 py-1 font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                            >
                                                Editar
                                            </button>

                                            {cita.estado !== 'cancelada' && (
                                                <button
                                                    type="button"
                                                    onClick={() => cancelar(cita)}
                                                    className="rounded-md border border-amber-300 px-2 py-1 font-medium text-amber-700 transition hover:bg-amber-50 dark:border-amber-700 dark:text-amber-400"
                                                >
                                                    Cancelar
                                                </button>
                                            )}

                                            {esAdmin && (
                                                <button
                                                    type="button"
                                                    onClick={() => eliminar(cita)}
                                                    className="rounded-md border border-red-200 px-2 py-1 font-medium text-red-600 transition hover:bg-red-50 dark:border-red-800 dark:text-red-400"
                                                >
                                                    Eliminar
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {whatsapp === 'log' && (
                            <p className="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                                WhatsApp está en modo desarrollo: los mensajes sólo se escriben en
                                el log. El botón verde abre WhatsApp con el texto listo para enviar.
                            </p>
                        )}
                    </div>
                </div>
            </div>

            {modalAbierto && (
                <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {citaEditando ? 'Editar cita' : 'Nueva cita'}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setModalAbierto(false)}
                                aria-label="Cerrar"
                                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={guardar} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Paciente
                                </label>
                                <select
                                    value={data.user_id}
                                    onChange={(e) =>
                                        setData('user_id', e.target.value ? Number(e.target.value) : '')
                                    }
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                >
                                    <option value="">— Bloquear horario —</option>
                                    {pacientes.map((paciente) => (
                                        <option key={paciente.id} value={paciente.id}>
                                            {paciente.name}{' '}
                                            {paciente.last_name ? paciente.last_name : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.user_id && (
                                    <p className="mt-1 text-xs text-red-500">{errors.user_id}</p>
                                )}
                            </div>

                            {!esSoloBariatra && (
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Doctor
                                    </label>
                                    <select
                                        value={data.bariatra_id}
                                        onChange={(e) =>
                                            setData('bariatra_id', Number(e.target.value))
                                        }
                                        className="w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    >
                                        {bariatras.map((bariatra) => (
                                            <option key={bariatra.id} value={bariatra.id}>
                                                {bariatra.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.bariatra_id && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {errors.bariatra_id}
                                        </p>
                                    )}
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Inicio
                                    </label>
                                    <input
                                        type="datetime-local"
                                        value={data.inicia_en}
                                        onChange={(e) => setData('inicia_en', e.target.value)}
                                        className="w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    />
                                    {errors.inicia_en && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {errors.inicia_en}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Fin
                                    </label>
                                    <input
                                        type="datetime-local"
                                        value={data.termina_en}
                                        onChange={(e) => setData('termina_en', e.target.value)}
                                        className="w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    />
                                    {errors.termina_en && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {errors.termina_en}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Tipo
                                    </label>
                                    <select
                                        value={data.tipo}
                                        onChange={(e) => setData('tipo', e.target.value)}
                                        className="w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    >
                                        {TIPOS.map((tipo) => (
                                            <option key={tipo.valor} value={tipo.valor}>
                                                {tipo.texto}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Estado
                                    </label>
                                    <select
                                        value={data.estado}
                                        onChange={(e) =>
                                            setData('estado', e.target.value as Estado)
                                        }
                                        className="w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    >
                                        {ESTADOS.map((estado) => (
                                            <option key={estado.valor} value={estado.valor}>
                                                {estado.texto}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Notas
                                </label>
                                <textarea
                                    value={data.notas}
                                    onChange={(e) => setData('notas', e.target.value)}
                                    rows={2}
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                />
                                {errors.notas && (
                                    <p className="mt-1 text-xs text-red-500">{errors.notas}</p>
                                )}
                            </div>

                            <div className="flex justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                                <button
                                    type="button"
                                    onClick={() => setModalAbierto(false)}
                                    className="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50"
                                >
                                    {processing ? 'Guardando…' : 'Guardar'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
