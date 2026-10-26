import RegisterPatientModal from '@/Components/pacientes/RegisterPatientModal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface Patient {
    id: number;
    name: string;
    last_name?: string | null;
    email: string;
    phone?: string | null;
}

interface DashboardProps {

    patients: Patient[];
}

export default function Dashboard({  patients = [] }: DashboardProps) {
    // 2. Estado para el texto del buscador
    const [searchQuery, setSearchQuery] = useState('');
    // 2. Estado para abrir/cerrar el modal
    const [isModalOpen, setIsModalOpen] = useState(false);

    // 3. Filtrar pacientes en base al nombre, apellido o correo
    const filteredPatients = patients.filter((patient) => {
        const fullName =
            `${patient.name} ${patient.last_name || ''}`.toLowerCase();
        const email = patient.email.toLowerCase();
        const search = searchQuery.toLowerCase();

        return fullName.includes(search) || email.includes(search);
    });

    return (
        <AuthenticatedLayout

            header={
                <div>
                    <h2>Pacientes</h2>
                    <p>Expedientes del consultorio</p>
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
                        {/* Cabecera del Dashboard */}
                        <div className="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                                Mis Pacientes ({filteredPatients.length})
                            </h3>

                            <div className="flex w-full flex-col gap-3 sm:flex-row md:w-auto">
                                {/* Input del Buscador */}
                                <div className="relative w-full sm:w-64">
                                    <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <svg
                                            className="h-4 w-4 text-gray-500 dark:text-gray-400"
                                            aria-hidden="true"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                stroke="currentColor"
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth="2"
                                                d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"
                                            />
                                        </svg>
                                    </div>
                                    <input
                                        type="text"
                                        placeholder="Buscar por nombre o correo..."
                                        value={searchQuery}
                                        onChange={(e) =>
                                            setSearchQuery(e.target.value)
                                        }
                                        className="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2 pl-10 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:border-blue-500 dark:focus:ring-blue-500"
                                    />
                                    {searchQuery && (
                                        <button
                                            onClick={() => setSearchQuery('')}
                                            className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                        >
                                            ✕
                                        </button>
                                    )}
                                </div>

                                <button
                                    onClick={() => setIsModalOpen(true)}
                                    className="whitespace-nowrap rounded bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700"
                                >
                                    + Registrar Paciente
                                </button>
                            </div>
                        </div>

                        {/* Tabla de Pacientes */}
                        {filteredPatients.length === 0 ? (
                            <div className="py-8 text-center">
                                <p className="text-gray-500 dark:text-gray-400">
                                    {searchQuery
                                        ? `No se encontraron pacientes que coincidan con "${searchQuery}".`
                                        : 'No hay pacientes registrados aún.'}
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                                Nombre
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                                Correo
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                                Teléfono
                                            </th>
                                            <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                        {filteredPatients.map((patient) => (
                                            <tr
                                                key={patient.id}
                                                className="transition hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                            >
                                                <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {patient.name}{' '}
                                                    {patient.last_name || ''}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                    {patient.email}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                    {patient.phone || 'N/A'}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                                    <Link
                                                        href={route(
                                                            'patients.show',
                                                            { id: patient.id },
                                                        )}
                                                        className="font-semibold text-blue-600 hover:underline dark:text-blue-400"
                                                    >
                                                        Ver Expediente →
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                <RegisterPatientModal
                                    isOpen={isModalOpen}
                                    onClose={() => setIsModalOpen(false)}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
