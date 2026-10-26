<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Expediente clínico</title>
    <style>
        @page { margin: 28px 32px; }
        * { font-family: Helvetica, Arial, sans-serif; }
        body { color: #1f2937; font-size: 10px; line-height: 1.45; }

        .encabezado { border-bottom: 2px solid #047857; padding-bottom: 8px; margin-bottom: 14px; }
        .encabezado .clinica { font-size: 16px; font-weight: bold; color: #047857; }
        .encabezado .titulo { font-size: 11px; color: #4b5563; margin-top: 2px; }
        .encabezado .meta { font-size: 9px; color: #6b7280; text-align: right; }

        h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #047857;
             border-bottom: 1px solid #d1d5db; padding-bottom: 3px; margin: 16px 0 8px; }

        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 4px 6px; text-align: left; vertical-align: top; }

        .datos td { border-bottom: 1px solid #f3f4f6; }
        .datos .etiqueta { color: #6b7280; width: 22%; }

        .resumen { margin-top: 6px; }
        .resumen td { width: 25%; border: 1px solid #e5e7eb; padding: 8px; }
        .resumen .numero { font-size: 15px; font-weight: bold; display: block; }
        .resumen .texto { font-size: 8px; color: #6b7280; text-transform: uppercase; }

        .historial th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; color: #4b5563; }
        .historial td { border-bottom: 1px solid #e5e7eb; font-size: 9px; }
        .historial tr:nth-child(even) td { background: #fafafa; }

        .fotos td { padding: 4px; text-align: center; width: 33%; }
        .fotos img { border: 1px solid #e5e7eb; max-width: 320px; }
        .fotos .etiqueta-foto {
            display: inline-block;
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            border-radius: 999px;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: .3px;
            padding: 2px 6px;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .fotos .fecha { font-size: 8px; color: #6b7280; display: block; margin-top: 2px; }

        .pie { margin-top: 18px; border-top: 1px solid #e5e7eb; padding-top: 6px;
               font-size: 8px; color: #9ca3af; }
        .vacio { color: #9ca3af; font-style: italic; }
    </style>
</head>
<body>
    <div class="encabezado">
        <table>
            <tr>
                <td>
                    <div class="clinica">{{ config('app.name') }}</div>
                    <div class="titulo">Expediente clínico</div>
                </td>
                <td class="meta">
                    Generado el {{ $generado }}<br>
                    @if ($usuario)
                        Por {{ $usuario }}
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <h2>Datos del paciente</h2>
    <table class="datos">
        <tr>
            <td class="etiqueta">Nombre</td>
            <td>{{ trim($patient->name.' '.$patient->last_name) }}</td>
            <td class="etiqueta">Correo</td>
            <td>{{ $patient->email }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Teléfono</td>
            <td>{{ $patient->phone ?: 'No registrado' }}</td>
            <td class="etiqueta">Estatura</td>
            <td>{{ $patient->estatura ? number_format((float) $patient->estatura, 2).' m' : 'No registrada' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Inicio de tratamiento</td>
            <td>{{ $patient->treatment_start_date?->format('d/m/Y') ?? 'No asignado' }}</td>
            <td class="etiqueta">Consultas registradas</td>
            <td>{{ $medidas->count() }}</td>
        </tr>
    </table>

    <h2>Resumen</h2>
    <table class="resumen">
        <tr>
            <td>
                <span class="numero">{{ $inicial ? number_format((float) $inicial->peso, 1) : '—' }} kg</span>
                <span class="texto">Peso inicial</span>
            </td>
            <td>
                <span class="numero">{{ $actual ? number_format((float) $actual->peso, 1) : '—' }} kg</span>
                <span class="texto">Peso actual</span>
            </td>
            <td>
                <span class="numero">
                    @if ($perdida !== null)
                        {{ $perdida >= 0 ? '-' : '+' }}{{ number_format(abs($perdida), 1) }} kg
                    @else
                        —
                    @endif
                </span>
                <span class="texto">Cambio total</span>
            </td>
            <td>
                <span class="numero">{{ $actual ? number_format((float) $actual->imc, 1) : '—' }}</span>
                <span class="texto">IMC actual</span>
            </td>
        </tr>
    </table>

    <h2>Historial de mediciones</h2>

    @if ($medidas->isEmpty())
        <p class="vacio">El expediente todavía no tiene mediciones.</p>
    @else
        <table class="historial">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Peso (kg)</th>
                    <th>IMC</th>
                    <th>T. superior</th>
                    <th>Abd. sup.</th>
                    <th>Abd. medio</th>
                    <th>Abd. inf.</th>
                    <th>Ex. sup.</th>
                    <th>Ex. inf.</th>
                    <th>Notas</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($medidas as $medida)
                    <tr>
                        <td>{{ $medida->created_at?->timezone(config('clinic.zona_horaria', 'UTC'))->format('d/m/Y') }}</td>
                        <td>{{ number_format((float) $medida->peso, 1) }}</td>
                        <td>{{ number_format((float) $medida->imc, 1) }}</td>
                        <td>{{ $medida->tSuperior !== null ? number_format((float) $medida->tSuperior, 1) : '—' }}</td>
                        <td>{{ $medida->abdomenSuperior !== null ? number_format((float) $medida->abdomenSuperior, 1) : '—' }}</td>
                        <td>{{ $medida->abdomenMedio !== null ? number_format((float) $medida->abdomenMedio, 1) : '—' }}</td>
                        <td>{{ $medida->abdomenInferior !== null ? number_format((float) $medida->abdomenInferior, 1) : '—' }}</td>
                        <td>{{ $medida->exSuperior !== null ? number_format((float) $medida->exSuperior, 1) : '—' }}</td>
                        <td>{{ $medida->exInferior !== null ? number_format((float) $medida->exInferior, 1) : '—' }}</td>
                        <td>{{ $medida->notes ?: '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (count($fotos) > 0)
        <h2>Fotos de progreso</h2>
        <table class="fotos">
            @foreach (array_chunk($fotos, 3) as $fila)
                <tr>
                    @foreach ($fila as $foto)
                        <td>
                            <span class="etiqueta-foto">{{ $foto['categoria_legible'] ?? ucfirst($foto['categoria'] ?? 'Frente') }}</span>
                            <img src="{{ $foto['src'] }}" alt="Foto de progreso">
                            <span class="fecha">{{ $foto['fecha'] }}</span>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif

    <div class="pie">
        Documento generado desde {{ config('app.name') }}. La información es confidencial
        y de uso exclusivo del personal clínico autorizado.
    </div>
</body>
</html>
