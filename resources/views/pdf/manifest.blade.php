<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Посадочная ведомость</title>
    <style>
        @page {
            margin: 10mm;
            size: A4 landscape;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header h1 {
            font-size: 13pt;
            margin: 0 0 8px 0;
            text-transform: uppercase;
            font-weight: bold;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }
        .info-grid td {
            padding: 3px 5px;
            vertical-align: top;
        }
        .info-label {
            font-weight: bold;
            width: 25%;
        }
        .info-value {
            width: 25%;
        }
        .passengers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .passengers-table th {
            background-color: #f0f0f0;
            border: 1px solid #000;
            padding: 5px 3px;
            text-align: center;
            font-weight: bold;
            font-size: 8pt;
        }
        .passengers-table td {
            border: 1px solid #000;
            padding: 4px 3px;
            font-size: 8pt;
        }
        .passengers-table tbody tr {
            page-break-inside: avoid;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            margin-top: 15px;
            font-size: 9pt;
        }
        .signature-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            width: 200px;
            margin: 0 10px;
        }
        .check-mark {
            font-weight: bold;
            font-size: 11pt;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ПОСАДОЧНАЯ ВЕДОМОСТЬ</h1>
    </div>

    <table class="info-grid">
        <tr>
            <td class="info-label">Сформировано:</td>
            <td class="info-value">{{ now()->timezone('Asia/Sakhalin')->format('d.m.Y H:i') }}</td>
            <td class="info-label">Диспетчер:</td>
            <td class="info-value">{{ $manifest->creator->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="info-label">Перевозчик:</td>
            <td class="info-value">{{ $manifest->carrier_name ?? '—' }}</td>
            <td class="info-label">Дата и время отправления:</td>
            <td class="info-value">{{ $manifest->dt_depart ? \Carbon\Carbon::parse($manifest->dt_depart)->timezone('Asia/Sakhalin')->format('d.m.Y H:i') : '—' }}</td>
        </tr>
        <tr>
            <td class="info-label">Водитель:</td>
            <td class="info-value">{{ $driverNames ?? '—' }}</td>
            <td class="info-label">Транспортное средство:</td>
            <td class="info-value">{{ $manifest->vehicle_model ? $manifest->bus_number . ' (' . $manifest->vehicle_model . ')' : ($manifest->bus_number ?? '—') }}</td>
        </tr>
        <tr>
            <td class="info-label">Рейс:</td>
            <td class="info-value">{{ $manifest->route_number }} {{ $manifest->from_name }} – {{ $manifest->to_name }}</td>
            <td class="info-label">Станция отправления:</td>
            <td class="info-value">{{ $manifest->from_name ?? '—' }}</td>
        </tr>
    </table>

    <table class="passengers-table">
        <thead>
            <tr>
                <th style="width: 30px;">№</th>
                <th style="width: 50px;">Место</th>
                <th style="width: 60px;">Вид</th>
                <th style="width: 60px;">Серия</th>
                <th style="width: 80px;">Номер</th>
                <th style="width: 120px;">Ст.отправления</th>
                <th style="width: 120px;">Ст.назначения</th>
                <th style="width: 60px;">Тариф</th>
                <th style="width: 180px;">Пассажир</th>
                <th style="width: 50px;">Контр.</th>
                <th style="width: 100px;">Тел.</th>
                <th style="width: 80px;">Регистрация</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">{{ $item->passenger->seat_number ?? '—' }}</td>
                    <td class="text-center">{{ $item->passenger->passenger_type ?? 'взрослый' }}</td>
                    <td class="text-center">{{ $item->passenger->document_series ?? '—' }}</td>
                    <td class="text-center">{{ $item->passenger->document_number ?? '—' }}</td>
                    <td>{{ $manifest->from_name ?? '—' }}</td>
                    <td>{{ $manifest->to_name ?? '—' }}</td>
                    <td class="text-right">{{ $item->passenger->ticket_total_price ? number_format($item->passenger->ticket_total_price, 2) : ($item->passenger->ticket_price ? number_format($item->passenger->ticket_price, 2) : '—') }}</td>
                    <td>{{ $item->passenger->last_name }} {{ $item->passenger->first_name }} {{ $item->passenger->middle_name }}</td>
                    <td class="text-center">
                        @if ($item->checked_in === true)
                            <span class="check-mark">✓</span>
                        @elseif ($item->checked_in === false)
                            <span class="check-mark">✗</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $item->passenger->phone ?? '—' }}</td>
                    <td class="text-center">{{ $item->passenger->ticket_purchased_at ? \Carbon\Carbon::parse($item->passenger->ticket_purchased_at)->timezone('Asia/Sakhalin')->format('d.m.Y H:i') : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="text-center">Нет пассажиров</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p><strong>Всего пассажиров:</strong> {{ $items->count() }} | 
           <strong>Явка:</strong> {{ $items->where('checked_in', true)->count() }} | 
           <strong>Неявка:</strong> {{ $items->where('checked_in', false)->count() }} | 
           <strong>Не отмечено:</strong> {{ $items->where('checked_in', null)->count() }}</p>
        <br>
        <p>
            Диспетчер: <span class="signature-line"></span> / {{ $manifest->creator->name ?? '_________________' }} /
        </p>
    </div>
</body>
</html>
