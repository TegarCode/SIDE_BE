<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $hasUuidColumn = Schema::hasColumn('roles', 'uuid');

        $roles = [
            ['name' => 'visitor', 'slug' => 'visitor', 'description' => null, 'status' => 'active', 'guard_name' => 'web'],
            ['name' => 'admin', 'slug' => 'admin', 'description' => null, 'status' => 'active', 'guard_name' => 'web'],
            ['name' => 'user', 'slug' => 'user', 'description' => null, 'status' => 'active', 'guard_name' => 'web'],
            ['name' => 'visitor_pertamina', 'slug' => 'visitor_pertamina', 'description' => null, 'status' => 'active', 'guard_name' => 'web'],
            ['name' => 'super_admin', 'slug' => 'super_admin', 'description' => null, 'status' => 'active', 'guard_name' => 'web'],
            ['name' => 'Testsos', 'slug' => 'testsos', 'description' => 'Testingsos', 'status' => 'active', 'guard_name' => 'web'],
        ];

        $rolePermissions = [
            'admin' => [
                'view_analisis_daya_saing_pariwisata_provinsi',
                'view_arus_tik_prioritas',
                'view_data_pekerja_migran_indonesia',
                'view_diplomasi_ekonomi_indonesia',
                'view_energi_prioritas',
                'view_farmasi_prioritas',
                'view_geopolitik_perdagangan_analisis',
                'view_hilirisasi_prioritas',
                'view_ide_analisis',
                'view_indeks_daya_saing_pariwisata_negara',
                'view_indikator_ekonomi_data_generator',
                'view_infrastruktur_indonesia',
                'view_insight_pasar_jasa',
                'view_investasi_data_generator',
                'view_investasi_negara_mitra',
                'view_jasa_data_generator',
                'view_jasa_negara_mitra',
                'view_kerjasama_bilateral_indonesia',
                'view_kerjasama_perdagangan_report_generator',
                'view_kinerja_ekonomi_indonesia',
                'view_market_share_report_generator',
                'view_mineral_kritis_prioritas',
                'view_operational_risk_analisis',
                'view_overview_negara_mitra',
                'view_pangan_prioritas',
                'view_pariwisata_negara_mitra',
                'view_perdagangan_data_generator',
                'view_perdagangan_negara_mitra',
                'view_pertahanan_prioritas',
                'view_peta_perjalanan_transportasi',
                'view_peta_persebaran_wisman',
                'view_potensi_daya_saing_analisis',
                'view_produk_komoditas_analisis',
                'view_rca_cmsa_report_generator',
                'view_segmentasi_frekuensi_moneter',
                'view_sektor_investasi',
                'view_sektor_jasa',
                'view_sektor_kspi',
                'view_sektor_parekraf',
                'view_sektor_perdagangan',
                'view_turis_data_generator',
            ],
            'super_admin' => [
                'create_admin_permissions',
                'create_admin_users',
                'create_admin_faqs',
                'create_admin_roles',
                'create_admin_api_clients',
                'delete_admin_permissions',
                'delete_admin_api_clients',
                'delete_admin_authentication_logs',
                'delete_admin_caches',
                'delete_admin_contacts',
                'delete_admin_users',
                'delete_admin_faqs',
                'delete_admin_roles',
                'delete_admin_tutorial_playlists',
                'read_admin_api_clients',
                'read_admin_authentication_logs',
                'read_admin_caches',
                'read_admin_contacts',
                'read_admin_permissions',
                'read_admin_side_page_views',
                'read_admin_tutorial_playlists',
                'read_admin_users',
                'read_admin_kinerja_ekonomi_current',
                'read_all_admin_kinerja_ekonomi',
                'read_admin_faqs',
                'read_admin_roles',
                'update_admin_api_clients',
                'update_admin_caches',
                'update_admin_contacts',
                'update_admin_permissions',
                'update_admin_tutorial_playlists',
                'update_admin_users',
                'update_admin_faqs',
                'update_admin_roles',
                'create_admin_tutorial_playlists',
                'view_admin_dashboard',
                'view_analisis_daya_saing_pariwisata_provinsi',
                'view_arus_tik_prioritas',
                'view_data_pekerja_migran_indonesia',
                'view_diplomasi_ekonomi_indonesia',
                'view_energi_prioritas',
                'view_farmasi_prioritas',
                'view_geopolitik_perdagangan_analisis',
                'view_hilirisasi_prioritas',
                'view_ide_analisis',
                'view_indeks_daya_saing_pariwisata_negara',
                'view_indikator_ekonomi_data_generator',
                'view_infrastruktur_indonesia',
                'view_insight_pasar_jasa',
                'view_investasi_data_generator',
                'view_investasi_negara_mitra',
                'view_jasa_data_generator',
                'view_jasa_negara_mitra',
                'view_kerjasama_bilateral_indonesia',
                'view_kerjasama_perdagangan_report_generator',
                'view_kinerja_ekonomi_indonesia',
                'view_market_share_report_generator',
                'view_mineral_kritis_prioritas',
                'view_operational_risk_analisis',
                'view_overview_negara_mitra',
                'view_pangan_prioritas',
                'view_pariwisata_negara_mitra',
                'view_perdagangan_data_generator',
                'view_perdagangan_negara_mitra',
                'view_pertahanan_prioritas',
                'view_peta_perjalanan_transportasi',
                'view_peta_persebaran_wisman',
                'view_potensi_daya_saing_analisis',
                'view_produk_komoditas_analisis',
                'view_rca_cmsa_report_generator',
                'view_segmentasi_frekuensi_moneter',
                'view_sektor_investasi',
                'view_sektor_jasa',
                'view_sektor_kspi',
                'view_sektor_parekraf',
                'view_sektor_perdagangan',
                'view_turis_data_generator',
            ],
            'Testsos' => [
                'view_admin_dashboard',
                'view_arus_tik_prioritas',
                'view_energi_prioritas',
                'view_farmasi_prioritas',
                'view_hilirisasi_prioritas',
                'view_mineral_kritis_prioritas',
                'view_pangan_prioritas',
                'view_pertahanan_prioritas',
            ],
            'user' => [
                'view_analisis_daya_saing_pariwisata_provinsi',
                'view_arus_tik_prioritas',
                'view_data_pekerja_migran_indonesia',
                'view_diplomasi_ekonomi_indonesia',
                'view_energi_prioritas',
                'view_farmasi_prioritas',
                'view_geopolitik_perdagangan_analisis',
                'view_hilirisasi_prioritas',
                'view_ide_analisis',
                'view_indeks_daya_saing_pariwisata_negara',
                'view_infrastruktur_indonesia',
                'view_insight_pasar_jasa',
                'view_investasi_data_generator',
                'view_investasi_negara_mitra',
                'view_jasa_data_generator',
                'view_jasa_negara_mitra',
                'view_kerjasama_bilateral_indonesia',
                'view_kerjasama_perdagangan_report_generator',
                'view_kinerja_ekonomi_indonesia',
                'view_market_share_report_generator',
                'view_mineral_kritis_prioritas',
                'view_operational_risk_analisis',
                'view_overview_negara_mitra',
                'view_pangan_prioritas',
                'view_pariwisata_negara_mitra',
                'view_perdagangan_data_generator',
                'view_perdagangan_negara_mitra',
                'view_pertahanan_prioritas',
                'view_peta_perjalanan_transportasi',
                'view_peta_persebaran_wisman',
                'view_potensi_daya_saing_analisis',
                'view_produk_komoditas_analisis',
                'view_rca_cmsa_report_generator',
                'view_segmentasi_frekuensi_moneter',
                'view_sektor_investasi',
                'view_sektor_jasa',
                'view_sektor_kspi',
                'view_sektor_parekraf',
                'view_sektor_perdagangan',
                'view_turis_data_generator',
            ],
            'visitor' => [
                'view_geopolitik_perdagangan_analisis',
            ],
            'visitor_pertamina' => [
                'view_analisis_daya_saing_pariwisata_provinsi',
                'view_arus_tik_prioritas',
                'view_data_pekerja_migran_indonesia',
                'view_diplomasi_ekonomi_indonesia',
                'view_energi_prioritas',
                'view_farmasi_prioritas',
                'view_geopolitik_perdagangan_analisis',
                'view_hilirisasi_prioritas',
                'view_ide_analisis',
                'view_indeks_daya_saing_pariwisata_negara',
                'view_infrastruktur_indonesia',
                'view_insight_pasar_jasa',
                'view_investasi_negara_mitra',
                'view_jasa_negara_mitra',
                'view_kerjasama_bilateral_indonesia',
                'view_kerjasama_perdagangan_report_generator',
                'view_kinerja_ekonomi_indonesia',
                'view_market_share_report_generator',
                'view_mineral_kritis_prioritas',
                'view_operational_risk_analisis',
                'view_overview_negara_mitra',
                'view_pangan_prioritas',
                'view_pariwisata_negara_mitra',
                'view_perdagangan_negara_mitra',
                'view_pertahanan_prioritas',
                'view_peta_perjalanan_transportasi',
                'view_peta_persebaran_wisman',
                'view_potensi_daya_saing_analisis',
                'view_produk_komoditas_analisis',
                'view_rca_cmsa_report_generator',
                'view_segmentasi_frekuensi_moneter',
                'view_sektor_jasa',
                'view_sektor_parekraf',
            ],
        ];

        foreach ($roles as $data) {
            $role = Role::query()->firstOrNew([
                'name' => $data['name'],
                'guard_name' => $data['guard_name'],
            ]);

            $role->slug = $data['slug'];
            $role->description = $data['description'];
            $role->status = $data['status'];

            if ($hasUuidColumn && empty($role->uuid)) {
                $role->uuid = (string) Str::uuid();
            }

            $role->save();
        }

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if ($role !== null) {
                $role->syncPermissions($permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
