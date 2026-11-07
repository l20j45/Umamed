import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useEffect, useState } from 'react';
import type { PageProps } from '@/types';

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>;
}

interface Enlace {
    href: string;
    label: string;
    active: boolean;
}

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const page = usePage().props as unknown as PageProps;
    const user = page.auth.user;
    const flash = page.flash;

    const roles: string[] = user.roles ?? [];
    const esPaciente = roles.includes('paciente');
    const esAdmin = roles.includes('admin');
    const veAgenda = roles.some((rol) =>
        ['admin', 'bariatra', 'recepcionista'].includes(rol),
    );
    // Dinero del consultorio: la recepcionista no ve catálogo, ingresos ni gastos.
    const veFinanzas = roles.some((rol) => ['admin', 'bariatra'].includes(rol));

    const [menuAbierto, setMenuAbierto] = useState(false);
    const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null);
    const [mostrarBotonInstalacion, setMostrarBotonInstalacion] = useState(false);

    useEffect(() => {
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
            (window.navigator as Navigator & { standalone?: boolean }).standalone;

        if (isStandalone) {
            return;
        }

        const handleBeforeInstallPrompt = (event: Event) => {
            event.preventDefault();
            setDeferredPrompt(event as BeforeInstallPromptEvent);
            setMostrarBotonInstalacion(true);
        };

        const handleAppInstalled = () => {
            setDeferredPrompt(null);
            setMostrarBotonInstalacion(false);
        };

        window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
        window.addEventListener('appinstalled', handleAppInstalled);

        return () => {
            window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
            window.removeEventListener('appinstalled', handleAppInstalled);
        };
    }, []);

    const instalarPwa = async () => {
        if (!deferredPrompt) {
            return;
        }

        await deferredPrompt.prompt();
        await deferredPrompt.userChoice;

        setDeferredPrompt(null);
        setMostrarBotonInstalacion(false);
    };

    const enlaces: Enlace[] = [
        ...(esPaciente
            ? []
            : [
                  {
                      href: route('dashboard'),
                      label: 'Pacientes',
                      active: !!route().current('dashboard'),
                  },
              ]),
        ...(veAgenda
            ? [
                  {
                      href: route('agenda.index'),
                      label: 'Agenda',
                      active: !!route().current('agenda.*'),
                  },
              ]
            : []),
        ...(veFinanzas
            ? [
                  {
                      href: route('catalogo.index'),
                      label: 'Catálogo',
                      active: !!route().current('catalogo.*'),
                  },
                  {
                      href: route('ingresos.index'),
                      label: 'Ingresos',
                      active: !!route().current('ingresos.*'),
                  },
                  {
                      href: route('gastos.index'),
                      label: 'Gastos',
                      active: !!route().current('gastos.*'),
                  },
              ]
            : []),
        ...(esPaciente
            ? [
                  {
                      href: route('patient.portal'),
                      label: 'Mi progreso',
                      active: !!route().current('patient.portal'),
                  },
                  {
                      href: route('patient.citas'),
                      label: 'Mis citas',
                      active: !!route().current('patient.citas'),
                  },
              ]
            : []),
        ...(esAdmin
            ? [
                  {
                      href: route('auditoria.index'),
                      label: 'Actividad',
                      active: !!route().current('auditoria.*'),
                  },
              ]
            : []),
    ];

    const enlaceClase = (activo: boolean) =>
        `flex items-center justify-between rounded-sm px-3 py-2 text-sm transition-colors ${
            activo
                ? 'bg-pine-50 font-medium text-pine-800'
                : 'text-gray-600 hover:bg-gray-50 hover:text-ink'
        }`;

    return (
        <div className="min-h-screen bg-paper text-ink">
            {/* Barra lateral de escritorio */}
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-60 flex-col border-r border-sand bg-white lg:flex">
                <div className="border-b border-sand px-5 py-5">
                    <Link href="/" className="block">
                        <div className="flex items-center gap-3">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-pine-700 shadow-sm ring-1 ring-pine-900/10">
                                <svg viewBox="0 0 64 64" className="h-5 w-5 text-white" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M32 12.5C24.6 12.5 19 18.1 19 25.5C19 38.5 31.4 46 32 46.2C32.6 46 45 38.5 45 25.5C45 18.1 39.4 12.5 32 12.5Z" fill="#A7E6D0" opacity="0.9"/>
                                    <path d="M32 21V43" stroke="#F8FFFC" strokeWidth="4.5" strokeLinecap="round"/>
                                    <path d="M18.5 32H45.5" stroke="#F8FFFC" strokeWidth="4.5" strokeLinecap="round"/>
                                </svg>
                            </span>
                            <div>
                                <span className="block font-display text-[22px] leading-none text-ink">
                                    Umamed
                                </span>
                                <span className="mt-1.5 block text-[10px] uppercase tracking-[0.18em] text-gray-500">
                                    Bariatría y nutrición
                                </span>
                            </div>
                        </div>
                    </Link>
                </div>

                <nav className="flex-1 overflow-y-auto px-3 py-4">
                    <p className="px-3 pb-2 text-[10px] uppercase tracking-[0.18em] text-gray-400">
                        {esPaciente ? 'Mi seguimiento' : 'Consultorio'}
                    </p>
                    <div className="space-y-0.5">
                        {enlaces.map((enlace) => (
                            <Link
                                key={enlace.href}
                                href={enlace.href}
                                className={enlaceClase(enlace.active)}
                            >
                                <span>{enlace.label}</span>
                                {enlace.active && (
                                    <span className="h-1.5 w-1.5 rounded-full bg-pine-600" />
                                )}
                            </Link>
                        ))}
                    </div>
                </nav>

                <div className="border-t border-sand px-4 py-4">
                    <p className="truncate text-sm font-medium text-ink">
                        {user.name} {user.last_name ?? ''}
                    </p>
                    <p className="truncate text-xs text-gray-500">{user.email}</p>
                    {mostrarBotonInstalacion && (
                        <button
                            type="button"
                            onClick={instalarPwa}
                            className="mt-3 w-full rounded-sm bg-pine-700 px-3 py-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-white"
                        >
                            Instalar app
                        </button>
                    )}
                    <div className="mt-3 flex gap-2">
                        <Link
                            href={route('profile.edit')}
                            className="btn-secondary flex-1 px-2 py-1 text-xs"
                        >
                            Perfil
                        </Link>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="btn-secondary px-2 py-1 text-xs"
                        >
                            Salir
                        </Link>
                    </div>
                </div>
            </aside>

            <div className="flex min-h-screen flex-col lg:pl-60">
                {/* Barra superior en móvil */}
                <div className="flex items-center justify-between border-b border-sand bg-white px-4 py-3 lg:hidden">
                    <Link href="/" className="flex items-center gap-2 font-display text-lg leading-none">
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-pine-700 text-white">
                            <svg viewBox="0 0 64 64" className="h-4 w-4" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M32 12.5C24.6 12.5 19 18.1 19 25.5C19 38.5 31.4 46 32 46.2C32.6 46 45 38.5 45 25.5C45 18.1 39.4 12.5 32 12.5Z" fill="#A7E6D0" opacity="0.9"/>
                                <path d="M32 21V43" stroke="#F8FFFC" strokeWidth="4.5" strokeLinecap="round"/>
                                <path d="M18.5 32H45.5" stroke="#F8FFFC" strokeWidth="4.5" strokeLinecap="round"/>
                            </svg>
                        </span>
                        <span>Umamed</span>
                    </Link>
                    <div className="flex items-center gap-2">
                        {mostrarBotonInstalacion && (
                            <button
                                type="button"
                                onClick={instalarPwa}
                                className="rounded-sm bg-pine-700 px-2.5 py-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-white"
                            >
                                Instalar
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={() => setMenuAbierto((abierto) => !abierto)}
                            aria-expanded={menuAbierto}
                            aria-label="Abrir menú"
                            className="rounded-sm border border-sand px-3 py-1.5 text-xs font-medium text-gray-600"
                        >
                            {menuAbierto ? 'Cerrar' : 'Menú'}
                        </button>
                    </div>
                </div>

                {menuAbierto && (
                    <nav className="border-b border-sand bg-white px-3 py-3 lg:hidden">
                        <div className="space-y-0.5">
                            {enlaces.map((enlace) => (
                                <Link
                                    key={enlace.href}
                                    href={enlace.href}
                                    className={enlaceClase(enlace.active)}
                                    onClick={() => setMenuAbierto(false)}
                                >
                                    <span>{enlace.label}</span>
                                    {enlace.active && (
                                        <span className="h-1.5 w-1.5 rounded-full bg-pine-600" />
                                    )}
                                </Link>
                            ))}
                        </div>
                        <div className="mt-3 flex gap-2 border-t border-sand pt-3">
                            <Link
                                href={route('profile.edit')}
                                className="btn-secondary flex-1 px-2 py-1 text-xs"
                            >
                                Perfil
                            </Link>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="btn-secondary px-2 py-1 text-xs"
                            >
                                Salir
                            </Link>
                        </div>
                    </nav>
                )}

                {header && (
                    <div className="page-header border-b border-sand bg-white px-5 py-6 sm:px-8 lg:px-10">
                        {header}
                    </div>
                )}

                <main className="flex-1 px-5 py-8 sm:px-8 lg:px-10">
                    {(flash?.success || flash?.error) && (
                        <div className="mb-6 space-y-2">
                            {flash?.success && (
                                <div
                                    role="status"
                                    className="border-l-2 border-pine-600 bg-pine-50 px-4 py-3 text-sm text-pine-900"
                                >
                                    {flash.success}
                                </div>
                            )}
                            {flash?.error && (
                                <div
                                    role="alert"
                                    className="border-l-2 border-arcilla-500 bg-arcilla-50 px-4 py-3 text-sm text-arcilla-800"
                                >
                                    {flash.error}
                                </div>
                            )}
                        </div>
                    )}

                    {children}
                </main>

                <footer className="border-t border-sand px-5 py-4 text-[11px] text-gray-400 sm:px-8 lg:px-10">
                    Umamed · Expediente clínico confidencial · Uso exclusivo del
                    personal autorizado
                </footer>
            </div>
        </div>
    );
}
