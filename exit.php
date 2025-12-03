<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Exit - QRGate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 600px; 
            margin: 0 auto; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container { 
            background: white; 
            padding: 40px; 
            border-radius: 20px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.3); 
            text-align: center;
        }
        h2 { 
            color: #333; 
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .scan-area {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin: 30px 0;
            border: 3px dashed #667eea;
        }
        .qr-icon {
            font-size: 80px;
            margin-bottom: 15px;
        }
        input {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 18px;
            box-sizing: border-box;
            text-align: center;
            font-family: monospace;
        }
        input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            width: 100%;
            margin-top: 20px;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        button:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
        }
        .message {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 16px;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffeaa7;
        }
        .visitor-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: left;
        }
        .visitor-card p {
            margin: 10px 0;
            font-size: 16px;
        }
        .visitor-card strong {
            display: inline-block;
            width: 120px;
        }
        .time-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            margin-top: 10px;
        }
        .instructions {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #2196F3;
        }
        .instructions h3 {
            margin-top: 0;
            color: #1976D2;
        }
        .instructions ul {
            text-align: left;
            margin: 10px 0;
        }
        .instructions li {
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🚪 Exit Gate</h2>
        <p class="subtitle">Scan your QR code to log your exit</p>

        <div class="instructions">
            <h3>📱 How to Exit</h3>
            <ul>
                <li>Scan your QR code with the scanner</li>
                <li>Or manually enter your QR code below</li>
                <li>Press "Log Exit" to record your departure</li>
            </ul>
        </div>

        <div class="scan-area">
            <div class="qr-icon">📷</div>
            <input 
                type="text" 
                id="qrInput" 
                placeholder="Enter QR Code or Scan"
                autofocus
                autocomplete="off">
        </div>

        <button id="exitBtn" onclick="logExit()">🚪 Log Exit</button>

        <div id="messageArea"></div>
        <div id="visitorInfo"></div>
    </div>

    <script>
        // Auto-submit on Enter key
        document.getElementById('qrInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                logExit();
            }
        });

        // Auto-submit when QR scanner inputs data (typically ends with Enter)
        let scanBuffer = '';
        let scanTimeout;

        document.getElementById('qrInput').addEventListener('input', function(e) {
            clearTimeout(scanTimeout);
            scanBuffer = this.value;
            
            // If input is long enough (QR codes are typically 10+ chars), auto-submit after brief delay
            if (scanBuffer.length >= 10) {
                scanTimeout = setTimeout(() => {
                    logExit();
                }, 300);
            }
        });

        function showMessage(text, type = 'success') {
            const msgArea = document.getElementById('messageArea');
            msgArea.innerHTML = `<div class="message ${type}">${text}</div>`;
            
            // Auto-clear after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(() => {
                    msgArea.innerHTML = '';
                }, 5000);
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async function logExit() {
            const qrCode = document.getElementById('qrInput').value.trim();
            const btn = document.getElementById('exitBtn');
            
            if (!qrCode) {
                showMessage('⚠️ Please enter or scan a QR code', 'warning');
                return;
            }

            btn.disabled = true;
            btn.textContent = '⏳ Processing...';

            try {
                const response = await fetch('log_exit.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `qr_code=${encodeURIComponent(qrCode)}`
                });

                const data = await response.json();

                if (data.ok) {
                    showMessage(`✅ ${data.msg}`, 'success');
                    
                    // Display visitor info
                    const visitorInfo = document.getElementById('visitorInfo');
                    visitorInfo.innerHTML = `
                        <div class="visitor-card">
                            <p><strong>👤 Name:</strong> ${escapeHtml(data.visitor_name)}</p>
                            <p><strong>📧 Email:</strong> ${escapeHtml(data.email || 'N/A')}</p>
                            <p><strong>📞 Phone:</strong> ${escapeHtml(data.phone || 'N/A')}</p>
                            <p><strong>🎯 Purpose:</strong> ${escapeHtml(data.purpose || 'N/A')}</p>
                            <p><strong>🏠 Host:</strong> ${escapeHtml(data.host || 'N/A')}</p>
                            <div class="time-badge">
                                🕐 Entered: ${escapeHtml(data.entry_time)}<br>
                                🕐 Exited: ${escapeHtml(data.exit_time)}
                            </div>
                        </div>
                    `;

                    // Clear input after 2 seconds
                    setTimeout(() => {
                        document.getElementById('qrInput').value = '';
                        document.getElementById('qrInput').focus();
                        visitorInfo.innerHTML = '';
                    }, 5000);
                } else {
                    showMessage(`❌ ${data.msg}`, 'error');
                    document.getElementById('visitorInfo').innerHTML = '';
                }
            } catch (error) {
                showMessage(`❌ Error: ${error.message}`, 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = '🚪 Log Exit';
            }
        }
    </script>
</body>
</html>