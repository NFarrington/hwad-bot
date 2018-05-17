<?php

namespace App\Console\Commands;

use App\Models\Guild;
use App\Models\Member;
use App\Services\DiscordService;
use App\Services\MessageService;
use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Models\GuildMember;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

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
     * The message service.
     *
     * @var \App\Services\MessageService
     */
    protected $messageService;

    /**
     * Create a new command instance.
     *
     * @param \App\Services\MessageService $messageService
     * @return void
     */
    public function __construct(MessageService $messageService)
    {
        parent::__construct();

        $this->messageService = $messageService;
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

        $client->on('error', function ($error) {
            $this->handleError($error);
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

        $client->on('guildMemberRemove', function (GuildMember $member) {
            echo 'guildMemberRemove: deleting member'.PHP_EOL;
            $this->deleteGuildMember($member);
            echo 'guildMemberRemove: member deleted'.PHP_EOL;
        });

        $client->on('message', function (Message $message) {
            $this->messageService->log($message);
            $this->messageService->handle($message);
        });

        $client->login(config('services.discord.bot.token'));
        $loop->run();
    }

    /**
     * Handle an exception.
     *
     * @param $error
     * @throws \Exception
     */
    protected function handleError($error)
    {
        DiscordService::handlePromiseRejection($error);
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
     * @throws \Exception
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
     * @throws \Exception
     */
    protected function syncGuildMembers($members)
    {
        $guild = Guild::where('guild_id', $members->first()->guild->id)->first();
        $knownMembers = Member::where('guild_id', $guild->id)->pluck('id', 'uid');

        foreach ($members as $member) {
            $this->syncGuildMember($member);
            unset($knownMembers[$member->id]);
        }

        Member::whereIn('id', $knownMembers)->delete();
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

        $member = Member::withTrashed()
            ->updateOrCreate(
                ['uid' => $member->id, 'guild_id' => $guild->id],
                [
                    'username' => $member->user->username,
                    'nickname' => $member->nickname,
                    'bot' => $member->user->bot,
                    'deleted_at' => null,
                ]
            );

        if ($member->wasRecentlyCreated) {
            $member->update(['last_message_at' => Carbon::now()]);
        }

        return $member;
    }

    /**
     * Delete a guild member.
     *
     * @param \CharlotteDunois\Yasmin\Models\GuildMember $member
     * @throws \Exception
     */
    protected function deleteGuildMember($member)
    {
        $guild = Guild::where('guild_id', $member->guild->id)->first();

        Member::where('guild_id', $guild->id)
            ->where('uid', $member->id)
            ->delete();
    }

    /**
     * Sync a user.
     *
     * @param \CharlotteDunois\Yasmin\Models\User $user
     * @return void
     */
    protected function syncUser(User $user)
    {
        Member::withTrashed()
            ->where('uid', $user->id)
            ->update(['username' => $user->username]);
    }
}
