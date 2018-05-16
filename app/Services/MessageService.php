<?php

namespace App\Services;

use App\Models\Guild;
use App\Models\Member;
use App\Models\Points;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MessageService
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

        if (preg_match('/^!(time|servertime) ?.*$/', $content)) {
            $this->sendServerTime($message->channel);
        } elseif (preg_match('/^!points ?.*$/', $content)) {
            $this->sendPointsSummary($message->channel, $guild);
        } elseif (preg_match('/^!([ghrs]) (add|sub|subtract|set) (\d+)$/', $content, $matches)) {
            if (!Gate::forUser($message->member)->check('points.modify')) {
                $this->sendError($message->channel, 'Sorry, you are not permitted to modify house points!');
                return;
            }

            $points = $this->updatePoints($guild, $matches[1], $matches[2], $matches[3]);
            $this->sendPointsUpdate($message->channel, $points);
        }
    }

    /**
     * Send the server time.
     *
     * @param \CharlotteDunois\Yasmin\Interfaces\TextChannelInterface $channel
     */
    protected function sendServerTime($channel)
    {
        $time = Carbon::now()->tz('America/New_York')->format('g:iA');

        $timeMessage = "It is currently {$time} ET.";

        $channel->send($timeMessage);
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

        $channel->send($pointsMessage);
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

        $channel->send($message);
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
        $channel->send($error);
    }

    /**
     * Log a message.
     *
     * @param $message
     */
    public function log($message)
    {
        $guild = Guild::where('guild_id', $message->guild->id)->first();

        Member::where('uid', $message->member->id)
            ->where('guild_id', $guild->id)
            ->update(['last_message_at' => Carbon::createFromTimestamp($message->createdTimestamp)]);
    }
}
