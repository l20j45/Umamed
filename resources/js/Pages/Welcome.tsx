import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import MapaUbicacion from '@/Components/Ui/MapaUbicacion'

export default function Welcome({
    auth,
    laravelVersion,
    phpVersion,
}: PageProps<{ laravelVersion: string; phpVersion: string }>) {
    const [nombre, setNombre] = useState('');
    const [telefono, setTelefono] = useState('');

    // Función para redirigir directamente al WhatsApp del especialista
    const handleWhatsAppRedirect = (e: FormEvent) => {
        e.preventDefault();
        const telefonoBariatra = '523312345678'; // 🛠️ Coloca aquí tu número de WhatsApp real
        const mensaje = `Hola, mi nombre es ${nombre}. Estoy interesado en agendar una valoración médica bariátrica. Mi teléfono de contacto es ${telefono}.`;
        const url = `https://wa.me/${telefonoBariatra}?text=${encodeURIComponent(mensaje)}`;
        window.open(url, '_blank');
    };

    return (
        <>
            <Head title="Bienvenido - Dr. Alejandro Rojas" />

            <div className="min-h-screen bg-gray-50 font-sans text-gray-800 antialiased dark:bg-zinc-950 dark:text-gray-200">
                {/* 1. NAV MINIMALISTA CON LINKS DE AUTENTICACIÓN DINÁMICOS */}
                <nav className="sticky top-0 z-50 border-b border-gray-100 bg-white/80 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                        <span className="text-lg font-black tracking-tight text-emerald-600 dark:text-emerald-400">
                            DRA. Jessi Mora{' '}
                            <span className="block text-xs font-medium text-gray-400 sm:ml-2 sm:inline">
                                Bariatría & Nutrición Clínica
                            </span>
                        </span>

                        {/* Links de Login / Register / Dashboard nativos conservados */}
                        <div className="flex items-center gap-2">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-lg bg-gray-900 px-4 py-2 text-xs font-bold text-white shadow transition hover:bg-gray-800 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="rounded-md px-3 py-2 text-xs font-bold text-gray-600 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                                    >
                                        Inicia Sesion
                                    </Link>
                                    {/* <Link
                                        href={route('register')}
                                        className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow transition hover:bg-emerald-700"
                                    >
                                        Register
                                    </Link> */}
                                </>
                            )}
                        </div>
                    </div>
                </nav>

                {/* 2. HERO SECTION */}
                <header className="mx-auto grid max-w-7xl grid-cols-1 items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-24">
                    <div className="space-y-6">
                        <span className="inline-block rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                            Control de Peso Médico y Seguro
                        </span>
                        <h1 className="text-4xl font-black tracking-tight text-gray-900 sm:text-5xl dark:text-white">
                            Recupera tu salud y alcanza tu{' '}
                            <span className="text-emerald-600 dark:text-emerald-400">
                                peso ideal
                            </span>{' '}
                            de forma integral.
                        </h1>
                        <p className="max-w-lg text-base leading-relaxed text-gray-500 dark:text-gray-400">
                            Tratamientos médicos personalizados orientados a la
                            pérdida de peso, cambios metabólicos y bienestar
                            sostenible. Sin soluciones mágicas, solo ciencia y
                            acompañamiento profesional.
                        </p>
                        <div className="flex flex-wrap gap-4 pt-2">
                            <a
                                href="#contacto"
                                className="rounded-lg bg-gray-900 px-5 py-3 text-sm font-bold text-white shadow transition hover:bg-gray-800 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                            >
                                Iniciar Valoración
                            </a>
                            <a
                                href="#servicios"
                                className="rounded-lg border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-zinc-800"
                            >
                                Ver Tratamientos
                            </a>
                        </div>
                    </div>

                    {/* Placeholder Corporativo */}
                    <div className="relative mx-auto w-full max-w-md lg:max-w-none">
                        <div className="flex aspect-[4/3] flex-col justify-between rounded-2xl border border-gray-200 bg-gradient-to-tr from-emerald-100 to-teal-50 p-8 shadow-sm dark:border-zinc-800 dark:from-zinc-900 dark:to-zinc-900">
                            <div className="space-y-2">
                                <span className="text-3xl">🩺</span>
                                <h4 className="text-lg font-bold text-gray-900 dark:text-white">
                                    Enfoque Clínico Moderno
                                </h4>
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    Monitoreo antropométrico digitalizado y
                                    planes nutricionales metabólicos avanzados.
                                </p>
                            </div>
                            <div className="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                                <div>
                                    <span className="block text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                        Pacientes Satisfechos
                                    </span>
                                    <span className="text-xl font-black text-emerald-600 dark:text-emerald-400">
                                        +500 Casos de Éxito
                                    </span>
                                </div>
                                <span className="text-xl">📈</span>
                            </div>
                        </div>
                    </div>
                </header>

                {/* 3. SECCIÓN DE SERVICIOS */}
                <section
                    id="servicios"
                    className="border-y border-gray-100 bg-white py-16 dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mx-auto mb-12 max-w-2xl text-center">
                            <h2 className="text-2xl font-extrabold tracking-tight text-gray-900 sm:text-3xl dark:text-white">
                                Tratamientos Especializados
                            </h2>
                            <p className="mt-2 text-sm text-gray-400">
                                Soluciones médicas a la medida de tu metabolismo
                                y estilo de vida.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="rounded-xl border border-gray-100 bg-gray-50 p-6 dark:border-zinc-800 dark:bg-zinc-950/50">
                                <span className="mb-3 block text-2xl">🥗</span>
                                <h3 className="mb-2 font-bold text-gray-900 dark:text-white">
                                    Control de Obesidad y Sobrepeso
                                </h3>
                                <p className="text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                    Diagnóstico oportuno de composición corporal
                                    y abordaje médico-nutricional para una
                                    pérdida de grasa efectiva.
                                </p>
                            </div>
                            <div className="rounded-xl border border-gray-100 bg-gray-50 p-6 dark:border-zinc-800 dark:bg-zinc-950/50">
                                <span className="mb-3 block text-2xl">🧬</span>
                                <h3 className="mb-2 font-bold text-gray-900 dark:text-white">
                                    Asesoría en Tratamientos con GLP-1
                                </h3>
                                <p className="text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                    Preparación preoperatoria rigurosa y
                                    seguimiento nutricional .
                                </p>
                            </div>
                            <div className="rounded-xl border border-gray-100 bg-gray-50 p-6 dark:border-zinc-800 dark:bg-zinc-950/50">
                                <span className="mb-3 block text-2xl">📊</span>
                                <h3 className="mb-2 font-bold text-gray-900 dark:text-white">
                                    Tratamiento de Síndrome Metabólico
                                </h3>
                                <p className="text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                    Control especializado de condiciones
                                    asociadas como resistencia a la insulina,
                                    hipertensión y dislipidemias.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* 4. FORMULARIO DE CONTACTO */}
                {/* 4. FORMULARIO DE CONTACTO + MAPA INTEGRADO */}
                <section
                    id="contacto"
                    className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8"
                >
                    <div className="grid grid-cols-1 items-start gap-8 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm md:p-10 lg:grid-cols-3 dark:border-zinc-800 dark:bg-zinc-900">
                        {/* Columna 1: Información Textual */}
                        <div className="space-y-4">
                            <h2 className="text-2xl font-black text-gray-900 dark:text-white">
                                Agenda tu consulta de valoración hoy
                            </h2>
                            <p className="text-xs leading-relaxed text-gray-400">
                                Llena el formato para coordinar tu cita por
                                WhatsApp o visítanos directamente en nuestras
                                instalaciones médicas.
                            </p>
                            <div className="space-y-3 border-t pt-4 text-xs text-gray-500 dark:border-zinc-800 dark:text-gray-400">
                                <p>
                                    📍 <strong>Ubicación:</strong> Calz del Federalismo Nte, Artesanos, 44200 Guadalajara, Jal.
                                </p>
                                <p>
                                    🕒 <strong>Horarios:</strong> Martes, Jueves y Sabado:
                                    9:00 AM - 7:00 PM
                                </p>
                            </div>
                        </div>

                        {/* Columna 2: Formulario */}
                        <form
                            onSubmit={handleWhatsAppRedirect}
                            className="space-y-4 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/50"
                        >
                            <div>
                                <label className="mb-1 block text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                    Nombre Completo
                                </label>
                                <input
                                    type="text"
                                    required
                                    placeholder="Ej. Juan Pérez"
                                    className="w-full rounded-lg border-gray-200 bg-white p-2.5 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    value={nombre}
                                    onChange={(e) => setNombre(e.target.value)}
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                    Número de Teléfono
                                </label>
                                <input
                                    type="tel"
                                    required
                                    placeholder="Ej. 3312345678"
                                    className="w-full rounded-lg border-gray-200 bg-white p-2.5 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    value={telefono}
                                    onChange={(e) =>
                                        setTelefono(e.target.value)
                                    }
                                />
                            </div>
                            <button
                                type="submit"
                                className="flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-3 text-sm font-bold text-white shadow transition hover:bg-emerald-700"
                            >
                                <span>💬</span> Contactar por WhatsApp
                            </button>
                        </form>

                        {/* Columna 3: El mapa de Google Maps adaptado */}
                        <div className="h-full min-h-[250px] w-full">
                            <label className="mb-2 block text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                Ubicación de la Clínica
                            </label>
                            <MapaUbicacion />
                        </div>
                    </div>
                </section>

                {/* 5. FOOTER CON VERSIONES DE ENTORNO CONSERVADAS */}
                <footer className="space-y-2 border-t border-gray-100 bg-white py-6 text-center text-xs text-gray-400 dark:border-zinc-800 dark:bg-zinc-900">
                    <p>
                        © {new Date().getFullYear()} l20j45 - Todos
                        los derechos reservados. Consultorio Clínico.
                    </p>
   
                </footer>
            </div>
        </>
    );
}
