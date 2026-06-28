<?php
// index.php
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/controllers/UserController.php';

$isLoggedIn = AuthMiddleware::checkSession();
?>
<!DOCTYPE html>
<html lang="en" class="">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nexus Core - <?php echo $isLoggedIn ? 'Enterprise AI Dashboard' : 'Enterprise Sign In'; ?></title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: {
            sans: ['Plus Jakarta Sans', 'sans-serif'],
          },
          colors: {
            brand: {
              50: '#f5f3ff',
              100: '#ede9fe',
              200: '#ddd6fe',
              300: '#c4b5fd',
              400: '#a78bfa',
              500: '#8b5cf6',
              600: '#7c3aed',
              700: '#6d28d9',
              800: '#5b21b6',
              900: '#4c1d95',
            }
          }
        }
      }
    }
  </script>
  <!-- Custom CSS -->
  <link rel="stylesheet" href="index.css">
  <!-- Chart.js & ApexCharts CDNs -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>
</head>

<?php if (!$isLoggedIn): ?>
<!-- ================= AUTH LAYOUT ================= -->
<body class="sparkle-bg min-h-screen flex flex-col justify-between transition-colors duration-300 bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
  <!-- Top Bar with Theme Toggler -->
  <header class="w-full max-w-7xl mx-auto px-4 sm:px-6 py-4 flex justify-between items-center select-none">
    <div class="flex items-center space-x-2">
      <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-gradient-to-tr from-brand-600 to-indigo-600 flex items-center justify-center shadow-lg shadow-brand-500/20 hover:scale-105 transition-transform duration-300">
        <i data-lucide="cpu" class="w-5 h-5 sm:w-6 sm:h-6 text-white"></i>
      </div>
      <span class="text-lg sm:text-xl font-bold tracking-tight bg-gradient-to-r from-brand-600 to-indigo-600 dark:from-brand-400 dark:to-indigo-400 bg-clip-text text-transparent">Nexus Core</span>
    </div>
    
    <button type="button" id="themeToggle" class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors duration-200" title="Toggle Theme" aria-label="Toggle Theme">
      <i data-lucide="sun" id="sunIcon" class="w-4 h-4 sm:w-5 sm:h-5 hidden dark:block"></i>
      <i data-lucide="moon" id="moonIcon" class="w-4 h-4 sm:w-5 sm:h-5 block dark:hidden"></i>
    </button>
  </header>

  <main class="flex-grow flex items-center justify-center px-4 py-6 sm:py-8">
    <div id="auth-placeholder" class="w-full max-w-md flex justify-center">
      <!-- auth.component renders here -->
    </div>
  </main>

  <footer class="w-full text-center py-4 sm:py-6 text-xs text-slate-400 dark:text-slate-600 select-none px-4">
    <p>&copy; 2026 Nexus Core. All rights reserved. Version 2.4.0-Enterprise</p>
  </footer>

  <script>
    window.userSession = { isLoggedIn: false };
  </script>
  <script type="module" src="app.js"></script>
</body>

<?php else: ?>
<!-- ================= DASHBOARD LAYOUT ================= -->
<body class="sparkle-bg min-h-screen transition-colors duration-300 bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 flex overflow-x-hidden">
  
  <!-- Sidebar Placeholder -->
  <div id="sidebar-placeholder"></div>

  <!-- Main Container -->
  <div class="flex-grow flex flex-col h-screen overflow-hidden">
    <!-- Header Placeholder -->
    <div id="header-placeholder"></div>

    <!-- Main Scrollable Dashboard Content Area -->
    <main class="flex-grow overflow-y-auto p-6 relative">
      <!-- Dynamic Page Header (injected per view by app.js) -->
      <div id="page-header-placeholder"></div>

      <!-- Views Placeholder -->
      <div id="views-placeholder"></div>
    </main>
  </div>

  <!-- Drawers Placeholder -->
  <div id="drawers-placeholder"></div>

  <!-- Terminal Placeholder -->
  <div id="terminal-placeholder"></div>

  <!-- Modals Placeholder -->
  <div id="modals-placeholder"></div>

  <?php
    if ($isLoggedIn && (!isset($_SESSION['phone_number']) || !isset($_SESSION['address_line_1']) || !isset($_SESSION['country_region']))) {
        require_once __DIR__ . '/config/database.php';
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT phone_number, address_line_1, address_line_2, city, country_region FROM auth_users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $uData = $stmt->fetch();
        if ($uData) {
            $_SESSION['phone_number'] = $uData['phone_number'];
            $_SESSION['address_line_1'] = $uData['address_line_1'];
            $_SESSION['address_line_2'] = $uData['address_line_2'];
            $_SESSION['city'] = $uData['city'];
            $_SESSION['country_region'] = $uData['country_region'];
        }
    }
  ?>
  <!-- Pass User Details to JS Orchestrator -->
  <script>
    window.userSession = {
      isLoggedIn: true,
      userId: <?php echo json_encode($_SESSION['user_id']); ?>,
      accountNo: <?php echo json_encode($_SESSION['account_no']); ?>,
      email: <?php echo json_encode($_SESSION['email']); ?>,
      firstName: <?php echo json_encode($_SESSION['first_name']); ?>,
      lastName: <?php echo json_encode($_SESSION['last_name']); ?>,
      organizationName: <?php echo json_encode($_SESSION['organization_name']); ?>,
      profileImage: <?php echo json_encode($_SESSION['profile_image']); ?>,
      roleName: <?php echo json_encode($_SESSION['role_name']); ?>,
      phoneNumber: <?php echo json_encode($_SESSION['phone_number'] ?? ''); ?>,
      addressLine1: <?php echo json_encode($_SESSION['address_line_1'] ?? ''); ?>,
      addressLine2: <?php echo json_encode($_SESSION['address_line_2'] ?? ''); ?>,
      city: <?php echo json_encode($_SESSION['city'] ?? ''); ?>,
      countryRegion: <?php echo json_encode($_SESSION['country_region'] ?? ''); ?>,
      permissions: <?php echo json_encode($_SESSION['permissions']); ?>
    };
  </script>
  <!-- Application Logic Orchestrator -->
  <script type="module" src="app.js?v=<?php echo time(); ?>"></script>
</body>
<?php endif; ?>
</html>
