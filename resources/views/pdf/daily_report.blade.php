<style>
    @media print {
        @page {
            size: 58mm auto;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
        }
    }

    body {
        font-family: monospace;
        font-size: 12px;
        width: 58mm !important;
        max-width: 100%;
        padding: 6px 8px;
        margin: 0 auto;
        box-sizing: border-box;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin: 4px 0;
    }

    td {
        padding: 2px;
        vertical-align: top;
    }

    .qr-code {
        text-align: center;
        margin: 4px 0;
    }

    .qr-code img {
        width: 80px;
        height: 80px;
    }

    h2 {
        text-align: center;
        font-size: 16px;
        margin: 6px 0;
    }

    hr {
        border: none;
        border-top: 1px dashed #000;
        margin: 6px 0;
    }

    .footer {
        text-align: center;
        font-size: 10px;
        margin-top: 8px;
    }

    .bold {
        font-weight: bold;
    }

    .center {
        text-align: center;
    }
</style>
<div id="printArea">
    <h2>Laporan STO<br>{{ strtoupper($report->created_at->format('F Y')) }}</h2>

    <div class="qr-code">
        <img src="{{ $qrCodeBase64 }}" alt="QR"><br>
        <small>{{ $report->part->Inv_id }}</small>
    </div>

    <table>
        <tr>
            <td>NO</td>
            <td>: {{ $report->id }}</td>
        </tr>
        <tr>
            <td>DATE</td>
            <td>: {{ $report->created_at->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td>INV ID</td>
            <td>: {{ $report->part->Inv_id }}</td>
        </tr>
        <tr>
            <td>PART</td>
            <td>: {{ \Str::limit($report->part->Part_name, 30) }}</td>
        </tr>
        <tr>
            <td>PART NO</td>
            <td>: {{ $report->part->Part_number }}</td>
        </tr>
        <tr>
            <td>CUST</td>
            <td>: {{ $report->part->customer->username ?? '-' }}</td>
        </tr>
        <tr>
            <td>TYPE</td>
            <td>: {{ $report->part->category->name ?? '-' }}</td>
        </tr>
        <tr>
            <td>STATUS</td>
            <td>: {{ $report->status }}</td>
        </tr>
        <tr>
            <td>PLANT</td>
            <td>: {{ $report->areaHead->plan->name ?? '-' }}</td>
        </tr>
        <tr>
            <td>AREA</td>
            <td>: {{ $report->areaHead->nama_area ?? ($report->part->area->nama_area ?? '-') }}</td>
        </tr>
        <tr>
            <td>PIC</td>
            <td>: {{ $report->user->username ?? '-' }}</td>
        </tr>
    </table>

    <hr>

    <table class="center bold">
        <tr>
            <td>PLAN</td>
            <td>ACTUAL</td>
        </tr>
        <tr>
            <td>{{ $report->part->inventory->plan_stock ?? '--' }}</td>
            <td>{{ $report->Total_qty }}</td>
        </tr>
    </table>

    <hr>

    <div class="footer">
        <strong><span> &copy;2025</span></strong> Sto Management System
    </div>
</div>
