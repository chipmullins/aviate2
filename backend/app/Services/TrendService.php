<?php

namespace App\Services;

use App\Models\Trend;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Counts the interesting words people post, for the "Trending" sidebar. */
class TrendService
{
    private const STOP_WORDS = [
        'about', 'above', 'across', 'after', 'again', 'against', 'all', 'almost', 'alone', 'along', 'already',
        'also', 'although', 'always', 'among', 'and', 'another', 'any', 'anybody', 'anyone', 'anything',
        'anywhere', 'are', 'area', 'around', 'ask', 'asked', 'away', 'back', 'became', 'because', 'become',
        'been', 'before', 'began', 'behind', 'being', 'best', 'better', 'between', 'big', 'both', 'but', 'came',
        'can', 'cannot', 'certain', 'come', 'could', 'did', 'does', 'doing', 'done', 'down', 'during', 'each',
        'either', 'else', 'end', 'enough', 'even', 'ever', 'every', 'everyone', 'everything', 'far', 'few',
        'find', 'first', 'for', 'from', 'full', 'gave', 'get', 'gets', 'give', 'given', 'going', 'gone', 'good',
        'got', 'great', 'had', 'has', 'have', 'having', 'her', 'here', 'hers', 'herself', 'him', 'himself', 'his',
        'how', 'however', 'into', 'its', 'itself', 'just', 'keep', 'kind', 'knew', 'know', 'known', 'last',
        'later', 'least', 'less', 'let', 'like', 'likely', 'long', 'made', 'make', 'making', 'many', 'may',
        'might', 'more', 'most', 'much', 'must', 'myself', 'need', 'never', 'new', 'next', 'nobody', 'not',
        'nothing', 'now', 'off', 'often', 'old', 'once', 'one', 'only', 'onto', 'other', 'others', 'our', 'ours',
        'out', 'over', 'own', 'part', 'per', 'perhaps', 'put', 'quite', 'rather', 'really', 'right', 'said',
        'same', 'saw', 'say', 'says', 'see', 'seem', 'seemed', 'seems', 'sees', 'several', 'shall', 'she',
        'should', 'show', 'since', 'some', 'someone', 'something', 'still', 'such', 'sure', 'take', 'taken',
        'than', 'that', 'the', 'their', 'theirs', 'them', 'then', 'there', 'therefore', 'these', 'they',
        'thing', 'things', 'think', 'this', 'those', 'though', 'thought', 'three', 'through', 'thus', 'today',
        'together', 'too', 'took', 'toward', 'two', 'under', 'until', 'upon', 'use', 'used', 'very', 'want',
        'wanted', 'was', 'way', 'well', 'went', 'were', 'what', 'when', 'where', 'whether', 'which', 'while',
        'who', 'whole', 'whose', 'why', 'will', 'with', 'within', 'without', 'would', 'yet', 'you', 'your',
        'yours', 'yourself', 'http', 'https', 'www', 'com', 'youtube', 'watch', 'yeah', 'lol', 'okay',
    ];

    public function record(?string $text): void
    {
        if (! $text) {
            return;
        }

        $text = preg_replace('~https?://\S+~i', ' ', $text);

        $terms = collect(preg_split('/[^\p{L}\p{N}\']+/u', Str::lower($text)))
            ->map(fn ($w) => trim($w, "'"))
            ->filter(fn ($w) => mb_strlen($w) >= 3 && mb_strlen($w) <= 60 && ! ctype_digit($w))
            ->reject(fn ($w) => in_array($w, self::STOP_WORDS, true))
            ->unique()
            ->take(25)
            ->values();

        if ($terms->isEmpty()) {
            return;
        }

        $now = now();
        Trend::query()->upsert(
            $terms->map(fn ($t) => ['term' => $t, 'hits' => 1, 'created_at' => $now, 'updated_at' => $now])->all(),
            ['term'],
            ['hits' => DB::raw('trends.hits + 1'), 'updated_at' => $now]
        );
    }
}
