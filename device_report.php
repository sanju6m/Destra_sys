<?php
// device_report.php
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/config/database.php';

AuthMiddleware::enforcePermission('ticket_management', 'read');

$deviceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($deviceId <= 0) {
    die("Device ID is required.");
}

$db = Database::getConnection();

// Fetch device details
$stmt = $db->prepare("SELECT * FROM registered_devices WHERE id = ? LIMIT 1");
$stmt->execute([$deviceId]);
$device = $stmt->fetch();

if (!$device) {
    die("Device not found.");
}

// Fetch Organization branding
$orgName = $device['organization'];
$orgStmt = $db->prepare("SELECT * FROM organization_details WHERE name = ? LIMIT 1");
$orgStmt->execute([$orgName]);
$org = $orgStmt->fetch();

$themeColor = $org ? $org['theme_color'] : '#4f46e5';
$orgAddress = $org ? $org['address'] : 'Colombo, Sri Lanka';
$orgTel = $org ? $org['tel'] : '+94 11 000 0000';
$orgEmail = $org ? $org['email'] : 'info@softnetlanka.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Device Delivery Note - <?php echo htmlspecialchars($device['asset_code']); ?></title>
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
      <button type="button" onclick="downloadPDF();" class="px-4 py-1.5 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm transition-all" style="background-color: <?php echo $themeColor; ?>; filter: brightness(0.95);">
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
        <!-- Brand Header Bar -->
        <div class="flex items-center justify-between pb-6 border-b-2" style="border-color: <?php echo $themeColor; ?>;">
          <div>
            <h1 class="text-xl font-bold uppercase tracking-wider text-slate-800"><?php echo htmlspecialchars($orgName); ?></h1>
            <p class="text-[10px] text-slate-500 mt-1 max-w-[320px] leading-relaxed">
              <?php echo htmlspecialchars($orgAddress); ?><br>
              Tel: <?php echo htmlspecialchars($orgTel); ?> | Email: <?php echo htmlspecialchars($orgEmail); ?>
            </p>
          </div>
          <div class="text-right">
            <span class="inline-block px-3 py-1 text-[10px] font-bold text-white rounded-lg uppercase tracking-wider mb-2" style="background-color: <?php echo $themeColor; ?>;">
              Device Registry
            </span>
            <div class="font-mono text-slate-600">DELIVERY NOTE: <span class="font-bold text-slate-850">#<?php echo htmlspecialchars($device['asset_code']); ?></span></div>
            <div class="text-[10px] text-slate-400 mt-0.5">Date: <?php echo htmlspecialchars($device['date_time']); ?></div>
          </div>
        </div>

        <div class="mt-8 text-center">
          <h2 class="text-md font-bold uppercase tracking-widest text-slate-800">Device Delivery &amp; Installation Note</h2>
          <div class="w-16 h-1 mx-auto mt-2 rounded" style="background-color: <?php echo $themeColor; ?>;"></div>
        </div>

        <!-- Section Grid -->
        <div class="grid grid-cols-2 gap-8 mt-8">
          <!-- Client Company Details -->
          <div class="bg-slate-50/60 p-4 border border-slate-100 rounded-xl">
            <h3 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-1.5">
              <i data-lucide="building-2" class="w-3.5 h-3.5" style="color: <?php echo $themeColor; ?>;"></i>
              <span>Customer Details</span>
            </h3>
            <table class="w-full text-left font-sans">
              <tr class="border-b border-slate-100"><th class="py-1.5 pr-2 font-semibold text-slate-450 uppercase text-[9px] w-1/3">Company</th><td class="py-1.5 font-bold text-slate-800"><?php echo htmlspecialchars($device['company_name']); ?></td></tr>
              <tr class="border-b border-slate-100"><th class="py-1.5 pr-2 font-semibold text-slate-450 uppercase text-[9px]">Branch</th><td class="py-1.5 text-slate-700"><?php echo htmlspecialchars($device['branch'] ?: '-'); ?></td></tr>
              <tr class="border-b border-slate-100"><th class="py-1.5 pr-2 font-semibold text-slate-450 uppercase text-[9px]">Address</th><td class="py-1.5 text-slate-600 leading-normal"><?php echo htmlspecialchars($device['address_1'] . ' ' . $device['address_2']); ?></td></tr>
              <tr class="border-b border-slate-100"><th class="py-1.5 pr-2 font-semibold text-slate-450 uppercase text-[9px]">Country</th><td class="py-1.5 text-slate-600"><?php echo htmlspecialchars($device['country']); ?></td></tr>
              <tr class="border-b border-slate-100"><th class="py-1.5 pr-2 font-semibold text-slate-450 uppercase text-[9px]">Contact Person</th><td class="py-1.5 text-slate-700 font-medium"><?php echo htmlspecialchars($device['contact_person'] ?: '-'); ?></td></tr>
              <tr class="border-b border-slate-100"><th class="py-1.5 pr-2 font-semibold text-slate-450 uppercase text-[9px]">Mobile / Tel</th><td class="py-1.5 text-slate-700 font-mono"><?php echo htmlspecialchars($device['mobile_no'] ?: '-'); ?> / <?php echo htmlspecialchars($device['tel_no'] ?: '-'); ?></td></tr>
              <tr><th class="py-1.5 pr-2 font-semibold text-slate-450 uppercase text-[9px]">Email</th><td class="py-1.5 text-slate-700 font-mono"><?php echo htmlspecialchars($device['email'] ?: '-'); ?></td></tr>
            </table>
          </div>

          <!-- Agreement Terms -->
          <div class="bg-slate-50/60 p-4 border border-slate-100 rounded-xl">
            <h3 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-1.5">
              <i data-lucide="file-check-2" class="w-3.5 h-3.5" style="color: <?php echo $themeColor; ?>;"></i>
              <span>Agreement Details</span>
            </h3>
            <table class="w-full text-left font-sans">
              <tr class="border-b border-slate-100"><th class="py-1.5 pr-2 font-semibold text-slate-450 uppercase text-[9px] w-1/3">Agreement Type</th><td class="py-1.5 font-bold text-slate-800"><span class="px-2 py-0.5 rounded text-[10px] font-bold" style="background-color: <?php echo $themeColor; ?>15; color: <?php echo $themeColor; ?>;"><?php echo htmlspecialchars($device['agreement_type']); ?></span></td></tr>
              <tr class="border-b border-slate-100"><th class="py-1.5 pr-2 font-semibold text-slate-450 uppercase text-[9px]">Start Date</th><td class="py-1.5 text-slate-700 font-medium"><?php echo htmlspecialchars($device['agreement_start_date']); ?></td></tr>
              <tr class="border-b border-slate-100"><th class="py-1.5 pr-2 font-semibold text-slate-450 uppercase text-[9px]">Valid Duration</th><td class="py-1.5 text-slate-700 font-medium"><?php echo htmlspecialchars($device['valid_duration']); ?></td></tr>
              <tr class="border-b border-slate-100"><th class="py-1.5 pr-2 font-semibold text-slate-450 uppercase text-[9px]">Agreement End</th><td class="py-1.5 font-bold text-slate-800"><?php echo htmlspecialchars($device['agreement_end_date']); ?></td></tr>
              <tr class="border-b border-slate-100"><th class="py-1.5 pr-2 font-semibold text-slate-450 uppercase text-[9px]">Request Type</th><td class="py-1.5 text-slate-600 font-medium"><?php echo htmlspecialchars($device['request_type']); ?></td></tr>
              <tr class="border-b border-slate-100"><th class="py-1.5 pr-2 font-semibold text-slate-450 uppercase text-[9px]">Salesman</th><td class="py-1.5 text-slate-700 font-medium"><?php echo htmlspecialchars($device['salesman_name']); ?></td></tr>
              <tr><th class="py-1.5 pr-2 font-semibold text-slate-450 uppercase text-[9px]">Verified Status</th><td class="py-1.5"><span class="font-bold uppercase tracking-wider text-[10px]"><?php echo htmlspecialchars($device['status']); ?></span></td></tr>
            </table>
          </div>
        </div>

        <!-- Specifications Table -->
        <div class="mt-8 overflow-hidden border border-slate-200 rounded-xl">
          <table class="w-full text-left border-collapse font-sans text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[9px] font-bold">
                <th class="p-3 w-1/4">Device Type</th>
                <th class="p-3">Model</th>
                <th class="p-3">Serial Number</th>
                <th class="p-3">Toner Model</th>
                <th class="p-3 text-right">Start Counter</th>
                <th class="p-3 text-right">Warranty</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr class="hover:bg-slate-50/20">
                <td class="p-3 font-semibold text-slate-800"><?php echo htmlspecialchars($device['device_type']); ?></td>
                <td class="p-3 text-slate-700"><?php echo htmlspecialchars($device['model']); ?></td>
                <td class="p-3 font-mono text-slate-800"><?php echo htmlspecialchars($device['serial_no']); ?></td>
                <td class="p-3 text-slate-600 font-medium"><?php echo htmlspecialchars($device['toner_model'] ?: '-'); ?></td>
                <td class="p-3 text-right font-mono font-semibold text-slate-800"><?php echo number_format($device['start_usage_counter']); ?></td>
                <td class="p-3 text-right font-bold text-slate-700"><?php echo htmlspecialchars($device['warranty']); ?></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-8 p-4 bg-slate-50/40 border border-slate-100 rounded-xl text-slate-650 leading-relaxed text-[11px]">
          <strong>Delivery &amp; Installation Policy Terms:</strong> The device listed above has been delivered, checked, and found in good working order by our engineers. All technical configurations have been synchronized. The customer accepts agreement terms, countersigned below.
        </div>
      </div>

      <!-- Signatures Footer -->
      <div class="pt-10">
        <!-- Approved By Metadata -->
        <?php if (!empty($device['approved_by'])): ?>
          <div class="text-[10px] text-slate-400 mb-6 italic text-left">
            Approved &amp; verified by supervisor: <span class="font-bold text-slate-600"><?php echo htmlspecialchars($device['approved_by']); ?></span>
          </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 gap-16 pt-10 border-t border-slate-100">
          <div class="text-left">
            <div class="border-b border-slate-300 pb-8 relative flex flex-col justify-end min-h-[50px]">
              <span class="font-bold text-slate-750 block"><?php echo htmlspecialchars($device['salesman_name']); ?></span>
            </div>
            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider block mt-1.5">Authorized Representative</span>
          </div>
          <div class="text-left">
            <div class="border-b border-slate-300 pb-8 relative flex flex-col justify-end min-h-[50px]">
              <span class="font-bold text-slate-700 block">&nbsp;</span>
            </div>
            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider block mt-1.5">Customer Acknowledgment Signature</span>
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
        pdfDoc.save('Delivery_Note_<?php echo htmlspecialchars($device['asset_code']); ?>.pdf');
      } catch (err) {
        console.error('PDF error:', err);
        alert('An error occurred during PDF generation.');
      } finally {
        document.body.classList.remove('generating-pdf');
        wrapper.style.zoom = originalZoom;
      }
    }
  </script>
</body>
</html>
