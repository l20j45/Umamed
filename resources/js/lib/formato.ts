const formatoMoneda = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
});

/** Importe en pesos: $1,250.00 */
export const moneda = (valor: number | string | null | undefined): string =>
    formatoMoneda.format(Number(valor ?? 0));

/** "2026-11" → "noviembre de 2026" */
export const nombreMes = (mes: string): string => {
    const [anio, numero] = mes.split('-').map(Number);
    const fecha = new Date(anio, numero - 1, 1);

    return fecha.toLocaleDateString('es-MX', { month: 'long', year: 'numeric' });
};

/** "2026-11-10" → "10 de noviembre" */
export const fechaCorta = (fecha: string): string =>
    new Date(`${fecha}T12:00:00`).toLocaleDateString('es-MX', {
        day: 'numeric',
        month: 'long',
    });
