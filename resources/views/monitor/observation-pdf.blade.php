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
        .reference-box {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 11px;
            border-left: 4px solid #e74c3c;
        }
        .reference-box strong {
            color: #2c3e50;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            background: #34495e;
            color: white;
            padding: 10px 15px;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 3px;
        }
        .section-content {
            padding: 0 10px;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        .grid-item {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 3px;
        }
        .grid-item label {
            display: block;
            font-weight: bold;
            color: #2c3e50;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .grid-item value {
            display: block;
            color: #333;
            font-size: 12px;
            word-wrap: break-word;
        }
        .observation-text {
            border-left: 4px solid #3498db;
            padding: 12px;
            background: #ecf0f1;
            margin: 15px 0;
            border-radius: 3px;
            font-size: 12px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            margin-right: 5px;
            color: white;
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
        
        .photos-section {
            margin: 15px 0;
        }
        .photo-item {
            page-break-inside: avoid;
            margin-bottom: 15px;
        }
        .photo-item img {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .photo-caption {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        .documents-section {
            margin: 15px 0;
        }
        .document-item {
            background: #f9f9f9;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
            margin-bottom: 8px;
            font-size: 11px;
        }
        .document-item .name {
            font-weight: bold;
            color: #2c3e50;
        }
        .document-item .size {
            color: #666;
            font-size: 10px;
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
        .footer-left {
            flex: 1;
        }
        .footer-right {
            text-align: right;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 11px;
        }
        table th {
            background: #ecf0f1;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid #bdc3c7;
        }
        table td {
            padding: 8px;
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
            <p>Independent Electoral Commission - South Africa</p>
        </div>
        <div class="header-right">
            <p><strong>Report Generated:</strong></p>
            <p>{{ $generatedAt }}</p>
        </div>
    </div>

    <!-- Reference Box -->
    <div class="reference-box">
        <strong>Reference Number:</strong> {{ $referenceNumber }} | 
        <strong>Observation ID:</strong> {{ $observation->id }} |
        <strong>Monitor ID:</strong> {{ $monitor->id }}
    </div>

    <!-- Observation Details -->
    <div class="section">
        <div class="section-title">📋 Observation Information</div>
        <div class="section-content">
            <div class="grid">
                <div class="grid-item">
                    <label>Observation Type</label>
                    <span class="badge type-{{ $observation->observation_type }}">{{ ucfirst(str_replace('_', ' ', $observation->observation_type)) }}</span>
                </div>
                <div class="grid-item">
                    <label>Severity Level</label>
                    <span class="badge severity-{{ $observation->severity }}">{{ ucfirst($observation->severity) }}</span>
                </div>
                <div class="grid-item">
                    <label>Observation Title</label>
                    <value>{{ $observation->title }}</value>
                </div>
                <div class="grid-item">
                    <label>Observed Date & Time</label>
                    <value>{{ \Carbon\Carbon::parse($observation->observed_at)->format('Y-m-d H:i') }}</value>
                </div>
            </div>
        </div>
    </div>

    <!-- Station Information -->
    <div class="section">
        <div class="section-title">📍 Polling Station</div>
        <div class="section-content">
            <table>
                <tr>
                    <td><strong>Station Code:</strong></td>
                    <td>{{ $observation->station_code }}</td>
                    <td><strong>Station Name:</strong></td>
                    <td>{{ $observation->station_name }}</td>
                </tr>
                <tr>
                    <td><strong>Ward:</strong></td>
                    <td colspan="3">{{ $observation->ward_name ?? 'N/A' }}</td>
                </tr>
                @if ($observation->latitude || $observation->longitude)
                <tr>
                    <td><strong>GPS Location:</strong></td>
                    <td colspan="3">{{ $observation->latitude }}, {{ $observation->longitude }}</td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    <!-- Observation Details -->
    <div class="section">
        <div class="section-title">📝 Detailed Observation</div>
        <div class="section-content">
            <div class="observation-text">{{ $observation->observation }}</div>
        </div>
    </div>

    <!-- Supporting Photos -->
    @if (!empty($photos))
    <div class="section">
        <div class="section-title">📷 Supporting Photos</div>
        <div class="section-content photos-section">
            @foreach ($photos as $idx => $photo)
            <div class="photo-item">
                <img src="{{ public_path('storage/' . $photo) }}" alt="Photo {{ $idx + 1 }}">
                <div class="photo-caption">Photo {{ $idx + 1 }} - Submitted on {{ \Carbon\Carbon::parse($observation->created_at)->format('Y-m-d') }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Supporting Documents -->
    @if (!empty($documents))
    <div class="section">
        <div class="section-title">📄 Supporting Documents</div>
        <div class="section-content documents-section">
            @foreach ($documents as $idx => $doc)
            <div class="document-item">
                <div class="name">📎 {{ $doc['name'] ?? 'Document ' . ($idx + 1) }}</div>
                <div class="size">Size: {{ number_format(($doc['size'] ?? 0) / 1024 / 1024, 2) }} MB</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Monitor Information -->
    <div class="section">
        <div class="section-title">👤 Monitor Information</div>
        <div class="section-content">
            <table>
                <tr>
                    <td><strong>Monitor ID:</strong></td>
                    <td>{{ $monitor->id }}</td>
                    <td><strong>Visibility:</strong></td>
                    <td>{{ $observation->is_public ? '🌐 Public' : '🔒 Private' }}</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-left">
            <p>This is an official IEC document. Unauthorized distribution is prohibited.</p>
        </div>
        <div class="footer-right">
            <p>Page 1 of 1</p>
        </div>
    </div>
</body>
</html>
