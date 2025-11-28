<?php
// sidebar.php - Reusable Sidebar Component

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'config.php';

// Get user's booking stats
try {
    $user_id = $_SESSION['user_id'] ?? null;
    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN booking_status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN booking_status = 'on_hold' THEN 1 ELSE 0 END) as on_hold,
                SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN booking_status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM bookings 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $booking_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    // If bookings table doesn't exist or query fails, set default values
    error_log("Booking stats error: " . $e->getMessage());
    $booking_stats = [
        'total' => 0,
        'confirmed' => 0,
        'processing' => 0,
        'on_hold' => 0,
        'cancelled' => 0,
        'completed' => 0
    ];
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get current user data
$current_user = [
    'name' => $_SESSION['user_name'] ?? 'Guest User',
    'email' => $_SESSION['user_email'] ?? 'guest@example.com',
    'role' => $_SESSION['user_role'] ?? 'User'
];
?>

<!-- Sidebar Styles -->
<style>
    /* Your existing CSS styles remain the same */
    body,html{
        padding: 0;
        margin: 0;
        font-family: 'Arial','Sans-serif';
    }
    
    /* Topbar Styles */
    .topbar {
        position: fixed;
        top: 0;
        left: 250px;
        right: 0;
        height: 70px;
        background: #ffffff;
        border-bottom: 1px solid #e1e8ed;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 30px;
        z-index: 999;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .page-title {
        font-size: 24px;
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 15px;
        position: relative;
    }

    .user-info {
        text-align: right;
    }

    .user-name {
        font-weight: 600;
        color: #2c3e50;
        font-size: 14px;
        margin: 0;
    }

    .user-email {
        color: #7f8c8d;
        font-size: 12px;
        margin: 0;
    }

    .user-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 16px;
        cursor: pointer;
        border: 3px solid #e1e8ed;
        transition: all 0.3s ease;
    }

    .user-avatar:hover {
        transform: scale(1.05);
        border-color: #3498db;
        box-shadow: 0 0 15px rgba(52, 152, 219, 0.3);
    }

    .profile-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
        padding: 15px;
        min-width: 200px;
        z-index: 1001;
        display: none;
        border: 1px solid #e1e8ed;
        margin-top: 10px;
    }

    .profile-dropdown.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .dropdown-header {
        padding-bottom: 10px;
        border-bottom: 1px solid #e1e8ed;
        margin-bottom: 10px;
    }

    .dropdown-header .user-name {
        font-size: 16px;
        color: #2c3e50;
    }

    .dropdown-header .user-email {
        font-size: 13px;
        color: #7f8c8d;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        padding: 10px 0;
        color: #2c3e50;
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 14px;
        border-radius: 5px;
    }

    .dropdown-item:hover {
        background: #f8f9fa;
        color: #3498db;
        padding-left: 10px;
        padding-right: 10px;
    }

    .dropdown-item i {
        width: 20px;
        margin-right: 10px;
        font-size: 14px;
        color: #7f8c8d;
    }

    .dropdown-item:hover i {
        color: #3498db;
    }

    .dropdown-divider {
        height: 1px;
        background: #e1e8ed;
        margin: 8px 0;
    }

    .sidebar {
        width: 250px;
        background: #ffffff;
        border-right: 1px solid #e1e8ed;
        display: flex;
        flex-direction: column;
        position: fixed;
        height: 100vh;
        z-index: 1000;
        flex-shrink: 0;
        transition: transform 0.3s ease;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        top: 0;
        left: 0;
    }

    .logo-section {
        padding: 25px 20px;
        border-bottom: 1px solid #e1e8ed;
        text-align: center;
        background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
    }

    .logo {
        font-size: 24px;
        font-weight: 800;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        text-decoration: none;
    }

    .logo:hover {
        color: white;
    }

    .nav-section {
        flex: 1;
        padding: 20px 0;
    }

    .nav-item {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        color: #2c3e50;
        text-decoration: none;
        transition: all 0.3s ease;
        border-left: 3px solid transparent;
        font-weight: 500;
        font-size: 14px;
        cursor: pointer;
        position: relative;
    }

    .nav-item.active {
        background: #e3f2fd;
        border-left-color: #3498db;
        color: #3498db;
    }

    .nav-item:hover {
        background: #f5f7fa;
        color: #3498db;
    }

    .nav-item i {
        width: 20px;
        margin-right: 12px;
        font-size: 16px;
        text-align: center;
    }

    .nav-item .badge {
        margin-left: auto;
        background: #3498db;
        color: white;
        padding: 2px 6px;
        border-radius: 8px;
        font-size: 11px;
        min-width: 20px;
        text-align: center;
    }

    /* Submenu Styles */
    .submenu {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
        background: #f8f9fa;
    }

    .submenu.open {
        max-height: 300px;
    }

    .submenu-item {
        display: flex;
        align-items: center;
        padding: 12px 20px 12px 50px;
        color: #7f8c8d;
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 13px;
        border-left: 3px solid transparent;
    }

    .submenu-item:hover {
        background: #e3f2fd;
        color: #3498db;
    }

    .submenu-item.active {
        background: #e3f2fd;
        border-left-color: #3498db;
        color: #3498db;
    }

    .submenu-item i {
        width: 16px;
        margin-right: 10px;
        font-size: 12px;
    }

    .submenu-item .badge {
        margin-left: auto;
        background: #e74c3c;
        color: white;
        padding: 1px 5px;
        border-radius: 6px;
        font-size: 10px;
        min-width: 18px;
        text-align: center;
    }

    .logout-section {
        padding: 20px;
        border-top: 1px solid #e1e8ed;
    }

    .logout-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        padding: 12px;
        background: #f8f9fa;
        color: #2c3e50;
        border: 1px solid #e1e8ed;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 14px;
        font-weight: 600;
    }

    .logout-btn:hover {
        background: #e74c3c;
        color: white;
        border-color: #e74c3c;
    }

    /* Mobile Styles */
    .mobile-menu-btn {
        display: none;
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 1001;
        background: #3498db;
        color: white;
        border: none;
        border-radius: 6px;
        padding: 10px 12px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .mobile-menu-btn:hover {
        background: #2980b9;
        transform: scale(1.1);
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 999;
    }

    @media (max-width: 768px) {
        .topbar {
            left: 0;
            padding: 0 20px;
        }
        
        .sidebar {
            transform: translateX(-100%);
            width: 280px;
        }
        
        .sidebar.mobile-open {
            transform: translateX(0);
        }
        
        .mobile-menu-btn {
            display: block;
        }

        .sidebar-overlay.mobile-open {
            display: block;
        }
        
        .user-info {
            display: none;
        }
    }
</style>

<!-- Topbar Component -->
<div class="topbar" id="topbar">
    <h1 class="page-title" id="pageTitle">
        <?php 
        $page_titles = [
            'flights.php' => 'Book Flights',
            'bookings.php' => 'My Bookings',
            'ledger.php' => 'Financial Ledger',
            'profile.php' => 'My Profile',
            'payments.php' => 'Payment Options',
            'support.php' => 'Customer Support'
        ];
        echo $page_titles[$current_page] ?? 'Dashboard';
        ?>
    </h1>
    
    <div class="user-profile">
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($current_user['name']); ?></div>
            <div class="user-email"><?php echo htmlspecialchars($current_user['email']); ?></div>
        </div>
        <div class="user-avatar" id="userAvatar" title="<?php echo htmlspecialchars($current_user['name']); ?>">
            <?php 
            // Generate initials from name
            $initials = '';
            $name_parts = explode(' ', $current_user['name']);
            foreach ($name_parts as $part) {
                $initials .= strtoupper(substr($part, 0, 1));
                if (strlen($initials) >= 2) break;
            }
            echo $initials ?: 'GU';
            ?>
        </div>
        
        <!-- Profile Dropdown -->
        <div class="profile-dropdown" id="profileDropdown">
            <div class="dropdown-header">
                <div class="user-name"><?php echo htmlspecialchars($current_user['name']); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($current_user['email']); ?></div>
            </div>
            
            <a href="profile.php" class="dropdown-item">
                <i class="fas fa-user"></i>
                My Profile
            </a>
            
            <a href="settings.php" class="dropdown-item">
                <i class="fas fa-cog"></i>
                Settings
            </a>
            
            <div class="dropdown-divider"></div>
            
            <a href="logout.php" class="dropdown-item" style="color: #e74c3c;">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>
</div>

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn" id="mobileMenuBtn">
    <i class="fas fa-bars"></i>
</button>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar Component -->
<aside class="sidebar" id="sidebar">
    <div class="logo-section">
        <a href="flights.php" class="logo">
            Hussain Group
        </a>
    </div>

    <nav class="nav-section">
        <a href="flights.php" class="nav-item <?php echo $current_page == 'flights.php' ? 'active' : ''; ?>">
            <i class="fas fa-plane"></i>
            Book Flights
        </a>
        
        <div class="nav-item <?php echo strpos($current_page, 'bookings') !== false ? 'active' : ''; ?>" id="bookingsMenu">
            <i class="fas fa-suitcase"></i>
            My Bookings
            <span class="badge"><?php echo $booking_stats['total'] ?? 0; ?></span>
            <i class="fas fa-chevron-down" style="margin-left: 8px; font-size: 10px;"></i>
        </div>
        
        <div class="submenu" id="bookingsSubmenu">
            <a href="bookings.php?status=all" class="submenu-item <?php echo $current_page == 'bookings.php' && ($_GET['status'] ?? '') == 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                All Bookings
                <span class="badge"><?php echo $booking_stats['total'] ?? 0; ?></span>
            </a>
            <a href="bookings.php?status=processing" class="submenu-item <?php echo $current_page == 'bookings.php' && ($_GET['status'] ?? '') == 'processing' ? 'active' : ''; ?>">
                <i class="fas fa-sync-alt"></i>
                Processing
                <span class="badge"><?php echo $booking_stats['processing'] ?? 0; ?></span>
            </a>
            <a href="bookings.php?status=on_hold" class="submenu-item <?php echo $current_page == 'bookings.php' && ($_GET['status'] ?? '') == 'on_hold' ? 'active' : ''; ?>">
                <i class="fas fa-pause-circle"></i>
                On Hold
                <span class="badge"><?php echo $booking_stats['on_hold'] ?? 0; ?></span>
            </a>
            <a href="bookings.php?status=confirmed" class="submenu-item <?php echo $current_page == 'bookings.php' && ($_GET['status'] ?? '') == 'confirmed' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i>
                Confirmed
                <span class="badge"><?php echo $booking_stats['confirmed'] ?? 0; ?></span>
            </a>
            <a href="bookings.php?status=completed" class="submenu-item <?php echo $current_page == 'bookings.php' && ($_GET['status'] ?? '') == 'completed' ? 'active' : ''; ?>">
                <i class="fas fa-flag-checkered"></i>
                Completed
                <span class="badge"><?php echo $booking_stats['completed'] ?? 0; ?></span>
            </a>
            <a href="bookings.php?status=cancelled" class="submenu-item <?php echo $current_page == 'bookings.php' && ($_GET['status'] ?? '') == 'cancelled' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i>
                Cancelled
                <span class="badge"><?php echo $booking_stats['cancelled'] ?? 0; ?></span>
            </a>
        </div>
        
        <a href="ledger.php" class="nav-item <?php echo $current_page == 'ledger.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            Ledger
        </a>

        <a href="profile.php" class="nav-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            My Profile
        </a>
        
        <a href="payments.php" class="nav-item <?php echo $current_page == 'payments.php' ? 'active' : ''; ?>">
            <i class="fas fa-credit-card"></i>
            Payments
        </a>
        
        <a href="support.php" class="nav-item <?php echo $current_page == 'support.php' ? 'active' : ''; ?>">
            <i class="fas fa-headset"></i>
            Support
        </a>
    </nav>

    <div class="logout-section">
        <button class="logout-btn" onclick="logout()">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </button>
    </div>
</aside>

<!-- Sidebar JavaScript -->
<script>
    // Mobile menu functionality
    document.getElementById('mobileMenuBtn').addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('mobile-open');
    });

    document.getElementById('sidebarOverlay').addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('mobile-open');
    });

    // Bookings submenu toggle - OPEN BY DEFAULT if on bookings page
    document.addEventListener('DOMContentLoaded', function() {
        const bookingsMenu = document.getElementById('bookingsMenu');
        const submenu = document.getElementById('bookingsSubmenu');
        const chevron = bookingsMenu.querySelector('.fa-chevron-down');
        
        // Open submenu by default if on bookings page
        if (window.location.pathname.includes('bookings.php')) {
            submenu.classList.add('open');
            chevron.style.transform = 'rotate(180deg)';
        }
        
        bookingsMenu.addEventListener('click', function() {
            submenu.classList.toggle('open');
            chevron.style.transform = submenu.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0)';
        });
    });

    // Profile dropdown functionality
    document.getElementById('userAvatar').addEventListener('click', function(e) {
        e.stopPropagation();
        const dropdown = document.getElementById('profileDropdown');
        dropdown.classList.toggle('active');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function() {
        const dropdown = document.getElementById('profileDropdown');
        dropdown.classList.remove('active');
    });

    // Prevent dropdown from closing when clicking inside
    document.getElementById('profileDropdown').addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Logout function
    function logout() {
        if(confirm('Are you sure you want to logout?')) {
            window.location.href = 'logout.php';
        }
    }

    // Update page title based on current page
    document.addEventListener('DOMContentLoaded', function() {
        const pageTitle = document.getElementById('pageTitle');
        const currentPage = window.location.pathname.split('/').pop();
        
        const pageTitles = {
            'flights.php': 'Book Flights',
            'bookings.php': 'My Bookings',
            'ledger.php': 'Financial Ledger',
            'profile.php': 'My Profile',
            'payments.php': 'Payment Options',
            'support.php': 'Customer Support'
        };
        
        if (pageTitles[currentPage]) {
            pageTitle.textContent = pageTitles[currentPage];
        }
    });
</script>