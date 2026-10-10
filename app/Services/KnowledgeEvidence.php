<?php

namespace App\Services;

use Illuminate\Support\Str;

class KnowledgeEvidence
{
    /**
     * Preserve source sentences verbatim. If no useful keyword matches, retain the retrieved
     * excerpts rather than treating lexical matching as a semantic relevance threshold.
     *
     * @param  list<string>  $excerpts
     */
    public function forQuestion(array $excerpts, string $question): string
    {
        if ($excerpts === []) {
            return 'No relevant results found.';
        }

        if (preg_match('/\S\s+(?:y|and|et)\s+\S/iu', $question)) {
            return implode("\n", $excerpts);
        }

        $words = $this->words($question);
        $sentences = [];

        foreach ($excerpts as $excerpt) {
            foreach (preg_split('/(?<=[.!?])\s+(?=\p{Lu})/u', $excerpt) as $sentence) {
                $sentence = trim($sentence);
                $sentences[$sentence] = count(array_intersect($words, $this->words($sentence)));
            }
        }

        $bestScore = max($sentences);
        $evidence = $bestScore > 0
            ? array_keys(array_filter($sentences, fn (int $score): bool => $score === $bestScore))
            : $excerpts;

        $normalizedQuestion = Str::lower(Str::ascii($question));

        if (preg_match('/\b(horarios?|horas?|abren|cierran|opening|closing|hours|horaires?|ouverture)\b/', $normalizedQuestion)) {
            $supportPattern = '/\b(atencion|soporte|support|assistance)\b|service client|customer service/';
            $asksSupport = preg_match($supportPattern, $normalizedQuestion) === 1;
            $hours = array_values(array_filter(array_keys($sentences), function (string $sentence) use ($supportPattern, $asksSupport): bool {
                $normalized = Str::lower(Str::ascii($sentence));
                $isSupport = preg_match($supportPattern, $normalized) === 1;
                $isHours = preg_match('/\b(abr\w*|abiert\w*|cerr\w*|open\w*|clos\w*|ouvert\w*|ouvr\w*|ferm\w*|horario\w*|hours|horaires)\b/', $normalized) === 1
                    || ($asksSupport && preg_match('/\b(available|disponible)\b/', $normalized));

                return $isSupport === $asksSupport && $isHours;
            }));

            if ($hours !== []) {
                $evidence = $hours;
            }
        }

        return implode("\n", $evidence);
    }

    /** @return list<string> */
    private function words(string $text): array
    {
        preg_match_all('/[a-z]{4,}/', Str::lower(Str::ascii($text)), $matches);

        return array_values(array_diff(array_unique($matches[0]), [
            'cual', 'cuales', 'cuanto', 'cuantos', 'como', 'para', 'tiene', 'tienen', 'nuestro', 'nuestra', 'negocio',
            'what', 'which', 'when', 'where', 'does', 'have', 'your', 'this', 'that', 'about', 'business',
            'quel', 'quelle', 'quels', 'quelles', 'comment', 'combien', 'notre', 'votre', 'avec', 'pour',
        ]));
    }
}
