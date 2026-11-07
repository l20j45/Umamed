import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

function HealthMark({ className = '' }: { className?: string }) {
    return (
        <span
            className={`inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/12 ring-1 ring-white/20 ${className}`}
            aria-hidden="true"
        >
            <svg viewBox="0 0 64 64" className="h-5 w-5 text-white" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M32 12.5C24.6 12.5 19 18.1 19 25.5C19 38.5 31.4 46 32 46.2C32.6 46 45 38.5 45 25.5C45 18.1 39.4 12.5 32 12.5Z" fill="#A7E6D0" opacity="0.9"/>
                <path d="M32 21V43" stroke="#F8FFFC" strokeWidth="4.5" strokeLinecap="round"/>
                <path d="M18.5 32H45.5" stroke="#F8FFFC" strokeWidth="4.5" strokeLinecap="round"/>
                <path d="M32 12.5C24.6 12.5 19 18.1 19 25.5C19 38.5 31.4 46 32 46.2C32.6 46 45 38.5 45 25.5C45 18.1 39.4 12.5 32 12.5Z" stroke="#F8FFFC" strokeWidth="2" opacity="0.7"/>
            </svg>
        </span>
    );
}

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="grid min-h-screen bg-paper lg:grid-cols-[1.05fr_1fr]">
            <aside className="hidden flex-col justify-between bg-pine-800 px-12 py-12 text-pine-100 lg:flex">
                <Link href="/" className="flex items-center gap-3 font-display text-2xl text-white">
                    <HealthMark />
                    <span>Umamed</span>
                </Link>

                <div className="max-w-sm">
                    <p className="font-display text-[28px] leading-snug text-white">
                        El seguimiento que tu tratamiento merece.
                    </p>
                    <p className="mt-4 text-sm leading-relaxed text-pine-200">
                        Plataforma clínica de la Dra. Jessi Mora para el control
                        médico de peso, composición corporal y metabolismo.
                    </p>
                </div>

                <p className="text-[10px] uppercase tracking-[0.18em] text-pine-300">
                    Bariatría y nutrición clínica · Guadalajara
                </p>
            </aside>

            <main className="flex items-center justify-center px-6 py-12">
                <div className="w-full max-w-[360px]">
                    <Link
                        href="/"
                        className="mb-10 flex items-center gap-2 font-display text-xl text-ink lg:hidden"
                    >
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-pine-700 text-white shadow-sm">
                            <svg viewBox="0 0 64 64" className="h-4 w-4" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M32 12.5C24.6 12.5 19 18.1 19 25.5C19 38.5 31.4 46 32 46.2C32.6 46 45 38.5 45 25.5C45 18.1 39.4 12.5 32 12.5Z" fill="#A7E6D0" opacity="0.9"/>
                                <path d="M32 21V43" stroke="#F8FFFC" strokeWidth="4.5" strokeLinecap="round"/>
                                <path d="M18.5 32H45.5" stroke="#F8FFFC" strokeWidth="4.5" strokeLinecap="round"/>
                            </svg>
                        </span>
                        <span>Umamed</span>
                    </Link>
                    {children}
                </div>
            </main>
        </div>
    );
}
