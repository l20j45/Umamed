import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { moneda, nombreMes } from '@/lib/formato';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface CitaCobrada {
    id: number;
    fecha: string;
    hora: string;
    paciente: string;
    bariatra: string | null;
    conceptos: number;
    total: number;
    pagado: number;
    saldo: number;
    estado_pago: 'pendiente' | 'parcial' | 'pagado';
    metodo_pago: string | null;
}

interface IngresosProps {
    mes: string;
    kpis: { facturado: number; cobrado: number; pendiente: number; citas: number };
    porTipo: { tipo: string; total: number }[];
    citas: CitaCobrada[];
    deudaPorPaciente: { paciente: string | null; total: number; citas: number }[];
    resumenTrimestral: { mes: string; facturado: number; cobrado: number; pendiente: number }[];
    bariatras: { id: number; name: string }[];
    filtros: { bariatra_id: number | null; estado_pago?: string; paciente?: string };
    esSoloDoctor: boolean;
}

const TIPO_LEGIBLE: Record<string, string> = {
    consulta: 'Consultas',
    producto: 'Productos',
    tratamiento: 'Tratamientos',
    otro: 'Otros',
};

const ESTADO_LABEL: Record<string, string> = {
    pendiente: 'Pendiente',
    parcial: 'Parcial',
    pagado: 'Pagado',
};

export default function Ingresos({
    mes,
    kpis,
    porTipo,
    citas,
    deudaPorPaciente,
    resumenTrimestral,
    bariatras,
    filtros,
    esSoloDoctor,
}: IngresosProps) {
    const [busquedaPaciente, setBusquedaPaciente] = useState(filtros.paciente ?? '');

    useEffect(() => {
        setBusquedaPaciente(filtros.paciente ?? '');
    }, [filtros.paciente]);

    const cambiarFiltros = (cambios: {
        mes?: string;
        bariatra_id?: number | null;
        estado_pago?: string | null;
        paciente?: string | null;
    }) => {
        router.get(
            route('ingresos.index'),
            {
                mes: cambios.mes ?? mes,
                bariatra_id:
                    cambios.bariatra_id === undefined
                        ? (filtros.bariatra_id ?? undefined)
                        : (cambios.bariatra_id ?? undefined),
                estado_pago:
                    cambios.estado_pago === undefined
                        ? (filtros.estado_pago ?? 'todos')
                        : (cambios.estado_pago ?? 'todos'),
                paciente:
                    cambios.paciente === undefined
                        ? (filtros.paciente ?? '')
                        : (cambios.paciente ?? ''),
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    useEffect(() => {
        const valor = busquedaPaciente.trim();
        const valorActual = (filtros.paciente ?? '').trim();

        if (valor === valorActual) {
            return undefined;
        }

        const timer = window.setTimeout(() => {
            cambiarFiltros({ paciente: valor });
        }, 400);

        return () => window.clearTimeout(timer);
    }, [busquedaPaciente, filtros.paciente]);

    const tarjetas = [
        { etiqueta: 'Facturado', valor: kpis.facturado, nota: 'Total de las citas del mes' },
        { etiqueta: 'Cobrado', valor: kpis.cobrado, nota: 'Abonos y pagos recibidos' },
        {
            etiqueta: 'Pendiente',
            valor: kpis.pendiente,
            nota: 'Saldo por cobrar',
            acento: kpis.pendiente > 0,
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2>Ingresos</h2>
                        <p className="first-letter:uppercase">{nombreMes(mes)}</p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            type="month"
                            value={mes}
                            onChange={(e) => cambiarFiltros({ mes: e.target.value })}
                            className="field w-auto py-1.5"
                        />

                        {!esSoloDoctor && bariatras.length > 1 && (
                            <select
                                value={filtros.bariatra_id ?? ''}
                                onChange={(e) =>
                                    cambiarFiltros({
                                        bariatra_id: e.target.value
                                            ? Number(e.target.value)
                                            : null,
                                    })
                                }
                                className="field w-auto py-1.5"
                            >
                                <option value="">Todos los doctores</option>
                                {bariatras.map((doctor) => (
                                    <option key={doctor.id} value={doctor.id}>
                                        {doctor.name}
                                    </option>
                                ))}
                            </select>
                        )}

                        <select
                            value={filtros.estado_pago ?? 'todos'}
                            onChange={(e) =>
                                cambiarFiltros({
                                    estado_pago: e.target.value || 'todos',
                                })
                            }
                            className="field w-auto py-1.5"
                        >
                            <option value="todos">Todos los estados</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="parcial">Parcial</option>
                            <option value="pagado">Pagado</option>
                        </select>

                        <input
                            type="search"
                            value={busquedaPaciente}
                            onChange={(e) => setBusquedaPaciente(e.target.value)}
                            placeholder="Paciente"
                            className="field w-auto py-1.5"
                        />

                        <a
                            href={route('ingresos.exportar', {
                                mes,
                                bariatra_id: filtros.bariatra_id ?? undefined,
                                estado_pago: filtros.estado_pago ?? undefined,
                                paciente: filtros.paciente || undefined,
                            })}
                            className="btn-secondary"
                        >
                            Exportar CSV
                        </a>
                    </div>
                </div>
            }
        >
            <Head title="Ingresos" />

            <div className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {tarjetas.map((tarjeta) => (
                        <div key={tarjeta.etiqueta} className="card p-5">
                            <p className="eyebrow">{tarjeta.etiqueta}</p>
                            <p
                                className={`mt-1.5 font-display text-[26px] leading-none tabular ${
                                    tarjeta.acento ? 'text-arcilla-700' : 'text-ink'
                                }`}
                            >
                                {moneda(tarjeta.valor)}
                            </p>
                            <p className="mt-2 text-[11px] text-gray-500">{tarjeta.nota}</p>
                        </div>
                    ))}

                    <div className="card p-5">
                        <p className="eyebrow">Citas con cobro</p>
                        <p className="mt-1.5 font-display text-[26px] leading-none tabular text-ink">
                            {kpis.citas}
                        </p>
                        <p className="mt-2 text-[11px] text-gray-500">
                            En {nombreMes(mes)}
                        </p>
                    </div>
                </div>

                {porTipo.length > 0 && (
                    <div className="card p-5">
                        <p className="eyebrow">Por tipo de concepto</p>
                        <div className="mt-3 flex flex-wrap gap-x-8 gap-y-3">
                            {porTipo.map((fila) => (
                                <div key={fila.tipo}>
                                    <p className="text-[11px] text-gray-500">
                                        {TIPO_LEGIBLE[fila.tipo] ?? fila.tipo}
                                    </p>
                                    <p className="text-sm font-medium tabular text-ink">
                                        {moneda(fila.total)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <div className="grid gap-6 xl:grid-cols-2">
                    <div className="card p-5">
                        <p className="eyebrow">Deuda por paciente</p>
                        {deudaPorPaciente.length === 0 ? (
                            <p className="mt-3 text-sm text-gray-500">
                                No hay saldo pendiente para los filtros actuales.
                            </p>
                        ) : (
                            <div className="mt-3 space-y-3">
                                {deudaPorPaciente.map((fila) => (
                                    <div
                                        key={`${fila.paciente ?? 'sin-paciente'}-${fila.total}`}
                                        className="flex items-center justify-between border-b border-sand pb-2 last:border-none last:pb-0"
                                    >
                                        <div>
                                            <p className="text-sm font-medium text-ink">
                                                {fila.paciente ?? 'Sin paciente'}
                                            </p>
                                            <p className="text-[11px] text-gray-500">
                                                {fila.citas} cita{fila.citas === 1 ? '' : 's'}
                                            </p>
                                        </div>
                                        <p className="text-sm font-semibold tabular text-arcilla-700">
                                            {moneda(fila.total)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="card p-5">
                        <p className="eyebrow">Reporte trimestral</p>
                        <div className="mt-3 space-y-3">
                            {resumenTrimestral.map((fila) => (
                                <div
                                    key={fila.mes}
                                    className="flex items-center justify-between rounded-md border border-sand px-3 py-2"
                                >
                                    <div>
                                        <p className="text-xs uppercase tracking-[0.12em] text-gray-500">
                                            {fila.mes}
                                        </p>
                                        <p className="text-sm font-medium text-ink">
                                            {moneda(fila.facturado)}
                                        </p>
                                    </div>
                                    <div className="text-right text-xs text-gray-500">
                                        <p>Cobrado: {moneda(fila.cobrado)}</p>
                                        <p>Pend: {moneda(fila.pendiente)}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="card overflow-hidden">
                    <div className="border-b border-sand px-5 py-4">
                        <h3 className="text-sm font-semibold text-ink">Detalle por cita</h3>
                    </div>

                    {citas.length === 0 ? (
                        <p className="px-5 py-12 text-center text-sm text-gray-500">
                            No hay citas con cobro en este mes.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-sand">
                                <thead className="bg-gray-50">
                                    <tr>
                                        {[
                                            'Fecha',
                                            'Paciente',
                                            'Doctor',
                                            'Conceptos',
                                            'Total',
                                            'Estado',
                                            '',
                                        ].map((encabezado) => (
                                            <th
                                                key={encabezado}
                                                className="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.12em] text-gray-500"
                                            >
                                                {encabezado}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-sand">
                                    {citas.map((cita) => (
                                        <tr key={cita.id} className="hover:bg-gray-50">
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                                {cita.fecha}
                                                <span className="block text-[11px] text-gray-400">
                                                    {cita.hora}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-sm text-ink">
                                                {cita.paciente}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-600">
                                                {cita.bariatra}
                                            </td>
                                            <td className="px-4 py-3 text-sm tabular text-gray-600">
                                                {cita.conceptos}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm font-medium tabular text-ink">
                                                {moneda(cita.total)}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3">
                                                <span
                                                    className={`badge border ${
                                                        cita.estado_pago === 'pagado'
                                                            ? 'border-pine-200 bg-pine-50 text-pine-800'
                                                            : cita.estado_pago === 'parcial'
                                                              ? 'border-amber-200 bg-amber-50 text-amber-800'
                                                              : 'border-arcilla-200 bg-arcilla-50 text-arcilla-800'
                                                    }`}
                                                >
                                                    {ESTADO_LABEL[cita.estado_pago] ?? 'Pendiente'}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-right">
                                                <Link
                                                    href={route('agenda.cobro', cita.id)}
                                                    className="text-xs font-medium text-pine-700 hover:underline"
                                                >
                                                    Ver cobro
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
