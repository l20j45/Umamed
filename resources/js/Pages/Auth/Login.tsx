import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Iniciar sesión" />

            <p className="eyebrow">Acceso al sistema</p>
            <h1 className="mt-2 font-display text-[26px] leading-tight text-ink">
                Iniciar sesión
            </h1>
            <p className="mt-2 text-sm text-gray-500">
                Ingresa con las credenciales que te entregó la clínica.
            </p>

            {status && (
                <div className="mt-5 border-l-2 border-pine-600 bg-pine-50 px-3 py-2 text-sm text-pine-900">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="mt-7 space-y-5">
                <div>
                    <InputLabel htmlFor="email" value="Correo electrónico" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Contraseña" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} />
                </div>

                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData(
                                    'remember',
                                    (e.target.checked || false) as false,
                                )
                            }
                        />
                        <span className="text-[13px] text-gray-600">
                            Mantener sesión
                        </span>
                    </label>

                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-[13px] text-pine-700 underline decoration-pine-300 underline-offset-2 hover:text-pine-900"
                        >
                            ¿Olvidaste tu contraseña?
                        </Link>
                    )}
                </div>

                <PrimaryButton className="w-full" disabled={processing}>
                    {processing ? 'Entrando…' : 'Entrar'}
                </PrimaryButton>
            </form>

            <p className="mt-8 border-t border-sand pt-5 text-[12px] leading-relaxed text-gray-400">
                Las cuentas de paciente las crea el personal de la clínica. Si
                necesitas acceso, comunícate con recepción.
            </p>
        </GuestLayout>
    );
}
