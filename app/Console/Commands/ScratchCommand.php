<?php

namespace App\Console\Commands;



use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use function Laravel\Ai\agent;
use Illuminate\Support\Str;

#[Signature('app:scratch-command')]
#[Description('Command description')]
class ScratchCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $r = agent(instructions: 'Responde breve en espanol.')->prompt('Que es Laravel en una frase?');
//        $r = agent(instructions: 'Reply briefly.')->prompt('In one sentence, what is Laravel?');
        dd($r);

        dd(Str::of('hola mundo')->toEmbeddings());
    }
}
