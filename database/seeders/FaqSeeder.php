<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'How do I track my order?',
                'answer' => 'You can track your order in the "My Orders" section.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'question' => 'How do I contact support?',
                'answer' => 'You can contact us via the support chat or email.',
                'is_active' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($faqs as $faq) {
            \App\Models\Faq::updateOrCreate(['question' => $faq['question']], $faq);
        }
    }
}
