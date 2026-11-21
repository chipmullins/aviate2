<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\FriendRequest;
use App\Models\Like;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
use App\Services\TrendService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo data so the app isn't empty on first run.
 * Every demo account's password is: aviate-demo-1
 */
class DatabaseSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'aviate-demo-1';

    public function run(TrendService $trends): void
    {
        if (User::where('email', 'maya.chen@delta.com')->exists()) {
            $this->command?->info('Demo data already exists, skipping.');

            return;
        }

        $people = [
            ['Maya', 'Chen', 'maya.chen@delta.com'],
            ['Marcus', 'Webb', 'marcus.webb@united.com'],
            ['Priya', 'Raman', 'priya.raman@aa.com'],
            ['Diego', 'Alvarez', 'diego.alvarez@southwest.com'],
            ['Hannah', 'Brooks', 'hannah.brooks@jetblue.com'],
            ['Tom', 'Okafor', 'tom.okafor@allegiant.com'],
            ['Lena', 'Novak', 'lena.novak@suncountry.com'],
        ];

        $users = collect($people)->map(fn ($p) => User::forceCreate([
            'first_name' => $p[0],
            'last_name' => $p[1],
            'username' => User::generateUsername($p[0], $p[1]),
            'email' => $p[2],
            'password' => self::DEMO_PASSWORD,
            'is_demo' => true,
        ]));

        [$maya, $marcus, $priya, $diego, $hannah, $tom, $lena] = $users->all();

        // Friendships (stored in both directions).
        foreach ([[$maya, $marcus], [$maya, $priya], [$maya, $diego], [$marcus, $priya], [$priya, $hannah], [$diego, $tom], [$hannah, $lena], [$maya, $hannah]] as [$a, $b]) {
            DB::table('friendships')->insert([
                ['user_id' => $a->id, 'friend_id' => $b->id, 'created_at' => now(), 'updated_at' => now()],
                ['user_id' => $b->id, 'friend_id' => $a->id, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
        FriendRequest::create(['sender_id' => $tom->id, 'recipient_id' => $maya->id]);
        FriendRequest::create(['sender_id' => $lena->id, 'recipient_id' => $maya->id]);

        $posts = [
            [$maya, null, 'Four-day trip done: ATL → SEA → ANC → MSP → ATL. Anchorage at sunrise never gets old.', 5],
            [$marcus, null, 'Anyone else stuck in the ORD deicing line tonight? Bring snacks for your crew.', 4],
            [$priya, null, 'New hire class graduated today at DFW! Proud of every one of them. Welcome to the line.', 3],
            [$diego, $maya, 'Great flying with you on the BWI turn yesterday! Next coffee is on me.', 3],
            [$hannah, null, 'Bid results are out. Got the Caribbean pairings I wanted for December.', 2],
            [$tom, null, 'Reminder: hydrate on the long days. Your crew lounge water bottle counts.', 2],
            [$lena, null, 'MSP crew lounge has the best views of the deicing pads. Fight me.', 1],
            [$maya, null, 'Hotel recommendation in Lisbon for layovers? Trying something new this month.', 1],
        ];

        foreach ($posts as [$author, $wall, $body, $daysAgo]) {
            $post = Post::create(['user_id' => $author->id, 'wall_user_id' => $wall?->id, 'body' => $body]);
            $post->forceFill(['created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo)])->save();
            $trends->record($body);
        }

        $first = Post::orderBy('id')->first();
        foreach ([$marcus, $priya, $diego] as $u) {
            Like::create(['post_id' => $first->id, 'user_id' => $u->id]);
        }
        Comment::create(['post_id' => $first->id, 'user_id' => $marcus->id, 'body' => 'That view from the flight deck must have been unreal.']);
        Comment::create(['post_id' => $first->id, 'user_id' => $priya->id, 'body' => 'Jealous! I keep getting stuck with red-eyes.']);

        Message::create(['sender_id' => $marcus->id, 'recipient_id' => $maya->id, 'body' => 'Are you on the Denver layover next Tuesday?']);
        Message::create(['sender_id' => $maya->id, 'recipient_id' => $marcus->id, 'body' => 'Yes! Want to grab dinner?']);
        Message::create(['sender_id' => $marcus->id, 'recipient_id' => $maya->id, 'body' => 'Definitely. I know a great taco spot near the hotel.']);
    }
}
