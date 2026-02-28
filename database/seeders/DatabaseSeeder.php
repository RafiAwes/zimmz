<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Core Data
        $this->call([
            IslandSeeder::class,
            FerrySeeder::class,
            RestaurantSeeder::class,
            PageSeeder::class,
            FaqSeeder::class,
        ]);

        // 2. Specific Users

        // Admin
        User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // Users (5 members)
        for ($i = 1; $i <= 5; $i++) {
            User::factory()->create([
                'name' => "user{$i}",
                'email' => "user{$i}@example.com",
                'password' => bcrypt('password'),
                'role' => 'user',
            ]);
        }

        // Runners (5 members)
        for ($i = 1; $i <= 5; $i++) {
            $runnerUser = User::factory()->create([
                'name' => "runner{$i}",
                'email' => "runner{$i}@example.com",
                'password' => bcrypt('password'),
                'role' => 'runner',
            ]);

            \App\Models\Runner::factory()->create([
                'user_id' => $runnerUser->id,
            ]);
        }

        // 3. Seed some ads and messages
        \App\Models\Ad::factory(5)->create();

        $users = User::where('role', 'user')->get();
        $runners = User::where('role', 'runner')->get();
        $allUsers = User::all();

        foreach ($allUsers as $sender) {
            $receiver = $allUsers->where('id', '!=', $sender->id)->random();
            \App\Models\Message::factory(2)->create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
            ]);
        }

        // 4. Seed some orders and task services
        $restaurants = \App\Models\Restaurant::all();
        $ferries = \App\Models\Ferry::all();
        $islands = \App\Models\Island::all();

        foreach ($users as $user) {
            // Food Delivery Order
            $order1 = \App\Models\Order::factory()->create([
                'user_id' => $user->id,
                'type' => 'food_delivery',
            ]);
            \App\Models\FoodDelivery::factory()->create([
                'order_id' => $order1->id,
                'restaurant_id' => $restaurants->random()->id,
            ]);

            // Ferry Drop Order
            $order2 = \App\Models\Order::factory()->create([
                'user_id' => $user->id,
                'type' => 'ferry_drops',
            ]);
            \App\Models\FerryDrop::factory()->create([
                'order_id' => $order2->id,
                'ferry_id' => $ferries->random()->id,
                'island_id' => $islands->random()->id,
            ]);

            // Task Services
            \App\Models\TaskService::factory()->count(2)->create([
                'user_id' => $user->id,
                'status' => 'new',
            ]);

            // Create some accepted/pending tasks
            \App\Models\TaskService::factory()->create([
                'user_id' => $user->id,
                'runner_id' => $runners->random()->id,
                'status' => 'pending',
            ]);
        }
    }
}
