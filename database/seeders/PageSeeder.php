<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            [
                'title' => 'Privacy Policy',
                'content' => '<h1>Privacy Policy</h1><p>This is the privacy policy for Zimmz.</p>',
            ],
            [
                'title' => 'Terms & Conditions',
                'content' => '<h1>Terms & Conditions</h1><p>These are the terms and conditions for Zimmz.</p>',
            ],
            [
                'title' => 'About Us',
                'content' => '<h1>About Us</h1><p>Zimmz is your delivery partner.</p>',
            ],
        ];

        foreach ($pages as $page) {
            \App\Models\Page::updateOrCreate(['title' => $page['title']], $page);
        }
    }
}
