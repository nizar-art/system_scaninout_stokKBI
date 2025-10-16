<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sedang Dalam Perbaikan - {{ config('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --dark: #1f2937;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f9fafb;
            color: var(--dark);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            text-align: center;
            line-height: 1.6;
        }

        .maintenance-container {
            max-width: 600px;
            padding: 2rem;
            margin: 0 auto;
        }

        .maintenance-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        h1 {
            color: var(--primary);
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .maintenance-message {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            background: #f3f4f6;
            padding: 1.5rem;
            border-radius: 0.5rem;
            border-left: 4px solid var(--primary);
        }

        .maintenance-details {
            text-align: left;
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .maintenance-details h3 {
            margin-top: 0;
            color: var(--primary);
        }

        .contact-info {
            margin-top: 2rem;
            font-size: 0.95rem;
        }

        .btn {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 600;
            margin-top: 1rem;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #4338ca;
            transform: translateY(-2px);
        }

        .progress-container {
            width: 100%;
            background-color: #e5e7eb;
            border-radius: 9999px;
            margin: 2rem 0;
            height: 8px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            border-radius: 9999px;
            background-color: var(--primary);
            width: 65%;
            animation: progress 2s ease-in-out infinite;
        }

        @keyframes progress {
            0% {
                width: 65%;
            }

            50% {
                width: 70%;
            }

            100% {
                width: 65%;
            }
        }
    </style>
</head>

<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <path d="M9.5 9h5"></path>
                <path d="M9.5 12h5"></path>
                <path d="M9.5 15h5"></path>
            </svg>
        </div>

        <h1>Sedang Dalam Perbaikan</h1>

        <div class="maintenance-message">
            Mohon maaf atas ketidaknyamanan ini. Kami sedang melakukan perbaikan dan peningkatan sistem untuk pengalaman
            yang lebih baik.
        </div>

        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>

        <div class="maintenance-details">
            <h3>Informasi Maintenance:</h3>
            <p><strong>Waktu Mulai:</strong> {{ now()->format('d F Y, H:i') }}</p>
            <p><strong>Perkiraan Selesai:</strong> {{ now()->addHours(8)->format('d F Y, H:i') }}</p>
            <p><strong>Status:</strong> Dalam Proses</p>
        </div>

        <footer id="footer" class="footer">
            <div class="copyright" style="text-align: center">
                <strong><span> &copy;</span></strong>By Salman fauzi
            </div>
        </footer>
    </div>
</body>

</html>
