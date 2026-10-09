<?php

use App\Services\ChunkingService;

it('returns no chunks for blank text', function () {
    expect((new ChunkingService(maxWords: 5, overlapWords: 2))->chunk("  \n\n "))->toBe([]);
});

it('keeps text that fits in one chunk together, across paragraphs', function () {
    expect((new ChunkingService(maxWords: 5, overlapWords: 2))->chunk("a b\n\nc d"))->toBe(['a b c d']);
});

it('repeats the overlap words at the start of the next chunk', function () {
    expect((new ChunkingService(maxWords: 5, overlapWords: 2))->chunk('a b c d e f g h'))
        ->toBe(['a b c d e', 'd e f g h']);
});

it('closes a full chunk before starting the next paragraph', function () {
    expect((new ChunkingService(maxWords: 5, overlapWords: 2))->chunk("a b c d e\n\nf"))
        ->toBe(['a b c d e', 'd e f']);
});

it('does not repeat words when the overlap is zero', function () {
    expect((new ChunkingService(maxWords: 3, overlapWords: 0))->chunk('a b c d e'))
        ->toBe(['a b c', 'd e']);
});

it('rejects an invalid chunk configuration', function (int $maxWords, int $overlapWords, string $message) {
    expect(fn () => new ChunkingService($maxWords, $overlapWords))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'zero max words' => [0, 0, 'maxWords must be greater than 0.'],
    'negative overlap' => [5, -1, 'overlapWords cannot be negative.'],
    'overlap equal to max' => [5, 5, 'overlapWords must be lower than maxWords.'],
]);
