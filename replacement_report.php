<?php
// replacement_report.php
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/config/database.php';

AuthMiddleware::enforcePermission('ticket_management', 'read');

$refId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($refId)) {
    die("Replacement Request ID is required.");
}

$db = Database::getConnection();

// Fetch replacement details
$stmt = $db->prepare("SELECT r.*, 
                             CONCAT(u_sales.first_name, ' ', u_sales.last_name) AS salesman_name
                      FROM replacement_requests r 
                      LEFT JOIN auth_users u_sales ON r.salesman_id = u_sales.id
                      WHERE r.ref_id = ? LIMIT 1");
$stmt->execute([$refId]);
$rep = $stmt->fetch();

if (!$rep) {
    die("Replacement request not found.");
}

$replacement_items = json_decode($rep['replacement_items'], true) ?: [];
$defective_items = json_decode($rep['defective_items'], true) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Replacement Request Report - <?php echo htmlspecialchars($refId); ?></title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Google Fonts Outfit -->
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- html2canvas and jsPDF CDNs -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <!-- Lucide Icons CDN -->
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    body {
      font-family: 'Outfit', sans-serif;
    }
    .print-page {
      width: 210mm;
      min-height: 297mm;
      padding: 20mm;
      margin: 10px auto;
      background: white;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      box-sizing: border-box;
      position: relative;
    }
    @media print {
      body {
        background: none;
      }
      .print-page {
        margin: 0;
        box-shadow: none;
        page-break-after: always;
      }
      .no-print {
        display: none !important;
      }
    }
  </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 text-xs">
  
  <!-- Action Bar -->
  <div class="no-print max-w-[210mm] mx-auto pt-6 px-4 flex justify-between items-center">
    <div class="flex items-center gap-2">
      <a href="index.php" class="px-3.5 py-1.5 bg-slate-200 hover:bg-slate-350 text-slate-700 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        <span>Go to Workspace</span>
      </a>
    </div>
    <div class="flex items-center gap-2">
      <button type="button" onclick="downloadPDF();" class="px-4 py-1.5 bg-violet-600 hover:bg-violet-750 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm transition-all">
        <i data-lucide="download" class="w-4 h-4"></i>
        <span>Download PDF</span>
      </button>
      <button type="button" onclick="window.print();" class="px-4 py-1.5 bg-slate-850 hover:bg-slate-900 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm transition-all">
        <i data-lucide="printer" class="w-4 h-4"></i>
        <span>Print Document</span>
      </button>
    </div>
  </div>

  <div id="reportPagesWrapper">
    <!-- Page 1 -->
    <div class="print-page border border-slate-200 flex flex-col justify-between" id="page-1">
      <div>
        <!-- Header -->
        <div class="flex justify-between items-start border-b border-slate-200 pb-5 mb-5">
          <div class="space-y-1.5">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-violet-600 to-indigo-600 flex items-center justify-center flex-shrink-0 shadow-md">
                <i data-lucide="cpu" class="w-5 h-5 text-white"></i>
              </div>
              <span class="font-extrabold text-slate-800 tracking-tight text-lg">Nexus Core</span>
            </div>
            <p class="text-[10px] text-slate-500 font-medium">SNL GROUP ENTERPRISE SOLUTIONS</p>
          </div>
          <div class="text-right space-y-1">
            <h2 class="text-base font-extrabold text-violet-600 tracking-wider uppercase">Replacement Request</h2>
            <p class="font-mono font-bold text-slate-700 text-sm">Ref: <?php echo htmlspecialchars($refId); ?></p>
            <p class="text-[10px] text-slate-400">Status: <span class="font-bold text-amber-500"><?php echo htmlspecialchars($rep['status']); ?></span></p>
          </div>
        </div>

        <!-- Details Grid -->
        <div class="grid grid-cols-2 gap-8 mb-6">
          <!-- Left: Basic Details -->
          <div class="bg-slate-50 p-4 rounded-xl border border-slate-150 space-y-2">
            <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-200 pb-1 mb-2">Replacement Details</h3>
            <div class="grid grid-cols-2 gap-y-2">
              <div>
                <span class="text-slate-400 block font-medium text-[10px]">Device Type:</span>
                <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($rep['device_type'] ?: '-'); ?></span>
              </div>
              <div>
                <span class="text-slate-400 block font-medium text-[10px]">Priority:</span>
                <span class="font-semibold text-red-600"><?php echo htmlspecialchars($rep['priority'] ?: '-'); ?></span>
              </div>
              <div>
                <span class="text-slate-400 block font-medium text-[10px]">Date & Time:</span>
                <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($rep['ticket_datetime'] ?: '-'); ?></span>
              </div>
              <div>
                <span class="text-slate-400 block font-medium text-[10px]">Salesman:</span>
                <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($rep['salesman_name'] ?: '-'); ?></span>
              </div>
              <div class="col-span-2 border-t border-slate-100 pt-1.5 mt-1">
                <span class="text-slate-400 block font-medium text-[10px]">Secondary Status:</span>
                <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($rep['secondary_status'] ?: 'Under Review'); ?><?php echo ($rep['secondary_status'] === 'Waiting' && $rep['waiting_reason']) ? ' (' . htmlspecialchars($rep['waiting_reason']) . ')' : ''; ?></span>
              </div>
            </div>
          </div>

          <!-- Right: Customer & Contact -->
          <div class="bg-slate-50 p-4 rounded-xl border border-slate-155 space-y-2">
            <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-200 pb-1 mb-2">Customer &amp; Contact</h3>
            <div class="grid grid-cols-2 gap-y-2">
              <div class="col-span-2">
                <span class="text-slate-400 block font-medium text-[10px]">Company / Customer Name:</span>
                <span class="font-bold text-slate-850"><?php echo htmlspecialchars($rep['company_name'] ?: '-'); ?></span>
              </div>
              <div>
                <span class="text-slate-400 block font-medium text-[10px]">Branch:</span>
                <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($rep['branch'] ?: '-'); ?></span>
              </div>
              <div>
                <span class="text-slate-400 block font-medium text-[10px]">Contact Person:</span>
                <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($rep['contact_person'] ?: '-'); ?></span>
              </div>
              <div>
                <span class="text-slate-400 block font-medium text-[10px]">Mobile:</span>
                <span class="font-semibold text-slate-800 font-mono"><?php echo htmlspecialchars($rep['mobile_no'] ?: '-'); ?></span>
              </div>

              <div class="col-span-2">
                <span class="text-slate-400 block font-medium text-[10px]">Address:</span>
                <span class="font-semibold text-slate-850"><?php echo htmlspecialchars(trim(($rep['address_line_1'] ?: '') . ' ' . ($rep['address_line_2'] ?: '')) ?: '-'); ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Nature of Replacement -->
        <div class="mb-6">
          <span class="text-slate-400 block font-medium text-[10px] uppercase tracking-wider mb-1">Nature of Replacement:</span>
          <div class="p-3 bg-violet-50/50 border border-violet-100 rounded-xl font-medium text-slate-700">
            <?php echo htmlspecialchars($rep['nature_of_replacement'] ?: 'Not Specified'); ?>
          </div>
        </div>

        <!-- Items Section -->
        <div class="space-y-6">
          <!-- Replacement Details (New) -->
          <div>
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2 border-b border-slate-200 pb-1.5">Replacement Details (New Items)</h3>
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-100 text-slate-500 uppercase tracking-wider font-bold text-[9px] border-b border-slate-200">
                  <th class="p-2 w-1/12 text-center">#</th>
                  <th class="p-2 w-7/12">Model</th>
                  <th class="p-2 w-3/12">Serial Number (New)</th>
                  <th class="p-2 w-1/12 text-right">Qty</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php if (empty($replacement_items)): ?>
                  <tr>
                    <td colspan="4" class="p-4 text-center text-slate-400 italic">No replacement items specified.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($replacement_items as $index => $item): ?>
                    <tr class="text-slate-700">
                      <td class="p-2 text-center font-mono text-[10px] text-slate-400"><?php echo $index + 1; ?></td>
                      <td class="p-2 font-semibold"><?php echo htmlspecialchars($item['model'] ?? '-'); ?></td>
                      <td class="p-2 font-mono text-[10px] text-slate-500"><?php echo htmlspecialchars($item['serial_new'] ?? '-'); ?></td>
                      <td class="p-2 text-right font-bold"><?php echo htmlspecialchars($item['qty'] ?? '1'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Defective Details -->
          <div>
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2 border-b border-slate-200 pb-1.5">Defective Details</h3>
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-100 text-slate-500 uppercase tracking-wider font-bold text-[9px] border-b border-slate-200">
                  <th class="p-2 w-1/12 text-center">#</th>
                  <th class="p-2 w-7/12">Model</th>
                  <th class="p-2 w-3/12">Serial Number (Defective)</th>
                  <th class="p-2 w-1/12 text-right">Qty</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php if (empty($defective_items)): ?>
                  <tr>
                    <td colspan="4" class="p-4 text-center text-slate-400 italic">No defective items specified.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($defective_items as $index => $item): ?>
                    <tr class="text-slate-700">
                      <td class="p-2 text-center font-mono text-[10px] text-slate-400"><?php echo $index + 1; ?></td>
                      <td class="p-2 font-semibold"><?php echo htmlspecialchars($item['model'] ?? '-'); ?></td>
                      <td class="p-2 font-mono text-[10px] text-slate-500"><?php echo htmlspecialchars($item['serial_defective'] ?? '-'); ?></td>
                      <td class="p-2 text-right font-bold"><?php echo htmlspecialchars($item['qty'] ?? '1'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Office Use Only Section -->
        <div class="mt-8 p-4 bg-slate-50 border border-slate-200 rounded-2xl">
          <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 pb-1 mb-3">Office Use Only</h4>
          <div class="grid grid-cols-3 gap-6 text-[10px]">
            <div>
              <span class="text-slate-400 block font-medium">Approved / Verified By:</span>
              <span class="font-semibold text-slate-800 block mt-1"><?php echo htmlspecialchars($rep['approved_by'] ?: '___________________________'); ?></span>
            </div>
            <div>
              <span class="text-slate-400 block font-medium">Verify Date:</span>
              <span class="font-semibold text-slate-800 block mt-1"><?php echo htmlspecialchars($rep['verify_date'] ?: '_____ / _____ / 20_____'); ?></span>
            </div>
            <div>
              <span class="text-slate-400 block font-medium">Review Status:</span>
              <span class="inline-flex items-center px-2.5 py-0.5 mt-0.5 rounded-full font-bold bg-amber-100 text-amber-700 uppercase">
                <?php echo htmlspecialchars($rep['status']); ?>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer elements including Terms and Signatures -->
      <div class="pt-8 border-t border-slate-200 mt-8 space-y-6">
        <!-- Terms and Conditions -->
        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
          <h4 class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Terms and Conditions</h4>
          <ul class="list-decimal pl-4 text-[9px] text-slate-500 space-y-1">
            <li>All items replaced under warranty remain subject to hardware verification rules.</li>
            <li>All defective items must be returned to the office repository in full as per details entered.</li>
            <li>The signatures below confirm that the replacement was carried out successfully in the quantities stated.</li>
          </ul>
        </div>

        <!-- Signatures Grid -->
        <div class="grid grid-cols-3 gap-4 text-center">
          <div class="space-y-12">
            <div class="border-b border-slate-300 pb-2 min-h-[20px]">
              <span class="font-bold text-slate-700 block"><?php echo htmlspecialchars($rep['authorised_name'] ?: ' '); ?></span>
            </div>
            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Authorised Signature</span>
          </div>
          <div class="space-y-12">
            <div class="border-b border-slate-300 pb-2 min-h-[20px]">
              <span class="font-bold text-slate-700 block"><?php echo htmlspecialchars($rep['replaced_by_name'] ?: ' '); ?></span>
            </div>
            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Replaced By Signature</span>
          </div>
          <div class="space-y-12">
            <div class="border-b border-slate-300 pb-2 min-h-[20px]">
              <span class="font-bold text-slate-700 block">&nbsp;</span>
            </div>
            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Customer Signature</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Lucide Icons
    if (window.lucide) {
      window.lucide.createIcons();
    }

    async function downloadPDF() {
      const wrapper = document.getElementById('reportPagesWrapper');
      if (!wrapper) return;
      
      const pageEl = document.getElementById('page-1');
      if (!pageEl) return;
      
      const originalZoom = wrapper.style.zoom || '1';
      wrapper.style.zoom = '1.0';
      document.body.classList.add('generating-pdf');
      
      const jsPDFClass = window.jsPDF || (window.jspdf && window.jspdf.jsPDF);
      if (!jsPDFClass) {
        alert('PDF library not fully loaded. Please refresh.');
        document.body.classList.remove('generating-pdf');
        wrapper.style.zoom = originalZoom;
        return;
      }
      
      try {
        const canvas = await html2canvas(pageEl, {
          scale: 2.5,
          useCORS: true,
          logging: false,
          backgroundColor: '#ffffff'
        });
        
        const imgData = canvas.toDataURL('image/jpeg', 0.95);
        const widthPx = canvas.width;
        const heightPx = canvas.height;
        const pdfWidthMm = 210;
        const pdfHeightMm = (heightPx / widthPx) * pdfWidthMm;
        
        const pdfDoc = new jsPDFClass({
          orientation: 'portrait',
          unit: 'mm',
          format: [pdfWidthMm, pdfHeightMm]
        });
        
        pdfDoc.addImage(imgData, 'JPEG', 0, 0, pdfWidthMm, pdfHeightMm);
        pdfDoc.save('Replacement_Request_<?php echo htmlspecialchars($refId); ?>.pdf');
      } catch (err) {
        console.error('PDF error:', err);
        alert('An error occurred during PDF generation.');
      } finally {
        document.body.classList.remove('generating-pdf');
        wrapper.style.zoom = originalZoom;
      }
    }

    // Auto trigger download
    <?php if (isset($_GET['autodownload']) && $_GET['autodownload'] == '1'): ?>
    window.addEventListener('DOMContentLoaded', () => {
      setTimeout(downloadPDF, 1500);
    });
    <?php endif; ?>
  </script>
</body>
</html>
