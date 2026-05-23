<?php

namespace App\Providers;

use App\Repositories\AdminDashboard\ApiClientManagement\ApiClientManagementRepository;
use App\Repositories\AdminDashboard\ApiClientManagement\ApiClientManagementRepositoryInterface;
use App\Repositories\AdminDashboard\AuthenticationLogManagement\AuthenticationLogManagementRepository;
use App\Repositories\AdminDashboard\AuthenticationLogManagement\AuthenticationLogManagementRepositoryInterface;
use App\Repositories\AdminDashboard\CacheManagement\CacheManagementRepository;
use App\Repositories\AdminDashboard\CacheManagement\CacheManagementRepositoryInterface;
use App\Repositories\AdminDashboard\PermissionManagement\PermissionManagementRepository;
use App\Repositories\AdminDashboard\PermissionManagement\PermissionManagementRepositoryInterface;
use App\Repositories\AdminDashboard\ContactManagement\ContactManagementRepository;
use App\Repositories\AdminDashboard\ContactManagement\ContactManagementRepositoryInterface;
use App\Repositories\AdminDashboard\FaqManagement\FaqManagementRepository;
use App\Repositories\AdminDashboard\FaqManagement\FaqManagementRepositoryInterface;
use App\Repositories\AdminDashboard\ManajemenData\KinerjaEkonomiManagement\KinerjaEkonomiManagementRepository as AdminKinerjaEkonomiManagementRepository;
use App\Repositories\AdminDashboard\ManajemenData\KinerjaEkonomiManagement\KinerjaEkonomiManagementRepositoryInterface as AdminKinerjaEkonomiManagementRepositoryInterface;
use App\Repositories\AdminDashboard\RoleManagement\RoleManagementRepository;
use App\Repositories\AdminDashboard\RoleManagement\RoleManagementRepositoryInterface;
use App\Repositories\AdminDashboard\SidePageViewManagement\SidePageViewManagementRepository;
use App\Repositories\AdminDashboard\SidePageViewManagement\SidePageViewManagementRepositoryInterface;
use App\Repositories\AdminDashboard\TutorialPlaylistManagement\TutorialPlaylistManagementRepository;
use App\Repositories\AdminDashboard\TutorialPlaylistManagement\TutorialPlaylistManagementRepositoryInterface;
use App\Repositories\AdminDashboard\UserManagement\UserManagementRepository;
use App\Repositories\AdminDashboard\UserManagement\UserManagementRepositoryInterface;
use App\Repositories\Analisis\AnalisisRCACMSA\AnalisisRCACMSARepository;
use App\Repositories\Analisis\AnalisisRCACMSA\AnalisisRCACMSARepositoryInterface;
use App\Repositories\Analisis\EksporUtama\EksporUtamaRepository;
use App\Repositories\Analisis\EksporUtama\EksporUtamaRepositoryInterface;
use App\Repositories\Analisis\GeopolitikPerdagangan\GeopolitikPerdaganganRepository;
use App\Repositories\Analisis\GeopolitikPerdagangan\GeopolitikPerdaganganRepositoryInterface;
use App\Repositories\Analisis\OperationalRisk\OperationalRiskRepository;
use App\Repositories\Analisis\OperationalRisk\OperationalRiskRepositoryInterface;
use App\Repositories\ChatBot\ChatBotRepository;
use App\Repositories\ChatBot\ChatBotRepositoryInterface;
use App\Repositories\DataGenerator\Investasi\InvestasiRepository;
use App\Repositories\DataGenerator\Investasi\InvestasiRepositoryInterface;
use App\Repositories\DataGenerator\Jasa\JasaRepository;
use App\Repositories\DataGenerator\Jasa\JasaRepositoryInterface;
use App\Repositories\DataGenerator\KinerjaEkonomi\KinerjaEkonomiRepository as DataGenKinerjaEkonomiRepository;
use App\Repositories\DataGenerator\KinerjaEkonomi\KinerjaEkonomiRepositoryInterface as DataGenKinerjaEkonomiRepositoryInterface;
use App\Repositories\DataGenerator\Pariwisata\PariwisataRepository;
use App\Repositories\DataGenerator\Pariwisata\PariwisataRepositoryInterface;
use App\Repositories\DataGenerator\Perdagangan\PerdaganganRepository;
use App\Repositories\DataGenerator\Perdagangan\PerdaganganRepositoryInterface;
use App\Repositories\Indonesia\EconomyDiplomation\NilaiBantuanKerjasamaRepository;
use App\Repositories\Indonesia\EconomyDiplomation\NilaiBantuanKerjasamaRepositoryInterface;
use App\Repositories\Indonesia\EconomyDiplomation\NilaiInvestasiRepository;
use App\Repositories\Indonesia\EconomyDiplomation\NilaiInvestasiRepositoryInterface;
use App\Repositories\Indonesia\EconomyDiplomation\NilaiJasaRepository;
use App\Repositories\Indonesia\EconomyDiplomation\NilaiJasaRepositoryInterface;
use App\Repositories\Indonesia\EconomyDiplomation\NilaiPerdaganganRepository;
use App\Repositories\Indonesia\EconomyDiplomation\NilaiPerdaganganRepositoryInterface;
use App\Repositories\Indonesia\EconomyDiplomation\InsightKompetitorPerdaganganRepository;
use App\Repositories\Indonesia\EconomyDiplomation\InsightKompetitorPerdaganganRepositoryInterface;
use App\Repositories\Indonesia\EconomyDiplomation\NilaiWisatawanRepository;
use App\Repositories\Indonesia\EconomyDiplomation\NilaiWisatawanRepositoryInterface;
use App\Repositories\Indonesia\EconomyDiplomation\StatCardRepository;
use App\Repositories\Indonesia\EconomyDiplomation\StatCardRepositoryInterface;
use App\Repositories\Indonesia\Infrastruktur\InfrastrukturRepository;
use App\Repositories\Indonesia\Infrastruktur\InfrastrukturRepositoryInterface;
use App\Repositories\Indonesia\Infrastruktur\PameranInfrastrukturRepository;
use App\Repositories\Indonesia\Infrastruktur\PameranInfrastrukturRepositoryInterface;
use App\Repositories\Indonesia\Infrastruktur\PerjanjianInfrastrukturRepository;
use App\Repositories\Indonesia\Infrastruktur\PerjanjianInfrastrukturRepositoryInterface;
use App\Repositories\Indonesia\KinerjaEkonomi\KinerjaEkonomiRepository;
use App\Repositories\Indonesia\KinerjaEkonomi\KinerjaEkonomiRepositoryInterface;
use App\Repositories\NegaraMitra\Investasi\InvestmentRepository;
use App\Repositories\NegaraMitra\Investasi\InvestmentRepositoryInterface;
use App\Repositories\NegaraMitra\Jasa\ServiceRepository;
use App\Repositories\NegaraMitra\Jasa\ServiceRepositoryInterface;
use App\Repositories\NegaraMitra\Overview\OverviewRepository;
use App\Repositories\NegaraMitra\Overview\OverviewRepositoryInterface;
use App\Repositories\NegaraMitra\Overview\TopInvestasiRepository;
use App\Repositories\NegaraMitra\Overview\TopInvestasiRepositoryInterface;
use App\Repositories\NegaraMitra\Overview\TopJasaRepository;
use App\Repositories\NegaraMitra\Overview\TopJasaRepositoryInterface;
use App\Repositories\NegaraMitra\Overview\TopPariwisataRepository;
use App\Repositories\NegaraMitra\Overview\TopPariwisataRepositoryInterface;
use App\Repositories\NegaraMitra\Overview\TopPerdaganganRepository;
use App\Repositories\NegaraMitra\Overview\TopPerdaganganRepositoryInterface;
use App\Repositories\NegaraMitra\Pariwisata\TourismRepository;
use App\Repositories\NegaraMitra\Pariwisata\TourismRepositoryInterface;
use App\Repositories\NegaraMitra\Perdagangan\TradeRepository;
use App\Repositories\NegaraMitra\Perdagangan\TradeRepositoryInterface;
use App\Repositories\ReportGenerator\KerjasamaPerdagangan\KerjasamaPerdaganganRepository;
use App\Repositories\ReportGenerator\KerjasamaPerdagangan\KerjasamaPerdaganganRepositoryInterface;
use App\Repositories\ReportGenerator\MarketShare\MarketShareRepositoryInterface;
use App\Repositories\ReportGenerator\MarketShare\MarketShareRepository;
use App\Repositories\ReportGenerator\RCACMSA\RCACMSARepository;
use App\Repositories\ReportGenerator\RCACMSA\RCACMSARepositoryInterface;
use App\Repositories\SektorJasa\DataPMI\DataPMIRepository;
use App\Repositories\SektorJasa\DataPMI\DataPMIRepositoryInterface;
use App\Repositories\SektorJasa\DemografiPMI\DemografiPMIRepository;
use App\Repositories\SektorJasa\DemografiPMI\DemografiPMIRepositoryInterface;
use App\Repositories\SektorJasa\InsightPasarPMI\InsightPasarPMIRepository;
use App\Repositories\SektorJasa\InsightPasarPMI\InsightPasarPMIRepositoryInterface;
use App\Repositories\SektorJasa\Overview\OverviewRepository as OverviewSektorJasaRepository;
use App\Repositories\SektorJasa\Overview\OverviewRepositoryInterface as OverviewSektorJasaRepositoryInterface;
use App\Repositories\SektorPrioritas\EkonomiDigital\NilaiEcommerceRepository;
use App\Repositories\SektorPrioritas\EkonomiDigital\NilaiEcommerceRepositoryInterface;
use App\Repositories\SektorPrioritas\EkonomiDigital\NilaiInfrastrukturRepository;
use App\Repositories\SektorPrioritas\EkonomiDigital\NilaiInfrastrukturRepositoryInterface;
use App\Repositories\SektorPrioritas\EkonomiDigital\TIKRepository;
use App\Repositories\SektorPrioritas\EkonomiDigital\TIKRepositoryInterface;
use App\Repositories\SektorPrioritas\Hilirisasi\NilaiPerdaganganHilirisasiRepository;
use App\Repositories\SektorPrioritas\Hilirisasi\NilaiPerdaganganHilirisasiRepositoryInterface;
use App\Repositories\SektorPrioritas\MineralKritis\MineralKritisRepository;
use App\Repositories\SektorPrioritas\MineralKritis\MineralKritisRepositoryInterface;
use App\Repositories\SektorPrioritas\Pangan\NilaiPanganRepositoryRepository;
use App\Repositories\SektorPrioritas\Pangan\NilaiPanganRepositoryRepositoryInterface;
use App\Repositories\SektorPrioritas\Pertahanan\NilaiPerdaganganPertahananRepository;
use App\Repositories\SektorPrioritas\Pertahanan\NilaiPerdaganganPertahananRepositoryInterface;
use App\Repositories\SektorPrioritas\SektorPrioritasRepository;
use App\Repositories\SektorPrioritas\SektorPrioritasRepositoryInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Rappasoft\LaravelAuthenticationLog\Models\AuthenticationLog;
use App\Repositories\Analisis\AnalisisRSCATBI\AnalisisRSCATBIRepository;
use App\Repositories\Analisis\AnalisisRSCATBI\AnalisisRSCATBIRepositoryInterface;
use App\Repositories\Analisis\AnalisisRCAEPD\AnalisisRCAEPDRepository;
use App\Repositories\Analisis\AnalisisRCAEPD\AnalisisRCAEPDRepositoryInterface;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    $this->app->bind(ApiClientManagementRepositoryInterface::class, ApiClientManagementRepository::class);
    $this->app->bind(AuthenticationLogManagementRepositoryInterface::class, AuthenticationLogManagementRepository::class);
    $this->app->bind(CacheManagementRepositoryInterface::class, CacheManagementRepository::class);
    $this->app->bind(ContactManagementRepositoryInterface::class, ContactManagementRepository::class);
    $this->app->bind(FaqManagementRepositoryInterface::class, FaqManagementRepository::class);
    $this->app->bind(AdminKinerjaEkonomiManagementRepositoryInterface::class, AdminKinerjaEkonomiManagementRepository::class);
    $this->app->bind(PermissionManagementRepositoryInterface::class, PermissionManagementRepository::class);
    $this->app->bind(RoleManagementRepositoryInterface::class, RoleManagementRepository::class);
    $this->app->bind(SidePageViewManagementRepositoryInterface::class, SidePageViewManagementRepository::class);
    $this->app->bind(TutorialPlaylistManagementRepositoryInterface::class, TutorialPlaylistManagementRepository::class);
    $this->app->bind(UserManagementRepositoryInterface::class, UserManagementRepository::class);
    $this->app->bind(PerdaganganRepositoryInterface::class, PerdaganganRepository::class);
    $this->app->bind(PariwisataRepositoryInterface::class, PariwisataRepository::class);
    $this->app->bind(InvestasiRepositoryInterface::class, InvestasiRepository::class);
    $this->app->bind(JasaRepositoryInterface::class, JasaRepository::class);
    $this->app->bind(DataGenKinerjaEkonomiRepositoryInterface::class, DataGenKinerjaEkonomiRepository::class);
    $this->app->bind(ChatBotRepositoryInterface::class, ChatBotRepository::class);
    $this->app->bind(RCACMSARepositoryInterface::class, RCACMSARepository::class);
    $this->app->bind(MarketShareRepositoryInterface::class, MarketShareRepository::class);
    $this->app->bind(KerjasamaPerdaganganRepositoryInterface::class, KerjasamaPerdaganganRepository::class);

    $this->app->bind(OverviewRepositoryInterface::class, OverviewRepository::class);
    $this->app->bind(TopPerdaganganRepositoryInterface::class, TopPerdaganganRepository::class);
    $this->app->bind(TopInvestasiRepositoryInterface::class, TopInvestasiRepository::class);
    $this->app->bind(TopPariwisataRepositoryInterface::class, TopPariwisataRepository::class);
    $this->app->bind(TopJasaRepositoryInterface::class, TopJasaRepository::class);
    $this->app->bind(TradeRepositoryInterface::class, TradeRepository::class);
    $this->app->bind(InvestmentRepositoryInterface::class, InvestmentRepository::class);
    $this->app->bind(TourismRepositoryInterface::class, TourismRepository::class);
    $this->app->bind(ServiceRepositoryInterface::class, ServiceRepository::class);

    $this->app->bind(StatCardRepositoryInterface::class, StatCardRepository::class);
    $this->app->bind(NilaiPerdaganganRepositoryInterface::class, NilaiPerdaganganRepository::class);
    $this->app->bind(InsightKompetitorPerdaganganRepositoryInterface::class, InsightKompetitorPerdaganganRepository::class);
    $this->app->bind(NilaiInvestasiRepositoryInterface::class, NilaiInvestasiRepository::class);
    $this->app->bind(NilaiWisatawanRepositoryInterface::class, NilaiWisatawanRepository::class);
    $this->app->bind(NilaiBantuanKerjasamaRepositoryInterface::class, NilaiBantuanKerjasamaRepository::class);
    $this->app->bind(NilaiJasaRepositoryInterface::class, NilaiJasaRepository::class);
    $this->app->bind(InfrastrukturRepositoryInterface::class, InfrastrukturRepository::class);
    $this->app->bind(PameranInfrastrukturRepositoryInterface::class, PameranInfrastrukturRepository::class);
    $this->app->bind(PerjanjianInfrastrukturRepositoryInterface::class, PerjanjianInfrastrukturRepository::class);
    $this->app->bind(KinerjaEkonomiRepositoryInterface::class, KinerjaEkonomiRepository::class);

    $this->app->bind(EksporUtamaRepositoryInterface::class, EksporUtamaRepository::class);
    $this->app->bind(OperationalRiskRepositoryInterface::class, OperationalRiskRepository::class);
    $this->app->bind(AnalisisRCACMSARepositoryInterface::class, AnalisisRCACMSARepository::class);
    $this->app->bind(GeopolitikPerdaganganRepositoryInterface::class, GeopolitikPerdaganganRepository::class);

    $this->app->bind(NilaiEcommerceRepositoryInterface::class, NilaiEcommerceRepository::class);
    $this->app->bind(NilaiInfrastrukturRepositoryInterface::class, NilaiInfrastrukturRepository::class);
    $this->app->bind(NilaiPerdaganganHilirisasiRepositoryInterface::class, NilaiPerdaganganHilirisasiRepository::class);
    $this->app->bind(NilaiPerdaganganPertahananRepositoryInterface::class, NilaiPerdaganganPertahananRepository::class);
    $this->app->bind(NilaiPanganRepositoryRepositoryInterface::class, NilaiPanganRepositoryRepository::class);
    $this->app->bind(SektorPrioritasRepositoryInterface::class, SektorPrioritasRepository::class);
    $this->app->bind(MineralKritisRepositoryInterface::class, MineralKritisRepository::class);
    $this->app->bind(TIKRepositoryInterface::class, TIKRepository::class);

    $this->app->bind(OverviewSektorJasaRepositoryInterface::class, OverviewSektorJasaRepository::class);
    $this->app->bind(DataPMIRepositoryInterface::class, DataPMIRepository::class);
    $this->app->bind(DemografiPMIRepositoryInterface::class, DemografiPMIRepository::class);
    $this->app->bind(InsightPasarPMIRepositoryInterface::class, InsightPasarPMIRepository::class);
    $this->app->bind(AnalisisRSCATBIRepositoryInterface::class,AnalisisRSCATBIRepository::class   );
    $this->app->bind(AnalisisRCAEPDRepositoryInterface::class, AnalisisRCAEPDRepository::class);
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    AuthenticationLog::creating(function (AuthenticationLog $log) {
      if (empty($log->uuid)) {
        $log->uuid = (string) Str::uuid();
      }
    });
  }
}
