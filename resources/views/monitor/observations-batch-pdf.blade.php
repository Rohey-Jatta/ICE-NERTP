<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.6;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #2c3e50;
            margin-bottom: 30px;
            padding-bottom: 15px;
        }
        .header-left {
            flex: 1;
        }
        .header-left h1 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 5px;
        }
        .header-left p {
            color: #666;
            font-size: 12px;
        }
        .header-right {
            text-align: right;
            font-size: 11px;
            color: #666;
        }
        .summary-box {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 25px;
            border-left: 4px solid #3498db;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }
        .summary-item {
            background: white;
            padding: 10px;
            border-radius: 3px;
            text-align: center;
            border: 1px solid #bdc3c7;
        }
        .summary-item strong {
            display: block;
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 3px;
        }
        .summary-item small {
            display: block;
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
        }
        .observation-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 3px;
            padding: 15px;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .observation-card h3 {
            color: #2c3e50;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .observation-card .badges {
            margin-bottom: 10px;
            font-size: 11px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            color: white;
            margin-right: 5px;
        }
        .badge.type-general { background: #3498db; }
        .badge.type-positive { background: #27ae60; }
        .badge.type-process_concern { background: #f39c12; }
        .badge.type-irregularity { background: #e67e22; }
        .badge.type-incident { background: #c0392b; }
        
        .badge.severity-low { background: #27ae60; }
        .badge.severity-medium { background: #f39c12; }
        .badge.severity-high { background: #e67e22; }
        .badge.severity-critical { background: #c0392b; }
        
        .observation-meta {
            font-size: 11px;
            color: #666;
            margin-bottom: 8px;
            border-bottom: 1px solid #ecf0f1;
            padding-bottom: 8px;
        }
        .observation-meta span {
            margin-right: 15px;
        }
        .observation-text {
            font-size: 12px;
            color: #333;
            line-height: 1.5;
            padding: 10px;
            background: #f9f9f9;
            border-left: 3px solid #3498db;
            margin: 8px 0;
            border-radius: 2px;
        }
        .footer {
            border-top: 1px solid #ddd;
            padding-top: 10px;
            margin-top: 30px;
            font-size: 10px;
            color: #666;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin: 5px 0;
        }
        table td {
            padding: 5px;
            border-bottom: 1px solid #ddd;
        }
        table tr:nth-child(even) {
            background: #f9f9f9;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-left">
            <h1>🇿🇦 IEC Election Monitor Report</h1>
            <p>Batch Observations Report - Independent Electoral Commission</p>
        </div>
        <div class="header-right">
            <p><strong>Report Generated:</strong></p>
            <p>{{ $generatedAt }}</p>
        </div>
    </div>

    <!-- Summary -->
    <div class="summary-box">
        <strong>📊 Report Summary</strong>
        <div class="summary-grid">
            <div class="summary-item">
                <strong>{{ $count }}</strong>
                <small>Total Observations</small>
            </div>
            <div class="summary-item">
                <strong>{{ $observations->where('severity', 'critical')->count() }}</strong>
                <small>Critical Issues</small>
            </div>
            <div class="summary-item">
                <strong>{{ $observations->whereIn('observation_type', ['irregularity', 'incident'])->count() }}</strong>
                <small>Flagged Items</small>
            </div>
            <div class="summary-item">
                <strong>{{ $observations->where('is_public', true)->count() }}</strong>
                <small>Public Observations</small>
            </div>
        </div>
    </div>

    <!-- Observations List -->
    @foreach ($observations as $idx => $observation)
    <div class="observation-card">
        <h3>{{ $idx + 1 }}. {{ $observation->title }}</h3>
        
        <div class="badges">
            <span class="badge type-{{ $observation->observation_type }}">{{ ucfirst(str_replace('_', ' ', $observation->observation_type)) }}</span>
            <span class="badge severity-{{ $observation->severity }}">{{ ucfirst($observation->severity) }}</span>
            @if (!$observation->is_public)
            <span class="badge" style="background: #95a5a6;">🔒 Private</span>
            @endif
        </div>

        <div class="observation-meta">
            <span><strong>📍 Station:</strong> {{ $observation->station_code }} - {{ $observation->station_name }}</span>
            <span><strong>🕐 Observed:</strong> {{ \Carbon\Carbon::parse($observation->observed_at)->format('Y-m-d H:i') }}</span>
            @if ($observation->latitude)
            <span><strong>📍 Location:</strong> {{ $observation->latitude }}, {{ $observation->longitude }}</span>
            @endif
        </div>

        <div class="observation-text">{{ substr($observation->observation, 0, 300) }}{{ strlen($observation->observation) > 300 ? '...' : '' }}</div>
    </div>
    @endforeach

    <!-- Footer -->
    <div class="footer">
        <div>
            <p>This is an official IEC document. Unauthorized distribution is prohibited.</p>
        </div>
        <div style="text-align: right;">
            <p>Generated: {{ $generatedAt }} | Monitor: {{ $monitor->id }}</p>
        </div>
    </div>
</body>
</html>
