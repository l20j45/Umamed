import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { moneda, nombreMes } from '@/lib/formato';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Gasto {
    id: number;
    user_id: number;
    bariatra: string | null;
    concepto: string;
    categoria: string;
    categoria_legible: string;
    monto: number;
    fecha: string | null;
    notas: string | null;
}

interface GastosProps {
    mes: string;
    gastos: Gasto[];
    total: number;
    categorias: string[];
    bariatras: { id: number; name: string }[];
    filtros: { bariatra_id: number | null };
    esSoloDoctor: boolean;
}

const CATEGORIAS: Record<string, string> = {
    insumos: 'Insumos',
    renta: 'Renta',
    servicios: 'Servicios',
    publicidad: 'Publicidad',
    personal: 'Personal',
    otro: 'Otro',
};

export default function Gastos({
    mes,
    gastos,
    total,
    categorias,
    bariatras,
    filtros,
    esSoloDoctor,
}: GastosProps) {
    const form = useForm({
        user_id: '',
        concepto: '',
        categoria: categorias[0] ?? 'otro',
        monto: '',
        fecha: new Date().toISOString().slice(0, 10),
        notas: '',
    });

    const cambiarFiltros = (cambios: { mes?: string; bariatra_id?: number | null }) => {
        router.get(
            route('gastos.index'),
            {
                mes: cambios.mes ?? mes,
                bariatra_id:
                    cambios.bariatra_id === undefined
                        ? (filtros.bariatra_id ?? undefined)
                        : (cambios.bariatra_id ?? undefined),
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    const enviar = (evento: FormEvent) => {
        evento.preventDefault();
        form.post(route('gastos.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2>Gastos</h2>
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
                    </div>
                </div>
            }
        >
            <Head title="Gastos" />

            <div className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                <div className="card overflow-hidden">
                    <div className="border-b border-sand px-5 py-4">
                        <h3 className="text-sm font-semibold text-ink">Detalle de gastos</h3>
                    </div>

                    {gastos.length === 0 ? (
                        <p className="px-5 py-12 text-center text-sm text-gray-500">
                            No hay gastos en este mes.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-sand">
                                <thead className="bg-gray-50">
                                    <tr>
                                        {['Fecha', 'Concepto', 'Categoría', 'Monto', 'Doctor'].map(
                                            (encabezado) => (
                                                <th
                                                    key={encabezado}
                                                    className="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.12em] text-gray-500"
                                                >
                                                    {encabezado}
                                                </th>
                                            ),
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-sand">
                                    {gastos.map((gasto) => (
                                        <tr key={gasto.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 text-sm text-gray-600">
                                                {gasto.fecha ?? '-'}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-ink">
                                                {gasto.concepto}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-600">
                                                {gasto.categoria_legible}
                                            </td>
                                            <td className="px-4 py-3 text-sm font-medium tabular text-ink">
                                                {moneda(gasto.monto)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-600">
                                                {gasto.bariatra}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div className="card p-5">
                    <p className="eyebrow">Total del mes</p>
                    <p className="mt-2 font-display text-[30px] leading-none tabular text-ink">
                        {moneda(total)}
                    </p>

                    <form onSubmit={enviar} className="mt-5 space-y-3">
                        <div>
                            <label className="label">Doctor</label>
                            <select
                                value={form.data.user_id}
                                onChange={(e) => form.setData('user_id', e.target.value)}
                                className="field"
                            >
                                <option value="">Selecciona un doctor</option>
                                {bariatras.map((doctor) => (
                                    <option key={doctor.id} value={doctor.id}>
                                        {doctor.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="label">Concepto</label>
                            <input
                                type="text"
                                value={form.data.concepto}
                                onChange={(e) => form.setData('concepto', e.target.value)}
                                className="field"
                            />
                        </div>

                        <div>
                            <label className="label">Categoría</label>
                            <select
                                value={form.data.categoria}
                                onChange={(e) => form.setData('categoria', e.target.value)}
                                className="field"
                            >
                                {categorias.map((categoria) => (
                                    <option key={categoria} value={categoria}>
                                        {CATEGORIAS[categoria] ?? categoria}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label className="label">Monto</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={form.data.monto}
                                    onChange={(e) => form.setData('monto', e.target.value)}
                                    className="field"
                                />
                            </div>
                            <div>
                                <label className="label">Fecha</label>
                                <input
                                    type="date"
                                    value={form.data.fecha}
                                    onChange={(e) => form.setData('fecha', e.target.value)}
                                    className="field"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="label">Notas</label>
                            <input
                                type="text"
                                value={form.data.notas}
                                onChange={(e) => form.setData('notas', e.target.value)}
                                className="field"
                                placeholder="Opcional"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="btn-primary w-full"
                        >
                            Registrar gasto
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
