<?php

namespace Database\Seeders;

use App\Models\TutorialPlaylist;
use Illuminate\Database\Seeder;

class TutorialPlaylistSeeder extends Seeder
{
    public function run(): void
    {
        $playlists = [
            [
                'id' => '13777aec-ea0b-11f0-8e5a-00090faa0001',
                'title' => 'Overview Tutorial - Sistem Informasi Diplomasi Ekonomi',
                'slug' => 'overview-tutorial-sistem-informasi-diplomasi-ekonomi',
                'desc' => 'Panduan ringkas dan terstruktur yang menjelaskan fitur fitur utama pada Sistem Informasi Diplomasi Ekonomi.',
                'url' => 'https://www.youtube.com/embed/YApKEfORDiY?si=OEvZeD3hT0bM4X4R',
                'thumbnail' => 'tutorial/overview-tutorial-sistem-informasi-diplomasi-ekonomi.png',
            ],
            [
                'id' => '6e35c74f-45ba-4093-a4de-a5e962a44d65',
                'title' => 'Modul Beranda - Sistem Informasi Diplomasi Ekonomi',
                'slug' => 'modul-beranda-sistem-informasi-diplomasi-ekonomi',
                'desc' => 'Panduan lengkap mengenai halaman utama dari Sistem Informasi Diplomasi Ekonomi.',
                'url' => 'https://www.youtube.com/embed/3E-Ou6TM_aw?si=yBvEO0L2vJjXQ2dX',
                'thumbnail' => 'tutorial/overview-tutorial-sistem-informasi-diplomasi-ekonomi.png',
            ],
            [
                'id' => '987d4294-bf23-4c71-b527-dea24203b682',
                'title' => 'Modul Indonesia - Sistem Informasi Diplomasi Ekonomi',
                'slug' => 'modul-indonesia-sistem-informasi-diplomasi-ekonomi',
                'desc' => 'Panduan lengkap mengenai fitur fitur yang tersedia pada Modul Indonesia.',
                'url' => 'https://www.youtube.com/embed/Dfud1MwAduk?si=ywl42kD2m6a9mI5D',
                'thumbnail' => 'tutorial/overview-tutorial-sistem-informasi-diplomasi-ekonomi.png',
            ],
            [
                'id' => '987d4294-bf23-4c71-b527-dea24203b683',
                'title' => 'Modul Mitra - Sistem Informasi Diplomasi Ekonomi',
                'slug' => 'modul-mitra-sistem-informasi-diplomasi-ekonomi',
                'desc' => 'Panduan lengkap mengenai fitur fitur yang tersedia pada Modul Mitra.',
                'url' => 'https://www.youtube.com/embed/q3sIZOcMrSQ?si=ivpJA5x8w2l0mM6x',
                'thumbnail' => 'tutorial/overview-tutorial-sistem-informasi-diplomasi-ekonomi.png',
            ],
            [
                'id' => '987d4294-bf23-4c71-b527-dea24203b684',
                'title' => 'Modul Sektor Prioritas - Sistem Informasi Diplomasi Ekonomi',
                'slug' => 'modul-sektor-prioritas-sistem-informasi-diplomasi-ekonomi',
                'desc' => 'Panduan lengkap mengenai fitur fitur yang tersedia pada Modul Sektor Prioritas.',
                'url' => 'https://www.youtube.com/embed/stpN6HUWlE4?si=zug1UkS9mK3fM4Qe',
                'thumbnail' => 'tutorial/overview-tutorial-sistem-informasi-diplomasi-ekonomi.png',
            ],
            [
                'id' => '987d4294-bf23-4c71-b527-dea24203b685',
                'title' => 'Modul Analisis - Sistem Informasi Diplomasi Ekonomi',
                'slug' => 'modul-analisis-sistem-informasi-diplomasi-ekonomi',
                'desc' => 'Panduan lengkap mengenai fitur fitur yang tersedia pada Modul Analisis.',
                'url' => 'https://www.youtube.com/embed/Jn6W7gKIuCQ?si=4H8i1N8z0gQ3nQ6V',
                'thumbnail' => 'tutorial/overview-tutorial-sistem-informasi-diplomasi-ekonomi.png',
            ],
            [
                'id' => '987d4294-bf23-4c71-b527-dea24203b689',
                'title' => 'Modul Databank - Sistem Informasi Diplomasi Ekonomi',
                'slug' => 'modul-databank-sistem-informasi-diplomasi-ekonomi',
                'desc' => 'Panduan lengkap mengenai fitur fitur yang tersedia pada Modul Databank.',
                'url' => 'https://www.youtube.com/embed/oPbhqS521V4?si=LQZWRc2nK6bV9p8F',
                'thumbnail' => 'tutorial/overview-tutorial-sistem-informasi-diplomasi-ekonomi.png',
            ],
        ];

        foreach ($playlists as $playlist) {
            TutorialPlaylist::query()->updateOrCreate(
                ['id' => $playlist['id']],
                $playlist
            );
        }
    }
}
