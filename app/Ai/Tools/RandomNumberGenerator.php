<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RandomNumberGenerator implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Generate a random integer between a minimum and maximum value. Use this whenever the user asks for a random number.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        logger()->info('tool invoked', $request->all());
        $min = $request->integer('min');
        $max = $request->integer('max');

        return (string)random_int($min, $max);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'min' => $schema->integer()->required()->description('The minimum number to generate.'),
            'max' => $schema->integer()->required()->description('The maximum number to generate.'),
        ];
    }
}
