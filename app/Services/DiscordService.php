<?php

namespace App\Services;

use Throwable;

abstract class DiscordService
{
    /**
     * Handle a rejected promise.
     *
     * @param $error
     * @throws \Exception
     */
    public static function handlePromiseRejection($error)
    {
        if (is_string($error)) {
            \Log::error($error);
            return report(new \Exception($error));
        } elseif ($error instanceof Throwable || method_exists($error, 'getMessage')) {
            $message = $error->getMessage();
            $message .= property_exists($error, 'path') ? " for path {$error->path}" : '';
            \Log::error("{$message}");
            return report($error);
        }

        throw new \Exception('Could not handle promise rejection.');
    }
}
