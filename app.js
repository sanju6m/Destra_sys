// app.js (Root Orchestrator)

// Global theme helper
const htmlElement = document.documentElement;

const applyTheme = (theme) => {
  if (theme === 'dark') {
    htmlElement.classList.add('dark');
    localStorage.setItem('theme', 'dark');
  } else {
    htmlElement.classList.remove('dark');
    localStorage.setItem('theme', 'light');
  }
  if (window.renderCharts) {
    window.renderCharts();
  }
};

window.applyTheme = applyTheme;

// Set theme from local storage on load
const userSelectedTheme = localStorage.getItem('theme');
if (userSelectedTheme) {
  applyTheme(userSelectedTheme);
} else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
  applyTheme('dark');
}

// Global Activity Logging Helper
window.logUserActivity = (action, details) => {
  if (!window.userSession || !window.userSession.isLoggedIn) return;
  fetch('api/log_activity.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ action, details })
  }).catch(err => console.error("Failed to log activity:", err));
};

if (!window.userSession || !window.userSession.isLoggedIn) {
  // ================= NOT AUTHENTICATED ORCHESTRATION =================
  import('./components/auth.component.js?v=' + Date.now()).then(module => {
    const authPl = document.getElementById('auth-placeholder');
    if (authPl) {
      authPl.innerHTML = module.authTemplate;
      module.default(); // initAuth
      
      // Load Lucide Icons for Auth
      if (window.lucide) {
        window.lucide.createIcons();
      }
    }
  });

} else {
  // ================= AUTHENTICATED ORCHESTRATION =================
  const cb = '?v=' + Date.now();
  Promise.all([
    import('./components/sidebar.component.js' + cb),
    import('./components/header.component.js' + cb),
    import('./components/dashboard-simplified.component.js' + cb),
    import('./components/dashboard-detailed.component.js' + cb),
    import('./components/task-creation.component.js' + cb),
    import('./components/drawers.component.js' + cb),
    import('./components/terminal.component.js' + cb),
    import('./components/ticket-actions.component.js' + cb),
    import('./components/charts.component.js' + cb),
    import('./components/customer-register.component.js' + cb),
    import('./components/replacement.component.js' + cb),
    import('./components/device.component.js' + cb)
  ]).then(([
    sidebarModule,
    headerModule,
    simpleViewModule,
    detailedViewModule,
    tasksViewModule,
    drawersModule,
    terminalModule,
    ticketActionsModule,
    chartsModule,
    customerRegisterModule,
    replacementModule,
    deviceModule
  ]) => {
    
    // Synchronously inject templates into placeholders
    const sidebarPl = document.getElementById('sidebar-placeholder');
    const headerPl = document.getElementById('header-placeholder');
    const viewsPl = document.getElementById('views-placeholder');
    const pageHeaderPl = document.getElementById('page-header-placeholder');
    const drawersPl = document.getElementById('drawers-placeholder');
    const terminalPl = document.getElementById('terminal-placeholder');
    const modalsPl = document.getElementById('modals-placeholder');

    // Dashboard page header HTML (only shown on dashboard views)
    const dashboardPageHeaderHTML = `
      <div id="dashboardPageHeader" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 mb-4 sm:mb-6">
        <div class="min-w-0">
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-800 dark:text-white flex items-center gap-2 flex-wrap">
            <span>Enterprise Workspace</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20">
              AI Powered
            </span>
          </h1>
          <p id="viewSubtitle" class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 truncate">Calm workspace environment for developer focus.</p>
        </div>
        <div class="inline-flex self-start sm:self-auto flex-shrink-0 p-1 bg-slate-200/60 dark:bg-slate-900/60 backdrop-blur-md border border-slate-200/20 dark:border-slate-800/20 rounded-2xl">
          <button type="button" id="simplifiedViewToggle" class="px-3 sm:px-4 py-2 rounded-xl text-xs font-bold flex items-center space-x-1 sm:space-x-2 transition-all bg-white dark:bg-slate-800 text-slate-800 dark:text-white shadow-sm">
            <i data-lucide="layout" class="w-4 h-4 flex-shrink-0"></i>
            <span class="hidden sm:inline">Simplified View</span>
          </button>
          <button type="button" id="dashboardViewToggle" class="px-3 sm:px-4 py-2 rounded-xl text-xs font-medium flex items-center space-x-1 sm:space-x-2 transition-all text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300">
            <i data-lucide="bar-chart-2" class="w-4 h-4 flex-shrink-0"></i>
            <span class="hidden sm:inline">Dashboard View</span>
          </button>
        </div>
      </div>
    `;

    const accessDeniedHTML = `
      <div id="accessDeniedContent" class="space-y-6 max-w-md mx-auto hidden py-16 text-center select-none">
        <div class="w-16 h-16 rounded-2xl bg-red-500/10 text-red-500 flex items-center justify-center mx-auto mb-4 border border-red-500/20">
          <i data-lucide="shield-alert" class="w-8 h-8"></i>
        </div>
        <h2 class="text-xl font-extrabold text-slate-800 dark:text-white">Access Denied</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">You do not have the required permissions to access this module or view. If you believe this is an error, please contact your administrator.</p>
        <button type="button" id="btnAccessDeniedBack" class="mt-6 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold shadow-sm transition-all inline-flex items-center gap-2">
          <i data-lucide="arrow-left" class="w-4 h-4"></i>
          <span>Go to Dashboard</span>
        </button>
      </div>
    `;

    if (sidebarPl) sidebarPl.outerHTML = sidebarModule.sidebarTemplate;
    if (headerPl) headerPl.outerHTML = headerModule.headerTemplate;
    // Inject dashboard page header by default (simplified view is default)
    if (pageHeaderPl) pageHeaderPl.innerHTML = dashboardPageHeaderHTML;
    if (viewsPl) viewsPl.outerHTML = simpleViewModule.simplifiedViewTemplate + detailedViewModule.dashboardViewTemplate + tasksViewModule.tasksViewTemplate + tasksViewModule.newTasksViewTemplate + customerRegisterModule.customerRegisterTemplate + replacementModule.newReplacementTemplate + replacementModule.pendingReplacementTemplate + replacementModule.allReplacementTemplate + replacementModule.waitingReplacementTemplate + deviceModule.newDeviceTemplate + deviceModule.pendingDeviceTemplate + deviceModule.allDeviceTemplate + accessDeniedHTML;
    if (drawersPl) drawersPl.outerHTML = drawersModule.drawersTemplate;
    if (terminalPl) terminalPl.outerHTML = terminalModule.terminalTemplate;
    if (modalsPl) modalsPl.outerHTML = ticketActionsModule.modalsTemplate;

    // Document bindings and logic init
    const initApp = () => {
      // Bind DB session details to page elements
      const session = window.userSession;
      
      const fullName = `${session.firstName} ${session.lastName}`;
      const helloUserEl = document.getElementById('helloUsername');
      const footerOrgEl = document.getElementById('footerOrgName');
      const profileEmailEl = document.getElementById('profileUserEmail');
      const drawerOrgNameEl = document.getElementById('drawerOrgName');
      const drawerOrgIdEl = document.getElementById('drawerOrgId');
      const userAvatarEl = document.getElementById('userAvatar');
      const drawerUserAvatarEl = document.getElementById('drawerUserAvatar');

      if (helloUserEl) helloUserEl.textContent = fullName;
      if (footerOrgEl) footerOrgEl.textContent = session.organizationName;
      if (profileEmailEl) profileEmailEl.textContent = session.email;
      if (drawerOrgNameEl) drawerOrgNameEl.textContent = session.organizationName;
      if (drawerOrgIdEl) drawerOrgIdEl.textContent = session.accountNo;
      
      if (userAvatarEl && session.profileImage) {
        userAvatarEl.src = session.profileImage;
        userAvatarEl.title = `${fullName} (${session.roleName})`;
      }
      if (drawerUserAvatarEl && session.profileImage) {
        drawerUserAvatarEl.src = session.profileImage;
        drawerUserAvatarEl.title = `${fullName} (You)`;
      }

      // Initialize modular components
      sidebarModule.default(); // initSidebar()
      drawersModule.default(); // initDrawers()
      terminalModule.default(); // initTerminal()
      chartsModule.default(); // initCharts()
      ticketActionsModule.default(); // initModals()
      tasksViewModule.default(); // initTasksView()
      if (customerRegisterModule && typeof customerRegisterModule.default === 'function') {
        customerRegisterModule.default(); // initCustomerRegister()
      }
      if (replacementModule && typeof replacementModule.default === 'function') {
        replacementModule.default(); // initReplacementView()
      }
      if (deviceModule && typeof deviceModule.default === 'function') {
        deviceModule.default(); // initDevicesModule()
      }

      // Log successful workspace loading
      window.logUserActivity('WORKSPACE_LOADED', `Loaded enterprise workspace dashboard with role: ${session.roleName}`);

      // Profile Dropdown Actions
      const profileBtn = document.getElementById('profileDropdownBtn');
      const profileDropdown = document.getElementById('profileDropdown');

      if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          profileDropdown.classList.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
          if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
            profileDropdown.classList.add('hidden');
          }
        });
      }

      // Sign Out Action redirect
      const signOutBtn = document.getElementById('signOutBtn');
      if (signOutBtn) {
        signOutBtn.addEventListener('click', () => {
          Swal.fire({
            title: 'Sign Out Session',
            text: 'Are you sure you want to exit your enterprise workstation?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Sign Out',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: htmlElement.classList.contains('dark') ? '#475569' : '#94a3b8',
            background: htmlElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
            color: htmlElement.classList.contains('dark') ? '#f8fafc' : '#0f172a'
          }).then((result) => {
            if (result.isConfirmed) {
              window.location.href = 'api/logout.php';
            }
          });
        });
      }

      // Access Denied Back button
      const btnAccessDeniedBack = document.getElementById('btnAccessDeniedBack');
      if (btnAccessDeniedBack) {
        btnAccessDeniedBack.addEventListener('click', () => {
          switchView('simplified');
        });
      }

      // Mobile Search Toggle
      const mobileSearchToggle = document.getElementById('mobileSearchToggle');
      const mobileSearchBar = document.getElementById('mobileSearchBar');
      if (mobileSearchToggle && mobileSearchBar) {
        mobileSearchToggle.addEventListener('click', () => {
          mobileSearchBar.classList.toggle('hidden');
          const inp = document.getElementById('globalSearchMobile');
          if (inp && !mobileSearchBar.classList.contains('hidden')) inp.focus();
        });
      }

      // View Switcher (Simplified View vs. Dashboard View vs. All Tasks View)
      // Note: toggles are inside dashboardPageHeader which is injected before initApp runs
      const getSimplifiedViewToggle = () => document.getElementById('simplifiedViewToggle');
      const getDashboardViewToggle = () => document.getElementById('dashboardViewToggle');
      const simpleViewContent = document.getElementById('simplifiedViewContent');
      const dashViewContent = document.getElementById('dashboardViewContent');
      const tasksViewContent = document.getElementById('tasksViewContent');

      // Bind initial click listeners for the view switcher buttons already in the DOM
      // (done after switchView is defined below)

      const setPageHeader = (viewName) => {
        const pageHeaderPl = document.getElementById('page-header-placeholder');
        if (!pageHeaderPl) return;
        if (viewName === 'simplified' || viewName === 'dashboard') {
          // Re-inject dashboard header if not already present
          if (!document.getElementById('dashboardPageHeader')) {
            pageHeaderPl.innerHTML = dashboardPageHeaderHTML;
            // Re-wire view switcher click handlers after injecting
            const st = document.getElementById('simplifiedViewToggle');
            const dt = document.getElementById('dashboardViewToggle');
            if (st) st.addEventListener('click', () => switchView('simplified'));
            if (dt) dt.addEventListener('click', () => switchView('dashboard'));
            if (window.lucide) window.lucide.createIcons();
          }
        } else {
          // For non-dashboard views, replace with a simple contextual title
          const titles = {
            tasks:     { title: 'Tasks Manager',  subtitle: 'Manage, filter, and track tasks assigned to this workspace.', badge: 'Manager' },
            new_tasks: { title: 'Create New Task', subtitle: 'Submit a new ticket or task to the workstation.', badge: 'Form' },
            pending_tasks: { title: 'Pending Tasks', subtitle: 'View and manage tasks awaiting action.', badge: 'Queue' },
            quotation_tasks: { title: 'Quotation Tasks', subtitle: 'Manage quotations and cost estimations.', badge: 'Billing' },
            new_service: { title: 'New Service Registry', subtitle: 'Register and schedule a new customer service request.', badge: 'Service' },
            upcoming_service: { title: 'Upcoming Services', subtitle: 'View scheduled service visits and maintenance queues.', badge: 'Service' },
            all_service: { title: 'All Services History', subtitle: 'Complete database of all registered service requests.', badge: 'Service' },
            new_replacement: { title: 'New Replacement Request', subtitle: 'Submit a new equipment or parts replacement request.', badge: 'Replacement' },
            pending_rev_replacement: { title: 'Pending Replacement Reviews', subtitle: 'Review and approve pending replacement requests.', badge: 'Replacement' },
            all_replacement: { title: 'Replacement Log', subtitle: 'Track all equipment replacements and historical records.', badge: 'Replacement' },
            new_device: { title: 'Register New Device', subtitle: 'Add a new enterprise hardware asset to the workstation.', badge: 'Asset' },
            pending_ad_device: { title: 'Pending Device Approvals', subtitle: 'Hardware assets awaiting deployment confirmation.', badge: 'Asset' },
            all_device: { title: 'Hardware Asset Inventory', subtitle: 'Complete tracking of deployed hardware assets.', badge: 'Asset' },
            new_customer: { title: 'Add New Customer Profile', subtitle: 'Register a new customer organization and contact details.', badge: 'CRM' },
            pending_inav_customer: { title: 'Pending Customer Verification', subtitle: 'Customer profiles awaiting system activation.', badge: 'CRM' },
            all_customer: { title: 'Customer Register Database', subtitle: 'Browse, search and manage customer organizations.', badge: 'CRM' },
            new_supplier: { title: 'Add New Vendor Profile', subtitle: 'Register a new vendor or supplier to the workstation.', badge: 'CRM' },
            pending_inav_supplier: { title: 'Pending Supplier Verification', subtitle: 'Vendor profiles awaiting system activation.', badge: 'CRM' },
            all_supplier: { title: 'Supplier Register Database', subtitle: 'Browse, search and manage supplier organizations.', badge: 'CRM' },
            analytics: { title: 'Analytics',      subtitle: 'Performance insights, trends and operational metrics.', badge: 'Insights' },
            knboard:   { title: 'KN Board',        subtitle: 'Visualise workflow stages with your Kanban board.', badge: 'Kanban' },
            reports:   { title: 'Reports',         subtitle: 'View and export system-generated reports and summaries.', badge: 'System' },
            documents: { title: 'Documents',       subtitle: 'Browse, upload and manage workspace documents.', badge: 'Files' },
            mapping:   { title: 'Mapping',         subtitle: 'Geographical mapping and location tracking.', badge: 'Geo' },
            settings:  { title: 'Settings',        subtitle: 'Configure workspace preferences and system settings.', badge: 'Admin' },
          };
          const info = titles[viewName] || { title: 'Workspace', subtitle: '', badge: '' };
          pageHeaderPl.innerHTML = `
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 mb-4 sm:mb-6">
              <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-800 dark:text-white flex items-center gap-2 flex-wrap">
                  <span>${info.title}</span>
                  ${info.badge ? `
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20">
                      ${info.badge}
                    </span>
                  ` : ''}
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 truncate">${info.subtitle}</p>
              </div>
            </div>
          `;
        }
      };

      const hasPermission = (module, permission) => {
        if (session.roleName === 'Global Administrator') return true;
        const perms = session.permissions;
        if (!perms) return false;
        return (perms[module]?.[permission] === true) || (perms[module]?.['manage'] === true);
      };

      const checkViewPermission = (view) => {
        const viewToModule = {
          simplified: 'ticket_management',
          dashboard: 'ticket_management',
          tasks: 'ticket_management',
          new_tasks: 'ticket_management',
          pending_tasks: 'ticket_management',
          quotation_tasks: 'ticket_management',
          new_service: 'ticket_management',
          upcoming_service: 'ticket_management',
          all_service: 'ticket_management',
          new_replacement: 'ticket_management',
          pending_rev_replacement: 'ticket_management',
          waiting_replacement: 'ticket_management',
          all_replacement: 'ticket_management',
          new_device: 'ticket_management',
          pending_ad_device: 'ticket_management',
          all_device: 'ticket_management',
          new_customer: 'client_management',
          pending_inav_customer: 'client_management',
          all_customer: 'client_management',
          new_supplier: 'client_management',
          pending_inav_supplier: 'client_management',
          all_supplier: 'client_management',
          hr_recruitment: 'hr_management',
          hr_payroll: 'hr_management',
          hr_employees: 'hr_management',
          proc_purchase_req: 'procurement',
          proc_suppliers: 'procurement',
          deliv_schedule: 'delivery',
          deliv_tracking: 'delivery',
          inv_stock: 'inventory',
          inv_movements: 'inventory',
          fin_invoices: 'finance',
          fin_budgets: 'finance',
          analytics: 'reporting',
          knboard: 'ticket_management',
          reports: 'reporting',
          documents: 'reporting',
          mapping: 'reporting',
          settings: 'settings_block'
        };

        const mod = viewToModule[view];
        if (!mod) return true;
        if (mod === 'settings_block') {
          const allowedAdminRoles = ['Global Administrator', 'System Administrator', 'Security Administrator'];
          return allowedAdminRoles.includes(session.roleName);
        }
        return hasPermission(mod, 'read');
      };

      const switchView = (viewName) => {
        if (viewName === 'waiting_replacement') {
          viewName = 'new_replacement';
        }
        // Route Guard check
        const isAllowed = checkViewPermission(viewName);
        const targetView = isAllowed ? viewName : 'access_denied';

        // 1. Update page header
        setPageHeader(targetView);

        // 2. Hide all content panes
        const accessDeniedContent = document.getElementById('accessDeniedContent');
        const newTasksViewContent = document.getElementById('newTasksViewContent');
        const customerRegisterViewContent = document.getElementById('customerRegisterViewContent');
        const newReplacementContent = document.getElementById('newReplacementContent');
        const pendingReplacementContent = document.getElementById('pendingReplacementContent');
        const allReplacementContent = document.getElementById('allReplacementContent');
        const waitingReplacementContent = document.getElementById('waitingReplacementContent');
        const newDeviceContent = document.getElementById('newDeviceContent');
        const pendingDeviceContent = document.getElementById('pendingDeviceContent');
        const allDeviceContent = document.getElementById('allDeviceContent');

        if (simpleViewContent) simpleViewContent.classList.add('hidden');
        if (dashViewContent)   dashViewContent.classList.add('hidden');
        if (tasksViewContent)  tasksViewContent.classList.add('hidden');
        if (newTasksViewContent) newTasksViewContent.classList.add('hidden');
        if (customerRegisterViewContent) customerRegisterViewContent.classList.add('hidden');
        if (accessDeniedContent) accessDeniedContent.classList.add('hidden');
        if (newReplacementContent) newReplacementContent.classList.add('hidden');
        if (pendingReplacementContent) pendingReplacementContent.classList.add('hidden');
        if (allReplacementContent) allReplacementContent.classList.add('hidden');
        if (waitingReplacementContent) waitingReplacementContent.classList.add('hidden');
        if (newDeviceContent) newDeviceContent.classList.add('hidden');
        if (pendingDeviceContent) pendingDeviceContent.classList.add('hidden');
        if (allDeviceContent) allDeviceContent.classList.add('hidden');

        // 3. Show the correct pane & update view-switcher styling
        const simplifiedViewToggle = getSimplifiedViewToggle();
        const dashboardViewToggle  = getDashboardViewToggle();
        const viewSubtitle         = document.getElementById('viewSubtitle');

        const activeToggle   = 'px-4 py-2 rounded-xl text-xs font-bold flex items-center space-x-2 transition-all bg-white dark:bg-slate-800 text-slate-800 dark:text-white shadow-sm';
        const inactiveToggle = 'px-4 py-2 rounded-xl text-xs font-medium flex items-center space-x-2 transition-all text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300';

        if (targetView === 'access_denied') {
          if (accessDeniedContent) accessDeniedContent.classList.remove('hidden');
          if (viewSubtitle) viewSubtitle.textContent = 'Resource restricted due to access control rules.';
          window.logUserActivity('UNAUTHORIZED_NAV_ATTEMPT', `Attempted unauthorized navigation to view: ${viewName}`);
          if (window.lucide) window.lucide.createIcons();

        } else if (targetView === 'simplified') {
          if (simpleViewContent) simpleViewContent.classList.remove('hidden');
          if (simplifiedViewToggle) simplifiedViewToggle.className = activeToggle;
          if (dashboardViewToggle)  dashboardViewToggle.className  = inactiveToggle;
          if (viewSubtitle) viewSubtitle.textContent = 'Calm workspace environment for developer focus.';
          window.logUserActivity('VIEW_CHANGED', 'Switched to simplified workspace.');

        } else if (targetView === 'dashboard') {
          if (dashViewContent) dashViewContent.classList.remove('hidden');
          if (simplifiedViewToggle) simplifiedViewToggle.className = inactiveToggle;
          if (dashboardViewToggle)  dashboardViewToggle.className  = activeToggle;
          if (viewSubtitle) viewSubtitle.textContent = 'Performance graphs, resources overhead, and system records metrics.';
          if (detailedViewModule && typeof detailedViewModule.renderDetailedDashboard === 'function') {
            detailedViewModule.renderDetailedDashboard('dynamicDashboardContent', session.roleName);
          }
          window.logUserActivity('VIEW_CHANGED', 'Switched to detailed metrics dashboard.');

        } else if (targetView === 'new_tasks') {
          if (newTasksViewContent) {
            newTasksViewContent.classList.remove('hidden');
          }
          if (typeof window.renderNewTasksPage === 'function') {
            window.renderNewTasksPage();
          }
          window.logUserActivity('VIEW_CHANGED', 'Switched to create new task page.');

        } else if (targetView === 'tasks' || targetView === 'quotation_tasks' || targetView === 'pending_tasks') {
          if (tasksViewContent) {
            tasksViewContent.classList.remove('hidden');
          }
          if (window.refreshTasksTable) {
            window.refreshTasksTable();
          } else if (tasksViewModule.drawTasksTable) {
            tasksViewModule.drawTasksTable();
          }
          window.logUserActivity('VIEW_CHANGED', `Switched to tasks database manager (${targetView}).`);

        } else if (targetView === 'all_customer') {
          if (customerRegisterViewContent) {
            customerRegisterViewContent.classList.remove('hidden');
          }
          if (window.reloadCustomerRegister) {
            window.reloadCustomerRegister();
          }
          window.logUserActivity('VIEW_CHANGED', 'Switched to customer register database.');
        } else if (targetView === 'new_replacement') {
          if (newReplacementContent) {
            newReplacementContent.classList.remove('hidden');
          }
          if (typeof window.renderNewReplacementPage === 'function') {
            window.renderNewReplacementPage();
          }
          window.logUserActivity('VIEW_CHANGED', 'Switched to new replacement request form.');
        } else if (targetView === 'pending_rev_replacement') {
          if (pendingReplacementContent) {
            pendingReplacementContent.classList.remove('hidden');
          }
          if (typeof window.renderPendingReplacementPage === 'function') {
            window.renderPendingReplacementPage();
          }
          window.logUserActivity('VIEW_CHANGED', 'Switched to pending replacement reviews.');
        } else if (targetView === 'all_replacement') {
          if (allReplacementContent) {
            allReplacementContent.classList.remove('hidden');
          }
          if (typeof window.renderAllReplacementPage === 'function') {
            window.renderAllReplacementPage();
          }
          window.logUserActivity('VIEW_CHANGED', 'Switched to replacement log.');
        } else if (targetView === 'waiting_replacement') {
          const waitingReplacementContent = document.getElementById('waitingReplacementContent');
          if (waitingReplacementContent) {
            waitingReplacementContent.classList.remove('hidden');
          }
          if (typeof window.renderWaitingReplacementPage === 'function') {
            window.renderWaitingReplacementPage();
          }
          window.logUserActivity('VIEW_CHANGED', 'Switched to waiting replacement requests.');
        } else if (targetView === 'new_device') {
          if (newDeviceContent) {
            newDeviceContent.classList.remove('hidden');
          }
          window.logUserActivity('VIEW_CHANGED', 'Switched to register new device form.');
        } else if (targetView === 'pending_ad_device') {
          if (pendingDeviceContent) {
            pendingDeviceContent.classList.remove('hidden');
          }
          if (window.reloadDevicesModule) {
            window.reloadDevicesModule();
          }
          window.logUserActivity('VIEW_CHANGED', 'Switched to pending device reviews.');
        } else if (targetView === 'all_device') {
          if (allDeviceContent) {
            allDeviceContent.classList.remove('hidden');
          }
          if (window.reloadDevicesModule) {
            window.reloadDevicesModule();
          }
          window.logUserActivity('VIEW_CHANGED', 'Switched to all registered devices log.');
        } else {
          // analytics, knboard, reports, documents, mapping, settings — no content pane yet
          window.logUserActivity('VIEW_CHANGED', `Navigated to: ${targetView}`);
        }

        // Save active view state for persistence on refresh
        if (isAllowed) {
          localStorage.setItem('activeView', viewName);
        }

        // 4. Update sidebar active states
        const navBtns = [
          document.getElementById('sidebarDashboardBtn'),
          document.getElementById('sidebarAllTasksBtn'),
          document.getElementById('sidebarNewTasksBtn'),
          document.getElementById('sidebarPendingTasksBtn'),
          document.getElementById('sidebarQuotationTasksBtn'),
          document.getElementById('sidebarNewServiceBtn'),
          document.getElementById('sidebarUpcomingServiceBtn'),
          document.getElementById('sidebarAllServiceBtn'),
          document.getElementById('sidebarNewReplacementBtn'),
          document.getElementById('sidebarPendingRevReplacementBtn'),
          document.getElementById('sidebarWaitingReplacementBtn'),
          document.getElementById('sidebarAllReplacementBtn'),
          document.getElementById('sidebarNewDeviceBtn'),
          document.getElementById('sidebarPendingADDeviceBtn'),
          document.getElementById('sidebarAllDeviceBtn'),
          document.getElementById('sidebarNewCustomerBtn'),
          document.getElementById('sidebarPendingINAVCustomerBtn'),
          document.getElementById('sidebarAllCustomerBtn'),
          document.getElementById('sidebarNewSupplierBtn'),
          document.getElementById('sidebarPendingINAVSupplierBtn'),
          document.getElementById('sidebarAllSupplierBtn'),
          document.getElementById('sidebarAnalyticsBtn'),
          document.getElementById('sidebarKnBtn'),
          document.getElementById('sidebarReportsBtn'),
          document.getElementById('sidebarDocumentsBtn'),
          document.getElementById('sidebarMappingBtn'),
          document.getElementById('sidebarSettingsBtn'),
        ];

        navBtns.forEach(btn => {
          if (!btn) return;
          btn.classList.remove('active', 'text-brand-600', 'dark:text-brand-400', 'bg-brand-50/50', 'dark:bg-brand-500/10', 'font-semibold');
          
          if (btn.id.startsWith('sidebarNew') || btn.id.startsWith('sidebarPending') || btn.id.startsWith('sidebarWaiting') || btn.id.startsWith('sidebarAllTasksBtn') || btn.id.startsWith('sidebarAllServiceBtn') || btn.id.startsWith('sidebarAllReplacementBtn') || btn.id.startsWith('sidebarAllDeviceBtn') || btn.id.startsWith('sidebarAllCustomerBtn') || btn.id.startsWith('sidebarAllSupplierBtn') || btn.id.startsWith('sidebarQuotationTasksBtn')) {
            // Nested accordion item
            if (btn.id.includes('Customer') || btn.id.includes('Supplier')) {
              btn.classList.remove('text-brand-500');
              btn.classList.add('text-slate-400');
            } else {
              btn.classList.remove('text-brand-600', 'dark:text-brand-400');
              btn.classList.add('text-slate-500', 'dark:text-slate-500');
            }
          } else {
            // Main item
            btn.classList.add('text-slate-600', 'dark:text-slate-400');
          }
          const sp = btn.querySelector('span.sidebar-text');
          if (sp) { sp.classList.remove('font-semibold'); sp.classList.add('font-medium'); }
        });

        const activeMap = {
          simplified: 'sidebarDashboardBtn',
          dashboard:  'sidebarDashboardBtn',
          tasks:      'sidebarAllTasksBtn',
          new_tasks:  'sidebarNewTasksBtn',
          pending_tasks: 'sidebarPendingTasksBtn',
          quotation_tasks: 'sidebarQuotationTasksBtn',
          new_service: 'sidebarNewServiceBtn',
          upcoming_service: 'sidebarUpcomingServiceBtn',
          all_service: 'sidebarAllServiceBtn',
          new_replacement: 'sidebarNewReplacementBtn',
          pending_rev_replacement: 'sidebarPendingRevReplacementBtn',
          waiting_replacement: 'sidebarWaitingReplacementBtn',
          all_replacement: 'sidebarAllReplacementBtn',
          new_device: 'sidebarNewDeviceBtn',
          pending_ad_device: 'sidebarPendingADDeviceBtn',
          all_device: 'sidebarAllDeviceBtn',
          new_customer: 'sidebarNewCustomerBtn',
          pending_inav_customer: 'sidebarPendingINAVCustomerBtn',
          all_customer: 'sidebarAllCustomerBtn',
          new_supplier: 'sidebarNewSupplierBtn',
          pending_inav_supplier: 'sidebarPendingINAVSupplierBtn',
          all_supplier: 'sidebarAllSupplierBtn',
          analytics:  'sidebarAnalyticsBtn',
          knboard:    'sidebarKnBtn',
          reports:    'sidebarReportsBtn',
          documents:  'sidebarDocumentsBtn',
          mapping:    'sidebarMappingBtn',
          settings:   'sidebarSettingsBtn',
        };

        const activeId  = activeMap[viewName];
        const activeBtn = activeId ? document.getElementById(activeId) : null;
        if (activeBtn) {
          activeBtn.classList.add('active', 'text-brand-600', 'dark:text-brand-400', 'bg-brand-50/50', 'dark:bg-brand-500/10', 'font-semibold');
          activeBtn.classList.remove('text-slate-650', 'dark:text-slate-450', 'text-slate-600', 'dark:text-slate-400', 'text-slate-500', 'dark:text-slate-500', 'text-slate-400');
          if (activeBtn.id.includes('Customer') || activeBtn.id.includes('Supplier')) {
            activeBtn.classList.remove('text-slate-400');
            activeBtn.classList.add('text-brand-500');
          }
          const sp = activeBtn.querySelector('span.sidebar-text');
          if (sp) { sp.classList.add('font-semibold'); sp.classList.remove('font-medium'); }
        }

        // Expand the accordion menu containing the active button, if any
        document.querySelectorAll('#sidebar .accordion-menu').forEach(menu => {
          if (activeBtn && menu.contains(activeBtn)) {
            menu.classList.remove('hidden');
            const toggleBtn = menu.parentElement.querySelector('.accordion-toggle');
            if (toggleBtn) {
              const chevron = toggleBtn.querySelector('.chevron');
              if (chevron) chevron.style.transform = 'rotate(180deg)';
            }
          } else {
            let isParentOfActive = false;
            if (activeBtn) {
              const nestedMenu = activeBtn.closest('.nested-menu');
              if (nestedMenu && menu.contains(nestedMenu)) {
                isParentOfActive = true;
              }
            }
            if (!isParentOfActive) {
              menu.classList.add('hidden');
              const toggleBtn = menu.parentElement.querySelector('.accordion-toggle');
              if (toggleBtn) {
                const chevron = toggleBtn.querySelector('.chevron');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
              }
            }
          }
        });

        // Expand nested CRM Customer/Supplier menus if active
        document.querySelectorAll('#sidebar .nested-menu').forEach(menu => {
          if (activeBtn && menu.contains(activeBtn)) {
            menu.classList.remove('hidden');
            const toggleBtn = menu.parentElement.querySelector('.nested-toggle');
            if (toggleBtn) {
              const chevron = toggleBtn.querySelector('.nested-chevron');
              if (chevron) chevron.style.transform = 'rotate(180deg)';
            }
          } else {
            menu.classList.add('hidden');
            const toggleBtn = menu.parentElement.querySelector('.nested-toggle');
            if (toggleBtn) {
              const chevron = toggleBtn.querySelector('.nested-chevron');
              if (chevron) chevron.style.transform = 'rotate(0deg)';
            }
          }
        });
      }; // end switchView
      window.switchView = switchView;

      // Bind view-switcher buttons (already in DOM from initial page header injection)
      const _stBtn = getSimplifiedViewToggle();
      const _dtBtn = getDashboardViewToggle();
      if (_stBtn) _stBtn.addEventListener('click', () => switchView('simplified'));
      if (_dtBtn) _dtBtn.addEventListener('click', () => switchView('dashboard'));

      // Sidebar link listeners — all delegate to switchView which handles
      // header refresh, content pane visibility, and active state highlighting.
      const sidebarNavMap = [
        { id: 'sidebarDashboardBtn', view: 'simplified' },
        { id: 'sidebarAllTasksBtn',  view: 'tasks'      },
        { id: 'sidebarNewTasksBtn',  view: 'new_tasks'  },
        { id: 'sidebarPendingTasksBtn', view: 'pending_tasks' },
        { id: 'sidebarQuotationTasksBtn', view: 'quotation_tasks' },
        { id: 'sidebarNewServiceBtn', view: 'new_service' },
        { id: 'sidebarUpcomingServiceBtn', view: 'upcoming_service' },
        { id: 'sidebarAllServiceBtn', view: 'all_service' },
        { id: 'sidebarNewReplacementBtn', view: 'new_replacement' },
        { id: 'sidebarPendingRevReplacementBtn', view: 'pending_rev_replacement' },
        { id: 'sidebarWaitingReplacementBtn', view: 'waiting_replacement' },
        { id: 'sidebarAllReplacementBtn', view: 'all_replacement' },
        { id: 'sidebarNewDeviceBtn', view: 'new_device' },
        { id: 'sidebarPendingADDeviceBtn', view: 'pending_ad_device' },
        { id: 'sidebarAllDeviceBtn', view: 'all_device' },
        { id: 'sidebarNewCustomerBtn', view: 'new_customer' },
        { id: 'sidebarPendingINAVCustomerBtn', view: 'pending_inav_customer' },
        { id: 'sidebarAllCustomerBtn', view: 'all_customer' },
        { id: 'sidebarNewSupplierBtn', view: 'new_supplier' },
        { id: 'sidebarPendingINAVSupplierBtn', view: 'pending_inav_supplier' },
        { id: 'sidebarAllSupplierBtn', view: 'all_supplier' },
        { id: 'sidebarAnalyticsBtn', view: 'analytics'  },
        { id: 'sidebarKnBtn',        view: 'knboard'    },
        { id: 'sidebarReportsBtn',   view: 'reports'    },
        { id: 'sidebarDocumentsBtn', view: 'documents'  },
        { id: 'sidebarMappingBtn',   view: 'mapping'    },
        { id: 'sidebarSettingsBtn',  view: 'settings'   },
        { id: 'viewAllTasksLink',    view: 'tasks'      },
      ];
      sidebarNavMap.forEach(({ id, view }) => {
        const el = document.getElementById(id);
        if (el) {
          el.addEventListener('click', (e) => {
            e.preventDefault();
            switchView(view);
          });
        }
      });

      // Keyboard Shortcuts (Ctrl+K focus search)
      const globalSearch = document.getElementById('globalSearch');
      document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
          e.preventDefault();
          if (globalSearch) globalSearch.focus();
        }
      });

      // AI Prompt Submission Listener
      const aiShortcutSubmit = document.getElementById('aiShortcutSubmit');
      const aiShortcutInput = document.getElementById('aiShortcutInput');
      if (aiShortcutSubmit) {
        aiShortcutSubmit.addEventListener('click', () => {
          if (!aiShortcutInput) return;
          const q = aiShortcutInput.value.trim();
          if (!q) return;

          window.logUserActivity('AI_PROMPT_SUBMIT', `Submitted AI Copilot query: "${q}"`);

          Swal.fire({
            title: 'Processing AI Query',
            text: `Executing instruction: "${q}"`,
            timer: 2000,
            timerProgressBar: true,
            background: htmlElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
            color: htmlElement.classList.contains('dark') ? '#f8fafc' : '#0f172a',
            didOpen: () => {
              Swal.showLoading();
            }
          }).then(() => {
            Swal.fire({
              title: 'Response Complete',
              text: `The AI assistant analyzed "${q}" and found system metrics normal. Complete logs available in the Terminal Console or reports sheet.`,
              icon: 'success',
              confirmButtonColor: '#7c3aed',
              background: htmlElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
              color: htmlElement.classList.contains('dark') ? '#f8fafc' : '#0f172a'
            });
            aiShortcutInput.value = '';
          });
        });
      }

      // Simplified Dashboard Quick Actions Delegation
      document.addEventListener('click', (e) => {
        const actionBtn = e.target.closest('.action-btn');
        if (actionBtn) {
          const title = actionBtn.getAttribute('title');
          if (title === 'Create New Task') {
            switchView('new_tasks');
          } else if (title === 'Create New Service') {
            switchView('new_service');
          } else if (title === 'Register Device') {
            switchView('new_device');
          } else if (title === 'Add Customer') {
            switchView('new_customer');
          }
        }
      });

      // Live clock and date updater
      const updateTimeWidget = () => {
        const timeEl = document.getElementById('currentTime');
        const dateEl = document.getElementById('currentDate');
        if (!timeEl || !dateEl) return;
        
        const now = new Date();
        timeEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        dateEl.textContent = now.toLocaleDateString([], { month: 'short', day: '2-digit', year: 'numeric' });
      };
      setInterval(updateTimeWidget, 1000);
      updateTimeWidget();

      // Restore previously active view on reload
      const savedView = localStorage.getItem('activeView') || 'simplified';
      switchView(savedView);

      // Initialize Lucide Icons for dashboard layout
      if (window.lucide) {
        window.lucide.createIcons();
      }
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initApp);
    } else {
      initApp();
    }

  });
}
