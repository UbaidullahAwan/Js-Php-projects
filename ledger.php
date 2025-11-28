<?php
session_start();
include 'config.php';

// Check if user is logged in
if(!isset($_SESSION['user_logged_in'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Helper function for status colors
function getStatusColor($status) {
    $colors = [
        'confirmed' => '#27ae60',
        'completed' => '#27ae60', 
        'pending' => '#f39c12',
        'processing' => '#3498db',
        'cancelled' => '#e74c3c',
        'refunded' => '#9b59b6'
    ];
    return $colors[strtolower($status)] ?? '#95a5a6';
}

// Get filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date_desc';

// Handle PDF download
if(isset($_GET['download_pdf'])) {
    // Generate PDF content using dompdf
    require_once 'dompdf/autoload.inc.php';
    
    // Use fully qualified class names
    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    // Instantiate dompdf
    $dompdf = new \Dompdf\Dompdf($options);
    
    // Get data for PDF
    try {
        $sql = "SELECT 
            booking_id,
            booking_date,
            total_amount as amount,
            booking_status as status,
            booking_reference,
            num_passengers,
            payment_status,
            flight_id,
            'debit' as type,
            'Flight Booking' as description
            FROM bookings 
            WHERE user_id = ?";
        
        $params = [$user_id];

        if(!empty($date_from)) {
            $sql .= " AND DATE(booking_date) >= ?";
            $params[] = $date_from;
        }

        if(!empty($date_to)) {
            $sql .= " AND DATE(booking_date) <= ?";
            $params[] = $date_to;
        }

        if(!empty($status_filter)) {
            $sql .= " AND booking_status = ?";
            $params[] = $status_filter;
        }

        $sql .= " ORDER BY booking_date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pdf_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        $pdf_entries = [];
    }
    
    // Calculate totals
    $total_debit = 0;
    $total_credit = 0;
    $running_balance = 0;
    $ledger_data = [];
    
    foreach($pdf_entries as $entry) {
        $amount = $entry['amount'] ?? 0;
        $debit_amount = $entry['type'] == 'debit' ? $amount : 0;
        $credit_amount = $entry['type'] == 'credit' ? $amount : 0;
        
        $running_balance += $credit_amount - $debit_amount;
        
        $ledger_data[] = [
            'entry' => $entry,
            'debit_amount' => $debit_amount,
            'credit_amount' => $credit_amount,
            'balance' => $running_balance
        ];
        
        if($entry['type'] == 'debit') {
            $total_debit += $debit_amount;
        } else {
            $total_credit += $credit_amount;
        }
    }
    
    $final_balance = $total_credit - $total_debit;
    
    // Create HTML content for PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Hussain Group - Financial Ledger</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 15px; 
                color: #333;
                line-height: 1.4;
                font-size: 12px;
            }
            .header { 
                text-align: center; 
                margin-bottom: 20px; 
                border-bottom: 2px solid #2c3e50; 
                padding-bottom: 15px;
            }
            .header h1 { 
                color: #2c3e50; 
                margin: 0 0 10px 0;
                font-size: 15px;
                font-weight: bold;
            }
            .header p { 
                color: #7f8c8d; 
                margin: 4px 0;
                font-size: 12px;
            }
            .agent-info {
                background: #f8f9fa;
                padding: 12px;
                border-radius: 5px;
                margin: 15px 0;
                border: 1px solid #e1e8ed;
            }
            .summary-section {
                margin: 15px 0;
                padding: 12px;
                border: 1px solid #e1e8ed;
                border-radius: 5px;
                background: #f8f9fa;
            }
            .summary-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 10px;
                text-align: center;
            }
            .summary-item {
                padding: 8px;
              
            }
            .summary-value {
                font-size: 12px;
                font-weight: bold;
                margin-bottom: 3px;
                display: flex;
            }
            .summary-label {
                font-size: 10px;
                color: #7f8c8d;
                font-weight: 500;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 15px;
                font-size: 10px;
            }
            th, td { 
                border: 1px solid #ddd; 
                padding: 6px; 
                text-align: left; 
                vertical-align: top;
            }
            th { 
                background-color: #f8f9fa; 
                font-weight: bold;
                color: #2c3e50;
                font-size: 10px;
            }
            .total-row { 
                background-color: #f8f9fa; 
                font-weight: bold; 
            }
            .debit { 
                color: #e74c3c;
            }
            .credit { 
                color: #27ae60;
            }
            .balance-positive {
                color: #27ae60;
            }
            .balance-negative {
                color: #e74c3c;
            }
            .footer {
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #bdc3c7;
                text-align: center;
                color: #7f8c8d;
                font-size: 10px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>HUSSAIN GROUP - FINANCIAL LEDGER REPORT</h1>
            <div class="agent-info">
                <p><strong>Agent:</strong> ' . htmlspecialchars($user_name) . ' (' . htmlspecialchars($user_email) . ')</p>
                <p><strong>Generated:</strong> ' . date('M j, Y g:i A') . ' | <strong>Period:</strong> ' . ($date_from ? $date_from : 'All Time') . ' to ' . ($date_to ? $date_to : date('Y-m-d')) . '</p>
            </div>
        </div>
        
        <div class="summary-section">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-value">' . number_format($total_debit, 2) . ' PKR</div>
                    <div class="summary-label">Total Debit</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">' . number_format($total_credit, 2) . ' PKR</div>
                    <div class="summary-label">Total Credit</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">' . number_format($final_balance, 2) . ' PKR</div>
                    <div class="summary-label">Net Balance</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">' . count($pdf_entries) . '</div>
                    <div class="summary-label">Transactions</div>
                </div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th width="12%">Date</th>
                    <th width="25%">Description</th>
                    <th width="8%">Type</th>
                    <th width="12%">Reference</th>
                    <th width="10%">Debit</th>
                    <th width="10%">Credit</th>
                    <th width="13%">Balance</th>
                    <th width="10%">Status</th>
                </tr>
            </thead>
            <tbody>';
    
    if(empty($ledger_data)) {
        $html .= '
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px; color: #7f8c8d;">
                        No transactions found for the selected period.
                    </td>
                </tr>';
    } else {
        foreach($ledger_data as $data) {
            $entry = $data['entry'];
            $description = $entry['description'] ?? 'Flight Booking';
            
            $passenger_info = '';
            if($entry['num_passengers'] && $entry['num_passengers'] > 1) {
                $passenger_info = ' (' . $entry['num_passengers'] . ' pax)';
            }
            
            $html .= '
                <tr>
                    <td>' . date('M j, Y', strtotime($entry['booking_date'])) . '</td>
                    <td>' . htmlspecialchars($description) . $passenger_info . '</td>
                    <td>' . strtoupper($entry['type']) . '</td>
                    <td>' . htmlspecialchars($entry['booking_reference'] ?? 'N/A') . '</td>
                    <td class="debit">' . ($data['debit_amount'] > 0 ? number_format($data['debit_amount'], 2) : '-') . '</td>
                    <td class="credit">' . ($data['credit_amount'] > 0 ? number_format($data['credit_amount'], 2) : '-') . '</td>
                    <td class="' . ($data['balance'] >= 0 ? 'balance-positive' : 'balance-negative') . '">' . number_format($data['balance'], 2) . '</td>
                    <td>' . ucfirst($entry['status']) . '</td>
                </tr>';
        }
        
        // Final totals row
        $html .= '
                <tr class="total-row">
                    <td colspan="4" style="text-align: right;">TOTALS:</td>
                    <td class="debit">' . number_format($total_debit, 2) . '</td>
                    <td class="credit">' . number_format($total_credit, 2) . '</td>
                    <td class="' . ($final_balance >= 0 ? 'balance-positive' : 'balance-negative') . '">' . number_format($final_balance, 2) . '</td>
                    <td></td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
        
        <div class="footer">
            <p>Hussain Group Travel & Tourism - Financial Ledger Report</p>
        </div>
    </body>
    </html>';
    
    // Load HTML content
    $dompdf->loadHtml($html);
    
    // Set paper size and orientation - PORTRAIT A4
    $dompdf->setPaper('A4', 'portrait');
    
    // Render PDF
    $dompdf->render();
    
    // Output PDF
    $dompdf->stream('ledger_report_' . date('Y-m-d') . '.pdf', [
        'Attachment' => true
    ]);
    exit;
}

// Get ledger data for web display
try {
    $sql = "SELECT 
        booking_id,
        booking_date,
        total_amount as amount,
        booking_status as status,
        booking_reference,
        num_passengers,
        payment_status,
        flight_id,
        'debit' as type,
        'Flight Booking' as description
        FROM bookings 
        WHERE user_id = ?";
    
    $params = [$user_id];

    if(!empty($date_from)) {
        $sql .= " AND DATE(booking_date) >= ?";
        $params[] = $date_from;
    }

    if(!empty($date_to)) {
        $sql .= " AND DATE(booking_date) <= ?";
        $params[] = $date_to;
    }

    if(!empty($status_filter)) {
        $sql .= " AND booking_status = ?";
        $params[] = $status_filter;
    }

    // Add sorting
    switch($sort_by) {
        case 'date_asc':
            $sql .= " ORDER BY booking_date ASC";
            break;
        case 'amount_asc':
            $sql .= " ORDER BY total_amount ASC";
            break;
        case 'amount_desc':
            $sql .= " ORDER BY total_amount DESC";
            break;
        default:
            $sql .= " ORDER BY booking_date DESC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ledger_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Error accessing ledger data: " . $e->getMessage());
}

// Calculate totals and prepare ledger data for display
$total_debit = 0;
$total_credit = 0;
$final_balance = 0;
$ledger_data = [];
$running_balance = 0;

foreach($ledger_entries as $entry) {
    $amount = $entry['amount'] ?? 0;
    $debit_amount = $entry['type'] == 'debit' ? $amount : 0;
    $credit_amount = $entry['type'] == 'credit' ? $amount : 0;
    
    $running_balance += $credit_amount - $debit_amount;
    
    $ledger_data[] = [
        'entry' => $entry,
        'debit_amount' => $debit_amount,
        'credit_amount' => $credit_amount,
        'balance' => $running_balance
    ];
    
    if($entry['type'] == 'debit') {
        $total_debit += $debit_amount;
    } else {
        $total_credit += $credit_amount;
    }
}

$final_balance = $total_credit - $total_debit;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Ledger - Hussain Group</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            color: #333;
            line-height: 1.6;
        }

        .main-content {
            margin-left: 250px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .content-area {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            margin-top: 70px;
        }

        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .page-subtitle {
            color: #7f8c8d;
            font-size: 16px;
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            display: flex;
            background: #ffffff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            border-left: 4px solid #3498db;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 20px;
            background: #e3f2fd;
            color: #3498db;
        }

        .stat-icon.debit {
            background: #ffeaa7;
            color: #fdcb6e;
        }

        .stat-icon.credit {
            background: #d1ecf1;
            color: #17a2b8;
        }

        .stat-icon.transactions {
            background: #d5eddb;
            color: #28a745;
        }

        .stat-icon.balance {
            background: #f8d7da;
            color: #dc3545;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #7f8c8d;
            font-weight: 500;
        }

        .filters-section {
            background: #ffffff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }

        .filter-select, .filter-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: border-color 0.3s ease;
            font-family: inherit;
        }

        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: #3498db;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: end;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .ledger-table-container {
            background: #ffffff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .ledger-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .ledger-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e1e8ed;
            font-size: 14px;
        }

        .ledger-table td {
            padding: 15px;
            border-bottom: 1px solid #e1e8ed;
            font-size: 14px;
        }

        .ledger-table tr:hover {
            background: #f8f9fa;
        }

        .amount {
            font-weight: 600;
            font-size: 14px;
        }

        .amount.debit {
            color: #e74c3c;
        }

        .amount.credit {
            color: #27ae60;
        }

        .balance-positive {
            color: #27ae60;
            font-weight: bold;
        }

        .balance-negative {
            color: #e74c3c;
            font-weight: bold;
        }

        .type-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-debit {
            background: #fed7d7;
            color: #742a2a;
        }

        .type-credit {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #feebcb;
            color: #744210;
        }

        .status-confirmed {
            background: #bee3f8;
            color: #1a365d;
        }

        .status-completed {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-cancelled {
            background: #fed7d7;
            color: #742a2a;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #bdc3c7;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                margin-left: 0;
                width: 100%;
                justify-content: flex-start;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .content-area {
                padding: 20px;
                margin-top: 70px;
            }
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .content-area {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Content Area -->
        <div class="content-area">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Financial Ledger</h1>
                    <p class="page-subtitle">Complete transaction history with running balance</p>
                </div>
                <div class="header-actions">
                    <a href="?download_pdf=1&<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                        <i class="fas fa-file-pdf"></i>
                        Download PDF
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon debit">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_debit, 2); ?> PKR</div>
                    <div class="stat-label">Total Debit</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon credit">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_credit, 2); ?> PKR</div>
                    <div class="stat-label">Total Credit</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon transactions">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-number"><?php echo count($ledger_entries); ?></div>
                    <div class="stat-label">Total Transactions</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon balance">
                        <i class="fas fa-scale-balanced"></i>
                    </div>
                    <div class="stat-number <?php echo $final_balance >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                        <?php echo number_format($final_balance, 2); ?> PKR
                    </div>
                    <div class="stat-label">Net Balance</div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" id="filtersForm">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="filter-label">Date From</label>
                            <input type="date" name="date_from" class="filter-input" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Date To</label>
                            <input type="date" name="date_to" class="filter-input" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Status</label>
                            <select name="status" class="filter-select">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Sort By</label>
                            <select name="sort_by" class="filter-select">
                                <option value="date_desc" <?php echo $sort_by == 'date_desc' ? 'selected' : ''; ?>>Date: Newest First</option>
                                <option value="date_asc" <?php echo $sort_by == 'date_asc' ? 'selected' : ''; ?>>Date: Oldest First</option>
                                <option value="amount_desc" <?php echo $sort_by == 'amount_desc' ? 'selected' : ''; ?>>Amount: High to Low</option>
                                <option value="amount_asc" <?php echo $sort_by == 'amount_asc' ? 'selected' : ''; ?>>Amount: Low to High</option>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i>
                                Apply Filters
                            </button>
                            <a href="ledger.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Ledger Table -->
            <div class="ledger-table-container">
                <?php if(empty($ledger_data)): ?>
                    <div class="no-data">
                        <i class="fas fa-receipt"></i>
                        <h3>No Transactions Found</h3>
                        <p>No ledger entries match your current filters or you haven't made any transactions yet.</p>
                        <a href="flights.php" class="btn btn-primary">Book Flights</a>
                    </div>
                <?php else: ?>
                    <table class="ledger-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Reference</th>
                                <th>Debit (PKR)</th>
                                <th>Credit (PKR)</th>
                                <th>Balance (PKR)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($ledger_data as $data): 
                                $entry = $data['entry'];
                                $description = $entry['description'] ?? 'Flight Booking';
                            ?>
                                <tr>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($entry['booking_date'])); ?><br>
                                        <small style="color: #7f8c8d;"><?php echo date('g:i A', strtotime($entry['booking_date'])); ?></small>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($description); ?></div>
                                        <?php if($entry['num_passengers'] > 1): ?>
                                            <small style="color: #7f8c8d;"><?php echo $entry['num_passengers']; ?> passengers</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="type-badge type-<?php echo $entry['type']; ?>">
                                            <?php echo ucfirst($entry['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                            <?php echo htmlspecialchars($entry['booking_reference'] ?? 'N/A'); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <?php if($data['debit_amount'] > 0): ?>
                                            <span class="amount debit">
                                                <?php echo number_format($data['debit_amount'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #bdc3c7;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($data['credit_amount'] > 0): ?>
                                            <span class="amount credit">
                                                <?php echo number_format($data['credit_amount'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #bdc3c7;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="<?php echo $data['balance'] >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                                            <?php echo number_format($data['balance'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $entry['status']; ?>">
                                            <?php echo ucfirst($entry['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Auto-submit form when filters change
        document.querySelectorAll('.filter-select').forEach(select => {
            select.addEventListener('change', function() {
                document.getElementById('filtersForm').submit();
            });
        });

        // Set max date for date_to to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const dateToInput = document.querySelector('input[name="date_to"]');
            if(dateToInput) {
                dateToInput.max = today;
            }
        });
    </script>
</body>
</html>