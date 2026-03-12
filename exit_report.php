<?php
require 'auth.php';
requireLogin(); // Protect this page

$username = $_SESSION['username'] ?? 'User';
$email = $_SESSION['email'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Exit Analytics - QRGate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            font-size: 32px;
            margin: 0;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-details {
            text-align: right;
        }
        .username {
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
        }
        .user-email {
            font-size: 12px;
            color: #7f8c8d;
        }
        .logout-btn {
            padding: 10px 20px;
            background: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s, transform 0.2s;
            display: inline-block;
        }
        .logout-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 5px solid #3498db;
        }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.danger { border-left-color: #e74c3c; }
        .stat-value {
            font-size: 42px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .table-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .filter-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .filter-controls select,
        .filter-controls input {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #34495e;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge.exited {
            background: #d4edda;
            color: #155724;
        }
        .badge.inside {
            background: #fff3cd;
            color: #856404;
        }
        .badge.expired {
            background: #f8d7da;
            color: #721c24;
        }
        /* new status badges matching dashboard logic */
        .badge.invalid {
            background: #fce4ec;
            color: #880e4f;
        }
        .badge.valid {
            background: #e0f7fa;
            color: #006064;
        }
        .duration {
            font-weight: bold;
            color: #3498db;
        }
        .loading {
            text-align: center;
            padding: 50px;
            font-size: 18px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Exit Analytics Dashboard</h1>
            <div class="user-info">
                <div class="user-details">
                    <div class="username">👤 <?php echo htmlspecialchars($username); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
                </div>
                <a href="auth.php?action=logout" class="logout-btn">🚪 Logout</a>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Visitors Today</div>
                <div class="stat-value" id="totalToday">0</div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">Exited Today</div>
                <div class="stat-value" id="exitedToday">0</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-label">Still Inside</div>
                <div class="stat-value" id="stillInside">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Avg Visit Duration</div>
                <div class="stat-value" id="avgDuration">--</div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h2>📋 Visitor Log</h2>
                <div class="filter-controls">
                    <select id="filterStatus" onchange="applyFilters()">
                        <option value="all">All Status</option>
                        <option value="exited">Exited</option>
                        <option value="inside">Still Inside</option>
                        <option value="expired">Expired</option>
                        <option value="valid">Pending/Valid</option>
                        <option value="invalid">Invalid</option>
                    </select>
                    <input type="date" id="filterDate" onchange="applyFilters()" 
                           value="<?php echo date('Y-m-d'); ?>">
                    <button onclick="refreshData()" style="padding: 8px 15px; background: white; border: none; border-radius: 5px; cursor: pointer;">
                        🔄 Refresh
                    </button>
                </div>
            </div>

            <div id="tableContent">
                <div class="loading">⏳ Loading data...</div>
            </div>
        </div>
    </div>

    <script>
        let allVisitors = [];

        async function fetchData() {
            try {
                const response = await fetch('get_visitors.php');
                const data = await response.json();
                
                if (data.ok) {
                    allVisitors = data.data;
                    updateStats();
                    applyFilters();
                }
            } catch (error) {
                console.error('Error fetching data:', error);
                document.getElementById('tableContent').innerHTML = 
                    '<div class="loading">❌ Error loading data</div>';
            }
        }

        // determine visitor status using same rules as Python dashboard
        function getVisitorStatus(visitor) {
            const raw = visitor.last_status;
            const last_status = String(raw || '').trim().toLowerCase();
            
            const expiryStr = visitor.expiry_at || '';
            let isExpired = false;
            if (expiryStr) {
                const expiry = new Date(expiryStr);
                const now = new Date();
                isExpired = expiry < now;
            }

            const exitStatuses = ['exited','exit','left','out','exited_by'];

            if (last_status === 'invalid') return 'Invalid';
            if (exitStatuses.includes(last_status)) return 'Exited';
            if (last_status === 'inside') {
                if (isExpired) return 'Expired';
                if (visitor.last_scan && visitor.last_scan !== 'None' && visitor.last_scan !== '') {
                    return 'Inside';
                }
            }
            if (last_status === 'expired') return 'Expired';
            if (isExpired) return 'Expired';
            if (visitor.last_scan && visitor.last_scan !== 'None' && visitor.last_scan !== '') {
                return 'Inside';
            }
            return 'Valid';
        }

        function updateStats() {
            const today = new Date().toISOString().split('T')[0];
            const todayVisitors = allVisitors.filter(v => 
                v.created_at && v.created_at.startsWith(today)
            );

            const statuses = todayVisitors.map(getVisitorStatus);
            const exited = statuses.filter(s => s === 'Exited').length;
            const inside = statuses.filter(s => s === 'Inside').length;
            const expired = statuses.filter(s => s === 'Expired').length;
            const valid = statuses.filter(s => s === 'Valid').length;

            document.getElementById('totalToday').textContent = todayVisitors.length;
            document.getElementById('exitedToday').textContent = exited;
            document.getElementById('stillInside').textContent = inside;
            // optionally show additional stats somewhere, e.g. console
            console.log(`Today stats - expired:${expired} valid:${valid}`);

            const durations = todayVisitors
                .filter(v => v.exit_time)
                .map(v => {
                    const entry = new Date(v.created_at);
                    const exit = new Date(v.exit_time);
                    return (exit - entry) / 1000 / 60;
                });

            if (durations.length > 0) {
                const avgMinutes = durations.reduce((a, b) => a + b, 0) / durations.length;
                const hours = Math.floor(avgMinutes / 60);
                const mins = Math.round(avgMinutes % 60);
                document.getElementById('avgDuration').textContent = 
                    hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;
            }
        }

        function applyFilters() {
            const statusFilter = document.getElementById('filterStatus').value;
            const dateFilter = document.getElementById('filterDate').value;
            
            let filtered = allVisitors;
            
            if (dateFilter) {
                filtered = filtered.filter(v => 
                    v.created_at && v.created_at.startsWith(dateFilter)
                );
            }
            
            if (statusFilter !== 'all') {
                filtered = filtered.filter(v => {
                    const s = getVisitorStatus(v).toLowerCase();
                    if (statusFilter === 'exited') return s === 'exited';
                    if (statusFilter === 'inside') return s === 'inside';
                    if (statusFilter === 'expired') return s === 'expired';
                    if (statusFilter === 'valid') return s === 'valid';
                    if (statusFilter === 'invalid') return s === 'invalid';
                    return true;
                });
            }
            
            renderTable(filtered);
        }

        function renderTable(visitors) {
            if (visitors.length === 0) {
                document.getElementById('tableContent').innerHTML = 
                    '<div class="loading">No visitors found for selected filters</div>';
                return;
            }
            
            const html = `
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Purpose</th>
                            <th>Entry Time</th>
                            <th>Exit Time</th>
                            <th>Duration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${visitors.map(v => `
                            <tr>
                                <td>${v.visitor_id}</td>
                                <td><strong>${escapeHtml(v.full_name)}</strong><br>
                                    <small>${escapeHtml(v.email || '')}</small></td>
                                <td>${escapeHtml(v.purpose || 'N/A')}</td>
                                <td>${formatDateTime(v.created_at)}</td>
                                <td>${v.exit_time ? formatDateTime(v.exit_time) : '—'}</td>
                                <td class="duration">${calculateDuration(v.created_at, v.exit_time)}</td>
                                <td>${getStatusBadge(v)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
            
            document.getElementById('tableContent').innerHTML = html;
        }

        function getStatusBadge(visitor) {
            const status = getVisitorStatus(visitor);
            switch(status) {
                case 'Exited':
                    return '<span class="badge exited">✓ Exited</span>';
                case 'Expired':
                    return '<span class="badge expired">⏰ Expired</span>';
                case 'Inside':
                    return '<span class="badge inside">👤 Inside</span>';
                case 'Valid':
                    return '<span class="badge valid">⏳ Pending</span>';
                case 'Invalid':
                    return '<span class="badge invalid">❌ Invalid</span>';
                default:
                    return `<span class="badge">${status}</span>`;
            }
        }

        function calculateDuration(entry, exit) {
            if (!exit) return '—';
            
            const entryTime = new Date(entry);
            const exitTime = new Date(exit);
            const diffMs = exitTime - entryTime;
            const diffMins = Math.floor(diffMs / 1000 / 60);
            
            const hours = Math.floor(diffMins / 60);
            const mins = diffMins % 60;
            
            if (hours > 0) {
                return `${hours}h ${mins}m`;
            } else {
                return `${mins}m`;
            }
        }

        function formatDateTime(dateStr) {
            if (!dateStr) return '—';
            const date = new Date(dateStr);
            return date.toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function refreshData() {
            document.getElementById('tableContent').innerHTML = 
                '<div class="loading">⏳ Refreshing...</div>';
            fetchData();
        }

        fetchData();
        setInterval(fetchData, 30000);
    </script>
</body>
</html>

