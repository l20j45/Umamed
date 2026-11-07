<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Observaciones del paciente</title>
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #1f2937;
            margin: 30px;
            font-size: 12px;
        }
        .header {
            border-bottom: 2px solid #059669;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        h1 {
            margin: 0;
            font-size: 24px;
            color: #065f46;
        }
        .meta {
            margin-top: 10px;
            line-height: 1.6;
        }
        .section {
            margin-top: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px 16px;
            background: #f9fafb;
        }
        .section h2 {
            margin: 0 0 8px;
            color: #0f172a;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .content {
            white-space: pre-line;
            line-height: 1.7;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Seguimiento clínico</h1>
        <div class="meta">
            <strong>Paciente:</strong> {{ $patient->name }} {{ $patient->last_name ?? '' }}<br>
            <strong>Título:</strong> {{ $reporte->titulo }}<br>
            <strong>Fecha:</strong> {{ $reporte->created_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}<br>
            <strong>Formato:</strong> {{ $reporte->formato }}
        </div>
    </div>

    <div class="section">
        <h2>Observaciones</h2>
        <div class="content">{!! nl2br(e($reporte->observaciones)) !!}</div>
    </div>

    <div class="section">
        <h2>Dieta / plan nutricional</h2>
        <div class="content">{!! nl2br(e($reporte->dieta ?? 'No se registró dieta para este seguimiento.')) !!}</div>
    </div>

    <div class="section">
        <h2>Recomendaciones</h2>
        <div class="content">{!! nl2br(e($reporte->recomendaciones ?? 'No se registraron recomendaciones adicionales.')) !!}</div>
    </div>
</body>
</html>
