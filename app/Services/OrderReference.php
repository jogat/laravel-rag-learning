<?php

namespace App\Services;

use Illuminate\Support\Str;

class OrderReference
{
    /** @param list<string> $earlierQuestions */
    public function isOrderQuestion(string $question, array $earlierQuestions): bool
    {
        if ($this->numbers($question) !== []) {
            return true;
        }

        $normalized = $this->normalize($question);

        if (preg_match('/\b(pedido|order|commande)\b/u', $normalized)
            && preg_match('/\b(mi|my|mon|ese|este|that|this|cet|estado|status|statut|articulos|items|articles|donde|where|ou|numero|number)\b/u', $normalized)) {
            return true;
        }

        $previous = $earlierQuestions === [] ? '' : $earlierQuestions[array_key_last($earlierQuestions)];

        return preg_match('/\b(pedido|order|commande)\b/u', $this->normalize($previous)) === 1
            && preg_match('/\b(cuando llega|when .*arriv|quand .*arriv|que contiene|what .*contain)\b/u', $normalized) === 1;
    }

    /** @param list<string> $earlierQuestions */
    public function resolve(string $question, array $earlierQuestions): ?string
    {
        $numbers = $this->numbers($question);

        if ($numbers !== []) {
            return count($numbers) === 1 ? $numbers[0] : null;
        }

        if ($earlierQuestions !== [] && preg_match('/^\s*([a-z0-9]+)\s*$/iu', $question, $match)) {
            return $match[1];
        }

        if (preg_match('/\b(otro|another|other|autre)\b/u', $this->normalize($question))
            || preg_match('/\b(?:pedido|order|commande)\s*(?:(?:numero|number|n[º°o]\.?)\s*)?[#:]*\s*\S*\d/iu', $this->normalize($question))
            || preg_match('/\b(?:pedido|order|commande)\s*(?:numero|number|#|n[º°o]\.?)\s*\S/iu', $this->normalize($question))) {
            return null;
        }

        foreach (array_reverse($earlierQuestions) as $earlierQuestion) {
            $numbers = $this->numbers($earlierQuestion);

            if ($numbers !== []) {
                return count($numbers) === 1 ? $numbers[0] : null;
            }
        }

        return null;
    }

    /**
     * Accept digit-containing identifiers or any alphanumeric identifier with a number marker.
     * Do not turn arbitrary dates, prices or malformed identifiers into order references.
     *
     * @return list<string>
     */
    private function numbers(string $question): array
    {
        if (preg_match('/^\s*#?([a-z0-9]*[0-9][a-z0-9]*)\s*$/iu', $question, $match)) {
            return [$match[1]];
        }

        preg_match_all('/\b(?:pedido|order|commande)\s*(?:(?:n[uú]mero|number|num[eé]ro|n[º°o]\.?)\s*)?[#:]*\s*([a-z0-9]*[0-9][a-z0-9]*)(?![a-z0-9\-]|\.[0-9])/iu', $question, $matches);

        preg_match_all('/\b(?:pedido|order|commande)\s*(?:n[uú]mero|number|num[eé]ro|n[º°o]\.?|#)\s*([a-z0-9]+)(?![a-z0-9\-]|\.[0-9])/iu', $question, $markedMatches);

        return array_values(array_unique([...$matches[1], ...$markedMatches[1]]));
    }

    private function normalize(string $question): string
    {
        return Str::lower(Str::ascii($question));
    }
}
