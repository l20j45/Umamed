<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo</title>
    <style>
        @page { margin: 32px 36px; }
        * { font-family: Helvetica, Arial, sans-serif; }
        body { color: #1f2937; font-size: 10px; line-height: 1.5; }

        .encabezado { border-bottom: 2px solid #047857; padding-bottom: 10px; margin-bottom: 16px; }
        .encabezado .clinica { font-size: 16px; font-weight: bold; color: #047857; }
        .encabezado .titulo { font-size: 11px; color: #4b5563; margin-top: 2px; }
        .encabezado .folio { font-size: 10px; color: #6b7280; text-align: right; }

        h2 { font-size: 10px; text-transform: uppercase; letter-spacing: .6px; color: #047857;
             border-bottom: 1px solid #d1d5db; padding-bottom: 3px; margin: 16px 0 8px; }

        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 4px 6px; text-align: left; vertical-align: top; }

        .datos td { border-bottom: 1px solid #f3f4f6; }
        .datos .etiqueta { color: #6b7280; width: 20%; }

        .items th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; color: #4b5563; }
        .items td { border-bottom: 1px solid #e5e7eb; font-size: 9px; }
        .items .num { text-align: right; }

        .totales { margin-top: 10px; width: 45%; margin-left: 55%; }
        .totales td { border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        .totales .num { text-align: right; }
        .totales .fuerte td { font-weight: bold; font-size: 11px; border-top: 1px solid #9ca3af; }

        .estado { display: inline-block; padding: 2px 8px; font-size: 9px; font-weight: bold;
                  border: 1px solid #d1d5db; }
        .pagado { color: #065f46; border-color: #6ee7b7; background: #ecfdf5; }
        .parcial { color: #92400e; border-color: #fcd34d; background: #fffbeb; }
        .pendiente { color: #991b1b; border-color: #fca5a5; background: #fef2f2; }

        .pie { margin-top: 22px; border-top: 1px solid #e5e7eb; padding-top: 6px;
               font-size: 8px; color: #9ca3af; }
        .aviso { margin-top: 10px; font-size: 8px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="encabezado">
        <table>
            <tr>
                <td>
                    <div class="clinica">{{ config('app.name') }}</div>
                    <div class="titulo">Recibo de servicios</div>
                </td>
                <td class="folio">
                    Folio: {{ str_pad((string) $cita->id, 5, '0', STR_PAD_LEFT) }}<br>
                    Generado el {{ $generado }}<br>
                    @if ($usuario)
                        Por {{ $usuario }}
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <h2>Paciente</h2>
    <table class="datos">
        <tr>
            <td class="etiqueta">Nombre</td>
            <td>{{ $paciente ? trim($paciente->name.' '.$paciente->last_name) : 'Horario bloqueado' }}</td>
            <td class="etiqueta">Fecha de la cita</td>
            <td>{{ $cita->inicioLocal()->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Teléfono</td>
            <td>{{ $paciente?->phone ?: 'No registrado' }}</td>
            <td class="etiqueta">Atendió</td>
            <td>{{ $bariatra?->name ?? '—' }}</td>
        </tr>
    </table>

    <h2>Conceptos</h2>
    @if ($cargos->isEmpty())
        <p style="color:#9ca3af; font-style:italic;">La cita no tiene conceptos registrados.</p>
    @else
        <table class="items">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th class="num">Precio</th>
                    <th class="num">Cantidad</th>
                    <th class="num">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cargos as $cargo)
                    <tr>
                        <td>{{ $cargo->nombre }}</td>
                        <td class="num">${{ number_format((float) $cargo->precio_unitario, 2) }}</td>
                        <td class="num">{{ $cargo->cantidad }}</td>
                        <td class="num">${{ number_format($cargo->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="totales">
        <tr>
            <td>Total</td>
            <td class="num">${{ number_format($total, 2) }}</td>
        </tr>
        <tr>
            <td>Pagado</td>
            <td class="num">${{ number_format($pagado, 2) }}</td>
        </tr>
        <tr class="fuerte">
            <td>Saldo</td>
            <td class="num">${{ number_format($saldo, 2) }}</td>
        </tr>
    </table>

    <div style="margin-top:8px;">
        <span class="estado {{ $estadoPago }}">
            {{ $estadoPago === 'pagado' ? 'PAGADO' : ($estadoPago === 'parcial' ? 'PAGO PARCIAL' : 'PENDIENTE DE PAGO') }}
        </span>
    </div>

    @if ($abonos->isNotEmpty())
        <h2>Abonos recibidos</h2>
        <table class="items">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Método</th>
                    <th>Nota</th>
                    <th class="num">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($abonos as $abono)
                    <tr>
                        <td>{{ $abono->pagado_en?->format('d/m/Y') }}</td>
                        <td>{{ ucfirst($abono->metodo_pago) }}</td>
                        <td>{{ $abono->nota ?: '—' }}</td>
                        <td class="num">${{ number_format((float) $abono->monto, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="aviso">
        Este recibo es un comprobante interno de la clínica y no constituye un
        comprobante fiscal (CFDI). Para facturación, solicítalo en recepción.
    </p>

    <div class="pie">
        {{ config('app.name') }} · Documento generado desde el sistema de expediente clínico.
    </div>
</body>
</html>
