import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { moneda } from '@/lib/formato';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Cargo {
    id: number;
    nombre: string;
    tipo: string;
    precio_unitario: number;
    cantidad: number;
    subtotal: number;
}

interface Concepto {
    id: number;
    nombre: string;
    tipo: string;
    precio: number;
}

interface Abono {
    id: number;
    monto: number;
    metodo_pago: string;
    pagado_en: string | null;
    nota: string | null;
}

interface CitaCobro {
    id: number;
    fecha: string;
    hora: string;
    estado: string;
    paciente: { id: number; nombre: string; telefono: string | null } | null;
    bariatra: string | null;
    total: number;
    pagado: number;
    saldo: number;
    estado_pago: 'pendiente' | 'parcial' | 'pagado';
    metodo_pago?: string | null;
    pagado_en?: string | null;
    abonos: Abono[];
}

interface CobroProps {
    cita: CitaCobro;
    cargos: Cargo[];
    abonos: Abono[];
    catalogo: Concepto[];
    metodosPago: string[];
}

const METODOS: Record<string, string> = {
    efectivo: 'Efectivo',
    transferencia: 'Transferencia',
    tarjeta: 'Tarjeta',
    otro: 'Otro',
};

const ESTADO_LABEL: Record<string, string> = {
    pendiente: 'Pendiente',
    parcial: 'Pago parcial',
    pagado: 'Pagado',
};

export default function Cobro({ cita, cargos, abonos, catalogo, metodosPago }: CobroProps) {
    const [metodo, setMetodo] = useState(cita.metodo_pago ?? 'efectivo');

    const form = useForm({
        catalog_item_id: (catalogo[0]?.id ?? '') as string | number,
        cantidad: 1,
    });

    const pagoForm = useForm({
        monto: Number((cita.saldo ?? 0).toFixed(2)),
        metodo_pago: cita.metodo_pago ?? 'efectivo',
        pagado_en: '',
        nota: '',
    });

    const { data, setData, errors, processing, reset } = form;
    const {
        data: pagoData,
        setData: setPagoData,
        errors: pagoErrors,
        processing: pagoProcessing,
        reset: resetPago,
    } = pagoForm;

    const agregar = (evento: FormEvent) => {
        evento.preventDefault();

        form.post(route('agenda.cobro.store', cita.id), {
            preserveScroll: true,
            onSuccess: () => {
                setData('cantidad', 1);
                reset('catalog_item_id');
            },
        });
    };

    const cambiarCantidad = (cargo: Cargo, cantidad: number) => {
        if (cantidad < 1 || cantidad > 99) {
            return;
        }

        router.patch(
            route('agenda.cobro.update', { cita: cita.id, cargo: cargo.id }),
            { cantidad },
            { preserveScroll: true },
        );
    };

    const quitar = (cargo: Cargo) => {
        if (!window.confirm(`¿Quitar "${cargo.nombre}" del cobro?`)) {
            return;
        }

        router.delete(route('agenda.cobro.destroy', { cita: cita.id, cargo: cargo.id }), {
            preserveScroll: true,
        });
    };

    const registrarAbono = (evento: FormEvent) => {
        evento.preventDefault();

        pagoForm.post(route('agenda.pagos.store', cita.id), {
            preserveScroll: true,
            onSuccess: () => {
                resetPago();
                setMetodo(pagoData.metodo_pago || 'efectivo');
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2>Cobro de la cita</h2>
                        <p>
                            {cita.paciente?.nombre ?? 'Horario bloqueado'} · {cita.fecha} ·{' '}
                            {cita.hora} · {cita.bariatra}
                        </p>
                    </div>

                    <Link href={route('agenda.index')} className="btn-secondary w-fit">
                        Volver a la agenda
                    </Link>
                </div>
            }
        >
            <Head title="Cobro de la cita" />

            <div className="grid gap-6 lg:grid-cols-[1.6fr_1fr]">
                <div className="space-y-6">
                    <div className="card overflow-hidden">
                        <div className="flex items-center justify-between border-b border-sand px-5 py-4">
                            <h3 className="text-sm font-semibold text-ink">
                                Conceptos de esta cita
                            </h3>
                            <span className="text-xs text-gray-500">
                                {cargos.length} {cargos.length === 1 ? 'concepto' : 'conceptos'}
                            </span>
                        </div>

                        {cargos.length === 0 ? (
                            <p className="px-5 py-10 text-center text-sm text-gray-500">
                                Aún no hay conceptos. Agrega lo que se llevó el paciente.
                            </p>
                        ) : (
                            <table className="min-w-full divide-y divide-sand">
                                <thead className="bg-gray-50">
                                    <tr>
                                        {['Concepto', 'Precio', 'Cantidad', 'Subtotal', ''].map(
                                            (encabezado) => (
                                                <th
                                                    key={encabezado}
                                                    className="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.12em] text-gray-500"
                                                >
                                                    {encabezado}
                                                </th>
                                            ),
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-sand">
                                    {cargos.map((cargo) => (
                                        <tr key={cargo.id}>
                                            <td className="px-4 py-3 text-sm text-ink">
                                                {cargo.nombre}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm tabular text-gray-600">
                                                {moneda(cargo.precio_unitario)}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3">
                                                <div className="inline-flex items-center border border-sand">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            cambiarCantidad(cargo, cargo.cantidad - 1)
                                                        }
                                                        aria-label="Quitar uno"
                                                        className="px-2 py-0.5 text-gray-500 hover:bg-gray-50"
                                                    >
                                                        −
                                                    </button>
                                                    <span className="w-8 text-center text-sm tabular">
                                                        {cargo.cantidad}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            cambiarCantidad(cargo, cargo.cantidad + 1)
                                                        }
                                                        aria-label="Agregar uno"
                                                        className="px-2 py-0.5 text-gray-500 hover:bg-gray-50"
                                                    >
                                                        +
                                                    </button>
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm font-medium tabular text-ink">
                                                {moneda(cargo.subtotal)}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => quitar(cargo)}
                                                    className="text-xs font-medium text-arcilla-700 hover:underline"
                                                >
                                                    Quitar
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}

                        <form
                            onSubmit={agregar}
                            className="flex flex-wrap items-end gap-3 border-t border-sand bg-[#FCFAF6] px-5 py-4"
                        >
                            <div className="min-w-[220px] flex-1">
                                <label className="label">Agregar del catálogo</label>
                                <select
                                    value={data.catalog_item_id}
                                    onChange={(e) =>
                                        setData(
                                            'catalog_item_id',
                                            e.target.value ? Number(e.target.value) : '',
                                        )
                                    }
                                    className="field"
                                >
                                    <option value="">Selecciona un concepto…</option>
                                    {catalogo.map((concepto) => (
                                        <option key={concepto.id} value={concepto.id}>
                                            {concepto.nombre} · {moneda(concepto.precio)}
                                        </option>
                                    ))}
                                </select>
                                {errors.catalog_item_id && (
                                    <p className="mt-1 text-[12px] text-arcilla-600">
                                        {errors.catalog_item_id}
                                    </p>
                                )}
                                {catalogo.length === 0 && (
                                    <p className="mt-1 text-[12px] text-gray-500">
                                        Este doctor aún no tiene conceptos.{' '}
                                        <Link
                                            href={route('catalogo.index')}
                                            className="text-pine-700 underline"
                                        >
                                            Agregar al catálogo
                                        </Link>
                                    </p>
                                )}
                            </div>

                            <div className="w-24">
                                <label className="label">Cantidad</label>
                                <input
                                    type="number"
                                    min="1"
                                    max="99"
                                    value={data.cantidad}
                                    onChange={(e) => setData('cantidad', Number(e.target.value))}
                                    className="field"
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={processing || !data.catalog_item_id}
                                className="btn-primary"
                            >
                                Agregar
                            </button>
                        </form>
                    </div>

                    <div className="card overflow-hidden">
                        <div className="border-b border-sand px-5 py-4">
                            <h3 className="text-sm font-semibold text-ink">Abonos registrados</h3>
                        </div>

                        {abonos.length === 0 ? (
                            <p className="px-5 py-8 text-sm text-gray-500">
                                Todavía no hay abonos para esta cita.
                            </p>
                        ) : (
                            <div className="divide-y divide-sand">
                                {abonos.map((abono) => (
                                    <div key={abono.id} className="flex items-center justify-between px-5 py-3">
                                        <div>
                                            <p className="text-sm font-medium text-ink">
                                                {moneda(abono.monto)}
                                            </p>
                                            <p className="text-[11px] text-gray-500">
                                                {abono.pagado_en ?? 'Sin fecha'} ·{' '}
                                                {METODOS[abono.metodo_pago] ?? abono.metodo_pago}
                                            </p>
                                        </div>
                                        <span className="text-[11px] text-gray-500">
                                            {abono.nota || 'Sin nota'}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                <aside className="space-y-4">
                    <div className="card p-5">
                        <p className="eyebrow">Cobro total</p>
                        <p className="mt-2 font-display text-[34px] leading-none tabular text-ink">
                            {moneda(cita.total)}
                        </p>

                        <div className="mt-4 flex flex-wrap items-center gap-2">
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
                            <span className="text-[11px] text-gray-500">
                                {moneda(cita.pagado)} cobrado
                            </span>
                        </div>

                        <a
                            href={route('agenda.recibo', cita.id)}
                            className="btn-secondary mt-4 w-full text-center"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Descargar recibo PDF
                        </a>

                        <div className="mt-4 space-y-2 rounded-lg border border-dashed border-sand bg-[#FCFAF6] p-3">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-gray-500">Pagado:</span>
                                <span className="font-medium tabular text-ink">
                                    {moneda(cita.pagado)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-gray-500">Saldo:</span>
                                <span className="font-medium tabular text-ink">
                                    {moneda(cita.saldo)}
                                </span>
                            </div>
                        </div>

                        {cita.saldo > 0 && (
                            <form onSubmit={registrarAbono} className="mt-5 space-y-3">
                                <div>
                                    <label className="label">Monto del abono</label>
                                    <input
                                        type="number"
                                        min="0.01"
                                        max={cita.saldo}
                                        step="0.01"
                                        value={pagoData.monto}
                                        onChange={(e) =>
                                            setPagoData('monto', Number(e.target.value || 0))
                                        }
                                        className="field"
                                    />
                                    {pagoErrors.monto && (
                                        <p className="mt-1 text-[12px] text-arcilla-600">
                                            {pagoErrors.monto}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="label">Método de pago</label>
                                    <select
                                        value={pagoData.metodo_pago}
                                        onChange={(e) =>
                                            setPagoData('metodo_pago', e.target.value)
                                        }
                                        className="field"
                                    >
                                        {metodosPago.map((valor) => (
                                            <option key={valor} value={valor}>
                                                {METODOS[valor] ?? valor}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="label">Fecha</label>
                                    <input
                                        type="date"
                                        value={pagoData.pagado_en}
                                        onChange={(e) => setPagoData('pagado_en', e.target.value)}
                                        className="field"
                                    />
                                </div>

                                <div>
                                    <label className="label">Nota</label>
                                    <input
                                        type="text"
                                        value={pagoData.nota}
                                        onChange={(e) => setPagoData('nota', e.target.value)}
                                        className="field"
                                        placeholder="Opcional"
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={pagoProcessing || pagoData.monto <= 0}
                                    className="btn-primary w-full"
                                >
                                    Registrar abono
                                </button>
                            </form>
                        )}
                    </div>

                    <div className="card p-5">
                        <p className="eyebrow">Paciente</p>
                        <p className="mt-2 text-sm font-medium text-ink">
                            {cita.paciente?.nombre ?? 'Horario bloqueado'}
                        </p>
                        <p className="text-xs text-gray-500">
                            {cita.paciente?.telefono ?? 'Sin teléfono registrado'}
                        </p>
                        <p className="mt-3 text-xs text-gray-500">
                            Cita: {cita.fecha} · {cita.hora}
                        </p>
                    </div>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
