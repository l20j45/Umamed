import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="grid min-h-screen bg-paper lg:grid-cols-[1.05fr_1fr]">
            <aside className="hidden flex-col justify-between bg-pine-800 px-12 py-12 text-pine-100 lg:flex">
                <Link href="/" className="font-display text-2xl text-white">
                    Umamed
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
                        className="mb-10 block font-display text-xl text-ink lg:hidden"
                    >
                        Umamed
                    </Link>
                    {children}
                </div>
            </main>
        </div>
    );
}
