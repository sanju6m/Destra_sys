<?php
// report.php
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/config/database.php';

AuthMiddleware::enforcePermission('ticket_management', 'read');

$refId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($refId)) {
    die("Task ID is required.");
}

$db = Database::getConnection();

// Fetch task details
$stmt = $db->prepare("SELECT t.*, 
                             CONCAT(u.first_name, ' ', u.last_name) AS technician_name, u.email AS technician_email,
                             u.organization_name AS technician_org, u.address_line_1 AS technician_addr,
                             r.role_name AS technician_role,
                             CONCAT(u1.first_name, ' ', u1.last_name) AS first_technician_name,
                             u1.organization_name AS first_technician_org, u1.address_line_1 AS first_technician_addr,
                             r1.role_name AS first_technician_role,
                             CONCAT(u2.first_name, ' ', u2.last_name) AS transfer_technician_name,
                             u2.organization_name AS transfer_technician_org, u2.address_line_1 AS transfer_technician_addr,
                             r2.role_name AS transfer_technician_role,
                             CONCAT(u_sales.first_name, ' ', u_sales.last_name) AS salesman_name,
                             u_sales.organization_name AS salesman_org, u_sales.address_line_1 AS salesman_addr,
                             r_sales.role_name AS salesman_role
                      FROM workspace_tasks t 
                      LEFT JOIN auth_users u ON t.assigned_technician_id = u.id 
                      LEFT JOIN user_roles r ON u.role_id = r.id
                      LEFT JOIN auth_users u1 ON t.first_technician_id = u1.id 
                      LEFT JOIN user_roles r1 ON u1.role_id = r1.id
                      LEFT JOIN auth_users u2 ON t.transfer_technician_id = u2.id 
                      LEFT JOIN user_roles r2 ON u2.role_id = r2.id
                      LEFT JOIN auth_users u_sales ON t.salesman_id = u_sales.id
                      LEFT JOIN user_roles r_sales ON u_sales.role_id = r_sales.id
                      WHERE t.ref_id = ? LIMIT 1");
$stmt->execute([$refId]);
$task = $stmt->fetch();

if (!$task) {
    die("Task not found.");
}

// Ownership / record-level verification
$role = $_SESSION['role_name'] ?? '';
$orgName = $_SESSION['organization_name'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

if ($role === 'Client Administrator' || $role === 'Client User') {
    if ($task['company_name'] !== $orgName) {
        require_once __DIR__ . '/controllers/AuthController.php';
        AuthController::logActivity($userId, 'UNAUTHORIZED_REPORT_VIEW', "User tried to view report for task " . $task['ref_id'] . " belonging to organization " . $task['company_name']);
        header('Location: index.php?route=access_denied');
        exit;
    }
} elseif (in_array($role, ['Technician', 'Senior Technician', 'Field Engineer', 'Helpdesk Operator', 'Customer Support Executive', 'Vendor / Supplier'])) {
    if ($task['assigned_technician_id'] != $userId && $task['first_technician_id'] != $userId && $task['transfer_technician_id'] != $userId) {
        require_once __DIR__ . '/controllers/AuthController.php';
        AuthController::logActivity($userId, 'UNAUTHORIZED_REPORT_VIEW', "User with role $role tried to view report for task " . $task['ref_id'] . " not assigned to them.");
        header('Location: index.php?route=access_denied');
        exit;
    }
}

function formatReportUser(?string $name, ?string $role, ?string $org, ?string $addr): ?string {
    if (empty($name) || $name === 'Not Assigned' || $name === '-' || $name === 'Non') {
        return $name;
    }
    $allowedRoles = [
        'Technician',
        'Client Administrator',
        'Client User',
        'Senior Technician',
        'Field Engineer',
        'Customer Support Executive',
        'Helpdesk Operator',
        'Vendor / Supplier',
        'Sales Manager',
        'Sales Coordinator',
        'Sales Executive'
    ];
    if (!in_array($role, $allowedRoles)) {
        return '-';
    }
    if ($role === 'Client User' || $role === 'Vendor / Supplier') {
        return ($org ?: '') . ($addr ? ' - ' . $addr : '');
    } elseif ($role === 'Client Administrator') {
        return $org ?: '';
    } else {
        return $name;
    }
}

// Fetch replaced parts
$partsStmt = $db->prepare("SELECT * FROM ticket_parts WHERE task_id = ? ORDER BY id ASC");
$partsStmt->execute([$refId]);
$parts = $partsStmt->fetchAll();

// Fetch quotation details & Submitted By name
$qStmt = $db->prepare("SELECT * FROM quotation_requests WHERE task_ref_id = ? LIMIT 1");
$qStmt->execute([$refId]);
$quotation = $qStmt->fetch();
$submittedByName = '-';
if ($quotation) {
    $quotation['items_to_replace'] = json_decode($quotation['items_to_replace'], true) ?: [];
    $quotation['damage_items'] = json_decode($quotation['damage_items'], true) ?: [];
    
    if (!empty($quotation['submitted_by_email'])) {
        $uStmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM auth_users WHERE email = ? LIMIT 1");
        $uStmt->execute([$quotation['submitted_by_email']]);
        $submittedByName = $uStmt->fetchColumn() ?: $quotation['submitted_by_email'];
    }
}

// Fetch notifications history for timeline
$notifStmt = $db->prepare("SELECT DISTINCT title, message, created_at FROM workspace_notifications WHERE task_ref_id = ? ORDER BY created_at ASC");
$notifStmt->execute([$refId]);
$notifications = $notifStmt->fetchAll();

// Fetch activity logs history for timeline
$logsStmt = $db->prepare("SELECT al.*, CONCAT(au.first_name, ' ', au.last_name) AS user_name 
                          FROM activity_logs al 
                          LEFT JOIN auth_users au ON al.user_id = au.id 
                          WHERE al.details LIKE ? 
                          ORDER BY al.created_at ASC");
$logsStmt->execute(["%" . $refId . "%"]);
$logs = $logsStmt->fetchAll();

// Compile timeline
$timeline = [];

// Add task creation date
$timeline[] = [
    'timestamp' => $task['ticket_datetime'] ?: $task['created_at'],
    'title' => 'Ticket Opened',
    'details' => 'Ticket was created in Pending status.',
    'actor' => $task['first_assigned_by'] ?: 'System'
];

// Add notifications to timeline
foreach ($notifications as $n) {
    $timeline[] = [
        'timestamp' => $n['created_at'],
        'title' => $n['title'],
        'details' => $n['message'],
        'actor' => 'Notification System'
    ];
}

// Add activity logs to timeline
foreach ($logs as $l) {
    if ($l['action'] === 'TASK_CREATED') continue;
    $timeline[] = [
        'timestamp' => $l['created_at'],
        'title' => str_replace('_', ' ', $l['action']),
        'details' => $l['details'],
        'actor' => $l['user_name'] ?: 'Admin User'
    ];
}

// Sort timeline by timestamp
usort($timeline, function($a, $b) {
    return strtotime($a['timestamp']) - strtotime($b['timestamp']);
});

// Format badges
$priority = $task['priority'];
$priorityClass = 'bg-slate-100 text-slate-600';
if ($priority === 'High') $priorityClass = 'bg-red-100 text-red-700';
if ($priority === 'Medium') $priorityClass = 'bg-amber-100 text-amber-700';
if ($priority === 'Low') $priorityClass = 'bg-emerald-100 text-emerald-700';

$status = $task['status'];
$statusClass = 'bg-amber-100 text-amber-700';
if (in_array($status, ['Completed', 'Resolved', 'Closed'])) {
    $statusClass = 'bg-emerald-100 text-emerald-700';
} else if ($status === 'Assigned') {
    $statusClass = 'bg-indigo-100 text-indigo-700';
} else if ($status === 'Hold') {
    $statusClass = 'bg-purple-100 text-purple-700';
} else if ($status === 'Cancelled') {
    $statusClass = 'bg-rose-100 text-rose-700';
}

// Clean status updater name to only include the name, omitting the email
$statusUpdatedByName = '-';
if (!empty($task['status_updated_by'])) {
    $updaterExplode = explode('(', $task['status_updated_by']);
    $statusUpdatedByName = trim($updaterExplode[0]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Service Ticket Report - <?php echo htmlspecialchars($refId); ?></title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>
  <!-- html2canvas and jsPDF CDNs -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .logo-accent-l {
      font-weight: 800;
      background: linear-gradient(to right, #dc2626, #2563eb);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
      color: transparent;
    }
    .logo-accent-group {
      font-weight: 800;
      background: linear-gradient(to right, #dc2626, #2563eb);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
      color: transparent;
    }
    @media print {
      .logo-accent-l {
        background: none !important;
        -webkit-background-clip: initial !important;
        background-clip: initial !important;
        -webkit-text-fill-color: #dc2626 !important;
        color: #dc2626 !important;
      }
      .logo-accent-group {
        background: none !important;
        -webkit-background-clip: initial !important;
        background-clip: initial !important;
        -webkit-text-fill-color: #2563eb !important;
        color: #2563eb !important;
      }
    }
    body.generating-pdf .logo-accent-l {
      background: none !important;
      -webkit-background-clip: initial !important;
      background-clip: initial !important;
      -webkit-text-fill-color: #dc2626 !important;
      color: #dc2626 !important;
    }
    body.generating-pdf .logo-accent-group {
      background: none !important;
      -webkit-background-clip: initial !important;
      background-clip: initial !important;
      -webkit-text-fill-color: #2563eb !important;
      color: #2563eb !important;
    }
  </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

  <!-- Floating Control Bar -->
  <div class="no-print bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm py-3 px-6">
    <div class="max-w-4xl mx-auto flex items-center justify-between">
      <div class="flex items-center space-x-2">
        <span class="text-slate-400"><i data-lucide="file-text" class="w-5 h-5 text-brand-500"></i></span>
        <h1 class="text-sm font-bold text-slate-700">Ticket Report Generator (SNL Group)</h1>
      </div>
      <div class="flex items-center space-x-4">
        <!-- Zoom Controls -->
        <div class="flex items-center space-x-1.5 border border-slate-200 rounded-xl p-1 bg-slate-50 select-none">
          <button type="button" onclick="zoomReport(-0.1);" class="p-1 text-slate-500 hover:bg-white rounded-lg hover:text-slate-700 active:scale-95 transition-all" title="Zoom Out">
            <i data-lucide="minus" class="w-3.5 h-3.5"></i>
          </button>
          <span id="zoomPercent" class="text-[10px] font-bold text-slate-600 min-w-[36px] text-center">100%</span>
          <button type="button" onclick="zoomReport(0.1);" class="p-1 text-slate-500 hover:bg-white rounded-lg hover:text-slate-700 active:scale-95 transition-all" title="Zoom In">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
          </button>
        </div>

        <div class="flex items-center space-x-2">
          <button type="button" onclick="window.close();" class="px-3 py-1.5 border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all">
            <i data-lucide="x" class="w-4 h-4"></i>
            <span>Close Window</span>
          </button>
          <button type="button" onclick="downloadPDF();" class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm transition-all">
            <i data-lucide="download" class="w-4 h-4"></i>
            <span>Download PDF</span>
          </button>
          <button type="button" onclick="window.print();" class="px-4 py-1.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm transition-all">
            <i data-lucide="printer" class="w-4 h-4"></i>
            <span>Print / Save as PDF</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <div id="reportPagesWrapper" class="w-full">

  <!-- Page 1 Container: DETAILS -->
  <div class="print-page max-w-4xl mx-auto my-6 p-8 bg-white border border-slate-200 shadow-sm rounded-3xl">
    <div class="space-y-6">
      
      <!-- Brand & Title Header -->
      <div class="flex justify-between items-start pb-5 border-b border-slate-200 mb-6">
        <div class="space-y-1.5">
          <h1 class="text-[26px] font-extrabold tracking-tight flex items-center select-none">
            <span class="text-[#dc2626]">S</span>
            <span class="text-[#2563eb]">N</span>
            <span class="logo-accent-l">L</span>
            <span class="logo-accent-group">&nbsp;GROUP</span>
          </h1>
          <div class="text-[10px] text-slate-500 font-medium space-y-0.5 leading-tight">
            <p>Address: #118 High Level Road, Maharagama, Sri Lanka</p>
            <p>Mobile: +94 77 790 7779 &bull; Telephone: +94 11 779 9666</p>
            <p>Email: sales2@softnetlanka.com</p>
          </div>
          <p class="text-[12px] text-purple-700 font-bold uppercase tracking-wider mt-2">Enterprise Service Report</p>
        </div>
        <div class="text-right">
          <span class="text-xs font-mono font-bold text-slate-400 block">TICKET ID</span>
          <span class="text-xl font-mono font-extrabold text-purple-600 tracking-wide"><?php echo htmlspecialchars($task['ref_id']); ?></span>
          <span class="text-[10px] font-bold text-slate-400 block mt-0.5">Created: <?php echo htmlspecialchars($task['ticket_datetime'] ?: $task['created_at']); ?></span>
          <?php if ($task['title'] === 'Workshop'): ?>
            <div class="text-[10px] font-bold text-slate-650 mt-0.5 flex items-center justify-end gap-1">
              <span class="select-none">STK No:</span>
              <input type="text" class="stk-input border-b border-dashed border-slate-300 bg-transparent focus:outline-none w-24 text-slate-800 font-mono font-bold text-[10px] text-right pb-0.5" placeholder="........................">
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Ticket Overview Block -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 bg-slate-50/50 rounded-2xl p-4 border border-slate-100">
        <div>
          <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Type of Ticket</span>
          <span class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($task['title'] ?: '-'); ?></span>
        </div>
        <div>
          <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Ticket Volume</span>
          <span class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($task['category'] ?: '-'); ?></span>
        </div>
        <div>
          <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Priority</span>
          <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold mt-0.5 <?php echo $priorityClass; ?>">
            <?php echo htmlspecialchars($task['priority']); ?>
          </span>
        </div>
        <div>
          <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Current Status</span>
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold mt-0.5 <?php echo $statusClass; ?>">
            <?php echo htmlspecialchars($task['status']); ?>
          </span>
        </div>
      </div>

      <!-- Two-Column Block: Customer details & Device Details -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
        
        <!-- Company & Customer Details -->
        <div class="border border-slate-150 rounded-2xl p-4 space-y-3">
          <h3 class="text-xs font-bold text-purple-700 uppercase tracking-wider flex items-center gap-1.5 pb-2 border-b border-slate-100">
            <i data-lucide="building" class="w-3.5 h-3.5 text-purple-500"></i>
            <span>Customer & Contact Details</span>
          </h3>
          <div class="space-y-2.5 text-xs">
            <div>
              <span class="text-[10px] font-medium text-slate-400 block">Company Name</span>
              <span class="font-bold text-slate-800"><?php echo htmlspecialchars($task['company_name'] ?: '-'); ?></span>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <span class="text-[10px] font-medium text-slate-400 block">Branch</span>
                <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($task['branch'] ?: '-'); ?></span>
              </div>
              <div>
                <span class="text-[10px] font-medium text-slate-400 block">Contact Person</span>
                <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($task['contact_person'] ?: '-'); ?></span>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <span class="text-[10px] font-medium text-slate-400 block">Mobile Number</span>
                <span class="font-medium font-mono text-slate-800"><?php echo htmlspecialchars($task['mobile_no'] ?: '-'); ?></span>
              </div>
              <div>
                <span class="text-[10px] font-medium text-slate-400 block">Telephone Number</span>
                <span class="font-medium font-mono text-slate-800"><?php echo htmlspecialchars($task['tel_no'] ?: '-'); ?></span>
              </div>
            </div>
            <div>
              <span class="text-[10px] font-medium text-slate-400 block">Email Address</span>
              <span class="font-medium text-slate-800 break-all"><?php echo htmlspecialchars($task['email'] ?: '-'); ?></span>
            </div>
            <div>
              <span class="text-[10px] font-medium text-slate-400 block">Address Line 1 &amp; 2</span>
              <span class="font-medium text-slate-700 leading-relaxed block">
                <?php echo htmlspecialchars($task['address_line_1'] ?: ''); ?>
                <?php if ($task['address_line_2']) echo '<br>' . htmlspecialchars($task['address_line_2']); ?>
                <?php if (!$task['address_line_1'] && !$task['address_line_2']) echo '-'; ?>
              </span>
            </div>
          </div>
        </div>

        <!-- Device Information Details -->
        <div class="border border-slate-150 rounded-2xl p-4 space-y-3">
          <h3 class="text-xs font-bold text-purple-700 uppercase tracking-wider flex items-center gap-1.5 pb-2 border-b border-slate-100">
            <i data-lucide="printer" class="w-3.5 h-3.5 text-purple-500"></i>
            <span>Device Information</span>
          </h3>
          <div class="space-y-2.5 text-xs">
            <div>
              <span class="text-[10px] font-medium text-slate-400 block">Device Type</span>
              <span class="font-bold text-slate-800"><?php echo htmlspecialchars($task['device_type'] ?: '-'); ?></span>
            </div>
            <div>
              <span class="text-[10px] font-medium text-slate-400 block">Device Model</span>
              <span class="font-bold text-slate-800"><?php echo htmlspecialchars($task['device_model'] ?: '-'); ?></span>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <span class="text-[10px] font-medium text-slate-400 block">Serial Number</span>
                <span class="font-semibold text-slate-800 font-mono"><?php echo htmlspecialchars($task['serial_no'] ?: '-'); ?></span>
              </div>
              <div>
                <span class="text-[10px] font-medium text-slate-400 block">Product No (AOD No)</span>
                <span class="font-semibold text-slate-800 font-mono"><?php echo htmlspecialchars($task['product_no'] ?: '-'); ?></span>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <span class="text-[10px] font-medium text-slate-400 block">Warranty</span>
                <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($task['warranty'] ?: 'No'); ?></span>
              </div>
              <div>
                <span class="text-[10px] font-medium text-slate-400 block">Unit Quantity</span>
                <span class="font-bold text-slate-800"><?php echo htmlspecialchars($task['unit_qty'] ?: 1); ?></span>
              </div>
            </div>
            <div>
              <span class="text-[10px] font-medium text-slate-400 block">Assigned Salesman</span>
              <span class="font-bold text-purple-700"><?php echo htmlspecialchars(formatReportUser($task['salesman_name'], $task['salesman_role'] ?? null, $task['salesman_org'] ?? null, $task['salesman_addr'] ?? null) ?: 'Non'); ?></span>
            </div>
          </div>
        </div>

      </div>

      <!-- Fault and Defect Details -->
      <div class="border border-slate-150 rounded-2xl p-4 space-y-3">
        <h3 class="text-xs font-bold text-purple-700 uppercase tracking-wider flex items-center gap-1.5 pb-2 border-b border-slate-100">
          <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-purple-500"></i>
          <span>Fault &amp; Service Request Details</span>
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
          <div>
            <span class="text-[10px] font-medium text-slate-400 block">Nature of Defect</span>
            <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($task['defect_type'] ?: '-'); ?></span>
          </div>
          <div>
            <span class="text-[10px] font-medium text-slate-400 block">Customer Request Service</span>
            <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($task['service_type'] ?: '-'); ?></span>
          </div>
          <div>
            <span class="text-[10px] font-medium text-slate-400 block">Damages / External Status</span>
            <span class="font-semibold text-slate-850"><?php echo htmlspecialchars($task['damages'] ?: 'None'); ?></span>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4 text-xs pt-1">
          <div>
            <span class="text-[10px] font-medium text-slate-400 block">Category 1</span>
            <span class="font-medium text-slate-700"><?php echo htmlspecialchars($task['category_1'] ?: '-'); ?></span>
          </div>
          <div>
            <span class="text-[10px] font-medium text-slate-400 block">Category 2</span>
            <span class="font-medium text-slate-700"><?php echo htmlspecialchars($task['category_2'] ?: '-'); ?></span>
          </div>
        </div>
        <div class="pt-2">
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Ticket Description / Special Notes</span>
          <div class="p-3 bg-slate-50 border border-slate-100 rounded-xl text-xs text-slate-700 whitespace-pre-line leading-relaxed">
            <?php echo htmlspecialchars($task['description'] ?: 'No description provided for this ticket.'); ?>
          </div>
        </div>
      </div>

      <!-- Signatures Block -->
      <div class="grid grid-cols-2 gap-12 pt-8 mt-8 border-t border-slate-200">
        <div class="flex flex-col items-center">
          <div class="w-full border-b border-dashed border-slate-350 h-16"></div>
          <span class="text-xs font-bold text-slate-550 uppercase tracking-wider mt-2">Customer Signature</span>
        </div>
        <div class="flex flex-col items-center">
          <div class="w-full border-b border-dashed border-slate-350 h-16"></div>
          <span class="text-xs font-bold text-slate-550 uppercase tracking-wider mt-2">Authorized Signature</span>
        </div>
      </div>

    </div>
  </div>

  <!-- Page 2 Container: HISTORY -->
  <div class="print-page max-w-4xl mx-auto my-6 p-8 bg-white border border-slate-200 shadow-sm rounded-3xl">
    <div class="space-y-6">
      
      <!-- Brand & Title Header Page 2 -->
      <div class="flex justify-between items-start pb-5 border-b border-slate-200 mb-6">
        <div class="space-y-1.5">
          <h1 class="text-[26px] font-extrabold tracking-tight flex items-center select-none">
            <span class="text-[#dc2626]">S</span>
            <span class="text-[#2563eb]">N</span>
            <span class="logo-accent-l">L</span>
            <span class="logo-accent-group">&nbsp;GROUP</span>
          </h1>
          <p class="text-xs text-purple-700 font-bold uppercase tracking-wider mt-1">Ticket Workflow &amp; History</p>
        </div>
        <div class="text-right">
          <span class="text-xs font-mono font-bold text-slate-400 block">TICKET ID</span>
          <span class="text-xl font-mono font-extrabold text-purple-600 tracking-wide"><?php echo htmlspecialchars($task['ref_id']); ?></span>
          <span class="text-[10px] font-bold text-slate-400 block mt-0.5">Created: <?php echo htmlspecialchars($task['ticket_datetime'] ?: $task['created_at']); ?></span>
          <?php if ($task['title'] === 'Workshop'): ?>
            <div class="text-[10px] font-bold text-slate-650 mt-0.5 flex items-center justify-end gap-1">
              <span class="select-none">STK No:</span>
              <input type="text" class="stk-input border-b border-dashed border-slate-300 bg-transparent focus:outline-none w-24 text-slate-800 font-mono font-bold text-[10px] text-right pb-0.5" placeholder="........................">
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Actors and Mappings Grid -->
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <div class="p-3 border border-slate-100 rounded-xl bg-slate-50/50">
          <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Created / First Assigned By</span>
          <span class="text-xs font-semibold text-slate-800"><?php echo htmlspecialchars($task['first_assigned_by'] ?: 'System / Admin'); ?></span>
        </div>
        <div class="p-3 border border-slate-100 rounded-xl bg-slate-50/50">
          <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Assigned Technician</span>
          <span class="text-xs font-semibold text-slate-800 font-bold">
            <?php 
              $techName = $task['technician_name'] ?: $task['first_technician_name'];
              $techRole = $task['technician_role'] ?: $task['first_technician_role'];
              $techOrg = $task['technician_org'] ?: $task['first_technician_org'];
              $techAddr = $task['technician_addr'] ?: $task['first_technician_addr'];
              if ($task['assignment_mode'] === 'Transferred' && $task['transfer_technician_name']) {
                  echo htmlspecialchars(formatReportUser($task['transfer_technician_name'], $task['transfer_technician_role'], $task['transfer_technician_org'], $task['transfer_technician_addr'])) . ' (Transferred)';
              } else {
                  echo htmlspecialchars(formatReportUser($techName, $techRole, $techOrg, $techAddr) ?: 'Not Assigned');
              }
            ?>
          </span>
        </div>
        <div class="p-3 border border-slate-100 rounded-xl bg-slate-50/50">
          <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Quotation Issued By</span>
          <span class="text-xs font-semibold text-slate-800"><?php echo htmlspecialchars($task['quotation_by'] ?: '-'); ?></span>
        </div>
        <div class="p-3 border border-slate-100 rounded-xl bg-slate-50/50">
          <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Resolved By</span>
          <span class="text-xs font-semibold text-slate-800"><?php echo htmlspecialchars($task['resolved_by'] ?: '-'); ?></span>
        </div>
        <div class="p-3 border border-slate-100 rounded-xl bg-slate-50/50">
          <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Closed By</span>
          <span class="text-xs font-semibold text-slate-800"><?php echo htmlspecialchars($task['closed_by'] ?: '-'); ?></span>
        </div>
        <div class="p-3 border border-slate-100 rounded-xl bg-slate-50/50">
          <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Last Status Update By</span>
          <span class="text-xs font-semibold text-slate-800 truncate block">
            <?php echo htmlspecialchars($statusUpdatedByName); ?>
          </span>
        </div>
      </div>

      <!-- Parts Replaced Block -->
      <div class="border border-slate-150 rounded-2xl p-4 space-y-3">
        <h3 class="text-xs font-bold text-purple-700 uppercase tracking-wider flex items-center gap-1.5 pb-2 border-b border-slate-100">
          <i data-lucide="package" class="w-3.5 h-3.5 text-purple-500"></i>
          <span>Parts Replaced or Repaired (<?php echo count($parts); ?>)</span>
        </h3>
        <?php if (empty($parts)): ?>
          <p class="text-xs text-slate-400 italic">No parts replacement logged for this ticket.</p>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
              <thead>
                <tr class="border-b border-slate-200 text-slate-400 font-bold uppercase tracking-wider text-[9px]">
                  <th class="pb-2">Part Description / Name</th>
                  <th class="pb-2 text-center">Quantity</th>
                  <th class="pb-2 text-right">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php foreach ($parts as $p): ?>
                  <tr>
                    <td class="py-2 font-semibold text-slate-800"><?php echo htmlspecialchars($p['part_name']); ?></td>
                    <td class="py-2 text-center font-bold text-slate-700"><?php echo (int)$p['quantity']; ?></td>
                    <td class="py-2 text-right">
                      <span class="px-1.5 py-0.5 rounded text-[10px] font-bold <?php echo $p['status'] === 'Replaced' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'; ?>">
                        <?php echo htmlspecialchars($p['status']); ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Quotation & Damage Details Block if Quotation Required -->
      <?php if ($quotation): ?>
        <div class="border border-slate-150 rounded-2xl p-4 space-y-3">
          <h3 class="text-xs font-bold text-purple-700 uppercase tracking-wider flex items-center gap-1.5 pb-2 border-b border-slate-100">
            <i data-lucide="file-text" class="w-3.5 h-3.5 text-purple-500"></i>
            <span>Quotation Request Information</span>
          </h3>
          <div class="grid grid-cols-2 gap-4 text-xs">
            <div>
              <span class="text-[10px] font-medium text-slate-400 block">Quotation Number</span>
              <span class="font-bold text-slate-800 font-mono"><?php echo htmlspecialchars($quotation['quotation_number']); ?></span>
            </div>
            <div>
              <span class="text-[10px] font-medium text-slate-400 block">Submitted By</span>
              <span class="font-semibold text-slate-800 block"><?php echo htmlspecialchars($submittedByName); ?></span>
            </div>
          </div>
          <?php if (!empty($quotation['send_date']) || !empty($quotation['total_value']) || !empty($quotation['reject_reason'])): ?>
            <div class="grid grid-cols-2 gap-4 text-xs pt-2.5 border-t border-slate-100">
              <?php if (!empty($quotation['send_date'])): ?>
                <div>
                  <span class="text-[10px] font-medium text-slate-400 block">Send Date &amp; Validity</span>
                  <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($quotation['send_date']); ?> &bull; <?php echo (int)$quotation['valid_days']; ?> Days</span>
                  <span class="text-[9px] text-slate-400 block">Expires: <?php echo htmlspecialchars($quotation['expire_date'] ?: '-'); ?></span>
                </div>
              <?php endif; ?>
              <?php if (!empty($quotation['total_value'])): ?>
                <div>
                  <span class="text-[10px] font-medium text-slate-400 block">Total Value</span>
                  <span class="font-bold text-slate-800"><?php echo number_format((float)$quotation['total_value'], 2); ?></span>
                </div>
              <?php endif; ?>
            </div>
            <?php if (!empty($quotation['reject_reason'])): ?>
              <div class="pt-2.5 border-t border-slate-100">
                <span class="text-[10px] font-medium text-slate-400 block">Quotation Reject Reason</span>
                <span class="font-semibold text-rose-600"><?php echo htmlspecialchars($quotation['reject_reason']); ?></span>
              </div>
            <?php endif; ?>
          <?php endif; ?>
          <?php if (!empty($quotation['items_to_replace'])): ?>
            <div class="pt-2">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Items To Replace</span>
              <ul class="list-disc pl-5 text-xs text-slate-700 space-y-1">
                <?php foreach ($quotation['items_to_replace'] as $item): ?>
                  <li><strong><?php echo htmlspecialchars($item['name']); ?></strong> - Qty: <?php echo (int)$item['qty']; ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <?php if (!empty($quotation['damage_items'])): ?>
            <div class="pt-2">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Damages Detailed</span>
              <ul class="list-disc pl-5 text-xs text-red-600 space-y-1 font-medium">
                <?php foreach ($quotation['damage_items'] as $item): ?>
                  <li><?php echo htmlspecialchars($item); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Chronological Timeline Log -->
      <div class="border border-slate-150 rounded-2xl p-4 space-y-4">
        <h3 class="text-xs font-bold text-purple-700 uppercase tracking-wider flex items-center gap-1.5 pb-2 border-b border-slate-100">
          <i data-lucide="clock" class="w-3.5 h-3.5 text-purple-500"></i>
          <span>Chronological Timeline History Logs</span>
        </h3>
        <div class="relative pl-5 space-y-4">
          <!-- Connective Timeline Line -->
          <div class="absolute left-[8px] top-1.5 bottom-1.5 w-0.5 border-l border-dashed border-slate-200"></div>

          <?php foreach ($timeline as $evt): ?>
            <div class="relative flex items-start space-x-3 text-xs">
              <div class="absolute -left-[20px] mt-0.5 flex items-center justify-center w-2.5 h-2.5 rounded-full bg-purple-500 border border-white z-10"></div>
              <div class="flex-grow min-w-0">
                <div class="flex justify-between items-baseline mb-0.5">
                  <h4 class="font-bold text-slate-800"><?php echo htmlspecialchars($evt['title']); ?></h4>
                  <span class="text-[9px] text-slate-400 font-bold"><?php echo htmlspecialchars($evt['timestamp']); ?></span>
                </div>
                <p class="text-slate-500 font-medium text-[11px] leading-relaxed"><?php echo htmlspecialchars($evt['details']); ?></p>
                <span class="text-[9px] text-purple-600 font-bold block mt-0.5">By: <?php echo htmlspecialchars($evt['actor']); ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

    <!-- Footer Disclaimer -->
    <div class="mt-8 pt-4 border-t border-slate-150 text-[10px] text-slate-400 font-medium flex justify-between select-none">
      <span>Generated by SNL Group Enterprise Service Portal</span>
      <span>&copy; 2026 SNL Group. System Version 2.4.0</span>
    </div>
  </div>

  <script>
    // Initialize Lucide Icons
    if (window.lucide) {
      window.lucide.createIcons();
    }
    
    // Zoom Functionality
    let currentZoom = 1.0;
    function zoomReport(factor) {
      currentZoom = Math.min(Math.max(currentZoom + factor, 0.5), 2.0);
      const wrapper = document.getElementById('reportPagesWrapper');
      if (wrapper) {
        wrapper.style.zoom = currentZoom;
      }
      document.getElementById('zoomPercent').textContent = Math.round(currentZoom * 100) + '%';
    }

    // PDF Download Functionality with dynamic page size based on content height
    async function downloadPDF() {
      const wrapper = document.getElementById('reportPagesWrapper');
      if (!wrapper) return;
      
      const pages = document.querySelectorAll('.print-page');
      if (pages.length === 0) return;
      
      const originalZoom = wrapper.style.zoom || '1';
      
      // Reset zoom factor for correct canvas layout generation
      wrapper.style.zoom = '1.0';
      document.body.classList.add('generating-pdf');
      
      // Get jsPDF constructor
      const jsPDFClass = window.jsPDF || (window.jspdf && window.jspdf.jsPDF);
      if (!jsPDFClass) {
        alert('PDF library not fully loaded. Please refresh and try again.');
        document.body.classList.remove('generating-pdf');
        wrapper.style.zoom = originalZoom;
        return;
      }
      
      try {
        let pdfDoc = null;
        
        for (let i = 0; i < pages.length; i++) {
          const pageEl = pages[i];
          
          // Render page element to canvas
          const canvas = await html2canvas(pageEl, {
            scale: 2.5, // High quality scale
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff'
          });
          
          const imgData = canvas.toDataURL('image/jpeg', 0.95);
          
          // Calculate dynamic dimensions
          const widthPx = canvas.width;
          const heightPx = canvas.height;
          const pdfWidthMm = 210; // Standard A4 width in mm
          const pdfHeightMm = (heightPx / widthPx) * pdfWidthMm;
          
          if (i === 0) {
            pdfDoc = new jsPDFClass({
              orientation: 'portrait',
              unit: 'mm',
              format: [pdfWidthMm, pdfHeightMm]
            });
          } else {
            pdfDoc.addPage([pdfWidthMm, pdfHeightMm], 'portrait');
          }
          
          pdfDoc.addImage(imgData, 'JPEG', 0, 0, pdfWidthMm, pdfHeightMm);
        }
        
        // Save PDF file
        const filename = 'SNL_Group_Service_Report_' + '<?php echo htmlspecialchars($refId); ?>' + '.pdf';
        pdfDoc.save(filename);
        
      } catch (err) {
        console.error('PDF Generation Error:', err);
        alert('An error occurred during PDF generation: ' + err.message);
      } finally {
        document.body.classList.remove('generating-pdf');
        wrapper.style.zoom = originalZoom;
      }
    }

    // Auto-trigger PDF download if requested via query parameter
    <?php if (isset($_GET['autodownload']) && $_GET['autodownload'] == '1'): ?>
    window.addEventListener('DOMContentLoaded', () => {
      setTimeout(downloadPDF, 1500);
    });
    <?php endif; ?>

    // Synchronize STK input fields
    document.querySelectorAll('.stk-input').forEach(input => {
      input.addEventListener('input', (e) => {
        const val = e.target.value;
        document.querySelectorAll('.stk-input').forEach(el => {
          el.value = val;
          el.setAttribute('value', val);
        });
      });
    });
  </script>
  <style>
    @media print {
      .no-print {
        display: none !important;
      }
      html, body {
        background-color: #ffffff !important;
        background: #ffffff !important;
      }
      #reportPagesWrapper {
        zoom: 1.0 !important;
        background-color: #ffffff !important;
        background: #ffffff !important;
      }
      .print-page {
        margin: 0 !important;
        padding: 20mm !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        page-break-after: always !important;
        break-after: page !important;
        background: #ffffff !important;
        background-color: #ffffff !important;
      }
      .print-page:last-child {
        page-break-after: avoid !important;
        break-after: avoid !important;
      }
      /* Avoid page breaks inside cards, tables, rows, and lists */
      .print-page .border,
      .print-page table,
      .print-page tr,
      .print-page ul,
      .print-page ol,
      .print-page .grid > div {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
      }

      /* Ensure background colors and badge elements print exactly */
      * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
    }
    @page {
      size: A4 portrait;
      margin: 0; /* Let print-page padding act as margins and suppress default browser headers/footers */
    }

    /* Dedicated styles for html2pdf generation */
    body.generating-pdf {
      background-color: #ffffff !important;
      background: #ffffff !important;
    }
    body.generating-pdf .print-page {
      margin: 0 !important;
      padding: 20mm !important;
      border: none !important;
      box-shadow: none !important;
      border-radius: 0 !important;
      width: 210mm !important;
      box-sizing: border-box !important;
      background: #ffffff !important;
    }
  </style>
</body>
</html>
