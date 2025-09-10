<?php
include("includes/db.php");
include("includes/auth.php");
checkLogin('manager'); // or 'admin' if only admins can access

$userId = $_SESSION['user_id'];
$query = $conn->prepare("SELECT name, role FROM users WHERE id = ?");
$query->bind_param("i", $userId);
$query->execute();
$query->bind_result($name, $role);
$query->fetch();
$query->close();
$nameParts = explode(' ', $name);
$initials = strtoupper(substr($nameParts[0],0,1)) . (isset($nameParts[1]) ? strtoupper(substr($nameParts[1],0,1)) : '');

// Get date filters (default to current month)
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Total requests statistics
$totalRequests = $conn->query("SELECT COUNT(*) as count FROM requests WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'")->fetch_assoc()['count'];
$approvedRequests = $conn->query("SELECT COUNT(*) as count FROM requests WHERE status='approved' AND DATE(created_at) BETWEEN '$startDate' AND '$endDate'")->fetch_assoc()['count'];
$pendingRequests = $conn->query("SELECT COUNT(*) as count FROM requests WHERE status='pending' AND DATE(created_at) BETWEEN '$startDate' AND '$endDate'")->fetch_assoc()['count'];
$rejectedRequests = $conn->query("SELECT COUNT(*) as count FROM requests WHERE status='rejected' AND DATE(created_at) BETWEEN '$startDate' AND '$endDate'")->fetch_assoc()['count'];

// Vehicle utilization
$totalVehicles = $conn->query("SELECT COUNT(*) as count FROM vehicles")->fetch_assoc()['count'];
$availableVehicles = $conn->query("SELECT COUNT(*) as count FROM vehicles WHERE status='available'")->fetch_assoc()['count'];
$bookedVehicles = $conn->query("SELECT COUNT(*) as count FROM vehicles WHERE status='booked'")->fetch_assoc()['count'];
$maintenanceVehicles = $conn->query("SELECT COUNT(*) as count FROM vehicles WHERE status='maintenance'")->fetch_assoc()['count'];

// Most requested vehicles
$topVehicles = $conn->query("
    SELECT v.name, v.license_plate, COUNT(r.id) as request_count
    FROM requests r
    JOIN vehicles v ON r.vehicle_id = v.id
    WHERE DATE(r.created_at) BETWEEN '$startDate' AND '$endDate'
    GROUP BY r.vehicle_id
    ORDER BY request_count DESC
    LIMIT 5
");

// Most active users
$topUsers = $conn->query("
    SELECT u.name, COUNT(r.id) as request_count
    FROM requests r
    JOIN users u ON r.user_id = u.id
    WHERE DATE(r.created_at) BETWEEN '$startDate' AND '$endDate'
    GROUP BY r.user_id
    ORDER BY request_count DESC
    LIMIT 5
");

// Daily request trends (last 7 days)
$dailyTrends = $conn->query("
    SELECT DATE(created_at) as date, COUNT(*) as count, status
    FROM requests
    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at), status
    ORDER BY date DESC
");

// Average response time (time from request to approval/rejection)
$avgResponseTime = $conn->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours
    FROM requests
    WHERE status IN ('approved', 'rejected') AND updated_at IS NOT NULL
    AND DATE(created_at) BETWEEN '$startDate' AND '$endDate'
")->fetch_assoc()['avg_hours'];

// Recent requests for detailed view
$recentRequests = $conn->query("
    SELECT r.*, u.name as requester, v.name as vehicle_name, v.license_plate,
           m.name as manager_name
    FROM requests r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN vehicles v ON r.vehicle_id = v.id
    LEFT JOIN users m ON r.manager_id = m.id
    WHERE DATE(r.created_at) BETWEEN '$startDate' AND '$endDate'
    ORDER BY r.created_at DESC
");

// Rejection reasons analysis
$rejectionReasons = $conn->query("
    SELECT decision_reason, COUNT(*) as count
    FROM requests
    WHERE status='rejected' AND decision_reason IS NOT NULL AND decision_reason != ''
    AND DATE(created_at) BETWEEN '$startDate' AND '$endDate'
    GROUP BY decision_reason
    ORDER BY count DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reports - BookiT</title>
    <link rel="stylesheet" href="assets/main.css">
    <style>
        .stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(200px, 1fr)); /* Ensures 4 columns on screen */
    gap: 20px;
    margin-bottom: 30px;
}

.dashboard-card {
    padding: 20px;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    text-align: center;
}

.dashboard-card b {
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 8px;
}

.dashboard-card .card-value {
    font-size: 2.2rem;
    font-weight: 700;
}

.card-desc {
    color: #888;
    font-size: 15px;
    margin-top: 6px;
}

@media print {
    .stats-grid {
        grid-template-columns: 1fr; /* Single column for vertical layout in print */
    }
    .dashboard-card {
        margin-bottom: 20px; /* Add space between cards in print */
    }
    .no-print { display: none !important; }
    .topbar { display: none !important; }
    body { background: white !important; }
    .container { margin: 0 !important; padding: 20px !important; max-width: none !important; }
    .dashboard-card { box-shadow: none !important; border: 1px solid #ddd !important; }
    .print-header { display: flex !important; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; }
    .print-header .logo { margin-right: 20px; }
    .print-header .report-info { text-align: right; }
}
        .print-header { display: none; }
        .print-header img { height: 80px; }
        .print-header .report-title { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
        .print-header .report-details { font-size: 14px; color: #666; }
        .date-filters { display: flex; gap: 12px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
        .date-filters input, .date-filters button { padding: 8px 12px; border: 1px solid #e5eaf2; border-radius: 8px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .chart-container { height: 300px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 20px 0; }
        .trend-table { width: 100%; margin-top: 15px; }
        .trend-table th, .trend-table td { padding: 8px; text-align: left; border-bottom: 1px solid #f1f1f1; }
        .progress-bar { background: #f1f1f1; border-radius: 10px; height: 20px; overflow: hidden; margin: 5px 0; }
        .progress-fill { height: 100%; background: #232c3d; transition: width 0.3s; }
        .print-options { display: flex; gap: 10px; margin-bottom: 20px; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="topbar no-print">
        <div class="topbar-left">
            <div class="logo">
                <img src="assets/Logo-Fast_Kwacha.png" alt="BookiT Logo" style="height:28px;margin-right:10px;">
            </div>
            <nav>
                <a href="manager_dashboard.php">Dashboard</a>
                <a href="all_bookings.php">All Bookings</a>
                <a href="vehicles.php">Vehicle Management</a>
                <a href="reports.php" class="active">Reports</a>
                <a href="profile.php">Profile</a>
            </nav>
        </div>
        <div class="user-info">
            <span class="user-role"><?php echo htmlspecialchars($role); ?></span>
            <span class="avatar"><?php echo $initials; ?></span>
            <a href="logout.php" style="text-decoration: none; color: white; background-color: #f5aa20ff; border-radius: 5px; padding: 4px;">Logout</a>
        </div>
    </div>

    <div class="print-header">
        <div class="logo">
            <img src="assets/content.png" alt="Fast Kwacha Logo">
        </div>
        <div class="report-info">
            <div class="report-title">Vehicle Booking System Report</div>
            <div class="report-details">
                Generated on <?php echo date('F d, Y h:i A', time()); ?><br>
                Report Period: <?php echo date('M d, Y', strtotime($startDate)); ?> to <?php echo date('M d, Y', strtotime($endDate)); ?>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="requests-header no-print">
            <div>
                <h2 class="dashboard-title">Reports & Analytics</h2>
                <p class="dashboard-subtitle">Comprehensive overview of booking requests and vehicle utilization</p>
            </div>
            <div class="print-options">
                <button class="new-booking-btn" onclick="printReport()">Print Report</button>
                <button class="modal-cancel-btn" onclick="togglePrintOptions()">Print Options</button>
            </div>
        </div>

        <!-- Date Filters -->
        <div class="date-filters no-print">
            <form method="GET" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <label>From:</label>
                <input type="date" name="start_date" value="<?php echo $startDate; ?>">
                <label>To:</label>
                <input type="date" name="end_date" value="<?php echo $endDate; ?>">
                <button type="submit" class="modal-submit-btn">Filter</button>
                <button type="button" onclick="resetDates()" class="modal-cancel-btn">Reset</button>
            </form>
        </div>

        <!-- Print Options Panel -->
        <div id="printOptions" class="dashboard-card no-print" style="display: none; margin-bottom: 20px;">
            <h3>Print Options</h3>
            <div style="display: flex; gap: 20px; margin-top: 15px; flex-wrap: wrap;">
                <label><input type="checkbox" id="printStats" checked> Statistics Overview</label>
                <label><input type="checkbox" id="printVehicles" checked> Vehicle Utilization</label>
                <label><input type="checkbox" id="printTrends" checked> Request Trends</label>
                <label><input type="checkbox" id="printDetails" checked> Detailed Requests</label>
                <label><input type="checkbox" id="printAnalysis" checked> Rejection Analysis</label>
            </div>
        </div>

        <!-- Key Statistics -->
        <div id="statsSection" class="stats-grid">
            <div class="dashboard-card">
                <b>Total Requests</b>
                <div class="card-value"><?php echo $totalRequests; ?></div>
                <div class="card-desc">For selected period</div>
            </div>
            <div class="dashboard-card">
                <b>Approved Requests</b>
                <div class="card-value"><?php echo $approvedRequests; ?></div>
                <div class="card-desc"><?php echo $totalRequests > 0 ? round(($approvedRequests/$totalRequests)*100, 1) : 0; ?>% approval rate</div>
            </div>
            <div class="dashboard-card">
                <b>Pending Requests</b>
                <div class="card-value"><?php echo $pendingRequests; ?></div>
                <div class="card-desc">Awaiting decision</div>
            </div>
            <div class="dashboard-card">
                <b>Average Response Time</b>
                <div class="card-value"><?php echo round($avgResponseTime ?? 0, 1); ?></div>
                <div class="card-desc">Hours to decision</div>
            </div>
        </div>

        <!-- Vehicle Utilization -->
        <div id="vehiclesSection" class="dashboard-card" style="margin-bottom: 30px;">
            <h3>Vehicle Utilization</h3>
            <div class="stats-grid">
                <div>
                    <b>Total Vehicles: <?php echo $totalVehicles; ?></b>
                    <div class="chart-container" style="margin-top: 10px; height: 250px;">
                        <canvas id="vehicleUtilizationChart"></canvas>
                    </div>
                    <div style="margin-top: 15px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span>Available: <?php echo $availableVehicles; ?></span>
                            <span><?php echo $totalVehicles > 0 ? round(($availableVehicles/$totalVehicles)*100, 1) : 0; ?>%</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Booked: <?php echo $bookedVehicles; ?></span>
                            <span><?php echo $totalVehicles > 0 ? round(($bookedVehicles/$totalVehicles)*100, 1) : 0; ?>%</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Maintenance: <?php echo $maintenanceVehicles; ?></span>
                            <span><?php echo $totalVehicles > 0 ? round(($maintenanceVehicles/$totalVehicles)*100, 1) : 0; ?>%</span>
                        </div>
                    </div>
                </div>
                <div>
                    <b>Most Requested Vehicles</b>
                    <table class="trend-table">
                        <thead>
                            <tr><th>Vehicle</th><th>License</th><th>Requests</th></tr>
                        </thead>
                        <tbody>
                            <?php while($tv = $topVehicles->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tv['name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($tv['license_plate'] ?? ''); ?></td>
                                    <td><?php echo $tv['request_count']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Request Trends -->
        <div id="trendsSection" class="dashboard-card" style="margin-bottom: 30px;">
            <h3>Request Trends & Top Users</h3>
            <div class="stats-grid">
                <div>
                    <b>Daily Request Trends (Last 7 Days)</b>
                    <table class="trend-table">
                        <thead>
                            <tr><th>Date</th><th>Total</th><th>Status Breakdown</th></tr>
                        </thead>
                        <tbody>
                            <?php 
                            $trends = [];
                            while($dt = $dailyTrends->fetch_assoc()) {
                                $date = $dt['date'];
                                if (!isset($trends[$date])) {
                                    $trends[$date] = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
                                }
                                $trends[$date][$dt['status']] = $dt['count'];
                                $trends[$date]['total'] += $dt['count'];
                            }
                            foreach($trends as $date => $data): ?>
                                <tr>
                                    <td><?php echo date('M d', strtotime($date)); ?></td>
                                    <td><?php echo $data['total']; ?></td>
                                    <td style="font-size: 12px;">
                                        A:<?php echo $data['approved']; ?> 
                                        P:<?php echo $data['pending']; ?> 
                                        R:<?php echo $data['rejected']; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div>
                    <b>Most Active Users</b>
                    <table class="trend-table">
                        <thead>
                            <tr><th>User</th><th>Requests</th></tr>
                        </thead>
                        <tbody>
                            <?php while($tu = $topUsers->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tu['name'] ?? ''); ?></td>
                                    <td><?php echo $tu['request_count']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Rejection Analysis -->
        <div id="analysisSection" class="dashboard-card" style="margin-bottom: 30px;">
            <h3>Rejection Analysis</h3>
            <div>
                <b>Common Rejection Reasons</b>
                <table class="trend-table" style="margin-top: 15px;">
                    <thead>
                        <tr><th>Reason</th><th>Count</th><th>Percentage</th></tr>
                    </thead>
                    <tbody>
                        <?php while($rr = $rejectionReasons->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rr['decision_reason']); ?></td>
                                <td><?php echo $rr['count']; ?></td>
                                <td><?php echo $rejectedRequests > 0 ? round(($rr['count']/$rejectedRequests)*100, 1) : 0; ?>%</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Detailed Requests -->
        <div id="detailsSection" class="dashboard-card">
            <h3>Detailed Request Log</h3>
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Requester</th>
                        <th>Purpose</th>
                        <th>Vehicle</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Manager</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($rr = $recentRequests->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($rr['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($rr['requester'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($rr['purpose'] ?? ''); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($rr['vehicle_name'] ?? ''); ?></div>
                                <div style="font-size: 12px; color: #888;"><?php echo htmlspecialchars($rr['license_plate'] ?? ''); ?></div>
                            </td>
                            <td>
                                <?php if($rr['start_datetime'] && $rr['end_datetime']): ?>
                                    <?php echo date('M d, H:i', strtotime($rr['start_datetime'])); ?><br>
                                    <span style="font-size: 12px; color: #888;">to <?php echo date('M d, H:i', strtotime($rr['end_datetime'])); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?php echo $rr['status']; ?>"><?php echo $rr['status']; ?></span></td>
                            <td><?php echo htmlspecialchars($rr['manager_name'] ?? ''); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!---print Function--------------------->
    <script>
        function printReport() {
            window.print();
        }

        function togglePrintOptions() {
            const options = document.getElementById('printOptions');
            options.style.display = options.style.display === 'none' ? 'block' : 'none';
        }

        function resetDates() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            
            document.querySelector('input[name="start_date"]').value = firstDay.toISOString().split('T')[0];
            document.querySelector('input[name="end_date"]').value = lastDay.toISOString().split('T')[0];
        }

        // Handle print options
        window.addEventListener('beforeprint', function() {
            const sections = {
                'printStats': 'statsSection',
                'printVehicles': 'vehiclesSection', 
                'printTrends': 'trendsSection',
                'printDetails': 'detailsSection',
                'printAnalysis': 'analysisSection'
            };

            Object.keys(sections).forEach(checkboxId => {
                const checkbox = document.getElementById(checkboxId);
                const section = document.getElementById(sections[checkboxId]);
                if (checkbox && section) {
                    section.style.display = checkbox.checked ? 'block' : 'none';
                }
            });
        });

        window.addEventListener('afterprint', function() {
            // Restore all sections after printing
            const sections = ['statsSection', 'vehiclesSection', 'trendsSection', 'detailsSection', 'analysisSection'];
            sections.forEach(sectionId => {
                const section = document.getElementById(sectionId);
                if (section) section.style.display = 'block';
            });
        });
    </script>

    <!----Chart Js----------------------------->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('vehicleUtilizationChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Available', 'Booked', 'Maintenance'],
                datasets: [{
                    data: [<?php echo $availableVehicles; ?>, <?php echo $bookedVehicles; ?>, <?php echo $maintenanceVehicles; ?>],
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                    borderColor: ['#fff', '#fff', '#fff'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 14,
                                family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif'
                            },
                            color: '#232c3d'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.raw || 0;
                                let total = <?php echo $totalVehicles; ?>;
                                let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%',
                layout: {
                    padding: 10
                }
            }
        });
    });
</script>
</body>
</html>