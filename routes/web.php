<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AdminAuditController;

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

// === API PUBLIQUE (consommée par template_site — pas d'authentification) ===
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

    // Gestion des Établissements (Tenants)
    Route::get('/establishments', [AdminAuditController::class, 'indexTenants'])->name('establishments.index');
    Route::get('/establishments/create', [AdminAuditController::class, 'createTenant'])->name('establishments.create');
    Route::post('/establishments', [AdminAuditController::class, 'storeTenant'])->name('establishments.store');
    Route::get('/establishments/{tenant}', [AdminAuditController::class, 'showTenant'])->name('establishments.show');
    Route::post('/establishments/{tenant}', [AdminAuditController::class, 'updateTenant'])->name('establishments.update');
    Route::delete('/establishments/{tenant}', [AdminAuditController::class, 'destroyTenant'])->name('establishments.destroy');
    Route::post('/establishments/{tenant}/create-manager', [AdminAuditController::class, 'createTenantManager'])->name('establishments.create-manager');
    Route::post('/establishments/{tenant}/modules', [AdminAuditController::class, 'updateModules'])->name('establishments.modules');
    Route::post('/establishments/{tenant}/site-content', [AdminAuditController::class, 'updateSiteContent'])->name('establishments.site-content');

    // Actions Docker pour les établissements
    Route::post('/establishments/{tenant}/provision', [AdminAuditController::class, 'provisionTenant'])->name('establishments.provision');
    Route::get('/establishments/{tenant}/provision/stream', [AdminAuditController::class, 'provisionTenantStream'])->name('establishments.provision.stream');
    Route::post('/establishments/{tenant}/start', [AdminAuditController::class, 'startTenant'])->name('establishments.start');
    Route::post('/establishments/{tenant}/stop', [AdminAuditController::class, 'stopTenant'])->name('establishments.stop');
    Route::post('/establishments/{tenant}/restart', [AdminAuditController::class, 'restartTenant'])->name('establishments.restart');
    Route::get('/establishments/{tenant}/health', [AdminAuditController::class, 'healthCheckTenant'])->name('establishments.health');
    Route::get('/establishments/{tenant}/versions', [AdminAuditController::class, 'availableVersions'])->name('establishments.versions');
    Route::get('/establishments/{tenant}/update-version/stream', [AdminAuditController::class, 'updateTenantVersionStream'])->name('establishments.update-version.stream');
    Route::post('/establishments/{tenant}/update-website', [AdminAuditController::class, 'updateTenantWebsite'])->name('establishments.update-website');

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
    Route::post('/establishments/{tenant}/create-manager', [AdminAuditController::class, 'createTenantManager'])->name('establishments.create-manager');
    Route::get('/analytics', [AdminAuditController::class, 'businessDashboard'])->name('analytics');
    Route::get('/employees', [AdminAuditController::class, 'businessDashboard'])->name('employees');
    Route::get('/revenue', [AdminAuditController::class, 'businessDashboard'])->name('revenue');
});
