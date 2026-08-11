<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AI Kanal Manager Bot — Mini App</title>
    <!-- Telegram Web App SDK -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js for statistics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Tailwind CSS for utility styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        wallet: {
                            bg: '#151f2b',
                            card: '#212d3b',
                            border: 'rgba(255, 255, 255, 0.08)',
                            textPrimary: '#ffffff',
                            textSecondary: '#8093a8',
                            blue: '#2f87f5',
                            green: '#00c076',
                            red: '#f64c5e',
                            purple: '#8c65f7',
                            pillActive: '#2a3848',
                            navBg: '#1e2936'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #151f2b;
            min-height: 100vh;
            color: #ffffff;
            -webkit-tap-highlight-color: transparent;
        }
        
        /* Custom UI classes to mimic Wallet app */
        .wallet-card {
            background-color: #212d3b;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
        }

        .wallet-pill-active {
            background-color: #2a3848;
            color: #ffffff;
        }

        .wallet-input {
            background-color: #1a2430;
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #ffffff;
            transition: all 0.2s ease-in-out;
        }
        
        .wallet-input:focus {
            border-color: #2f87f5;
            outline: none;
            box-shadow: 0 0 0 2px rgba(47, 135, 245, 0.2);
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 4px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.1);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.08);
            border-radius: 2px;
        }
        
        /* Interactive animations */
        .btn-active:active {
            transform: scale(0.96);
            transition: transform 0.1s ease;
        }
    </style>
</head>
<body class="overflow-x-hidden antialiased select-none">
    <div id="app"></div>

    <!-- Vue 3 directly loaded from CDN -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="{{ secure_asset('js/miniapp.js') }}?v={{ time() }}"></script>
</body>
</html>
