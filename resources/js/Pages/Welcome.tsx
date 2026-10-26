import MapaUbicacion from '@/Components/Ui/MapaUbicacion';
import type { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const TRATAMIENTOS = [
    {
        titulo: 'Control de obesidad y sobrepeso',
        texto: 'Valoración de composición corporal y plan médico-nutricional para una pérdida de grasa sostenida, con mediciones objetivas en cada consulta.',
    },
    {
        titulo: 'Tratamientos con GLP-1',
        texto: 'Indicación, ajuste de dosis y seguimiento nutricional de agonistas del receptor GLP-1, con control de efectos adversos.',
    },
    {
        titulo: 'Síndrome metabólico',
        texto: 'Manejo de resistencia a la insulina, hipertensión y dislipidemias integrado al plan de reducción de peso.',
    },
];

export default function Welcome({ auth }: PageProps) {
    const [nombre, setNombre] = useState('');
    const [telefono, setTelefono] = useState('');

    const telefonoBariatra = '523312345678';

    const handleWhatsAppRedirect = (e: FormEvent) => {
        e.preventDefault();

        const mensaje = `Hola, mi nombre es ${nombre}. Quisiera agendar una valoración bariátrica. Mi teléfono de contacto es ${telefono}.`;
        const url = `https://wa.me/${telefonoBariatra}?text=${encodeURIComponent(mensaje)}`;

        window.open(url, '_blank');
    };

    return (
        <>
            <Head title="Dra. Jessi Mora · Bariatría y nutrición clínica" />

            <div className="min-h-screen bg-paper text-ink">
                <header className="border-b border-sand">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
                        <div>
                            <p className="font-display text-xl leading-none">
                                Umamed
                            </p>
                            <p className="mt-1.5 text-[10px] uppercase tracking-[0.18em] text-gray-500">
                                Dra. Jessi Mora · Bariatría
                            </p>
                        </div>

                        <nav className="flex items-center gap-6">
                            <a
                                href="#tratamientos"
                                className="hidden text-[13px] text-gray-600 hover:text-ink sm:block"
                            >
                                Tratamientos
                            </a>
                            <a
                                href="#contacto"
                                className="hidden text-[13px] text-gray-600 hover:text-ink sm:block"
                            >
                                Contacto
                            </a>

                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="btn-primary px-4 py-1.5 text-[13px]"
                                >
                                    Entrar al sistema
                                </Link>
                            ) : (
                                <Link
                                    href={route('login')}
                                    className="btn-secondary px-4 py-1.5 text-[13px]"
                                >
                                    Iniciar sesión
                                </Link>
                            )}
                        </nav>
                    </div>
                </header>

                <section className="mx-auto grid max-w-6xl gap-12 px-6 py-16 lg:grid-cols-[1.15fr_0.85fr] lg:py-24">
                    <div>
                        <p className="eyebrow">
                            Consulta de bariatría y nutrición
                        </p>

                        <h1 className="mt-4 font-display text-[36px] leading-[1.12] text-ink sm:text-[44px]">
                            Atención médica para el control de peso, sin atajos.
                        </h1>

                        <p className="mt-6 max-w-xl text-[15px] leading-relaxed text-gray-600">
                            Valoración clínica, seguimiento antropométrico y
                            tratamiento nutricional individualizado. Cada
                            paciente lleva un expediente con sus mediciones y su
                            evolución registrada consulta por consulta.
                        </p>

                        <div className="mt-8 flex flex-wrap gap-3">
                            <a
                                href="#contacto"
                                className="btn-primary px-5 py-2.5"
                            >
                                Agendar valoración
                            </a>
                            <a
                                href="#tratamientos"
                                className="btn-secondary px-5 py-2.5"
                            >
                                Ver tratamientos
                            </a>
                        </div>

                        <p className="mt-8 text-[12px] text-gray-400">
                            Consultorio con cita previa · Guadalajara, Jalisco
                        </p>
                    </div>

                    <aside className="card h-fit p-6">
                        <p className="eyebrow">Ficha del consultorio</p>

                        <dl className="mt-4 space-y-4 text-sm">
                            <div>
                                <dt className="text-[12px] text-gray-500">
                                    Especialidad
                                </dt>
                                <dd className="mt-0.5">
                                    Bariatría y nutrición clínica
                                </dd>
                            </div>
                            <div className="rule pt-4">
                                <dt className="text-[12px] text-gray-500">
                                    Horario
                                </dt>
                                <dd className="mt-0.5">
                                    Martes, jueves y sábado
                                    <br />
                                    9:00 a 19:00 h
                                </dd>
                            </div>
                            <div className="rule pt-4">
                                <dt className="text-[12px] text-gray-500">
                                    Dirección
                                </dt>
                                <dd className="mt-0.5">
                                    Calz. del Federalismo Nte.
                                    <br />
                                    Artesanos, 44200 Guadalajara, Jal.
                                </dd>
                            </div>
                            <div className="rule pt-4">
                                <dt className="text-[12px] text-gray-500">
                                    Atención
                                </dt>
                                <dd className="mt-0.5">Solo con cita agendada</dd>
                            </div>
                        </dl>

                        <a
                            href="#contacto"
                            className="mt-6 block border-t border-sand pt-4 text-[13px] text-pine-700 hover:text-pine-900"
                        >
                            Solicitar informes
                        </a>
                    </aside>
                </section>

                <section
                    id="tratamientos"
                    className="border-y border-sand bg-white"
                >
                    <div className="mx-auto max-w-6xl px-6 py-16">
                        <div className="max-w-2xl">
                            <p className="eyebrow">Tratamientos</p>
                            <h2 className="mt-3 font-display text-[28px] leading-snug">
                                Enfoque clínico, no dietas de moda.
                            </h2>
                            <p className="mt-3 text-sm leading-relaxed text-gray-600">
                                Cada plan parte de una valoración médica y se
                                ajusta con mediciones objetivas, no con
                                impresiones.
                            </p>
                        </div>

                        <ol className="mt-10 grid gap-10 sm:grid-cols-3">
                            {TRATAMIENTOS.map((tratamiento, indice) => (
                                <li
                                    key={tratamiento.titulo}
                                    className="border-t border-sand pt-5"
                                >
                                    <p className="font-display text-sm text-pine-700">
                                        {String(indice + 1).padStart(2, '0')}
                                    </p>
                                    <h3 className="mt-2 text-[15px] font-medium text-ink">
                                        {tratamiento.titulo}
                                    </h3>
                                    <p className="mt-2 text-[13px] leading-relaxed text-gray-600">
                                        {tratamiento.texto}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    </div>
                </section>

                <section id="contacto" className="mx-auto max-w-6xl px-6 py-16">
                    <div className="grid gap-10 lg:grid-cols-[0.9fr_1.1fr]">
                        <div>
                            <p className="eyebrow">Contacto</p>
                            <h2 className="mt-3 font-display text-[28px] leading-snug">
                                Agenda tu valoración
                            </h2>
                            <p className="mt-3 max-w-md text-sm leading-relaxed text-gray-600">
                                Déjanos tu nombre y teléfono. Se abrirá WhatsApp
                                con el mensaje listo para enviar al consultorio.
                            </p>

                            <form
                                onSubmit={handleWhatsAppRedirect}
                                className="mt-6 space-y-4"
                            >
                                <div>
                                    <label
                                        htmlFor="nombre"
                                        className="label"
                                    >
                                        Nombre completo
                                    </label>
                                    <input
                                        id="nombre"
                                        type="text"
                                        required
                                        placeholder="Ej. Juan Pérez"
                                        className="field"
                                        value={nombre}
                                        onChange={(e) =>
                                            setNombre(e.target.value)
                                        }
                                    />
                                </div>

                                <div>
                                    <label
                                        htmlFor="telefono"
                                        className="label"
                                    >
                                        Teléfono
                                    </label>
                                    <input
                                        id="telefono"
                                        type="tel"
                                        required
                                        placeholder="Ej. 3312345678"
                                        className="field"
                                        value={telefono}
                                        onChange={(e) =>
                                            setTelefono(e.target.value)
                                        }
                                    />
                                </div>

                                <button
                                    type="submit"
                                    className="btn-primary w-full"
                                >
                                    Solicitar informes por WhatsApp
                                </button>

                                <p className="text-[11px] leading-relaxed text-gray-400">
                                    Tus datos se usan únicamente para agendar la
                                    consulta.
                                </p>
                            </form>
                        </div>

                        <div>
                            <MapaUbicacion />
                            <p className="mt-3 text-[12px] text-gray-500">
                                Calz. del Federalismo Nte., Artesanos, 44200
                                Guadalajara, Jal.
                            </p>
                        </div>
                    </div>
                </section>

                <footer className="border-t border-sand">
                    <div className="mx-auto flex max-w-6xl flex-col gap-2 px-6 py-6 text-[11px] text-gray-400 sm:flex-row sm:items-center sm:justify-between">
                        <p>
                            © {new Date().getFullYear()} Umamed · Dra. Jessi
                            Mora. Todos los derechos reservados.
                        </p>
                        <p>Consultorio médico · Guadalajara, Jalisco</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
