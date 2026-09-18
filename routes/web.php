<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AdminAuditController;
use App\Http\Controllers\ModuleCatalogController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\SiteEditorController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TenantDepartmentController;
use App\Http\Controllers\TenantUserController;

// ==========================================
// ESPACE ÉDITEUR DE CONTENU
// Adresse et page de connexion distinctes, sans marquage ERP : l'éditeur ne
// gère que le site de son établissement et n'a pas à connaître l'existence de
// la console d'administration. La séparation d'URL limite la découverte ; la
// protection réelle tient au refus de connexion des autres rôles et au
// cloisonnement par tenant_id.
// ==========================================
Route::prefix('espace-editeur')->name('site-editor.')->group(function () {
    Route::get('/connexion', [SiteEditorController::class, 'showLogin'])->name('login');
    Route::post('/connexion', [SiteEditorController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.store');

    Route::middleware(['auth', 'role:site_editor'])->group(function () {
        Route::post('/deconnexion', [SiteEditorController::class, 'logout'])->name('logout');
        Route::get('/', [SiteEditorController::class, 'content'])->name('content');
        Route::post('/', [SiteEditorController::class, 'update'])->name('content.update');
    });
});

// Page d'accueil -> redirige vers login ou le tableau de bord
Route::get('/', function () {
    return Auth::check()
        ? redirect()->to('/tech/dashboard') // Par défaut vers le dashboard tech si connecté
        : redirect()->to('/login');
});

// === AUTHENTICATION ROUTES ===
use App\Http\Controllers\Auth\AuthenticatedSessionController;

Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// === API PUBLIQUE (consommée par wetchah_site — pas d'authentification) ===
Route::get('/api/public/establishments/{tenant:slug}/content', [AdminAuditController::class, 'publicSiteContent'])->name('api.public.establishments.content');

// === ESPACE TECH (Supervision Technique) ===
Route::middleware(['auth', 'role:tech_admin'])->prefix('tech')->name('tech.')->group(function () {
    // Dashboard principal TECH (Santé des conteneurs, statistiques globales)
    Route::get('/dashboard', [AdminAuditController::class, 'index'])->name('dashboard');
    Route::get('/supervision/stats', [AdminAuditController::class, 'supervisionStats'])->name('supervision.stats');
    Route::get('/roles/distribution', [AdminAuditController::class, 'rolesDistribution'])->name('roles.distribution');
    Route::get('/support/interventions', [AdminAuditController::class, 'supportInterventions'])->name('support.interventions');
    Route::get('/support/app-logs', [AdminAuditController::class, 'supportAppLogs'])->name('support.app-logs');
    Route::get('/support/assistance', [AdminAuditController::class, 'assistanceList'])->name('support.assistance.list');
    Route::post('/support/assistance', [AdminAuditController::class, 'assistanceOpen'])->name('support.assistance.open');
    Route::post('/support/assistance/{session}/revoke', [AdminAuditController::class, 'assistanceRevoke'])->name('support.assistance.revoke');
    Route::get('/support/{tenant}/diagnostic', [AdminAuditController::class, 'supportDiagnostic'])->name('support.diagnostic');

    // Tickets remontés par le personnel des établissements depuis wetchah_app :
    // lecture agrégée pour le kanban, création manuelle et traitement écrit dans la base du tenant.
    Route::get('/support/tickets', [SupportTicketController::class, 'index'])->name('support.tickets');
    Route::post('/support/tickets', [SupportTicketController::class, 'update'])->name('support.tickets.update');
    Route::post('/support/tickets/create', [SupportTicketController::class, 'store'])->name('support.tickets.create');
    // Entrer en assistance se demande ticket par ticket : jamais un effet de bord de la lecture ci-dessus.
    Route::post('/support/tickets/assist', [SupportTicketController::class, 'assist'])->name('support.tickets.assist');

    // Répertoire des modules de l'application établissement : fiche et guide
    // d'utilisation de chaque module, ouverts depuis l'onglet « Modules ».
    Route::get('/modules/{module}', [ModuleCatalogController::class, 'show'])->name('modules.show');

    // Registre des propriétaires — une entrée par personne, d'où l'on ouvre
    // directement la fiche de l'un de ses établissements.
    Route::get('/owners', [OwnerController::class, 'index'])->name('owners.index');
    Route::get('/owners/{owner}', [OwnerController::class, 'show'])->name('owners.show');
    Route::post('/owners/{owner}', [OwnerController::class, 'update'])->name('owners.update');
    Route::post('/owners/{owner}/toggle-active', [OwnerController::class, 'toggleActive'])->name('owners.toggle-active');
    Route::delete('/owners/{owner}', [OwnerController::class, 'destroy'])->name('owners.destroy');

    // Gestion des Établissements (Tenants)
    Route::get('/establishments', [AdminAuditController::class, 'indexTenants'])->name('establishments.index');
    Route::get('/establishments/create', [AdminAuditController::class, 'createTenant'])->name('establishments.create');
    Route::post('/establishments', [AdminAuditController::class, 'storeTenant'])->name('establishments.store');
    Route::get('/establishments/{tenant}', [AdminAuditController::class, 'showTenant'])->name('establishments.show');
    Route::post('/establishments/{tenant}', [AdminAuditController::class, 'updateTenant'])->name('establishments.update');
    Route::delete('/establishments/{tenant}', [AdminAuditController::class, 'destroyTenant'])->name('establishments.destroy');
    Route::post('/establishments/{tenant}/create-manager', [AdminAuditController::class, 'createTenantManager'])->name('establishments.create-manager');
    Route::post('/establishments/{tenant}/create-controller', [AdminAuditController::class, 'createTenantController'])->name('establishments.create-controller');

    // Employés de l'établissement : ils vivent dans la base du tenant, donc
    // toute modification faite ici est immédiatement effective dans wetchah_app.
    Route::get('/establishments/{tenant}/users/{user}', [TenantUserController::class, 'show'])->whereNumber('user')->name('establishments.users.show');
    Route::post('/establishments/{tenant}/users', [TenantUserController::class, 'store'])->name('establishments.users.store');
    Route::post('/establishments/{tenant}/users/{user}', [TenantUserController::class, 'update'])->name('establishments.users.update');
    Route::post('/establishments/{tenant}/users/{user}/toggle-active', [TenantUserController::class, 'toggleActive'])->name('establishments.users.toggle-active');
    Route::delete('/establishments/{tenant}/users/{user}', [TenantUserController::class, 'destroy'])->name('establishments.users.destroy');

    // Départements de l'établissement
    Route::post('/establishments/{tenant}/departments', [TenantDepartmentController::class, 'store'])->name('establishments.departments.store');
    Route::put('/establishments/{tenant}/departments/{department}', [TenantDepartmentController::class, 'update'])->name('establishments.departments.update');
    Route::post('/establishments/{tenant}/departments/{department}', [TenantDepartmentController::class, 'update'])->name('establishments.departments.update.post');
    Route::delete('/establishments/{tenant}/departments/{department}', [TenantDepartmentController::class, 'destroy'])->name('establishments.departments.destroy');

    // Comptes éditeurs du site de l'établissement. Ils vivent dans la base de
    // l'ERP (contrairement aux employés), mais restent bornés à ce site.
    Route::post('/establishments/{tenant}/editors', [SiteEditorController::class, 'storeEditor'])->name('establishments.editors.store');
    Route::post('/establishments/{tenant}/editors/{editor}/toggle', [SiteEditorController::class, 'toggleEditor'])->name('establishments.editors.toggle');
    Route::delete('/establishments/{tenant}/editors/{editor}', [SiteEditorController::class, 'destroyEditor'])->name('establishments.editors.destroy');

    Route::post('/establishments/{tenant}/modules', [AdminAuditController::class, 'updateModules'])->name('establishments.modules');
    Route::post('/establishments/{tenant}/site-content', [AdminAuditController::class, 'updateSiteContent'])->name('establishments.site-content');

    // Actions Docker pour les établissements
    Route::post('/establishments/{tenant}/provision', [AdminAuditController::class, 'provisionTenant'])->name('establishments.provision');
    Route::get('/establishments/{tenant}/provision/stream', [AdminAuditController::class, 'provisionTenantStream'])->name('establishments.provision.stream');
    Route::post('/establishments/{tenant}/start', [AdminAuditController::class, 'startTenant'])->name('establishments.start');
    Route::post('/establishments/{tenant}/stop', [AdminAuditController::class, 'stopTenant'])->name('establishments.stop');
    Route::post('/establishments/{tenant}/restart', [AdminAuditController::class, 'restartTenant'])->name('establishments.restart');
    Route::post('/establishments/{tenant}/demo-data', [AdminAuditController::class, 'seedDemoData'])->name('establishments.demo-data');
    Route::delete('/establishments/{tenant}/demo-data', [AdminAuditController::class, 'purgeDemoData'])->name('establishments.demo-data.purge');
    Route::get('/establishments/{tenant}/health', [AdminAuditController::class, 'healthCheckTenant'])->name('establishments.health');
    Route::get('/establishments/{tenant}/versions', [AdminAuditController::class, 'availableVersions'])->name('establishments.versions');
    Route::get('/establishments/{tenant}/update-version/stream', [AdminAuditController::class, 'updateTenantVersionStream'])->name('establishments.update-version.stream');
    Route::post('/establishments/{tenant}/update-website', [AdminAuditController::class, 'updateTenantWebsite'])->name('establishments.update-website');
    Route::get('/establishments/{tenant}/update-website/stream', [AdminAuditController::class, 'updateTenantWebsiteStream'])->name('establishments.update-website.stream');
    Route::post('/establishments/{tenant}/update-grc', [AdminAuditController::class, 'updateTenantGrc'])->name('establishments.update-grc');
    Route::get('/establishments/{tenant}/update-grc/stream', [AdminAuditController::class, 'updateTenantGrcStream'])->name('establishments.update-grc.stream');

    // Gestion des Utilisateurs ( TECH et BUSINESS )
    Route::post('/users/{user}/toggle-active', [AdminAuditController::class, 'toggleUserActive'])->name('users.toggle-active');
    Route::post('/users/{user}/reset-password', [AdminAuditController::class, 'forcePasswordReset'])->name('users.reset-password');

    // Exports globaux
    Route::get('/export/supervision', [AdminAuditController::class, 'exportSupervision'])->name('export.supervision');

    // Sauvegardes des établissements (backups + planification cron)
    Route::get('/backups', [AdminAuditController::class, 'backupsIndex'])->name('backups.index');
    Route::post('/backups/all', [AdminAuditController::class, 'backupAll'])->name('backups.all');
    Route::post('/backups/{tenant}', [AdminAuditController::class, 'backupCreate'])->name('backups.create');
    Route::get('/backups/{backup}/download', [AdminAuditController::class, 'backupDownload'])->name('backups.download');
    Route::post('/backups/{backup}/restore', [AdminAuditController::class, 'backupRestore'])->name('backups.restore');
    Route::post('/backups/{tenant}/import', [AdminAuditController::class, 'backupImport'])->name('backups.import');
    Route::delete('/backups/{backup}', [AdminAuditController::class, 'backupDelete'])->name('backups.delete');
    Route::post('/backups/{tenant}/schedule', [AdminAuditController::class, 'backupSchedule'])->name('backups.schedule');
});

Route::middleware(['auth', 'role:owner'])->prefix('business')->name('business.')->group(function () {
    Route::get('/dashboard', [AdminAuditController::class, 'businessDashboard'])->name('dashboard');
    Route::get('/establishments', [AdminAuditController::class, 'businessDashboard'])->name('establishments');
    Route::get('/establishments/{tenant}', [AdminAuditController::class, 'businessShowTenant'])->name('establishments.show');
    Route::get('/establishments/{tenant}/finance-data', [AdminAuditController::class, 'businessEstablishmentFinance'])->name('establishments.finance-data');
    Route::post('/establishments/{tenant}/create-manager', [AdminAuditController::class, 'createTenantManager'])->name('establishments.create-manager');
    Route::post('/establishments/{tenant}/create-controller', [AdminAuditController::class, 'createTenantController'])->name('establishments.create-controller');
    Route::get('/analytics', [AdminAuditController::class, 'businessDashboard'])->name('analytics');
    Route::get('/clients', [AdminAuditController::class, 'businessDashboard'])->name('clients');
    Route::get('/employees', [AdminAuditController::class, 'businessDashboard'])->name('employees');
    Route::get('/revenue', [AdminAuditController::class, 'businessDashboard'])->name('revenue');

    // Données consolidées de la vue d'ensemble 360° (AJAX)
    Route::get('/overview/data', [AdminAuditController::class, 'businessOverviewData'])->name('overview.data');
    Route::get('/revenue/data', [AdminAuditController::class, 'businessRevenueData'])->name('revenue.data');
    Route::get('/stats/data', [AdminAuditController::class, 'businessStatsData'])->name('stats.data');
    Route::get('/clients/data', [AdminAuditController::class, 'businessClientsData'])->name('clients.data');
    Route::get('/employees/data', [AdminAuditController::class, 'businessEmployeesData'])->name('employees.data');
    Route::get('/report/data', [AdminAuditController::class, 'businessReportData'])->name('report.data');
    Route::get('/report/export/excel', [AdminAuditController::class, 'businessReportExcel'])->name('report.excel');
    Route::get('/report/export/pdf', [AdminAuditController::class, 'businessReportPdf'])->name('report.pdf');
});
