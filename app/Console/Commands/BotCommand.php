<?php

namespace App\Console\Commands;

use App\Models\Guild;
use App\Models\Points;
use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\Permissions;
use CharlotteDunois\Yasmin\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the Hogwarts bot.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        $loop = \React\EventLoop\Factory::create();
        $client = new Client([], $loop);

        $client->on('error', function (\Exception $error) {
            $this->handleException($error);
        });

        $client->on('ready', function () use ($client) {
            echo 'Ready: syncing guilds'.PHP_EOL;
            $this->syncGuilds($client->guilds);
            echo 'Ready: guilds synced'.PHP_EOL;
        });

        $client->on('guildCreate', function (\CharlotteDunois\Yasmin\Models\Guild $guild) {
            echo 'guildCreate: syncing guild'.PHP_EOL;
            $this->syncGuild($guild->id, $guild->name);
            echo 'guildCreate: guild synced'.PHP_EOL;
        });

        $client->on('message', function (Message $message) {
            $guild = Guild::where('guild_id', $message->guild->id)->first();
            $content = $message->content;

            if (preg_match('/^!points.*$/', $content)) {
                $points = Points::where('guild_id', $guild->id)->pluck('points', 'house');
                $pointsMessage = sprintf(
                    "Gryffindor: %s\nHufflepuff: %s\nRavenclaw: %s\nSlytherin: %s\n",
                    array_get($points, 'g', 0),
                    array_get($points, 'h', 0),
                    array_get($points, 'r', 0),
                    array_get($points, 's', 0)
                );
                $message->channel->send($pointsMessage);
            } elseif (preg_match('/^!([ghrs]) (add|sub|subtract|set) (\d+)$/', $content, $matches)) {
                $validRoles = $message->member->roles->filter(function (Role $role) {
                    return $role->permissions->has(Permissions::PERMISSIONS['ADMINISTRATOR'])
                        || in_array($role->name, ['Professors', 'Prefects']);
                });

                if ($validRoles->count() === 0) {
                    $message->channel->send('Sorry, you are not permitted to modify house points!');
                    return;
                }

                echo 'Updating points: '.$content.PHP_EOL;
                switch ($matches[2]) {
                    case 'add':
                        $operation = 'points + '.$matches[3];
                        break;
                    case 'sub':
                    case 'subtract':
                        $operation = 'points - '.$matches[3];
                        break;
                    case 'set':
                        $operation = $matches[3];
                        break;
                }

                $points = Points::updateOrCreate(['guild_id' => $guild->id, 'house' => $matches[1]], ['points' => DB::raw($operation)])->fresh();
                $message->channel->send(trans("houses.{$points->house}")." now has {$points->points} points.");
                echo 'Points updated: '.$content.PHP_EOL;
            }
        });

        $client->login(config('services.discord.bot.token'))->then(function () use ($client) {
//            $client->addPeriodicTimer(1, function (Client $client) {
//                /* @var \CharlotteDunois\Yasmin\Models\TextChannel $channel */
//                $channel = $client->channels->get('364507622604275715');
//                $time = Carbon::now()->tz('America/New_York')->format('H:i');
//                $topic = "It is currently {$time} ET.";
//
//                echo "Setting topic to: '{$topic}'.".PHP_EOL;
//                $channel->setTopic($topic)
//                    ->otherwise([self::class, 'handleException'])
//                    ->done();
//            });
        });

        $loop->run();
    }

    /**
     * Handle an exception.
     *
     * @param \Exception|\CharlotteDunois\Yasmin\HTTP\DiscordAPIException $e
     */
    public static function handleException(\Exception $e)
    {
        echo "ERROR: {$e->getMessage()}".PHP_EOL;
        if (property_exists($e, 'path')) {
            echo "PATH: {$e->path}".PHP_EOL;
        }
    }

    /**
     * Sync all guilds.
     *
     * @param \CharlotteDunois\Yasmin\Models\GuildStorage $guilds
     * @throws \Exception
     */
    protected function syncGuilds($guilds)
    {
        $guilds = $guilds->pluck('name', 'id');
        foreach ($guilds as $id => $name) {
            $this->syncGuild($id, $name);
        }
    }

    /**
     * Sync a guild.
     *
     * @param $id
     * @param $name
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function syncGuild($id, $name)
    {
        return Guild::withTrashed()->updateOrCreate(['guild_id' => $id], ['name' => $name, 'deleted_at' => null]);
    }
}
