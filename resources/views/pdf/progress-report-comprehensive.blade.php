<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Progress Report - {{ $client->first_name }} {{ $client->last_name }}</title>
    <style>
        @page {
            margin: 20mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #333;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 28pt;
        }

        .header .subtitle {
            color: #666;
            font-size: 12pt;
            margin-top: 10px;
        }

        .client-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
        }

        .client-info h3 {
            margin-top: 0;
            color: #007bff;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }

        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }

        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 150px;
        }

        .info-value {
            display: table-cell;
        }

        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }

        .section h2 {
            color: #007bff;
            border-bottom: 2px solid #007bff;
            padding-bottom: 8px;
            margin-bottom: 15px;
            font-size: 18pt;
        }

        .metrics-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .metric-box {
            display: table-cell;
            background: #f8f9fa;
            padding: 15px;
            text-align: center;
            border: 1px solid #dee2e6;
            width: 25%;
        }

        .metric-label {
            font-size: 9pt;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .metric-value {
            font-size: 20pt;
            font-weight: bold;
            color: #007bff;
        }

        .metric-unit {
            font-size: 10pt;
            color: #666;
        }

        .progress-bar-container {
            background: #e9ecef;
            height: 25px;
            border-radius: 4px;
            margin: 10px 0;
            position: relative;
        }

        .progress-bar {
            background: linear-gradient(to right, #28a745, #007bff);
            height: 100%;
            border-radius: 4px;
            text-align: center;
            line-height: 25px;
            color: white;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        th {
            background: #007bff;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
        }

        tr:nth-child(even) {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 9pt;
            font-weight: bold;
        }

        .badge-success {
            background: #28a745;
            color: white;
        }

        .badge-warning {
            background: #ffc107;
            color: #333;
        }

        .badge-danger {
            background: #dc3545;
            color: white;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #dee2e6;
            padding-top: 10px;
        }

        .highlight-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
        }

        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <h1>Progress Report</h1>
        <div class="subtitle">
            {{ $start_date->format('F j, Y') }} - {{ $end_date->format('F j, Y') }}
        </div>
        <div class="subtitle">
            Generated on {{ $generated_at->format('F j, Y \a\t g:i A') }}
        </div>
    </div>

    {{-- Client Information --}}
    <div class="client-info">
        <h3>Client Information</h3>
        <div class="info-row">
            <div class="info-label">Client Name:</div>
            <div class="info-value">{{ $client->first_name }} {{ $client->last_name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Coach:</div>
            <div class="info-value">{{ $coach->first_name }} {{ $coach->last_name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Report Period:</div>
            <div class="info-value">{{ $data['period_summary']['total_days'] }} days ({{ $data['period_summary']['total_weeks'] }} weeks)</div>
        </div>
    </div>

    {{-- Overall Progress Score --}}
    <div class="section">
        <h2>Overall Progress Score</h2>
        <div class="progress-bar-container">
            <div class="progress-bar" style="width: {{ $data['progress_score'] }}%">
                {{ $data['progress_score'] }}%
            </div>
        </div>
        @if($data['progress_score'] >= 80)
            <div class="success-box">
                <strong>Excellent Progress!</strong> You're doing an outstanding job staying on track with your goals.
            </div>
        @elseif($data['progress_score'] >= 60)
            <div class="highlight-box">
                <strong>Good Progress!</strong> Keep up the momentum to reach your full potential.
            </div>
        @endif
    </div>

    {{-- Weight Progress --}}
    @if($data['weight_progress']['starting_weight'])
    <div class="section">
        <h2>Weight Progress</h2>
        <div class="metrics-grid">
            <div class="metric-box">
                <div class="metric-label">Starting Weight</div>
                <div class="metric-value">{{ number_format($data['weight_progress']['starting_weight'], 1) }}</div>
                <div class="metric-unit">lbs</div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Current Weight</div>
                <div class="metric-value">{{ number_format($data['weight_progress']['ending_weight'], 1) }}</div>
                <div class="metric-unit">lbs</div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Total Change</div>
                <div class="metric-value" style="color: {{ $data['weight_progress']['total_change'] < 0 ? '#28a745' : '#dc3545' }}">
                    {{ $data['weight_progress']['total_change'] > 0 ? '+' : '' }}{{ number_format($data['weight_progress']['total_change'], 1) }}
                </div>
                <div class="metric-unit">lbs</div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Avg Weekly Change</div>
                <div class="metric-value">{{ number_format($data['weight_progress']['avg_weekly_change'], 1) }}</div>
                <div class="metric-unit">lbs/week</div>
            </div>
        </div>
    </div>
    @endif

    {{-- Body Composition --}}
    @if($data['body_composition']['starting_body_fat'])
    <div class="section">
        <h2>Body Composition</h2>
        <div class="metrics-grid">
            <div class="metric-box">
                <div class="metric-label">Starting Body Fat</div>
                <div class="metric-value">{{ number_format($data['body_composition']['starting_body_fat'], 1) }}</div>
                <div class="metric-unit">%</div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Current Body Fat</div>
                <div class="metric-value">{{ number_format($data['body_composition']['ending_body_fat'], 1) }}</div>
                <div class="metric-unit">%</div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Body Fat Change</div>
                <div class="metric-value" style="color: {{ $data['body_composition']['body_fat_change'] < 0 ? '#28a745' : '#dc3545' }}">
                    {{ $data['body_composition']['body_fat_change'] > 0 ? '+' : '' }}{{ number_format($data['body_composition']['body_fat_change'], 1) }}
                </div>
                <div class="metric-unit">%</div>
            </div>
        </div>
    </div>
    @endif

    {{-- Compliance Metrics --}}
    <div class="section">
        <h2>Compliance & Activity</h2>
        <div class="metrics-grid">
            <div class="metric-box">
                <div class="metric-label">Workout Compliance</div>
                <div class="metric-value">{{ number_format($data['compliance_metrics']['workout_compliance'], 1) }}</div>
                <div class="metric-unit">%</div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Workouts Completed</div>
                <div class="metric-value">{{ $data['compliance_metrics']['total_workouts_completed'] }}</div>
                <div class="metric-unit">of {{ $data['compliance_metrics']['total_workouts_planned'] }}</div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Avg Meals Logged</div>
                <div class="metric-value">{{ number_format($data['compliance_metrics']['avg_meals_logged'], 1) }}</div>
                <div class="metric-unit">per week</div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Avg Water Intake</div>
                <div class="metric-value">{{ number_format($data['compliance_metrics']['avg_water_intake'], 1) }}</div>
                <div class="metric-unit">oz/day</div>
            </div>
        </div>
    </div>

    {{-- Wellness Trends --}}
    @if(!empty($data['wellness_trends']))
    <div class="section">
        <h2>Wellness Trends</h2>
        <table>
            <thead>
                <tr>
                    <th>Metric</th>
                    <th>Average Score</th>
                    <th>Rating</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Energy Level</td>
                    <td>{{ number_format($data['wellness_trends']['avg_energy'], 1) }}/10</td>
                    <td>
                        @if($data['wellness_trends']['avg_energy'] >= 7)
                            <span class="badge badge-success">Excellent</span>
                        @elseif($data['wellness_trends']['avg_energy'] >= 5)
                            <span class="badge badge-warning">Good</span>
                        @else
                            <span class="badge badge-danger">Needs Improvement</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>Mood</td>
                    <td>{{ number_format($data['wellness_trends']['avg_mood'], 1) }}/10</td>
                    <td>
                        @if($data['wellness_trends']['avg_mood'] >= 7)
                            <span class="badge badge-success">Excellent</span>
                        @elseif($data['wellness_trends']['avg_mood'] >= 5)
                            <span class="badge badge-warning">Good</span>
                        @else
                            <span class="badge badge-danger">Needs Improvement</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>Sleep Quality</td>
                    <td>{{ number_format($data['wellness_trends']['avg_sleep_quality'], 1) }}/10</td>
                    <td>
                        @if($data['wellness_trends']['avg_sleep_quality'] >= 7)
                            <span class="badge badge-success">Excellent</span>
                        @elseif($data['wellness_trends']['avg_sleep_quality'] >= 5)
                            <span class="badge badge-warning">Good</span>
                        @else
                            <span class="badge badge-danger">Needs Improvement</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>Average Sleep Hours</td>
                    <td>{{ number_format($data['wellness_trends']['avg_sleep_hours'], 1) }} hours</td>
                    <td>
                        @if($data['wellness_trends']['avg_sleep_hours'] >= 7)
                            <span class="badge badge-success">Optimal</span>
                        @elseif($data['wellness_trends']['avg_sleep_hours'] >= 6)
                            <span class="badge badge-warning">Acceptable</span>
                        @else
                            <span class="badge badge-danger">Insufficient</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>Stress Level</td>
                    <td>{{ number_format($data['wellness_trends']['avg_stress'], 1) }}/10</td>
                    <td>
                        @if($data['wellness_trends']['avg_stress'] <= 3)
                            <span class="badge badge-success">Low</span>
                        @elseif($data['wellness_trends']['avg_stress'] <= 6)
                            <span class="badge badge-warning">Moderate</span>
                        @else
                            <span class="badge badge-danger">High</span>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    {{-- Weekly Check-ins Summary --}}
    @if($data['checkins']->count() > 0)
    <div class="section">
        <h2>Weekly Check-ins Summary</h2>
        <table>
            <thead>
                <tr>
                    <th>Week</th>
                    <th>Date</th>
                    <th>Weight</th>
                    <th>Compliance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['checkins'] as $checkin)
                <tr>
                    <td>Week {{ $checkin->week_number }}</td>
                    <td>{{ $checkin->checkin_date->format('M j, Y') }}</td>
                    <td>{{ $checkin->current_weight ? number_format($checkin->current_weight, 1) . ' lbs' : 'N/A' }}</td>
                    <td>{{ $checkin->compliance_rate }}%</td>
                    <td>
                        @if($checkin->status === 'reviewed')
                            <span class="badge badge-success">Reviewed</span>
                        @elseif($checkin->status === 'submitted')
                            <span class="badge badge-warning">Submitted</span>
                        @else
                            <span class="badge badge-danger">Pending</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Coach Notes and Recommendations --}}
    <div class="section">
        <h2>Coach's Notes & Recommendations</h2>
        <div class="highlight-box">
            <p><strong>Generated Report Summary:</strong></p>
            <p>This report covers {{ $data['period_summary']['total_weeks'] }} weeks of progress tracking.
               @if($data['progress_score'] >= 80)
                   The client has shown excellent dedication and consistency in following the program.
               @elseif($data['progress_score'] >= 60)
                   The client has made good progress with room for improvement in consistency.
               @else
                   The client would benefit from increased adherence to the program recommendations.
               @endif
            </p>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <p>BodyF1rst Progress Report | Confidential Client Information</p>
        <p>Generated by {{ $coach->first_name }} {{ $coach->last_name }} | {{ $generated_at->format('F j, Y') }}</p>
    </div>
</body>
</html>
