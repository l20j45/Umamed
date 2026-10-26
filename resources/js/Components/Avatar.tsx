interface AvatarProps {
    nombre: string;
    url?: string | null;
    className?: string;
}

/**
 * Avatar del paciente: usa la foto si existe y, si no, sus iniciales.
 * (Antes se pedía a un servicio externo, que traía su propio verde.)
 */
export default function Avatar({ nombre, url, className = '' }: AvatarProps) {
    const iniciales = nombre
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((parte) => parte[0]?.toUpperCase() ?? '')
        .join('');

    if (url) {
        return (
            <img
                src={url}
                alt={`Foto de ${nombre}`}
                className={`h-full w-full object-cover object-center ${className}`}
            />
        );
    }

    return (
        <div
            aria-hidden="true"
            className={`flex h-full w-full items-center justify-center bg-pine-700 font-display text-xl text-white ${className}`}
        >
            {iniciales || '—'}
        </div>
    );
}
