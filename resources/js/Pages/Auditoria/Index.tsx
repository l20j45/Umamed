import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Accion = 'created' | 'updated' | 'deleted';

interface Registro {
    id: number;
    accion: Accion;
    modelo: string;
    modelo_id: number | null;
    referencia: string;
    usuario: string | null;
    usuario_id: number | null;
    ip: string | null;
    cambios: Record<string, [unknown, unknown]> | null;
    fecha: string | null;
}

interface Enlace {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginador {
    data: Registro[];
    links: Enlace[];
    total: number;
    from: number | null;
    to: number | null;
}

interface Filtros {
    accion: string;
    modelo: string;
    usuario_id: number | null;
    desde: string;
    hasta: string;
}

interface ActividadProps {
    registros: Paginador;
    filtros: Filtros;
    usuarios: { id: number; name: string }[];
    acciones: Record<Accion, string>;
    modelos: string[];
    zona: string;
}

const ESTILO_ACCION: Record<Accion, string> = {
    created: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    updated: 'border-amber-200 bg-amber-50 text-amber-800',
    deleted: 'border-red-200 bg-red-50 text-red-800',
};

const NOMBRE_MODELO: Record<string, string> = {
    Appointment: 'Cita',
    MedidaPaciente: 'Medición',
    ProgressPhoto: 'Foto',
    User: 'Usuario',
};

const mostrarValor = (valor: unknown): string => {
    if (valor === null || valor === undefined || valor === '') {
        return '—';
    }

    if (typeof valor === 'boolean') {
        return valor ? 'sí' : 'no';
    }

    if (typeof valor === 'object') {
        return JSON.stringify(valor);
    }

    return String(valor);
};

export default function Actividad({
    registros,
    filtros,
    usuarios,
    acciones,
    modelos,
    zona,
}: ActividadProps) {
    const [filtro, setFiltro] = useState<Filtros>({
        accion: filtros.accion ?? '',
        modelo: filtros.modelo ?? '',
        usuario_id: filtros.usuario_id ?? null,
        desde: filtros.desde ?? '',
        hasta: filtros.hasta ?? '',
    });

    const aplicar = (evento: FormEvent) => {
        evento.preventDefault();

        router.get(
            route('auditoria.index'),
            {
                accion: filtro.accion || undefined,
                modelo: filtro.modelo || undefined,
                usuario_id: filtro.usuario_id ?? undefined,
                desde: filtro.desde || undefined,
                hasta: filtro.hasta || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const limpiar = () => {
        setFiltro({
            accion: '',
            modelo: '',
            usuario_id: null,
            desde: '',
            hasta: '',
        });
        router.get(route('auditoria.index'), {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Actividad del sistema
                    </h2>
                    <p className="text-sm text-gray-500">
                        Quién creó, cambió o eliminó registros clínicos · horario{' '}
                        {zona}
                    </p>
                </div>
            }
        >
            <Head title="Actividad" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={aplicar}
                        className="flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                Acción
                            </label>
                            <select
                                value={filtro.accion}
                                onChange={(e) =>
                                    setFiltro({ ...filtro, accion: e.target.value })
                                }
                                className="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            >
                                <option value="">Todas</option>
                                {Object.entries(acciones).map(([valor, texto]) => (
                                    <option key={valor} value={valor}>
                                        {texto}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                Tipo de registro
                            </label>
                            <select
                                value={filtro.modelo}
                                onChange={(e) =>
                                    setFiltro({ ...filtro, modelo: e.target.value })
                                }
                                className="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            >
                                <option value="">Todos</option>
                                {modelos.map((modelo) => (
                                    <option key={modelo} value={modelo}>
                                        {NOMBRE_MODELO[modelo] ?? modelo}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                Usuario
                            </label>
                            <select
                                value={filtro.usuario_id ?? ''}
                                onChange={(e) =>
                                    setFiltro({
                                        ...filtro,
                                        usuario_id: e.target.value
                                            ? Number(e.target.value)
                                            : null,
                                    })
                                }
                                className="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            >
                                <option value="">Todos</option>
                                {usuarios.map((usuario) => (
                                    <option key={usuario.id} value={usuario.id}>
                                        {usuario.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                Desde
                            </label>
                            <input
                                type="date"
                                value={filtro.desde}
                                onChange={(e) =>
                                    setFiltro({ ...filtro, desde: e.target.value })
                                }
                                className="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                Hasta
                            </label>
                            <input
                                type="date"
                                value={filtro.hasta}
                                onChange={(e) =>
                                    setFiltro({ ...filtro, hasta: e.target.value })
                                }
                                className="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            />
                        </div>

                        <button
                            type="submit"
                            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                        >
                            Filtrar
                        </button>

                        <button
                            type="button"
                            onClick={limpiar}
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            Limpiar
                        </button>
                    </form>

                    <div className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div className="flex items-center justify-between border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                            <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                {registros.total}{' '}
                                {registros.total === 1 ? 'registro' : 'registros'}
                            </h3>
                            <span className="text-xs text-gray-400">
                                {registros.from ?? 0}–{registros.to ?? 0} de{' '}
                                {registros.total}
                            </span>
                        </div>

                        {registros.data.length === 0 ? (
                            <p className="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                No hay movimientos con esos filtros.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                                        <tr>
                                            {[
                                                'Fecha',
                                                'Usuario',
                                                'Acción',
                                                'Registro',
                                                'Detalle',
                                            ].map((encabezado) => (
                                                <th
                                                    key={encabezado}
                                                    className="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                                >
                                                    {encabezado}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                        {registros.data.map((registro) => (
                                            <tr
                                                key={registro.id}
                                                className="align-top hover:bg-gray-50 dark:hover:bg-gray-700/30"
                                            >
                                                <td className="whitespace-nowrap px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                                                    {registro.fecha}
                                                    {registro.ip && (
                                                        <span className="block text-[10px] text-gray-400">
                                                            {registro.ip}
                                                        </span>
                                                    )}
                                                </td>

                                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                                                    {registro.usuario ?? (
                                                        <span className="text-xs text-gray-400">
                                                            sistema
                                                        </span>
                                                    )}
                                                </td>

                                                <td className="whitespace-nowrap px-4 py-3">
                                                    <span
                                                        className={`rounded-full border px-2 py-0.5 text-[11px] font-semibold ${ESTILO_ACCION[registro.accion]}`}
                                                    >
                                                        {acciones[registro.accion] ??
                                                            registro.accion}
                                                    </span>
                                                </td>

                                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                                                    <span className="block text-xs font-semibold uppercase tracking-wide text-gray-400">
                                                        {NOMBRE_MODELO[registro.modelo] ??
                                                            registro.modelo}
                                                    </span>
                                                    {registro.referencia}
                                                </td>

                                                <td className="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                                                    {registro.cambios ? (
                                                        <details>
                                                            <summary className="cursor-pointer text-blue-600 dark:text-blue-400">
                                                                {Object.keys(
                                                                    registro.cambios,
                                                                ).length}{' '}
                                                                campo(s)
                                                            </summary>
                                                            <ul className="mt-1 space-y-0.5">
                                                                {Object.entries(
                                                                    registro.cambios,
                                                                ).map(
                                                                    ([campo, valores]) => (
                                                                        <li key={campo}>
                                                                            <span className="font-medium text-gray-700 dark:text-gray-200">
                                                                                {campo}
                                                                            </span>
                                                                            {': '}
                                                                            {mostrarValor(valores[0])}
                                                                            {' → '}
                                                                            <span className="font-semibold">
                                                                                {mostrarValor(
                                                                                    valores[1],
                                                                                )}
                                                                            </span>
                                                                        </li>
                                                                    ),
                                                                )}
                                                            </ul>
                                                        </details>
                                                    ) : (
                                                        <span className="text-gray-400">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {registros.links.length > 3 && (
                            <div className="flex flex-wrap items-center gap-1 border-t border-gray-100 px-5 py-3 dark:border-gray-700">
                                {registros.links.map((enlace, indice) =>
                                    enlace.url ? (
                                        <Link
                                            key={indice}
                                            href={enlace.url}
                                            preserveScroll
                                            className={`rounded border px-2 py-1 text-xs ${
                                                enlace.active
                                                    ? 'border-blue-600 bg-blue-600 font-semibold text-white'
                                                    : 'border-gray-300 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700'
                                            }`}
                                            dangerouslySetInnerHTML={{
                                                __html: enlace.label,
                                            }}
                                        />
                                    ) : (
                                        <span
                                            key={indice}
                                            className="px-2 py-1 text-xs text-gray-400"
                                            dangerouslySetInnerHTML={{
                                                __html: enlace.label,
                                            }}
                                        />
                                    ),
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
