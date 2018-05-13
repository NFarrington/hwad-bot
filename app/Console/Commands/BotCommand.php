<?php

namespace App\Console\Commands;

use App\Models\Guild;
use App\Models\Member;
use App\Models\Points;
use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Models\GuildMember;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\Permissions;
use CharlotteDunois\Yasmin\Models\Role;
use CharlotteDunois\Yasmin\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
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
            echo 'ready: syncing guilds'.PHP_EOL;
            $this->syncGuilds($client->guilds);
            echo 'ready: guilds synced'.PHP_EOL;
        });

        $client->on('guildCreate', function (\CharlotteDunois\Yasmin\Models\Guild $guild) {
            echo 'guildCreate: syncing guild'.PHP_EOL;
            $this->syncGuild($guild);
            echo 'guildCreate: guild synced'.PHP_EOL;
        });

        $client->on('userUpdate', function (User $new, User $old) {
            echo 'userUpdate: syncing user'.PHP_EOL;
            $this->syncUser($new);
            echo 'userUpdate: user synced'.PHP_EOL;
        });

        $client->on('guildMemberAdd', function (GuildMember $member) {
            echo 'guildMemberAdd: syncing member'.PHP_EOL;
            $this->syncGuildMember($member);
            echo 'guildMemberAdd: member synced'.PHP_EOL;
        });

        $client->on('guildMemberUpdate', function (GuildMember $new, GuildMember $old) {
            echo 'guildMemberUpdate: syncing member'.PHP_EOL;
            $this->syncGuildMember($new);
            echo 'guildMemberUpdate: member synced'.PHP_EOL;
        });

        $client->on('message', function (Message $message) {
            $this->logMessage($message);
            $this->handleMessage($message);
        });

        $client->login(config('services.discord.bot.token'));
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
     * Handle an incoming message.
     *
     * @param \CharlotteDunois\Yasmin\Models\Message $message
     */
    protected function handleMessage(Message $message)
    {
        $guild = Guild::where('guild_id', $message->guild->id)->first();
        $content = $message->content;

        if (preg_match('/^!(time|servertime) ?.*$/', $content)) {
            $time = Carbon::now()->tz('America/New_York')->format('g:iA');
            $timeMessage = "It is currently {$time} ET.";
            $message->channel->send($timeMessage);
        } elseif (preg_match('/^!points ?.*$/', $content)) {
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
    }

    /**
     * Sync all guilds.
     *
     * @param \CharlotteDunois\Yasmin\Models\GuildStorage $guilds
     * @throws \Exception
     */
    protected function syncGuilds($guilds)
    {
        foreach ($guilds as $guild) {
            $this->syncGuild($guild);
        }
    }

    /**
     * Sync a guild.
     *
     * @param \CharlotteDunois\Yasmin\Models\Guild $guild
     * @return \CharlotteDunois\Yasmin\Models\Guild
     */
    protected function syncGuild(\CharlotteDunois\Yasmin\Models\Guild $guild)
    {
        Guild::withTrashed()->updateOrCreate(
            ['guild_id' => $guild->id],
            ['name' => $guild->name, 'deleted_at' => null]
        );

        $this->syncGuildMembers($guild->members);

        return $guild;
    }

    /**
     * Sync all guild members.
     *
     * @param \CharlotteDunois\Yasmin\Models\GuildMemberStorage $members
     */
    protected function syncGuildMembers($members)
    {
        foreach ($members as $member) {
            $this->syncGuildMember($member);
        }
    }

    /**
     * Sync a guild member.
     *
     * @param \CharlotteDunois\Yasmin\Models\GuildMember $member
     * @return \Illuminate\Database\Eloquent\Model
     */
    private function syncGuildMember(GuildMember $member)
    {
        $this->syncUser($member->user);

        $guild = Guild::where('guild_id', $member->guild->id)->first();

        return Member::updateOrCreate(
            ['uid' => $member->id, 'guild_id' => $guild->id],
            ['username' => $member->user->username, 'nickname' => $member->nickname]
        );
    }

    /**
     * Sync a user.
     *
     * @param \CharlotteDunois\Yasmin\Models\User $user
     * @return void
     */
    protected function syncUser(User $user)
    {
        Member::where('uid', $user->id)
            ->update(['username' => $user->username]);
    }

    /**
     * @param Message $message
     */
    protected function logMessage($message)
    {
        $guild = Guild::where('guild_id', $message->guild->id)->first();

        Member::where('uid', $message->member->id)
            ->where('guild_id', $guild->id)
            ->update(['last_message_at' => Carbon::createFromTimestamp($message->createdTimestamp)]);
    }
}
