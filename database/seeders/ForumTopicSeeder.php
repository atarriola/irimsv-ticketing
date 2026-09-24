<?php

namespace Database\Seeders;

use App\Models\ForumTopic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ForumTopicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $topics = [
            'General Discussion' => 'Talk about anything related to the system.',
            'Help & How-to' => 'Ask how to do something and share what worked for you.',
            'Concerns & Feedback' => 'Raise concerns and tell us what could be better.',
            'Ideas' => 'Discuss ideas before turning them into feature requests.',
        ];

        $position = 0;

        foreach ($topics as $name => $description) {
            ForumTopic::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $description, 'position' => $position++],
            );
        }
    }
}
