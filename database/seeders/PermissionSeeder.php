<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $hasUuidColumn = Schema::hasColumn('permissions', 'uuid');

        $permissions = [
            ['name' => 'view_diplomasi_ekonomi_indonesia', 'category' => 'Modul Indonesia', 'description' => 'Untuk menampilkan diplomasi ekonomi Indonesia', 'guard_name' => 'web'],
            ['name' => 'view_kerjasama_bilateral_indonesia', 'category' => 'Modul Indonesia', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_kinerja_ekonomi_indonesia', 'category' => 'Modul Indonesia', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_infrastruktur_indonesia', 'category' => 'Modul Indonesia', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_overview_negara_mitra', 'category' => 'Modul Mitra', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_perdagangan_negara_mitra', 'category' => 'Modul Mitra', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_investasi_negara_mitra', 'category' => 'Modul Mitra', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_pariwisata_negara_mitra', 'category' => 'Modul Mitra', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_jasa_negara_mitra', 'category' => 'Modul Mitra', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_produk_komoditas_analisis', 'category' => 'Modul Analisis', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_potensi_daya_saing_analisis', 'category' => 'Modul Analisis', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_ide_analisis', 'category' => 'Modul Analisis', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_operational_risk_analisis', 'category' => 'Modul Analisis', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_arus_tik_prioritas', 'category' => 'Modul Komoditas Utama', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_energi_prioritas', 'category' => 'Modul Komoditas Utama', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_mineral_kritis_prioritas', 'category' => 'Modul Komoditas Utama', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_farmasi_prioritas', 'category' => 'Modul Komoditas Utama', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_hilirisasi_prioritas', 'category' => 'Modul Komoditas Utama', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_pangan_prioritas', 'category' => 'Modul Komoditas Utama', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_pertahanan_prioritas', 'category' => 'Modul Komoditas Utama', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_perdagangan_data_generator', 'category' => 'Modul Data Generator', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_turis_data_generator', 'category' => 'Modul Data Generator', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_investasi_data_generator', 'category' => 'Modul Data Generator', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_jasa_data_generator', 'category' => 'Modul Data Generator', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_rca_cmsa_report_generator', 'category' => 'Modul Report Generator', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_market_share_report_generator', 'category' => 'Modul Report Generator', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_kerjasama_perdagangan_report_generator', 'category' => 'Modul Report Generator', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_sektor_parekraf', 'category' => 'Modul Parekraf', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_peta_persebaran_wisman', 'category' => 'Modul Parekraf', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_peta_perjalanan_transportasi', 'category' => 'Modul Parekraf', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_analisis_daya_saing_pariwisata_provinsi', 'category' => 'Modul Parekraf', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_indeks_daya_saing_pariwisata_negara', 'category' => 'Modul Parekraf', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_segmentasi_frekuensi_moneter', 'category' => 'Modul Parekraf', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_sektor_jasa', 'category' => 'Modul Jasa', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_data_pekerja_migran_indonesia', 'category' => 'Modul Jasa', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_insight_pasar_jasa', 'category' => 'Modul Jasa', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_sektor_perdagangan', 'category' => 'Modul Perdagangan', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_sektor_investasi', 'category' => 'Modul Investasi', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_sektor_kspi', 'category' => 'Modul KSPI', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_indikator_ekonomi_data_generator', 'category' => 'Modul Data Generator', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_geopolitik_perdagangan_analisis', 'category' => 'Modul Analisis', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'view_admin_dashboard', 'category' => 'Modul Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'read_admin_roles', 'category' => 'Modul Role Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'create_admin_roles', 'category' => 'Modul Role Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'update_admin_roles', 'category' => 'Modul Role Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'delete_admin_roles', 'category' => 'Modul Role Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'read_admin_permissions', 'category' => 'Modul Permission Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'create_admin_permissions', 'category' => 'Modul Permission Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'update_admin_permissions', 'category' => 'Modul Permission Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'delete_admin_permissions', 'category' => 'Modul Permission Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'read_admin_users', 'category' => 'Modul User Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'create_admin_users', 'category' => 'Modul User Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'update_admin_users', 'category' => 'Modul User Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'delete_admin_users', 'category' => 'Modul User Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'read_admin_faqs', 'category' => 'Modul FAQ Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'create_admin_faqs', 'category' => 'Modul FAQ Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'update_admin_faqs', 'category' => 'Modul FAQ Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'delete_admin_faqs', 'category' => 'Modul FAQ Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'read_admin_contacts', 'category' => 'Modul Contact Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'update_admin_contacts', 'category' => 'Modul Contact Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'delete_admin_contacts', 'category' => 'Modul Contact Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'read_admin_api_clients', 'category' => 'Modul API Client Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'create_admin_api_clients', 'category' => 'Modul API Client Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'update_admin_api_clients', 'category' => 'Modul API Client Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'delete_admin_api_clients', 'category' => 'Modul API Client Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'read_admin_caches', 'category' => 'Modul Cache Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'update_admin_caches', 'category' => 'Modul Cache Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'delete_admin_caches', 'category' => 'Modul Cache Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'read_admin_authentication_logs', 'category' => 'Modul Authentication Log Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'delete_admin_authentication_logs', 'category' => 'Modul Authentication Log Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'read_admin_side_page_views', 'category' => 'Modul Side Page View Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'read_admin_tutorial_playlists', 'category' => 'Modul Tutorial Playlist Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'create_admin_tutorial_playlists', 'category' => 'Modul Tutorial Playlist Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'update_admin_tutorial_playlists', 'category' => 'Modul Tutorial Playlist Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'delete_admin_tutorial_playlists', 'category' => 'Modul Tutorial Playlist Admin', 'description' => null, 'guard_name' => 'web'],
            ['name' => 'read_admin_kinerja_ekonomi', 'category' => 'Modul Kinerja Ekonomi Admin', 'description' => 'Untuk membaca batch staging kinerja ekonomi', 'guard_name' => 'web'],
            ['name' => 'read_admin_kinerja_ekonomi_current', 'category' => 'Modul Kinerja Ekonomi Admin', 'description' => 'Untuk membaca data aktif kinerja ekonomi yang sudah dipublikasi', 'guard_name' => 'web'],
            ['name' => 'read_all_admin_kinerja_ekonomi', 'category' => 'Modul Kinerja Ekonomi Admin', 'description' => 'Untuk membaca semua batch staging kinerja ekonomi tanpa dibatasi pengunggah', 'guard_name' => 'web'],
            ['name' => 'create_admin_kinerja_ekonomi', 'category' => 'Modul Kinerja Ekonomi Admin', 'description' => 'Untuk membuat batch staging kinerja ekonomi', 'guard_name' => 'web'],
            ['name' => 'update_admin_kinerja_ekonomi', 'category' => 'Modul Kinerja Ekonomi Admin', 'description' => 'Untuk mengubah dan memvalidasi batch staging kinerja ekonomi', 'guard_name' => 'web'],
            ['name' => 'approve_admin_kinerja_ekonomi', 'category' => 'Modul Kinerja Ekonomi Admin', 'description' => 'Untuk approve/reject batch staging kinerja ekonomi', 'guard_name' => 'web'],
            ['name' => 'publish_admin_kinerja_ekonomi', 'category' => 'Modul Kinerja Ekonomi Admin', 'description' => 'Untuk publish batch staging ke tabel utama kinerja ekonomi', 'guard_name' => 'web'],
            ['name' => 'delete_admin_kinerja_ekonomi', 'category' => 'Modul Kinerja Ekonomi Admin', 'description' => 'Untuk menghapus batch staging kinerja ekonomi yang belum publish', 'guard_name' => 'web'],
        ];

        foreach ($permissions as $data) {
            $permission = Permission::query()->firstOrNew([
                'name' => $data['name'],
                'guard_name' => $data['guard_name'],
            ]);

            $permission->category = $data['category'];
            $permission->description = $data['description'];

            if ($hasUuidColumn && empty($permission->uuid)) {
                $permission->uuid = (string) Str::uuid();
            }

            $permission->save();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
