<?php

namespace App\Services\Indonesia;

use App\Repositories\Indonesia\EconomyDiplomation\NilaiBantuanKerjasamaRepositoryInterface;
use App\Repositories\Indonesia\EconomyDiplomation\NilaiInvestasiRepositoryInterface;
use App\Repositories\Indonesia\EconomyDiplomation\NilaiJasaRepositoryInterface;
use App\Repositories\Indonesia\EconomyDiplomation\NilaiPerdaganganRepositoryInterface;
use App\Repositories\Indonesia\EconomyDiplomation\NilaiWisatawanRepositoryInterface;
use App\Repositories\Indonesia\EconomyDiplomation\StatCardRepositoryInterface;
use Illuminate\Support\Facades\Log;

class EconomyDiplomationService
{
  protected StatCardRepositoryInterface $statCardRepository;
  protected NilaiPerdaganganRepositoryInterface $repositoryNilaiPerdagangan;
  protected NilaiInvestasiRepositoryInterface $repositoryNilaiInvestasi;
  protected NilaiWisatawanRepositoryInterface $repositoryNilaiWisatawan;
  protected NilaiBantuanKerjasamaRepositoryInterface $repositoryNilaiBantuanKerjasama;
  protected NilaiJasaRepositoryInterface $repositoryNilaiJasa;

  public function __construct(
    StatCardRepositoryInterface $statCardRepository,
    NilaiPerdaganganRepositoryInterface $repositoryNilaiPerdagangan,
    NilaiInvestasiRepositoryInterface $repositoryNilaiInvestasi,
    NilaiWisatawanRepositoryInterface $repositoryNilaiWisatawan,
    NilaiBantuanKerjasamaRepositoryInterface $repositoryNilaiBantuanKerjasama,
    NilaiJasaRepositoryInterface $repositoryNilaiJasa,

  ) {
    $this->statCardRepository = $statCardRepository;
    $this->repositoryNilaiPerdagangan = $repositoryNilaiPerdagangan;
    $this->repositoryNilaiInvestasi = $repositoryNilaiInvestasi;
    $this->repositoryNilaiWisatawan = $repositoryNilaiWisatawan;
    $this->repositoryNilaiBantuanKerjasama = $repositoryNilaiBantuanKerjasama;
    $this->repositoryNilaiJasa = $repositoryNilaiJasa;
  }

  public function getComputeStats(array $filters, array $sources = []): array
  {
    return $this->statCardRepository->computeStats($filters, $sources);
  }

  public function getNilaiPerdagangan(array $filters, ?int $kodeSumber = null): array
  {
    return $this->repositoryNilaiPerdagangan->nilaiPerdagangan($filters, $kodeSumber);
  }

  public function getNilaiPerdaganganTopProduk(array $filters, int $kodeSumber = 5, int $limit = 50): array
  {
    return $this->repositoryNilaiPerdagangan->topProduk($filters, $kodeSumber, $limit);
  }

  public function getNilaiInvestasi(array $filters, ?int $kodeSumber = null): array
  {
    return $this->repositoryNilaiInvestasi->nilaiInvestasi($filters, $kodeSumber);
  }

  public function getNilaiWisatawan(array $filters, ?int $kodeSumber = null): array
  {
    return $this->repositoryNilaiWisatawan->nilaiWisatawan($filters, $kodeSumber);
  }

  public function getNilaiBantuanKerjasama(array $filters, ?int $kodeSumber = null): array
  {
    return $this->repositoryNilaiBantuanKerjasama->nilaiBantuanKerjasama($filters, $kodeSumber);
  }

  public function getNilaiJasa(array $filters, ?int $kodeSumber = null): array
  {
    return $this->repositoryNilaiJasa->nilaiJasa($filters, $kodeSumber);
  }
}
