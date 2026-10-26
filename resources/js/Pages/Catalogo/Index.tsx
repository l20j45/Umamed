import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { moneda } from '@/lib/formato';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Concepto {
    id: number;
    user_id: number;
    bariatra: string | null;
    nombre: string;
    descripcion: string | null;
    tipo: string;
    tipo_legible: string;
    precio: number;
    activo: boolean;
}

interface CatalogoProps {
    items: Concepto[];
    bariatras: { id: number; name: string }[];
    tipos: string[];
    filtros: { bariatra_id: number | null };
    puedeEditar: boolean;
    esSoloDoctor: boolean;
}

const TIPO_LEGIBLE: Record<string, string> = {
    consulta: 'Consulta',
    producto: 'Producto',
    tratamiento: 'Tratamiento',
    otro: 'Otro',
};

const ESTILO_TIPO: Record<string, string> = {
    consulta: 'border-pine-200 bg-pine-50 text-pine-800',
    producto: 'border-sand bg-[#FCFAF6] text-gray-700',
    tratamiento: 'border-arcilla-200 bg-arcilla-50 text-arcilla-800',
    otro: 'border-sand bg-white text-gray-600',
};

export default function Catalogo({
    items,
    bariatras,
    tipos,
    filtros,
    puedeEditar,
    esSoloDoctor,
}: CatalogoProps) {
    const [abierto, setAbierto] = useState(false);
    const [editando, setEditando] = useState<Concepto | null>(null);

    const form = useForm({
        user_id: (bariatras[0]?.id ?? '') as string | number,
        nombre: '',
        descripcion: '',
        tipo: 'consulta',
        precio: '',
        activo: true as boolean,
    });

    const { data, setData, errors, processing, clearErrors, reset } = form;

    const abrirNuevo = () => {
        setEditando(null);
        reset();
        clearErrors();
        setAbierto(true);
    };

    const abrirEdicion = (concepto: Concepto) => {
        setEditando(concepto);
        form.setData({
            user_id: concepto.user_id,
            nombre: concepto.nombre,
            descripcion: concepto.descripcion ?? '',
            tipo: concepto.tipo,
            precio: String(concepto.precio),
            activo: concepto.activo,
        });
        clearErrors();
        setAbierto(true);
    };

    const guardar = (evento: FormEvent) => {
        evento.preventDefault();

        const opciones = {
            preserveScroll: true,
            onSuccess: () => {
                setAbierto(false);
                reset();
            },
        };

        if (editando) {
            form.patch(route('catalogo.update', editando.id), opciones);
        } else {
            form.post(route('catalogo.store'), opciones);
        }
    };

    const alternarActivo = (concepto: Concepto) => {
        router.patch(
            route('catalogo.update', concepto.id),
            {
                nombre: concepto.nombre,
                tipo: concepto.tipo,
                descripcion: concepto.descripcion,
                precio: concepto.precio,
                activo: !concepto.activo,
            },
            { preserveScroll: true },
        );
    };

    const eliminar = (concepto: Concepto) => {
        if (
            !window.confirm(
                `¿Eliminar "${concepto.nombre}" del catálogo? Las citas ya cobradas conservan su registro.`,
            )
        ) {
            return;
        }

        router.delete(route('catalogo.destroy', concepto.id), { preserveScroll: true });
    };

    const filtrar = (valor: string) => {
        router.get(
            route('catalogo.index'),
            { bariatra_id: valor || undefined },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2>Catálogo</h2>
                        <p>Servicios, tratamientos y productos que cobra cada doctor.</p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {!esSoloDoctor && bariatras.length > 1 && (
                            <select
                                value={filtros.bariatra_id ?? ''}
                                onChange={(e) => filtrar(e.target.value)}
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

                        {puedeEditar && (
                            <button type="button" onClick={abrirNuevo} className="btn-primary">
                                Nuevo concepto
                            </button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Catálogo" />

            <div className="card overflow-hidden">
                {items.length === 0 ? (
                    <p className="px-6 py-12 text-center text-sm text-gray-500">
                        Todavía no hay conceptos en el catálogo.
                        {puedeEditar && ' Agrega el primero con el botón de arriba.'}
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-sand">
                            <thead className="bg-gray-50">
                                <tr>
                                    {['Concepto', 'Tipo', 'Costo', 'Estado', ''].map(
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
                                {items.map((concepto) => (
                                    <tr key={concepto.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-ink">
                                                {concepto.nombre}
                                            </p>
                                            {concepto.descripcion && (
                                                <p className="mt-0.5 max-w-md text-xs text-gray-500">
                                                    {concepto.descripcion}
                                                </p>
                                            )}
                                            {!esSoloDoctor && concepto.bariatra && (
                                                <p className="mt-0.5 text-[11px] text-gray-400">
                                                    {concepto.bariatra}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`badge border ${ESTILO_TIPO[concepto.tipo] ?? ESTILO_TIPO.otro}`}
                                            >
                                                {TIPO_LEGIBLE[concepto.tipo] ?? concepto.tipo}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm tabular text-ink">
                                            {moneda(concepto.precio)}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <span
                                                className={`badge border ${
                                                    concepto.activo
                                                        ? 'border-pine-200 bg-pine-50 text-pine-800'
                                                        : 'border-sand bg-gray-100 text-gray-500'
                                                }`}
                                            >
                                                {concepto.activo ? 'Activo' : 'Inactivo'}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            {puedeEditar && (
                                                <div className="flex justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => abrirEdicion(concepto)}
                                                        className="text-xs font-medium text-pine-700 hover:underline"
                                                    >
                                                        Editar
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => alternarActivo(concepto)}
                                                        className="text-xs font-medium text-gray-500 hover:underline"
                                                    >
                                                        {concepto.activo ? 'Desactivar' : 'Activar'}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => eliminar(concepto)}
                                                        className="text-xs font-medium text-arcilla-700 hover:underline"
                                                    >
                                                        Eliminar
                                                    </button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {abierto && (
                <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-ink/50 p-4">
                    <div className="w-full max-w-lg border border-sand bg-white p-6">
                        <div className="mb-5 flex items-start justify-between">
                            <div>
                                <h3 className="font-display text-lg text-ink">
                                    {editando ? 'Editar concepto' : 'Nuevo concepto'}
                                </h3>
                                <p className="text-xs text-gray-500">
                                    {editando
                                        ? 'Los cambios aplican a las próximas citas.'
                                        : 'Se podrá cobrar en las citas de este doctor.'}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setAbierto(false)}
                                aria-label="Cerrar"
                                className="text-gray-400 hover:text-ink"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={guardar} className="space-y-4">
                            {!esSoloDoctor && !editando && (
                                <div>
                                    <label className="label">Doctor</label>
                                    <select
                                        value={data.user_id}
                                        onChange={(e) => setData('user_id', Number(e.target.value))}
                                        className="field"
                                    >
                                        {bariatras.map((doctor) => (
                                            <option key={doctor.id} value={doctor.id}>
                                                {doctor.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.user_id && (
                                        <p className="mt-1 text-[12px] text-arcilla-600">
                                            {errors.user_id}
                                        </p>
                                    )}
                                </div>
                            )}

                            <div>
                                <label className="label">Nombre</label>
                                <input
                                    type="text"
                                    value={data.nombre}
                                    onChange={(e) => setData('nombre', e.target.value)}
                                    placeholder="Consulta de primera vez"
                                    className="field"
                                />
                                {errors.nombre && (
                                    <p className="mt-1 text-[12px] text-arcilla-600">{errors.nombre}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="label">Tipo</label>
                                    <select
                                        value={data.tipo}
                                        onChange={(e) => setData('tipo', e.target.value)}
                                        className="field"
                                    >
                                        {tipos.map((tipo) => (
                                            <option key={tipo} value={tipo}>
                                                {TIPO_LEGIBLE[tipo] ?? tipo}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="label">Costo (MXN)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.precio}
                                        onChange={(e) => setData('precio', e.target.value)}
                                        placeholder="900.00"
                                        className="field"
                                    />
                                    {errors.precio && (
                                        <p className="mt-1 text-[12px] text-arcilla-600">
                                            {errors.precio}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <label className="label">Descripción</label>
                                <textarea
                                    rows={2}
                                    value={data.descripcion}
                                    onChange={(e) => setData('descripcion', e.target.value)}
                                    placeholder="Qué incluye (opcional)"
                                    className="field"
                                />
                            </div>

                            <label className="flex items-center gap-2 text-sm text-gray-600">
                                <input
                                    type="checkbox"
                                    checked={data.activo}
                                    onChange={(e) => setData('activo', e.target.checked)}
                                    className="rounded-sm border-sand text-pine-700 focus:ring-pine-600"
                                />
                                Disponible para cobrar
                            </label>

                            <div className="flex justify-end gap-2 border-t border-sand pt-4">
                                <button
                                    type="button"
                                    onClick={() => setAbierto(false)}
                                    className="btn-secondary"
                                >
                                    Cancelar
                                </button>
                                <button type="submit" disabled={processing} className="btn-primary">
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
