<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 5mm;
            size: a4 landscape;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fff;
        }
        .cards-grid {
            width: 280mm;
            margin: 0 auto;
        }
        .row-container {
            width: 100%;
            clear: both;
            height: 86mm;
            margin-bottom: 5mm;
            overflow: hidden;
        }
        .id-card {
            width: 54mm;
            height: 85.6mm;
            float: left;
            position: relative;
            overflow: hidden;
            margin-right: 4mm;
            background: #fff;
            border: 0.1mm solid #ddd;
            page-break-inside: avoid;
        }
        .id-card:last-child {
            margin-right: 0;
        }
        .page-break {
            clear: both;
            page-break-after: always;
        }
        .template-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        .content {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10;
        }
        .qr-code-front {
            position: absolute;
            top: 0;
            left: 0;
            width: 18mm;
            height: 18mm;
            background: white;
            padding: 0.5mm;
            z-index: 20;
        }
        .qr-code-front img {
            width: 100%;
            height: 100%;
            display: block;
        }
        .photo-img {
            position: absolute;
            top: 23mm;
            left: 7mm;
            width: 40mm;
            height: 40mm;
            object-fit: cover;
            border: 1px solid #eee;
            z-index: 15;
        }
        .details {
            position: absolute;
            bottom: 12mm;
            left: 2mm;
            right: 2mm;
            text-align: center;
            z-index: 20;
        }
        .name {
            font-size: 10pt;
            font-weight: bold;
            color: #000;
            text-transform: uppercase;
            margin-bottom: 1mm;
            line-height: 1.1;
        }
        .position {
            font-size: 8pt;
            color: #333;
            font-weight: bold;
        }
        .id-number {
            position: absolute;
            bottom: 5mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9pt;
            font-family: 'Courier', monospace;
            font-weight: bold;
            z-index: 20;
        }
    </style>
</head>
<body>
    @php
        $frontBase64 = '';
        if ($template->front_image_path && file_exists(public_path('storage/' . $template->front_image_path))) {
            $frontData = file_get_contents(public_path('storage/' . $template->front_image_path));
            $frontBase64 = 'data:image/' . pathinfo($template->front_image_path, PATHINFO_EXTENSION) . ';base64,' . base64_encode($frontData);
        }

        $backBase64 = '';
        if ($template->back_image_path && file_exists(public_path('storage/' . $template->back_image_path))) {
            $backData = file_get_contents(public_path('storage/' . $template->back_image_path));
            $backBase64 = 'data:image/' . pathinfo($template->back_image_path, PATHINFO_EXTENSION) . ';base64,' . base64_encode($backData);
        }
    @endphp

    <div class="cards-grid">
        @foreach($employees->chunk(2) as $employeeChunk)
            <div class="row-container">
                @foreach($employeeChunk as $employee)
                    <!-- FRONT SIDE -->
                    <div class="id-card">
                        @if($frontBase64)
                            <img src="{{ $frontBase64 }}" class="template-bg">
                        @endif
                        <div class="content">
                            @php
                                $qrPath = public_path('storage/qr_codes/' . $employee->qr_code . '.svg');
                                $qrBase64 = '';
                                if (file_exists($qrPath)) {
                                    $qrData = file_get_contents($qrPath);
                                    $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrData);
                                }
                            @endphp
                            @if($qrBase64)
                                <div class="qr-code-front">
                                    <img src="{{ $qrBase64 }}">
                                </div>
                            @endif

                            @if($employee->profile_photo && file_exists(public_path('storage/' . $employee->profile_photo)))
                                @php
                                    $photoData = file_get_contents(public_path('storage/' . $employee->profile_photo));
                                    $photoBase64 = 'data:image/' . pathinfo($employee->profile_photo, PATHINFO_EXTENSION) . ';base64,' . base64_encode($photoData);
                                @endphp
                                <img src="{{ $photoBase64 }}" class="photo-img">
                            @endif
                            
                            <div class="details">
                                <div class="name">{{ $employee->user->name }}</div>
                                <div class="position">{{ $employee->activeEmploymentRecord->position->name ?? 'Employee' }}</div>
                            </div>
                            <div class="id-number">{{ $employee->employee_code }}</div>
                        </div>
                    </div>

                    <!-- BACK SIDE -->
                    <div class="id-card">
                        @if($backBase64)
                            <img src="{{ $backBase64 }}" class="template-bg">
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Page break after 2 rows (4 employees) --}}
            @if($loop->iteration % 2 == 0 && !$loop->last)
                <div class="page-break"></div>
            @endif
        @endforeach
    </div>
</body>
</html>
