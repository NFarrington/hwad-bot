<?php

namespace App\Services;

use App\Models\Guild;
use App\Models\Member;
use App\Models\Points;
use CharlotteDunois\Yasmin\Models\Role;
use DateInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MessageService extends DiscordService
{
    /**
     * Handle a new incoming message.
     *
     * @param \CharlotteDunois\Yasmin\Models\Message $message
     * @throws \Exception
     */
    public function handle($message)
    {
        $guild = Guild::where('guild_id', $message->guild->id)->first();
        $content = $message->content;

        // /** @var \CharlotteDunois\Yasmin\Models\TextChannel $channel */
        // $channel = $message->channel;
        // if (strcasecmp($channel->name, 'pensieve') === 0
        //     && $message->member->user->bot === false
        //     && $message->attachments->count() === 0
        // ) {
        //     $channel->send("<@{$message->member->id}> You may only send images in this channel. Discussion is not allowed.")
        //         ->then(function (Message $message) {
        //             $message->client->addTimer(5, function () use ($message) {
        //                 $message->delete();
        //             });
        //         }, [$this, 'handlePromiseRejection']);
        //
        //     $message->delete()
        //         ->otherwise([$this, 'handlePromiseRejection']);
        //
        //     return;
        // }

        if (preg_match('/^!(time|servertime) ?.*$/i', $content)) {
            $this->sendServerTime($message->channel);
        } elseif (preg_match('/^!points ?.*$/i', $content)) {
            $this->sendPointsSummary($message->channel, $guild);
        } elseif (preg_match('/^!(gryffindor|hufflepuff|ravenclaw|slytherin) ?.*$/i', $content, $matches)) {
            $existingHouse = $message->member->roles->filter(function (Role $role) {
                return strcasecmp($role->name, 'Gryffindor') === 0 ||
                    strcasecmp($role->name, 'Hufflepuff') === 0 ||
                    strcasecmp($role->name, 'Ravenclaw') === 0 ||
                    strcasecmp($role->name, 'Slytherin') === 0;
            });

            if ($existingHouse->count() > 0) {
                $this->sendError($message->channel, 'Sorry, you already have a house!');
                return;
            }

            $role = $message->guild->roles->filter(function (Role $role) use ($matches) {
                return strcasecmp($role->name, $matches[1]) === 0;
            })->first();

            if (!$role) {
                $this->sendError($message->channel, 'Sorry, we could not find that role!');
                return;
            }

            $message->member->addRole($role)
                ->then(function ($member) use ($message, $matches) {
                    return $message->channel->send('You are now a member of '.ucfirst($matches[1]).'!');
                }, [$this, 'handlePromiseRejection']);
        } elseif (preg_match('/^!([ghrs]) (add|sub|subtract|set) (\d+)$/i', $content, $matches)) {
            if (!Gate::forUser($message->member)->check('server.modify-points')) {
                $this->sendError($message->channel, 'Sorry, you are not permitted to modify house points!');
                return;
            }

            $points = $this->updatePoints($guild, $matches[1], $matches[2], $matches[3]);
            $this->sendPointsUpdate($message->channel, $points);
        } elseif (preg_match('/^!inactive (\d+[d|m|y])$/i', $content, $matches)) {
            if (!Gate::forUser($message->member)->check('server.list-inactive')) {
                $this->sendError($message->channel, 'Sorry, you are not permitted to list inactive members!');
                return;
            }

            $interval = new DateInterval('P'.strtoupper($matches[1]));
            $this->sendInactiveList($message->channel, $guild, $interval);
        } elseif (preg_match('/^!bumpyears$/i', $content, $matches)) {
            if (!Gate::forUser($message->member)->check('server.modify-year-groups')) {
                $this->sendError($message->channel, 'Sorry, you are not permitted to move year groups!');
                return;
            }

            $this->moveYearsForward($message->guild->roles, $message->guild->members);
            $message->channel->send('Updating years... this may take a minute or two!')
                ->otherwise([$this, 'handlePromiseRejection']);
        } elseif (preg_match('/^!removetags$/i', $content, $matches)) {
            if (!Gate::forUser($message->member)->check('server.remove-tags')) {
                $this->sendError($message->channel, 'Sorry, you are not permitted to remove tags!');
                return;
            }

            $this->removeTags($message->guild->members);
            $message->channel->send('Removing tags... this may take a minute or two!')
                ->otherwise([$this, 'handlePromiseRejection']);
        }
    }

    /**
     * Send the server time.
     *
     * @param \CharlotteDunois\Yasmin\Interfaces\TextChannelInterface $channel
     */
    protected function sendServerTime($channel)
    {
        $time = Carbon::now()->tz('America/New_York')->format('g:iA T');

        $timeMessage = "It is currently {$time}.";

        $channel->send($timeMessage)
            ->otherwise([$this, 'handlePromiseRejection']);
    }

    /**
     * Send a summary of the guild's points.
     *
     * @param \CharlotteDunois\Yasmin\Interfaces\TextChannelInterface $channel
     * @param \App\Models\Guild $guild
     */
    protected function sendPointsSummary($channel, $guild)
    {
        $points = Points::where('guild_id', $guild->id)->pluck('points', 'house');

        $pointsMessage = sprintf(
            "Gryffindor: %s\nHufflepuff: %s\nRavenclaw: %s\nSlytherin: %s\n",
            array_get($points, 'g', 0),
            array_get($points, 'h', 0),
            array_get($points, 'r', 0),
            array_get($points, 's', 0)
        );

        $channel->send($pointsMessage)
            ->otherwise([$this, 'handlePromiseRejection']);
    }

    /**
     * Update the house points for a guild.
     *
     * @param \App\Models\Guild $guild
     * @param string $house
     * @param string $operation
     * @param int $points
     * @return \App\Models\Points|\Illuminate\Database\Eloquent\Model
     * @throws \Exception
     */
    protected function updatePoints($guild, string $house, string $operation, int $points)
    {
        \Log::info("Updating points for #G{$guild->id}, {$house} {$operation} {$points}");

        switch ($operation) {
            case 'add':
                $operation = "points + {$points}";
                break;
            case 'sub':
            case 'subtract':
                $operation = "points - {$points}";
                break;
            case 'set':
                $operation = $points;
                break;
            default:
                throw new \Exception("Unknown operation \"{$operation}\"");
        }

        return Points::updateOrCreate(
            ['guild_id' => $guild->id, 'house' => $house],
            ['points' => DB::raw($operation)]
        )->fresh();
    }

    /**
     * Send a notification regarding the new points of a house.
     *
     * @param \CharlotteDunois\Yasmin\Interfaces\TextChannelInterface $channel
     * @param \App\Models\Points $points
     */
    protected function sendPointsUpdate($channel, $points)
    {
        $house = trans("houses.{$points->house}");
        $message = "{$house} now has {$points->points} points.";

        $channel->send($message)
            ->otherwise([$this, 'handlePromiseRejection']);
    }

    /**
     * Send a notification regarding the new points of a house.
     *
     * @param \CharlotteDunois\Yasmin\Interfaces\TextChannelInterface $channel
     * @param \App\Models\Guild $guild
     * @param \DateInterval $interval
     */
    protected function sendInactiveList($channel, $guild, $interval)
    {
        $inactiveSince = Carbon::now()->sub($interval);
        $inactiveMembers = Member::where('guild_id', $guild->id)
            ->where(function ($query) use ($inactiveSince) {
                $query->where('last_message_at', '<=', $inactiveSince)
                    ->orWhereNull('last_message_at');
            })->where('bot', false)
            ->orderByDesc('last_message_at')
            ->get(['username', 'nickname', 'last_message_at']);

        $inactiveMembers->transform(function (Member $member) {
            $name = $member->nickname ?: $member->username;
            $lastMessage = $member->last_message_at
                ? $member->last_message_at->tz('America/New_York')->format('Y-m-d H:i T')
                : '[unknown]';

            return "{$name} since {$lastMessage}";
        });

        if ($inactiveMembers->isEmpty()) {
            $channel->send('No inactive members were found.')
                ->otherwise([$this, 'handlePromiseRejection']);

            return;
        }

        $inactiveMembers = $inactiveMembers->implode("\n");
        if (strlen($inactiveMembers) > 2042) {
            $inactiveMembers = substr($inactiveMembers, 0, 2039).'...';
        }

        $channel->send('The following members are inactive:', ['embed' => [
            'title' => 'Inactive Members',
            'description' => "```{$inactiveMembers}```",
        ]])->otherwise([$this, 'handlePromiseRejection']);
    }

    /**
     * Change guild member roles to the next year.
     *
     * @param \CharlotteDunois\Yasmin\Models\RoleStorage $roles
     * @param \CharlotteDunois\Yasmin\Models\GuildMemberStorage $members
     */
    protected function moveYearsForward($roles, $members)
    {
        $roles = $roles->pluck('id', 'name')->all();
        $transitions = [
            'First Year' => 'Second Year',
            'Second Year' => 'Third Year',
            'Third Year' => 'Fourth Year',
            'Fourth Year' => 'Fifth Year',
            'Fifth Year' => 'Sixth Year',
            'Sixth Year' => 'Seventh Year',
            'Seventh Year' => 'Graduated',
        ];

        foreach ($members as $member) {
            /* @var \CharlotteDunois\Yasmin\Models\GuildMember $member */
            foreach ($member->roles as $role) {
                /* @var \CharlotteDunois\Yasmin\Models\Role $role */
                if (array_key_exists($role->name, $transitions)) {
                    if (!array_key_exists($transitions[$role->name], $roles)) {
                        continue;
                    }

                    $member->addRole($roles[$transitions[$role->name]])
                        ->otherwise([$this, 'handlePromiseRejection']);
                    $member->removeRole($role)
                        ->otherwise([$this, 'handlePromiseRejection']);
                }
            }
        }
    }

    /**
     * Remove year tags from names.
     *
     * @param \CharlotteDunois\Yasmin\Models\GuildMemberStorage $members
     */
    protected function removeTags($members)
    {
        foreach ($members as $member) {
            /* @var \CharlotteDunois\Yasmin\Models\GuildMember $member */
            if ($member->nickname && preg_match('/^\[\d+\] ?(.*)$/i', $member->nickname, $matches)) {
                $member->setNickname($matches[1])
                    ->otherwise([$this, 'handlePromiseRejection']);
            }
        }
    }

    /**
     * Send an error message.
     *
     * @param \CharlotteDunois\Yasmin\Interfaces\TextChannelInterface $channel
     * @param string $error
     * @return void
     */
    protected function sendError($channel, $error)
    {
        $channel->send($error)
            ->otherwise([$this, 'handlePromiseRejection']);
    }

    /**
     * Log a message.
     *
     * @param \CharlotteDunois\Yasmin\Models\Message $message
     */
    public function log($message)
    {
        if ($message->guild) {
            $guild = Guild::where('guild_id', $message->guild->id)->first();

            Member::where('uid', $message->member->id)
                ->where('guild_id', $guild->id)
                ->update(['last_message_at' => Carbon::createFromTimestamp($message->createdTimestamp)]);
        }
    }
}
