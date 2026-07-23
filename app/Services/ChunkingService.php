<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ChunkingService
{
    public function __construct(
        protected int $maxWords = 200,
        protected int $overlapWords = 40,
    ) {
        if ($this->maxWords <= 0) {
            throw new InvalidArgumentException(
                'maxWords must be greater than 0.'
            );
        }

        if ($this->overlapWords < 0) {
            throw new InvalidArgumentException(
                'overlapWords cannot be negative.'
            );
        }

        if ($this->overlapWords >= $this->maxWords) {
            throw new InvalidArgumentException(
                'overlapWords must be lower than maxWords.'
            );
        }
    }

    /** @return string[] */
    public function chunk(string $text): array
    {
        if (blank($text)) {
            return [];
        }

        $chunks = collect();
        $currentWords = collect();

        foreach ($this->paragraphs($text) as $paragraph) {
            $paragraphWords = $this->words($paragraph);

            while ($paragraphWords->isNotEmpty()) {
                $available = $this->maxWords - $currentWords->count();

                if ($available === 0) {
                    $currentWords = $this->closeChunk(
                        chunks: $chunks,
                        words: $currentWords,
                    );

                    $available = $this->maxWords - $currentWords->count();
                }

                $wordsToAdd = $paragraphWords->take($available);

                $currentWords = $currentWords
                    ->concat($wordsToAdd)
                    ->values();

                $paragraphWords = $paragraphWords
                    ->slice($wordsToAdd->count())
                    ->values();

                if ($paragraphWords->isNotEmpty()) {
                    $currentWords = $this->closeChunk(
                        chunks: $chunks,
                        words: $currentWords,
                    );
                }
            }
        }

        if ($currentWords->isNotEmpty()) {
            $chunks->push($currentWords->implode(' '));
        }

        return $chunks
            ->filter()
            ->values()
            ->all();
    }

    /** @return Collection<int, string> */
    protected function paragraphs(string $text): Collection
    {
        return Str::of($text)
            ->trim()
            ->split('/\R\s*\R/u')
            ->map(fn (string $paragraph) => trim($paragraph))
            ->filter()
            ->values();
    }

    /** @return Collection<int, string> */
    protected function words(string $text): Collection
    {
        return Str::of($text)
            ->trim()
            ->split('/\s+/u')
            ->filter()
            ->values();
    }

    /**
     * @param Collection<int, string> $chunks
     * @param Collection<int, string> $words
     *
     * @return Collection<int, string>
     */
    protected function closeChunk(
        Collection $chunks,
        Collection $words,
    ): Collection {
        if ($words->isEmpty()) {
            return collect();
        }

        $chunks->push($words->implode(' '));

        if ($this->overlapWords === 0) {
            return collect();
        }

        return $words
            ->take(-$this->overlapWords)
            ->values();
    }
}
