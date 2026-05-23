<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminDashboard\ApiClientManagementController;
use App\Http\Controllers\Api\AdminDashboard\AuthenticationLogManagementController;
use App\Http\Controllers\Api\AdminDashboard\CacheManagementController;
use App\Http\Controllers\Api\AdminDashboard\ContactManagementController;
use App\Http\Controllers\Api\AdminDashboard\FaqManagementController;
use App\Http\Controllers\Api\AdminDashboard\ManajemenData\KinerjaEkonomiManagementController;
use App\Http\Controllers\Api\AdminDashboard\PermissionManagementController;
use App\Http\Controllers\Api\AdminDashboard\RoleManagementController;
use App\Http\Controllers\Api\AdminDashboard\SidePageViewManagementController;
use App\Http\Controllers\Api\AdminDashboard\TutorialPlaylistManagementController;
use App\Http\Controllers\Api\AdminDashboard\UserManagementController;
use App\Http\Controllers\Api\CaptchaController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\TutorialPlaylistController;
use App\Http\Controllers\Api\V1\Analisis\AnalisisRCACMSAController;
use App\Http\Controllers\Api\V1\Analisis\GeopolitikPerdaganganController;
use App\Http\Controllers\Api\V1\Analisis\KomoditasEksporUtamaController;
use App\Http\Controllers\Api\V1\Analisis\OperationalRiskController;
use App\Http\Controllers\Api\V1\ChatBotController;
use App\Http\Controllers\Api\V1\DataGenerator\Investasi\InvestasiController;
use App\Http\Controllers\Api\V1\DataGenerator\Jasa\JasaController;
use App\Http\Controllers\Api\V1\DataGenerator\KinerjaEkonomi\KinerjaEkonomiController as DataGenKinerjaEkonomiController;
use App\Http\Controllers\Api\V1\DataGenerator\Pariwisata\PariwisataController;
use App\Http\Controllers\Api\V1\DataGenerator\Perdagangan\PerdaganganController;
use App\Http\Controllers\Api\V1\GrupNegaraController;
use App\Http\Controllers\Api\V1\Indonesia\DiplomasiEkonomiController;
use App\Http\Controllers\Api\V1\Indonesia\DiplomasiEkonomiSummaryController;
use App\Http\Controllers\Api\V1\Indonesia\InfrastrukturController;
use App\Http\Controllers\Api\V1\Indonesia\KerjasamaBilateralController;
use App\Http\Controllers\Api\V1\Indonesia\KerjasamaBilateralSummaryController;
use App\Http\Controllers\Api\V1\Indonesia\KinerjaEkonomiController;
use App\Http\Controllers\Api\V1\NegaraController;
use App\Http\Controllers\Api\V1\NegaraMitra\InvestasiController as NegaraMitraInvestasiController;
use App\Http\Controllers\Api\V1\NegaraMitra\InvestmentOverviewSummaryController;
use App\Http\Controllers\Api\V1\NegaraMitra\JasaController as NegaraMitraJasaController;
use App\Http\Controllers\Api\V1\NegaraMitra\OverviewController;
use App\Http\Controllers\Api\V1\NegaraMitra\OverviewSummaryController;
use App\Http\Controllers\Api\V1\NegaraMitra\PariwisataController as NegaraMitraPariwisataController;
use App\Http\Controllers\Api\V1\NegaraMitra\PerdaganganController as NegaraMitraPerdaganganController;
use App\Http\Controllers\Api\V1\NegaraMitra\ServiceOverviewSummaryController;
use App\Http\Controllers\Api\V1\NegaraMitra\TourismOverviewSummaryController;
use App\Http\Controllers\Api\V1\NegaraMitra\TradeOverviewSummaryController;
use App\Http\Controllers\Api\V1\ProdukHSController;
use App\Http\Controllers\Api\V1\ProfesiController;
use App\Http\Controllers\Api\V1\ReportGenerator\KerjasamaPerdagangan\KerjasamaPerdaganganController;
use App\Http\Controllers\Api\V1\ReportGenerator\MarketShare\MarketShareController;
use App\Http\Controllers\Api\V1\ReportGenerator\RCACMSA\RCACMSAController;
use App\Http\Controllers\Api\V1\SektorJasa\DataPMIController;
use App\Http\Controllers\Api\V1\SektorJasa\DemografiPMIController;
use App\Http\Controllers\Api\V1\SektorJasa\InsightPasarPMIController;
use App\Http\Controllers\Api\V1\SektorJasa\OverviewController as SektorJasaOverviewController;
use App\Http\Controllers\Api\V1\SektorPrioritas\EkonomiDigitalController;
use App\Http\Controllers\Api\V1\SektorPrioritas\EkonomiDigitalSummaryController;
use App\Http\Controllers\Api\V1\SektorPrioritas\HilirisasiController;
use App\Http\Controllers\Api\V1\SektorPrioritas\HilirisasiSummaryController;
use App\Http\Controllers\Api\V1\SektorPrioritas\PertahananController;
use App\Http\Controllers\Api\V1\SektorPrioritas\PertahananSummaryController;
use App\Http\Controllers\Api\V1\SektorPrioritas\SektorEnergiController;
use App\Http\Controllers\Api\V1\SektorPrioritas\SektorEnergiSummaryController;
use App\Http\Controllers\Api\V1\SektorPrioritas\SektorFarmasiController;
use App\Http\Controllers\Api\V1\SektorPrioritas\SektorFarmasiSummaryController;
use App\Http\Controllers\Api\V1\SektorPrioritas\SektorMineralKritisController;
use App\Http\Controllers\Api\V1\SektorPrioritas\SektorMineralKritisSummaryController;
use App\Http\Controllers\Api\V1\SektorPrioritas\SektorPanganController;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Controllers\Api\V1\SektorParekraf\OverviewController as ParekrafOverviewController;
use App\Http\Controllers\Api\V1\SektorPrioritas\SektorPanganSummaryController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Analisis\AnalisisRSCATBIController;
use App\Http\Controllers\Api\V1\Analisis\AnalisisRCAEPDController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Prefix default: /api
|--------------------------------------------------------------------------
*/

/**
 * =======================
 *  PUBLIC (tanpa login)
 * =======================
 */
Route::get('/captcha', [CaptchaController::class, 'get']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:60,1');
Route::post('/analytics/page-view', [AnalyticsController::class, 'storePageView']);
Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1');
Route::get('/tutorial-playlists', [TutorialPlaylistController::class, 'index'])->middleware('throttle:60,1');
Route::get('/faqs', [FaqController::class, 'index'])->middleware('throttle:60,1');

/**
 * Endpoint util untuk cek user & logout (BUTUH token Sanctum)
 */
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::prefix('admin-dashboard')
    ->middleware(['throttle:60,1', 'auth_or_api'])
    ->group(function () {
        Route::get('/roles', [RoleManagementController::class, 'index'])
            ->middleware('ability_or_permission:read_admin_roles');
        Route::get('/roles/{id}', [RoleManagementController::class, 'show'])
            ->middleware('ability_or_permission:read_admin_roles');
        Route::post('/roles', [RoleManagementController::class, 'store'])
            ->middleware('ability_or_permission:create_admin_roles');
        Route::put('/roles/{id}', [RoleManagementController::class, 'update'])
            ->middleware('ability_or_permission:update_admin_roles');
        Route::delete('/roles/{id}', [RoleManagementController::class, 'destroy'])
            ->middleware('ability_or_permission:delete_admin_roles');
        Route::get('/permissions', [PermissionManagementController::class, 'index'])
            ->middleware('ability_or_permission:read_admin_permissions');
        Route::get('/permissions/{id}', [PermissionManagementController::class, 'show'])
            ->middleware('ability_or_permission:read_admin_permissions');
        Route::post('/permissions', [PermissionManagementController::class, 'store'])
            ->middleware('ability_or_permission:create_admin_permissions');
        Route::put('/permissions/{id}', [PermissionManagementController::class, 'update'])
            ->middleware('ability_or_permission:update_admin_permissions');
        Route::delete('/permissions/{id}', [PermissionManagementController::class, 'destroy'])
            ->middleware('ability_or_permission:delete_admin_permissions');
        Route::get('/role-permissions', [RoleManagementController::class, 'permissions'])
            ->middleware('ability_or_permission:read_admin_roles');
        Route::get('/users', [UserManagementController::class, 'index'])
            ->middleware('ability_or_permission:read_admin_users');
        Route::get('/users/{id}', [UserManagementController::class, 'show'])
            ->middleware('ability_or_permission:read_admin_users');
        Route::post('/users', [UserManagementController::class, 'store'])
            ->middleware('ability_or_permission:create_admin_users');
        Route::put('/users/{id}', [UserManagementController::class, 'update'])
            ->middleware('ability_or_permission:update_admin_users');
        Route::delete('/users/{id}', [UserManagementController::class, 'destroy'])
            ->middleware('ability_or_permission:delete_admin_users');
        Route::get('/user-roles', [UserManagementController::class, 'roles'])
            ->middleware('ability_or_permission:read_admin_users');
        Route::get('/faqs', [FaqManagementController::class, 'index'])
            ->middleware('ability_or_permission:read_admin_faqs');
        Route::get('/faqs/{id}', [FaqManagementController::class, 'show'])
            ->middleware('ability_or_permission:read_admin_faqs');
        Route::post('/faqs', [FaqManagementController::class, 'store'])
            ->middleware('ability_or_permission:create_admin_faqs');
        Route::put('/faqs/{id}', [FaqManagementController::class, 'update'])
            ->middleware('ability_or_permission:update_admin_faqs');
        Route::delete('/faqs/{id}', [FaqManagementController::class, 'destroy'])
            ->middleware('ability_or_permission:delete_admin_faqs');
        Route::get('/contacts', [ContactManagementController::class, 'index'])
            ->middleware('ability_or_permission:read_admin_contacts');
        Route::get('/contacts/{id}', [ContactManagementController::class, 'show'])
            ->middleware('ability_or_permission:read_admin_contacts');
        Route::put('/contacts/{id}', [ContactManagementController::class, 'update'])
            ->middleware('ability_or_permission:update_admin_contacts');
        Route::delete('/contacts/{id}', [ContactManagementController::class, 'destroy'])
            ->middleware('ability_or_permission:delete_admin_contacts');
        Route::get('/api-clients', [ApiClientManagementController::class, 'index'])
            ->middleware('ability_or_permission:read_admin_api_clients');
        Route::get('/api-clients/{id}', [ApiClientManagementController::class, 'show'])
            ->middleware('ability_or_permission:read_admin_api_clients');
        Route::post('/api-clients', [ApiClientManagementController::class, 'store'])
            ->middleware('ability_or_permission:create_admin_api_clients');
        Route::put('/api-clients/{id}', [ApiClientManagementController::class, 'update'])
            ->middleware('ability_or_permission:update_admin_api_clients');
        Route::post('/api-clients/{id}/regenerate-key', [ApiClientManagementController::class, 'regenerateKey'])
            ->middleware('ability_or_permission:update_admin_api_clients');
        Route::delete('/api-clients/{id}', [ApiClientManagementController::class, 'destroy'])
            ->middleware('ability_or_permission:delete_admin_api_clients');
        Route::get('/api-client-permissions', [ApiClientManagementController::class, 'permissions'])
            ->middleware('ability_or_permission:read_admin_api_clients');
        Route::get('/caches', [CacheManagementController::class, 'index'])
            ->middleware('ability_or_permission:read_admin_caches');
        Route::get('/caches/{id}', [CacheManagementController::class, 'show'])
            ->middleware('ability_or_permission:read_admin_caches');
        Route::put('/caches/{id}', [CacheManagementController::class, 'update'])
            ->middleware('ability_or_permission:update_admin_caches');
        Route::delete('/caches/{id}', [CacheManagementController::class, 'destroy'])
            ->middleware('ability_or_permission:delete_admin_caches');
        Route::get('/authentication-logs', [AuthenticationLogManagementController::class, 'index'])
            ->middleware('ability_or_permission:read_admin_authentication_logs');
        Route::get('/authentication-logs/{id}', [AuthenticationLogManagementController::class, 'show'])
            ->middleware('ability_or_permission:read_admin_authentication_logs');
        Route::delete('/authentication-logs/{id}', [AuthenticationLogManagementController::class, 'destroy'])
            ->middleware('ability_or_permission:delete_admin_authentication_logs');
        Route::get('/side-page-views', [SidePageViewManagementController::class, 'index'])
            ->middleware('ability_or_permission:read_admin_side_page_views');
        Route::get('/side-page-views/{id}', [SidePageViewManagementController::class, 'show'])
            ->middleware('ability_or_permission:read_admin_side_page_views');
        Route::get('/side-page-view-modules', [SidePageViewManagementController::class, 'modules'])
            ->middleware('ability_or_permission:read_admin_side_page_views');
        Route::get('/tutorial-playlists', [TutorialPlaylistManagementController::class, 'index'])
            ->middleware('ability_or_permission:read_admin_tutorial_playlists');
        Route::get('/tutorial-playlists/{id}', [TutorialPlaylistManagementController::class, 'show'])
            ->middleware('ability_or_permission:read_admin_tutorial_playlists');
        Route::post('/tutorial-playlists', [TutorialPlaylistManagementController::class, 'store'])
            ->middleware('ability_or_permission:create_admin_tutorial_playlists');
        Route::match(['post', 'put'], '/tutorial-playlists/{id}', [TutorialPlaylistManagementController::class, 'update'])
            ->middleware('ability_or_permission:update_admin_tutorial_playlists');
        Route::delete('/tutorial-playlists/{id}', [TutorialPlaylistManagementController::class, 'destroy'])
            ->middleware('ability_or_permission:delete_admin_tutorial_playlists');
        Route::get('/kinerja-ekonomi/options', [KinerjaEkonomiManagementController::class, 'options'])
            ->middleware('ability_or_permission:read_admin_kinerja_ekonomi,read_admin_kinerja_ekonomi_current');
        Route::get('/kinerja-ekonomi', [KinerjaEkonomiManagementController::class, 'index'])
            ->middleware('ability_or_permission:read_admin_kinerja_ekonomi');
        Route::get('/kinerja-ekonomi/current', [KinerjaEkonomiManagementController::class, 'currentIndex'])
            ->middleware('ability_or_permission:read_admin_kinerja_ekonomi_current');
        Route::post('/kinerja-ekonomi/preview', [KinerjaEkonomiManagementController::class, 'previewUpload'])
            ->middleware('ability_or_permission:create_admin_kinerja_ekonomi');
        Route::get('/kinerja-ekonomi/{id}', [KinerjaEkonomiManagementController::class, 'show'])
            ->middleware('ability_or_permission:read_admin_kinerja_ekonomi');
        Route::post('/kinerja-ekonomi', [KinerjaEkonomiManagementController::class, 'store'])
            ->middleware('ability_or_permission:create_admin_kinerja_ekonomi');
        Route::put('/kinerja-ekonomi/{id}/rows/{rowId}', [KinerjaEkonomiManagementController::class, 'updateRow'])
            ->middleware('ability_or_permission:update_admin_kinerja_ekonomi');
        Route::put('/kinerja-ekonomi/current/{rowId}', [KinerjaEkonomiManagementController::class, 'updateCurrentRow'])
            ->middleware('ability_or_permission:update_admin_kinerja_ekonomi');
        Route::post('/kinerja-ekonomi/current/bulk-delete', [KinerjaEkonomiManagementController::class, 'deleteCurrentRows'])
            ->middleware('ability_or_permission:delete_admin_kinerja_ekonomi');
        Route::post('/kinerja-ekonomi/{id}/rows/bulk-delete', [KinerjaEkonomiManagementController::class, 'deleteRows'])
            ->middleware('ability_or_permission:delete_admin_kinerja_ekonomi');
        Route::delete('/kinerja-ekonomi/{id}/staging', [KinerjaEkonomiManagementController::class, 'clearStaging'])
            ->middleware('ability_or_permission:delete_admin_kinerja_ekonomi');
        Route::delete('/kinerja-ekonomi/current/{rowId}', [KinerjaEkonomiManagementController::class, 'deleteCurrentRow'])
            ->middleware('ability_or_permission:delete_admin_kinerja_ekonomi');
        Route::delete('/kinerja-ekonomi/{id}/rows/{rowId}', [KinerjaEkonomiManagementController::class, 'deleteRow'])
            ->middleware('ability_or_permission:delete_admin_kinerja_ekonomi');
        Route::post('/kinerja-ekonomi/{id}/validate', [KinerjaEkonomiManagementController::class, 'validateBatch'])
            ->middleware('ability_or_permission:update_admin_kinerja_ekonomi');
        Route::post('/kinerja-ekonomi/{id}/approve', [KinerjaEkonomiManagementController::class, 'approve'])
            ->middleware('ability_or_permission:approve_admin_kinerja_ekonomi');
        Route::post('/kinerja-ekonomi/{id}/publish', [KinerjaEkonomiManagementController::class, 'publish'])
            ->middleware('ability_or_permission:publish_admin_kinerja_ekonomi');
        Route::post('/kinerja-ekonomi/{id}/reject', [KinerjaEkonomiManagementController::class, 'reject'])
            ->middleware('ability_or_permission:approve_admin_kinerja_ekonomi');
        Route::delete('/kinerja-ekonomi/{id}', [KinerjaEkonomiManagementController::class, 'destroy'])
            ->middleware('ability_or_permission:delete_admin_kinerja_ekonomi');
    });

/**
 * ===========================
 *  API v1
 *  prefix: /api/v1/...
 *  Proteksi: auth_or_api → boleh:
 *    - user login (Sanctum), ATAU
 *    - API client pakai X-API-KEY (divalidasi VerifyApiClient)
 * ===========================
 */
Route::prefix('v1')
    ->middleware(['throttle:100,1', 'auth_or_api'])
    ->group(function () {

        // ========= COMMON MASTER DATA =========
        Route::get('/negara', [NegaraController::class, 'index']);
        Route::get('/wilayah', [NegaraController::class, 'commonWilayah']);
        Route::get('/common-negara', [NegaraController::class, 'commonNegara']);
        Route::get('/common-negara-rca-cmsa', [AnalisisRCACMSAController::class, 'commonNegaraRCACMSA']);
        Route::get('/grupnegara', [GrupNegaraController::class, 'index']);
        Route::get('/grupnegaralist', [GrupNegaraController::class, 'negaraByGroup']);
        Route::get('/hsproduk', [ProdukHSController::class, 'index']);
        Route::get('/profesi', [ProfesiController::class, 'index']);
        Route::get('/komoditas', [SektorPanganController::class, 'komoditas']);
        Route::get('/hscode-tik', [EkonomiDigitalController::class, 'hscode']);
        Route::get('/hscode-energi', [SektorEnergiController::class, 'hscode']);
        Route::get('/hscode-pangan', [SektorPanganController::class, 'hscode']);
        Route::get('/hscode-farmasi', [SektorFarmasiController::class, 'hscode']);
        Route::get('/hscode-pertahanan', [PertahananController::class, 'hscode']);
        Route::get('/hscode-hilirisasi', [HilirisasiController::class, 'hscode']);
        Route::get('/hscode-mineral-kritis', [SektorMineralKritisController::class, 'hscode']);
        Route::get('/data-generator/perdagangan/kode-sumber', [PerdaganganController::class, 'kodesumber']);
        Route::get('/data-generator/pariwisata/kode-sumber', [PariwisataController::class, 'kodesumber']);
        Route::get('/data-generator/investasi/kode-sumber', [InvestasiController::class, 'kodesumber']);
        Route::get('/data-generator/jasa/kode-sumber', [JasaController::class, 'kodesumber']);
        Route::get('/data-generator/perdagangan/tahun-perdagangan', [PerdaganganController::class, 'tahunPerdagangan']);
        Route::get('/data-generator/pariwisata/tahun-pariwisata', [PariwisataController::class, 'tahunPariwisata']);
        Route::get('/data-generator/investasi/tahun-investasi', [InvestasiController::class, 'tahunInvestasi']);
        Route::get('/data-generator/jasa/tahun-jasa', [JasaController::class, 'tahunJasa']);

        // Chatbot
        Route::post('/chatbot', [ChatBotController::class, 'handle']);

        // Tahun & indikator
        Route::get('/tahun-perdagangan', [PerdaganganController::class, 'tahunPerdagangan']);
        Route::get('/tahun-perdagangan-default', [PerdaganganController::class, 'tahunPerdaganganDefault']);
        Route::get('/tahun-investasi-default', [InvestasiController::class, 'tahunInvestasiDefault']);
        Route::get('/tahun-pariwisata-default', [PariwisataController::class, 'tahunPariwisataDefault']);
        Route::get('/tahun-kinerja-ekonomi', [KinerjaEkonomiController::class, 'tahunKinerjaEkonomi']);
        Route::get('/indikator-index-ekonomi', [KinerjaEkonomiController::class, 'indikatorKinerjaEkonomi']);
        Route::get('/indikator-index-ekonomi-all', [KinerjaEkonomiController::class, 'indikatorKinerjaEkonomiAll']);

        /**
         * ============= DATA GENERATOR =============
         * prefix: /api/v1/data-generator/...
         */
        Route::prefix('data-generator')->group(function () {

            // Perdagangan
            Route::prefix('perdagangan')
                ->middleware('ability_or_permission:view_perdagangan_data_generator')
                ->group(function () {
                    Route::post('/tablefilter', [PerdaganganController::class, 'tablefilter'])->middleware('throttle:100,1');
                    Route::post('/visualizationfilter', [PerdaganganController::class, 'visualizationfilter'])->middleware('throttle:100,1');
                });

            // Pariwisata
            Route::prefix('pariwisata')
                ->middleware('ability_or_permission:view_turis_data_generator')
                ->group(function () {
                    Route::post('/tablefilter', [PariwisataController::class, 'tablefilter'])->middleware('throttle:100,1');
                    Route::post('/visualizationfilter', [PariwisataController::class, 'visualizationfilter'])->middleware('throttle:100,1');
                });

            // Investasi
            Route::prefix('investasi')
                ->middleware('ability_or_permission:view_investasi_data_generator')
                ->group(function () {
                    Route::post('/tablefilter', [InvestasiController::class, 'tablefilter'])->middleware('throttle:100,1');
                    Route::post('/visualizationfilter', [InvestasiController::class, 'visualizationfilter'])->middleware('throttle:100,1');
                });

            // Jasa (data-generator)
            Route::prefix('jasa')
                ->middleware('ability_or_permission:view_jasa_data_generator')
                ->group(function () {
                    Route::post('/tablefilter', [JasaController::class, 'tablefilter'])->middleware('throttle:100,1');
                    Route::post('/visualizationfilter', [JasaController::class, 'visualizationfilter'])->middleware('throttle:100,1');
                });

            // Kinerja Ekonomi
            Route::prefix('kinerja-ekonomi')->group(function () {
                Route::post('/tablefilter', [DataGenKinerjaEkonomiController::class, 'tablefilter'])
                    ->middleware('throttle:100,1');
                Route::post('/visualizationfilter', [DataGenKinerjaEkonomiController::class, 'visualizationfilter'])
                    ->middleware('throttle:100,1');
            });
        });

        /**
         * ============= REPORT GENERATOR =============
         * prefix: /api/v1/report-generator/...
         */
        Route::prefix('report-generator')->group(function () {

            // RCA-CMSA
            Route::prefix('rca-cmsa')
                ->middleware('ability_or_permission:view_rca_cmsa_report_generator')
                ->group(function () {
                    Route::post('/filter', [RCACMSAController::class, 'filter'])->middleware('throttle:100,1');
                    Route::post('/snapshot/word', [RCACMSAController::class, 'snapshotWord'])->middleware('throttle:100,1');
                    Route::post('/snapshot/pdf', [RCACMSAController::class, 'snapshotPdf'])->middleware('throttle:100,1');
                    Route::post('/summary/word', [RCACMSAController::class, 'summaryWord'])->middleware('throttle:100,1');
                    Route::post('/summary/pdf', [RCACMSAController::class, 'summaryPdf'])->middleware('throttle:100,1');
                });

            // Market Share
            Route::prefix('market-share')
                ->middleware('ability_or_permission:view_market_share_report_generator')
                ->group(function () {
                    Route::post('/filter', [MarketShareController::class, 'filter']);
                    Route::post('/snapshot/word', [MarketShareController::class, 'snapshotWord'])->middleware('throttle:100,1');
                    Route::post('/snapshot/pdf', [MarketShareController::class, 'snapshotPdf'])->middleware('throttle:100,1');
                });

            // Kerjasama Perdagangan
            Route::prefix('kerjasama-perdagangan')
                ->middleware('ability_or_permission:view_kerjasama_perdagangan_report_generator')
                ->group(function () {
                    Route::post('/filter', [KerjasamaPerdaganganController::class, 'filter']);
                    Route::post('/snapshot/word', [KerjasamaPerdaganganController::class, 'snapshotWord'])->middleware('throttle:100,1');
                    Route::post('/snapshot/pdf', [KerjasamaPerdaganganController::class, 'snapshotPdf'])->middleware('throttle:100,1');
                });

            // Kerjasama Pariwisata
            Route::prefix('kerjasama-pariwisata')->group(function () {
                Route::post('/filter', [KerjasamaPerdaganganController::class, 'filter']);
                Route::post('/snapshot/word', [KerjasamaPerdaganganController::class, 'snapshotWord'])->middleware('throttle:100,1');
                Route::post('/snapshot/pdf', [KerjasamaPerdaganganController::class, 'snapshotPdf'])->middleware('throttle:100,1');
            });
        });

        /**
         * ============= NEGARA MITRA =============
         * prefix: /api/v1/negara-mitra/...
         */
        Route::prefix('negara-mitra')->group(function () {
            Route::prefix('overview')
                ->middleware('ability_or_permission:view_overview_negara_mitra')
                ->group(function () {
                    Route::get('/stats', [OverviewController::class, 'stats']);
                    Route::get('/perdagangan-negara', [OverviewController::class, 'tradeCountry']);
                    Route::get('/top-perdagangan', [OverviewController::class, 'topPerdagangan']);
                    Route::post('/top-perdagangan/summary/pdf', [OverviewSummaryController::class, 'topPerdaganganSummaryPdf'])->middleware('throttle:100,1');
                    Route::get('/top-investasi', [OverviewController::class, 'topInvestasi']);
                    Route::post('/top-investasi/summary/pdf', [OverviewSummaryController::class, 'topInvestasiSummaryPdf'])->middleware('throttle:100,1');
                    Route::get('/top-pariwisata', [OverviewController::class, 'topPariwisata']);
                    Route::post('/top-pariwisata/summary/pdf', [OverviewSummaryController::class, 'topPariwisataSummaryPdf'])->middleware('throttle:100,1');
                    Route::get('/top-jasa', [OverviewController::class, 'topJasa']);
                    Route::post('/top-jasa/summary/pdf', [OverviewSummaryController::class, 'topJasaSummaryPdf'])->middleware('throttle:100,1');
                });

            Route::post('/perdagangan', [NegaraMitraPerdaganganController::class, 'overview'])
                ->middleware('ability_or_permission:view_perdagangan_negara_mitra');
            Route::post('/perdagangan/summary/pdf', [TradeOverviewSummaryController::class, 'overviewSummaryPdf'])
                ->middleware('ability_or_permission:view_perdagangan_negara_mitra');
            Route::post('/jasa', [NegaraMitraJasaController::class, 'overview'])
                ->middleware('ability_or_permission:view_jasa_negara_mitra');
            Route::post('/jasa/summary/pdf', [ServiceOverviewSummaryController::class, 'overviewSummaryPdf'])
                ->middleware('ability_or_permission:view_jasa_negara_mitra');
            Route::post('/jasa/country', [NegaraMitraJasaController::class, 'countryOverview'])
                ->middleware('ability_or_permission:view_jasa_negara_mitra');
            Route::post('/investasi/single', [NegaraMitraInvestasiController::class, 'singleOverview'])
                ->middleware('ability_or_permission:view_investasi_negara_mitra');
            Route::post('/investasi/multi', [NegaraMitraInvestasiController::class, 'multiOverview'])
                ->middleware('ability_or_permission:view_investasi_negara_mitra');
            Route::post('/investasi/summary/pdf', [InvestmentOverviewSummaryController::class, 'overviewSummaryPdf'])
                ->middleware('ability_or_permission:view_investasi_negara_mitra');
            Route::post('/pariwisata/single', [NegaraMitraPariwisataController::class, 'singleOverview'])
                ->middleware('ability_or_permission:view_pariwisata_negara_mitra');
            Route::post('/pariwisata/multi', [NegaraMitraPariwisataController::class, 'multiOverview'])
                ->middleware('ability_or_permission:view_pariwisata_negara_mitra');
            Route::post('/pariwisata/summary/pdf', [TourismOverviewSummaryController::class, 'overviewSummaryPdf'])
                ->middleware('ability_or_permission:view_pariwisata_negara_mitra');
        });

        /**
         * ============= INDONESIA =============
         * prefix: /api/v1/indonesia/...
         */
        Route::prefix('indonesia')->group(function () {

            // Diplomasi Ekonomi
            Route::prefix('diplomasi-ekonomi')
                ->middleware('ability_or_permission:view_diplomasi_ekonomi_indonesia')
                ->group(function () {
                    Route::get('/stats', [DiplomasiEkonomiController::class, 'stats']);
                    Route::get('/nilai-perdagangan', [DiplomasiEkonomiController::class, 'nilaiPerdagangan']);
                    Route::post('/nilai-perdagangan/summary/pdf', [DiplomasiEkonomiSummaryController::class, 'pdf'])->middleware('throttle:100,1');
                    Route::post('/total-ekspor/summary/pdf', [DiplomasiEkonomiSummaryController::class, 'totalEksporPdf'])->middleware('throttle:100,1');
                    Route::post('/total-impor/summary/pdf', [DiplomasiEkonomiSummaryController::class, 'totalImporPdf'])->middleware('throttle:100,1');
                    Route::post('/neraca/summary/pdf', [DiplomasiEkonomiSummaryController::class, 'neracaPerdaganganPdf'])->middleware('throttle:100,1');
                    Route::post('/total-inbound-investasi/summary/pdf', [DiplomasiEkonomiSummaryController::class, 'totalInboundInvestasiPdf'])->middleware('throttle:100,1');
                    Route::post('/total-inbound-wisatawan/summary/pdf', [DiplomasiEkonomiSummaryController::class, 'totalInboundTourismPdf'])->middleware('throttle:100,1');
                    Route::get('/total-ekspor', [DiplomasiEkonomiController::class, 'totalEkspor']);
                    Route::get('/total-impor', [DiplomasiEkonomiController::class, 'totalImpor']);
                    Route::get('/total-inbound-investasi', [DiplomasiEkonomiController::class, 'totalInboundInvestasi']);
                    Route::get('/total-outbound-investasi', [DiplomasiEkonomiController::class, 'totalOutboundInvestasi']);
                    Route::get('/total-inbound-wisatawan', [DiplomasiEkonomiController::class, 'totalInboundTourism']);
                    Route::get('/total-bantuan-kerjasama', [DiplomasiEkonomiController::class, 'totalbantuanKerjasama']);
                });

            // Infrastruktur
            Route::prefix('infrastruktur')
                ->middleware('ability_or_permission:view_infrastruktur_indonesia')
                ->group(function () {
                    Route::get('/kategori', [InfrastrukturController::class, 'categories']);
                    Route::get('/perwakilan', [InfrastrukturController::class, 'perwakilan']);
                    Route::get('/perwakilan-asing', [InfrastrukturController::class, 'perwakilanAsing']);
                    Route::get('/pameran-indonesia', [InfrastrukturController::class, 'pameranIndonesia']);
                    Route::get('/pameran-perwakilan', [InfrastrukturController::class, 'pameranPerwakilan']);
                    Route::get('/perjanjian-antar-negara', [InfrastrukturController::class, 'perjanjianAntarNegara']);
                });

            // Kerjasama Bilateral
            Route::prefix('kerjasama-bilateral')
                ->middleware('ability_or_permission:view_kerjasama_bilateral_indonesia')
                ->group(function () {
                    Route::get('/nilai-perdagangan', [KerjasamaBilateralController::class, 'nilaiPerdagangan']);
                    Route::post('/nilai-perdagangan/insight-tujuan-kompetitor', [KerjasamaBilateralController::class, 'insightKompetitor']);
                    Route::post('/nilai-perdagangan/summary/pdf', [KerjasamaBilateralSummaryController::class, 'nilaiPerdaganganSummaryPdf'])->middleware('throttle:100,1');
                    Route::post('/nilai-pariwisata/summary/pdf', [KerjasamaBilateralSummaryController::class, 'nilaiPariwisataSummaryPdf'])->middleware('throttle:100,1');
                    Route::post('/nilai-investasi/summary/pdf', [KerjasamaBilateralSummaryController::class, 'nilaiInvestasiSummaryPdf'])->middleware('throttle:100,1');
                    Route::post('/nilai-jasa/summary/pdf', [KerjasamaBilateralSummaryController::class, 'nilaiJasaSummaryPdf'])->middleware('throttle:100,1');
                    Route::post('/nilai-bantuan/summary/pdf', [KerjasamaBilateralSummaryController::class, 'nilaiBantuanSummaryPdf'])->middleware('throttle:100,1');
                    Route::get('/nilai-pariwisata', [KerjasamaBilateralController::class, 'nilaiPariwisata']);
                    Route::get('/nilai-investasi', [KerjasamaBilateralController::class, 'nilaiInvestasi']);
                    Route::get('/nilai-jasa', [KerjasamaBilateralController::class, 'nilaiJasa']);
                    Route::get('/kerjasama-pembangunan', [KerjasamaBilateralController::class, 'nilaiBantuan']);
                });

            // Kinerja Ekonomi
            Route::get('/kinerja-ekonomi', [KinerjaEkonomiController::class, 'kinerjaEkonomi'])
                ->middleware('ability_or_permission:view_kinerja_ekonomi_indonesia');

        });

        /**
         * ============= ANALISIS =============
         * prefix: /api/v1/analisis/...
         */
        Route::prefix('analisis')->group(function () {
            Route::get('/komoditas-ekspor-utama', [KomoditasEksporUtamaController::class, 'eksporUtama'])
                ->middleware('ability_or_permission:view_produk_komoditas_analisis');
            Route::get('/geopolitik-perdagangan', [GeopolitikPerdaganganController::class, 'geopolitikPerdagangan'])
                ->middleware('ability_or_permission:view_produk_komoditas_analisis');
            Route::get('/operational-risk', [OperationalRiskController::class, 'operationalRisk'])
                ->middleware('ability_or_permission:view_operational_risk_analisis');
            Route::get('/rca-cmsa', [AnalisisRCACMSAController::class, 'rcaCmsaData'])
                ->middleware('ability_or_permission:view_potensi_daya_saing_analisis');
            Route::get('/rca-cmsa-kalkulasi', [AnalisisRCACMSAController::class, 'rcaCmsaCalculationData'])
                ->middleware('ability_or_permission:view_potensi_daya_saing_analisis');
            Route::get('/rsca-tbi', [AnalisisRSCATBIController::class, 'rscaTbiData']);
            Route::get('/rsca-tbi-kalkulasi', [AnalisisRSCATBIController::class, 'rscaTbiCalculation'])
                ->middleware('ability_or_permission:view_potensi_daya_saing_analisis');                
            Route::get('/rsca-tbi-comparison', [AnalisisRSCATBIController::class, 'comparison'])
                ->middleware('ability_or_permission:view_potensi_daya_saing_analisis');
            Route::get('/rca-epd', [AnalisisRCAEPDController::class, 'data']);
            Route::get('/rca-epd-kalkulasi', [AnalisisRCAEPDController::class, 'calculation'])
                ->middleware('ability_or_permission:view_potensi_daya_saing_analisis');
            Route::get('/rca-epd-comparison', [AnalisisRCAEPDController::class, 'comparison'])
                ->middleware('ability_or_permission:view_potensi_daya_saing_analisis');
            Route::get('/rca-epd-xmodel-options', [AnalisisRCAEPDController::class, 'xModelOptions']);
        });

        /**
         * ============= SEKTOR PRIORITAS =============
         * prefix: /api/v1/sektor-prioritas/...
         */
        Route::prefix('sektor-prioritas')->group(function () {

            // Ekonomi Digital
            Route::prefix('ekonomi-digital')
                ->middleware('ability_or_permission:view_arus_tik_prioritas')
                ->group(function () {
                    Route::get('/nilai-arus-tik', [EkonomiDigitalController::class, 'nilaiTIK']);
                    Route::get('/nilai-arus-tik-produk', [EkonomiDigitalController::class, 'nilaiTIKProduk']);
                    Route::post('/summary/pdf', [EkonomiDigitalSummaryController::class, 'summaryPdf'])
                        ->middleware('throttle:100,1');
                    Route::get('/nilai-ecommerce', [EkonomiDigitalController::class, 'nilaiEcommerce']);
                    Route::get('/nilai-infrastruktur', [EkonomiDigitalController::class, 'nilaiInfrastruktur']);
                });

            // Energi
            Route::get('/nilai-sektor-energi', [SektorEnergiController::class, 'nilaiEnergi'])
                ->middleware('ability_or_permission:view_energi_prioritas');
            Route::get('/nilai-sektor-produk-energi', [SektorEnergiController::class, 'nilaiEnergiProduk'])
                ->middleware('ability_or_permission:view_energi_prioritas');
            Route::post('/nilai-sektor-energi/summary/pdf', [SektorEnergiSummaryController::class, 'summaryPdf'])
                ->middleware('ability_or_permission:view_energi_prioritas');

            // Mineral Kritis
            Route::get('/nilai-sektor-mineral-kritis', [SektorMineralKritisController::class, 'nilaiMineralKritis'])
                ->middleware('ability_or_permission:view_mineral_kritis_prioritas');
            Route::get('/nilai-sektor-produk-mineral-kritis', [SektorMineralKritisController::class, 'nilaiMineralKritisProduk'])
                ->middleware('ability_or_permission:view_mineral_kritis_prioritas');
            Route::post('/nilai-sektor-mineral-kritis/summary/pdf', [SektorMineralKritisSummaryController::class, 'summaryPdf'])
                ->middleware('ability_or_permission:view_mineral_kritis_prioritas');

            // Farmasi
            Route::get('/nilai-sektor-farmasi', [SektorFarmasiController::class, 'nilaiFarmasi'])
                ->middleware('ability_or_permission:view_farmasi_prioritas');
            Route::get('/nilai-sektor-produk-farmasi', [SektorFarmasiController::class, 'nilaiFarmasiProduk'])
                ->middleware('ability_or_permission:view_farmasi_prioritas');
            Route::post('/nilai-sektor-farmasi/summary/pdf', [SektorFarmasiSummaryController::class, 'summaryPdf'])
                ->middleware('ability_or_permission:view_farmasi_prioritas');

            // Hilirisasi
            Route::get('/nilai-sektor-hilirisasi', [HilirisasiController::class, 'nilaiHilirisasi'])
                ->middleware('ability_or_permission:view_hilirisasi_prioritas');
            Route::get('/nilai-sektor-produk-hilirisasi', [HilirisasiController::class, 'nilaiHilirisasiProduk'])
                ->middleware('ability_or_permission:view_hilirisasi_prioritas');
            Route::post('/nilai-sektor-hilirisasi/summary/pdf', [HilirisasiSummaryController::class, 'summaryPdf'])
                ->middleware('ability_or_permission:view_hilirisasi_prioritas');

            // Pertahanan
            Route::get('/nilai-sektor-pertahanan', [PertahananController::class, 'nilaiPertahanan'])
                ->middleware('ability_or_permission:view_pertahanan_prioritas');
            Route::get('/nilai-sektor-produk-pertahanan', [PertahananController::class, 'nilaiPertahananProduk'])
                ->middleware('ability_or_permission:view_pertahanan_prioritas');
            Route::post('/nilai-sektor-pertahanan/summary/pdf', [PertahananSummaryController::class, 'summaryPdf'])
                ->middleware('ability_or_permission:view_pertahanan_prioritas');

            // Pangan
            Route::get('/nilai-sektor-pangan', [SektorPanganController::class, 'nilaiPangan'])
                ->middleware('ability_or_permission:view_pangan_prioritas');
            Route::get('/nilai-sektor-produk-pangan', [SektorPanganController::class, 'nilaiPanganProduk'])
                ->middleware('ability_or_permission:view_pangan_prioritas');
            Route::post('/nilai-sektor-pangan/summary/pdf', [SektorPanganSummaryController::class, 'summaryPdf'])
                ->middleware('ability_or_permission:view_pangan_prioritas');
        });

        /**
         * ============= SEKTOR JASA (DASHBOARD PMI) =============
         * prefix: /api/v1/sektor-jasa/...
         */
        Route::prefix('sektor-jasa')->group(function () {
            Route::get('/overview', [SektorJasaOverviewController::class, 'overview'])
                ->middleware('ability_or_permission:view_sektor_jasa');
            Route::get('/data-pmi', [DataPMIController::class, 'overview'])
                ->middleware('ability_or_permission:view_data_pekerja_migran_indonesia');
            Route::get('/demografi-pmi', [DemografiPMIController::class, 'overview'])
                ->middleware('ability_or_permission:view_data_pekerja_migran_indonesia');
            Route::get('/insight-pasar-pmi', [InsightPasarPMIController::class, 'overview'])
                ->middleware('ability_or_permission:view_insight_pasar_jasa');
        });
    });
