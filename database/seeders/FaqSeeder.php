<?php

namespace Database\Seeders;

use App\Models\FaqTopic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $topicHasUuidColumn = Schema::hasColumn('faq_topics', 'uuid');
        $itemHasUuidColumn = Schema::hasColumn('faq_items', 'uuid');

        $topics = [
            [
                'topic' => 'Akun dan Akses',
                'summary' => 'Panduan ringkas untuk masuk dan mengelola akun.',
                'is_featured' => true,
                'order' => 1,
                'items' => [
                    [
                        'question' => 'Bagaimana cara login ke sistem?',
                        'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                        'order' => 1,
                    ],
                    [
                        'question' => 'Apa yang harus dilakukan jika lupa kata sandi?',
                        'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                        'order' => 2,
                    ],
                ],
            ],
            [
                'topic' => 'Data dan Konten',
                'summary' => 'Pertanyaan umum tentang data di dalam platform.',
                'is_featured' => false,
                'order' => 2,
                'items' => [
                    [
                        'question' => 'Seberapa sering data diperbarui?',
                        'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                        'order' => 1,
                    ],
                    [
                        'question' => 'Apakah data bisa diunduh?',
                        'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                        'order' => 2,
                    ],
                ],
            ],
            [
                'topic' => 'Dukungan',
                'summary' => 'Bantuan teknis dan informasi kontak.',
                'is_featured' => true,
                'order' => 3,
                'items' => [
                    [
                        'question' => 'Bagaimana cara menghubungi tim support?',
                        'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                        'order' => 1,
                    ],
                ],
            ],
            [
                'topic' => 'Keamanan',
                'summary' => 'Informasi singkat terkait keamanan akun dan data.',
                'is_featured' => false,
                'order' => 4,
                'items' => [
                    [
                        'question' => 'Apakah data saya aman?',
                        'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                        'order' => 1,
                    ],
                    [
                        'question' => 'Bagaimana kebijakan privasi diterapkan?',
                        'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                        'order' => 2,
                    ],
                ],
            ],
            [
                'topic' => 'Penggunaan Platform',
                'summary' => 'Panduan dasar menggunakan fitur utama.',
                'is_featured' => true,
                'order' => 5,
                'items' => [
                    [
                        'question' => 'Bagaimana memulai menggunakan platform?',
                        'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                        'order' => 1,
                    ],
                    [
                        'question' => 'Di mana saya bisa menemukan dokumentasi?',
                        'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                        'order' => 2,
                    ],
                ],
            ],
        ];

        foreach ($topics as $topicData) {
            $items = $topicData['items'];
            unset($topicData['items']);

            $topic = FaqTopic::query()->firstOrNew([
                'topic' => $topicData['topic'],
            ]);

            $topic->summary = $topicData['summary'];
            $topic->is_featured = $topicData['is_featured'];
            $topic->order = $topicData['order'];

            if ($topicHasUuidColumn && empty($topic->uuid)) {
                $topic->uuid = (string) Str::uuid();
            }

            $topic->save();

            $existingItemIds = [];

            foreach ($items as $itemData) {
                $item = $topic->items()->firstOrNew([
                    'question' => $itemData['question'],
                ]);

                $item->answer = $itemData['answer'];
                $item->order = $itemData['order'];

                if ($itemHasUuidColumn && empty($item->uuid)) {
                    $item->uuid = (string) Str::uuid();
                }

                $item->save();
                $existingItemIds[] = $item->id;
            }

            $topic->items()
                ->whereNotIn('id', $existingItemIds)
                ->delete();
        }
    }
}
