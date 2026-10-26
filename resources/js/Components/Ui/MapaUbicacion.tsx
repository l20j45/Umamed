import React from 'react';

export default function MapaUbicacion() {
    // Reemplaza este src por el del iframe de Google Maps si cambia la dirección.
    const googleMapsEmbedUrl =
        'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3732.5608858933215!2d-103.35613563180551!3d20.68743646227814!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8428b1354c0dc669%3A0x2005c6a276b049f4!2sUMAMED%20Bariatra%20Cl%C3%ADnico!5e0!3m2!1ses!2smx!4v1780761468933!5m2!1ses!2smx';

    return (
        <div className="overflow-hidden border border-sand bg-gray-100">
            <div className="aspect-[4/3] w-full lg:aspect-square">
                <iframe
                    src={googleMapsEmbedUrl}
                    className="h-full w-full border-0"
                    allowFullScreen={true}
                    loading="lazy"
                    referrerPolicy="no-referrer-when-downgrade"
                    title="Ubicación del consultorio"
                />
            </div>
        </div>
    );
}
