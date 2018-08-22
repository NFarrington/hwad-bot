<?php

namespace App\Http\Controllers;

use App\Models\Guild;
use App\Models\Member;
use App\Models\Revision;
use Illuminate\Http\Request;

class HogwartsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function home()
    {
        return view('home');
    }

    public function nameChanges()
    {
        $token = \Session::get('user.access-token');
        if (!$token) {
            throw new \Exception('No access token found.');
        }

        $client = new \GuzzleHttp\Client();
        $response = $client->get(config('discord.uri').'/users/@me/guilds', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents());
        $guildIds = collect($data)->pluck('id');

        $guilds = Guild::whereIn('guild_id', $guildIds)->pluck('id');
        $changes = Revision::where('model_type', Member::class)
            ->whereIn('key', ['nickname', 'username'])
            ->whereHas('member', function ($query) use ($guilds) {
                $query->whereIn('id', $guilds);
            })->orderByDesc('created_at')->limit(100)->get();

        return view('name-changes')->with('changes', $changes);
    }
}
