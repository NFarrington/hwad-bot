<?php

namespace App\Http\Controllers;

use App\Models\User;

class OAuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function index()
    {
        return redirect()->route('login');
    }

    public function login()
    {
        if (\Request::has('error')) {
            report(new \Exception(\Request::input('error')));
            die('An error occurred while logging you in.');
        }

        if (\Request::has('code')) {
            $code = \Request::input('code');
            //\Session::put('discord.oauth.code', $code);

            $client = new \GuzzleHttp\Client();
            $response = $client->post(config('discord.uri').'/oauth2/token', [
                'form_params' => [
                    'client_id' => config('discord.client_id'),
                    'client_secret' => config('discord.client_secret'),
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => route('login'),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents());
            $token = $data->access_token;

            $response = $client->get(config('discord.uri').'/users/@me', [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents());

            if (empty($data->id)) {
                die('Unable to retrieve user ID.');
            }

            /* @var \App\Models\User $user */
            $user = User::firstOrCreate(['uid' => $data->id], ['username' => $data->username]);
            \Auth::login($user);
            \Session::put('user.access-token', $token);

            return redirect()->route('home');
        }

        $next = config('discord.uri').'/oauth2/authorize?response_type=code&client_id='.config('discord.client_id').'&scope=identify guilds&redirect_uri='.route('login');

        return redirect($next);
    }
}
