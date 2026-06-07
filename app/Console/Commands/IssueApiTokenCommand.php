<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('auth:token {email : Email of the user to issue the token for} {--name=docs : Token name}')]
#[Description('Issue a Sanctum personal access token to test the API from the docs.')]
class IssueApiTokenCommand extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user found with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $token = $user->createToken($this->option('name'))->plainTextToken;

        $this->info("Bearer token for {$user->email}:");
        $this->line($token);

        return self::SUCCESS;
    }
}
