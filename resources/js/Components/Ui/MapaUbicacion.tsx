import React from 'react';

export default function MapaUbicacion() {
    // 🛠️ Puedes reemplazar este src por el tuyo de Google Maps si es otra dirección.
    // Solo asegúrate de copiar ÚNICAMENTE lo que está dentro de src="..." en el iframe de Google.
    const googleMapsEmbedUrl = "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3732.5608858933215!2d-103.35613563180551!3d20.68743646227814!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8428b1354c0dc669%3A0x2005c6a276b049f4!2sUMAMED%20Bariatra%20Cl%C3%ADnico!5e0!3m2!1ses!2smx!4v1780761468933!5m2!1ses!2smx";

    return (
        <div className="w-full overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-800 bg-gray-100 dark:bg-zinc-900">
            {/* Contenedor con Aspect Ratio para que sea responsivo (4:3 o 16:9 en pantallas grandes) */}
            <div className="aspect-[4/3] sm:aspect-[16:9] lg:aspect-square w-full">
                <iframe
                    src={googleMapsEmbedUrl}
                    className="h-full w-full border-0"
                    allowFullScreen={true}
                    loading="lazy"
                    referrerPolicy="no-referrer-when-downgrade"
                    title="Ubicación del Consultorio Clínico"
                />
            </div>
        </div>
    );
}