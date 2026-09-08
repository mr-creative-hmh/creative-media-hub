<?php

namespace App\Services\Organizer;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZeroKeyGenreClassifierService
{
    public const GENRES = [
        'Action & Adventure',
        'Animation',
        'Comedy',
        'Crime & Mystery',
        'Documentary',
        'Drama & History',
        'Fantasy',
        'Horror & Thriller',
        'Romance',
        'Sci-Fi',
        'Arabic',
        'Indian',
    ];

    protected array $offlineTitleDb = [
        'the dark knight' => 'Action & Adventure',
        'the dark knight rises' => 'Action & Adventure',
        'batman begins' => 'Action & Adventure',
        'mad max fury road' => 'Action & Adventure',
        'furiosa' => 'Action & Adventure',
        'die hard' => 'Action & Adventure',
        'john wick' => 'Action & Adventure',
        'gladiator' => 'Action & Adventure',
        'top gun maverick' => 'Action & Adventure',
        'mission impossible' => 'Action & Adventure',
        'fast and furious' => 'Action & Adventure',
        'furious 7' => 'Action & Adventure',
        'fast x' => 'Action & Adventure',
        'transformers' => 'Action & Adventure',
        'bad boys' => 'Action & Adventure',
        'bad boys ride or die' => 'Action & Adventure',
        'twisters' => 'Action & Adventure',
        'fall guy' => 'Action & Adventure',
        'monkey man' => 'Action & Adventure',
        'godzilla x kong' => 'Action & Adventure',
        'kingdom of the planet of the apes' => 'Action & Adventure',

        // Animation
        'shrek' => 'Animation',
        'toy story' => 'Animation',
        'finding nemo' => 'Animation',
        'the lion king' => 'Animation',
        'spider-man into the spider-verse' => 'Animation',
        'spider-man across the spider-verse' => 'Animation',
        'spirited away' => 'Animation',
        'coco' => 'Animation',
        'wall-e' => 'Animation',
        'up' => 'Animation',
        'ratatouille' => 'Animation',
        'how to train your dragon' => 'Animation',
        'kung fu panda' => 'Animation',
        'inside out' => 'Animation',
        'inside out 2' => 'Animation',
        'despicable me' => 'Animation',
        'despicable me 4' => 'Animation',
        'minions' => 'Animation',
        'frozen' => 'Animation',
        'moana' => 'Animation',
        'moana 2' => 'Animation',
        'zootopia' => 'Animation',
        'the wild robot' => 'Animation',
        'flow' => 'Animation',
        'transformers one' => 'Animation',
        'arcane' => 'Animation',
        'rick and morty' => 'Animation',
        'avatar the last airbender' => 'Animation',
        'demon slayer' => 'Animation',
        'jujutsu kaisen' => 'Animation',
        'attack on titan' => 'Animation',
        'death note' => 'Animation',

        // Comedy
        'the hangover' => 'Comedy',
        'superbad' => 'Comedy',
        'step brothers' => 'Comedy',
        'anchorman' => 'Comedy',
        'the mask' => 'Comedy',
        'dumb and dumber' => 'Comedy',
        'liar liar' => 'Comedy',
        'groundhog day' => 'Comedy',
        'shaun of the dead' => 'Comedy',
        'hot fuzz' => 'Comedy',
        'tropic thunder' => 'Comedy',
        'borat' => 'Comedy',
        'ted' => 'Comedy',
        'ted 2' => 'Comedy',
        'deadpool' => 'Comedy',
        'deadpool 2' => 'Comedy',
        'deadpool & wolverine' => 'Comedy',
        'friends' => 'Comedy',
        'the office' => 'Comedy',
        'seinfeld' => 'Comedy',
        'brooklyn nine-nine' => 'Comedy',
        'modern family' => 'Comedy',

        // Crime & Mystery
        'the godfather' => 'Crime & Mystery',
        'the godfather part ii' => 'Crime & Mystery',
        'pulp fiction' => 'Crime & Mystery',
        'goodfellas' => 'Crime & Mystery',
        'se7en' => 'Crime & Mystery',
        'the silence of the lambs' => 'Crime & Mystery',
        'the usual suspects' => 'Crime & Mystery',
        'the departed' => 'Crime & Mystery',
        'knives out' => 'Crime & Mystery',
        'glass onion' => 'Crime & Mystery',
        'zodiac' => 'Crime & Mystery',
        'shutter island' => 'Crime & Mystery',
        'prisoners' => 'Crime & Mystery',
        'sherlock holmes' => 'Crime & Mystery',
        'breaking bad' => 'Crime & Mystery',
        'better call saul' => 'Crime & Mystery',
        'true detective' => 'Crime & Mystery',
        'fargo' => 'Crime & Mystery',
        'peaky blinders' => 'Crime & Mystery',

        // Drama & History
        'oppenheimer' => 'Drama & History',
        '1917' => 'Drama & History',
        'the shawshank redemption' => 'Drama & History',
        'forrest gump' => 'Drama & History',
        "schindler's list" => 'Drama & History',
        'fight club' => 'Drama & History',
        'the green mile' => 'Drama & History',
        'the prestige' => 'Drama & History',
        'whiplash' => 'Drama & History',
        'parasite' => 'Drama & History',
        'the wolf of wall street' => 'Drama & History',
        'catch me if you can' => 'Drama & History',
        'a beautiful mind' => 'Drama & History',
        'the pianist' => 'Drama & History',
        'dunkirk' => 'Drama & History',
        'saving private ryan' => 'Drama & History',
        'killers of the flower moon' => 'Drama & History',
        'the zone of interest' => 'Drama & History',
        'succession' => 'Drama & History',
        'the crown' => 'Drama & History',
        'chernobyl' => 'Drama & History',
        'band of brothers' => 'Drama & History',

        // Fantasy
        'harry potter' => 'Fantasy',
        'the lord of the rings' => 'Fantasy',
        'the hobbit' => 'Fantasy',
        'fantastic beasts' => 'Fantasy',
        'chronicles of narnia' => 'Fantasy',
        'percy jackson' => 'Fantasy',
        'game of thrones' => 'Fantasy',
        'house of the dragon' => 'Fantasy',
        'the witcher' => 'Fantasy',
        'the rings of power' => 'Fantasy',
        'wonka' => 'Fantasy',
        'dungeons & dragons' => 'Fantasy',

        // Horror & Thriller
        'the shining' => 'Horror & Thriller',
        'psycho' => 'Horror & Thriller',
        'alien' => 'Horror & Thriller',
        'the thing' => 'Horror & Thriller',
        'halloween' => 'Horror & Thriller',
        'a nightmare on elm street' => 'Horror & Thriller',
        'scream' => 'Horror & Thriller',
        'saw' => 'Horror & Thriller',
        'the conjuring' => 'Horror & Thriller',
        'insidious' => 'Horror & Thriller',
        'hereditary' => 'Horror & Thriller',
        'midsommar' => 'Horror & Thriller',
        'get out' => 'Horror & Thriller',
        'a quiet place' => 'Horror & Thriller',
        'a quiet place day one' => 'Horror & Thriller',
        'longlegs' => 'Horror & Thriller',
        'smile' => 'Horror & Thriller',
        'smile 2' => 'Horror & Thriller',
        'alien romulus' => 'Horror & Thriller',
        'stranger things' => 'Horror & Thriller',
        'the last of us' => 'Horror & Thriller',

        // Romance
        'titanic' => 'Romance',
        'la la land' => 'Romance',
        'the notebook' => 'Romance',
        'pride and prejudice' => 'Romance',
        'before sunrise' => 'Romance',
        'before sunset' => 'Romance',
        'before midnight' => 'Romance',
        'it ends with us' => 'Romance',
        'anyone but you' => 'Romance',

        // Sci-Fi
        'interstellar' => 'Sci-Fi',
        'inception' => 'Sci-Fi',
        'the matrix' => 'Sci-Fi',
        'blade runner' => 'Sci-Fi',
        'blade runner 2049' => 'Sci-Fi',
        '2001 a space odyssey' => 'Sci-Fi',
        'star wars' => 'Sci-Fi',
        'dune' => 'Sci-Fi',
        'dune part two' => 'Sci-Fi',
        'the martian' => 'Sci-Fi',
        'arrival' => 'Sci-Fi',
        'ex machina' => 'Sci-Fi',
        'avatar' => 'Sci-Fi',
        'avatar the way of water' => 'Sci-Fi',
        'tenet' => 'Sci-Fi',
        'civil war' => 'Sci-Fi',
        'the creator' => 'Sci-Fi',
        'black mirror' => 'Sci-Fi',
        'severance' => 'Sci-Fi',
        'fallout' => 'Sci-Fi',
        '3 body problem' => 'Sci-Fi',

        // Arabic
        'al fussool al arbaa' => 'Arabic',
        'al-fussool al-arbaa' => 'Arabic',
        'bab al-hara' => 'Arabic',
        'al hayba' => 'Arabic',
        'maraya' => 'Arabic',
        'buqaa daw' => 'Arabic',
        'al ikhtiyar' => 'Arabic',
        'rashash' => 'Arabic',
        'al thaman' => 'Arabic',
        'el keif' => 'Arabic',
        'kira wal jin' => 'Arabic',
    ];

    protected array $franchiseGenreMap = [
        'Fast & Furious' => 'Action & Adventure',
        'Transformers' => 'Action & Adventure',
        'Marvel Cinematic Universe' => 'Action & Adventure',
        'Mission: Impossible' => 'Action & Adventure',
        'James Bond 007' => 'Action & Adventure',
        'John Wick' => 'Action & Adventure',
        'Bad Boys' => 'Action & Adventure',
        'Die Hard' => 'Action & Adventure',
        'Pirates of the Caribbean' => 'Action & Adventure',
        'Indiana Jones' => 'Action & Adventure',
        'The Dark Knight' => 'Action & Adventure',
        'Mad Max' => 'Action & Adventure',
        'Bourne' => 'Action & Adventure',

        'Toy Story' => 'Animation',
        'Shrek' => 'Animation',
        'Ice Age' => 'Animation',
        'Despicable Me' => 'Animation',
        'Kung Fu Panda' => 'Animation',
        'How to Train Your Dragon' => 'Animation',
        'Cars' => 'Animation',
        'Spider-Verse' => 'Animation',

        'The Hangover' => 'Comedy',

        'The Godfather' => 'Crime & Mystery',
        'Knives Out' => 'Crime & Mystery',

        'Harry Potter' => 'Fantasy',
        'The Lord of the Rings' => 'Fantasy',
        'The Hobbit' => 'Fantasy',
        'Fantastic Beasts' => 'Fantasy',
        'The Chronicles of Narnia' => 'Fantasy',

        'Saw' => 'Horror & Thriller',
        'The Conjuring' => 'Horror & Thriller',
        'Insidious' => 'Horror & Thriller',
        'Scream' => 'Horror & Thriller',
        'Halloween' => 'Horror & Thriller',
        'A Quiet Place' => 'Horror & Thriller',
        'Alien' => 'Horror & Thriller',
        'Predator' => 'Horror & Thriller',

        'The Matrix' => 'Sci-Fi',
        'Star Wars' => 'Sci-Fi',
        'Star Trek' => 'Sci-Fi',
        'Dune' => 'Sci-Fi',
        'Back to the Future' => 'Sci-Fi',
        'Planet of the Apes' => 'Sci-Fi',
        'Men in Black' => 'Sci-Fi',
        'Jurassic Park' => 'Sci-Fi',
        'The Terminator' => 'Sci-Fi',

        'The Twilight Saga' => 'Romance',
    ];

    public function resolveGenres(string $title, ?string $collection = null, array $existing = []): array
    {
        if (! empty($existing)) {
            $primary = $existing[0] ?? 'Action & Adventure';
            $canonical = $this->normalizeToCanonical($primary);
            return [
                'primary' => $canonical,
                'joined' => count($existing) > 1 ? "{$canonical} & " . $this->normalizeToCanonical($existing[1]) : $canonical,
                'all' => array_map([$this, 'normalizeToCanonical'], $existing),
            ];
        }

        $cleanTitle = strtolower(trim(preg_replace('/[^a-z0-9\s]/i', '', $title)));

        // 1. Franchise Inheritance
        if ($collection && isset($this->franchiseGenreMap[$collection])) {
            $genre = $this->franchiseGenreMap[$collection];
            return ['primary' => $genre, 'joined' => $genre, 'all' => [$genre]];
        }

        // 2. Offline Database Exact & Substring Match
        foreach ($this->offlineTitleDb as $key => $genre) {
            if ($cleanTitle === $key || str_starts_with($cleanTitle, $key) || str_contains($cleanTitle, $key)) {
                return ['primary' => $genre, 'joined' => $genre, 'all' => [$genre]];
            }
        }

        // 3. Keyword / Heuristic analysis
        $heuristic = $this->classifyByKeywords($cleanTitle);
        if ($heuristic) {
            return ['primary' => $heuristic, 'joined' => $heuristic, 'all' => [$heuristic]];
        }

        // 4. Online Zero-Key Fallback (TVMaze API)
        $onlineGenre = $this->fetchFromTVMaze($cleanTitle);
        if ($onlineGenre) {
            return ['primary' => $onlineGenre, 'joined' => $onlineGenre, 'all' => [$onlineGenre]];
        }

        // 5. Optional Gemini Pro classification if API Key configured
        $geminiGenre = $this->classifyWithGemini($title);
        if ($geminiGenre) {
            return ['primary' => $geminiGenre, 'joined' => $geminiGenre, 'all' => [$geminiGenre]];
        }

        // Default to Action & Adventure instead of vague General
        return ['primary' => 'Action & Adventure', 'joined' => 'Action & Adventure', 'all' => ['Action & Adventure']];
    }

    public function normalizeToCanonical(string $rawGenre): string
    {
        $g = strtolower(trim($rawGenre));
        if (str_contains($g, 'anim') || str_contains($g, 'anime') || str_contains($g, 'cartoon')) return 'Animation';
        if (str_contains($g, 'comed') || str_contains($g, 'funny')) return 'Comedy';
        if (str_contains($g, 'fantas')) return 'Fantasy';
        if (str_contains($g, 'sci') || str_contains($g, 'science') || str_contains($g, 'fiction')) return 'Sci-Fi';
        if (str_contains($g, 'crime') || str_contains($g, 'myster') || str_contains($g, 'detective')) return 'Crime & Mystery';
        if (str_contains($g, 'horror') || str_contains($g, 'thrill') || str_contains($g, 'spooky') || str_contains($g, 'suspense')) return 'Horror & Thriller';
        if (str_contains($g, 'dram') || str_contains($g, 'hist') || str_contains($g, 'biograph') || str_contains($g, 'war')) return 'Drama & History';
        if (str_contains($g, 'roman') || str_contains($g, 'love')) return 'Romance';
        if (str_contains($g, 'docu')) return 'Documentary';
        if (str_contains($g, 'arab')) return 'Arabic';
        if (str_contains($g, 'india') || str_contains($g, 'bolly')) return 'Indian';
        if (str_contains($g, 'action') || str_contains($g, 'adventur')) return 'Action & Adventure';

        return 'Action & Adventure';
    }

    protected function classifyByKeywords(string $clean): string
    {
        if (preg_match('/\b(animation|animated|anime|cartoon|pokemon|naruto|one piece|dragon ball|pixar|disney)\b/i', $clean)) {
            return 'Animation';
        }
        if (preg_match('/\b(zombie|ghost|demon|evil|dead|haunted|blood|slasher|paranormal|creep|kill|dracula|vampire)\b/i', $clean)) {
            return 'Horror & Thriller';
        }
        if (preg_match('/\b(space|alien|galaxy|robot|cyber|future|matrix|time travel|apocalypse|star wars|planet)\b/i', $clean)) {
            return 'Sci-Fi';
        }
        if (preg_match('/\b(wizard|magic|dragon|quest|lord|sword|kingdom|witch|elf|sorcerer)\b/i', $clean)) {
            return 'Fantasy';
        }
        if (preg_match('/\b(murder|detective|cop|police|heist|gangster|mafia|fbi|cia|mystery|investigation)\b/i', $clean)) {
            return 'Crime & Mystery';
        }
        if (preg_match('/\b(comedy|funny|laugh|joke|parody|spoof)\b/i', $clean)) {
            return 'Comedy';
        }
        if (preg_match('/\b(war|soldier|battle|king|queen|emperor|revolution|historical|biography)\b/i', $clean)) {
            return 'Drama & History';
        }
        if (preg_match('/\b(love|romance|valentine|wedding|kiss|heart)\b/i', $clean)) {
            return 'Romance';
        }

        return '';
    }

    protected function fetchFromTVMaze(string $cleanTitle): ?string
    {
        $cacheKey = 'tvmaze_genre_' . md5($cleanTitle);
        return Cache::remember($cacheKey, now()->addDays(30), function () use ($cleanTitle) {
            try {
                $response = Http::timeout(2)->get('https://api.tvmaze.com/singlesearch/shows', ['q' => $cleanTitle]);
                if ($response->successful()) {
                    $data = $response->json();
                    $genres = $data['genres'] ?? [];
                    if (! empty($genres)) {
                        return $this->normalizeToCanonical($genres[0]);
                    }
                }
            } catch (\Throwable $e) {
            }
            return null;
        });
    }

    protected function classifyWithGemini(string $title): ?string
    {
        $apiKey = AppSetting::get('gemini_api_key') ?? config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
        if (empty($apiKey)) {
            return null;
        }

        $cacheKey = 'gemini_genre_' . md5(strtolower($title));
        return Cache::remember($cacheKey, now()->addDays(30), function () use ($apiKey, $title) {
            try {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey;
                $prompt = "Classify this movie or TV show title: '{$title}'. Choose ONLY ONE genre from: Action & Adventure, Animation, Comedy, Crime & Mystery, Documentary, Drama & History, Fantasy, Horror & Thriller, Romance, Sci-Fi, Arabic, Indian. Output ONLY the chosen genre name.";
                
                $response = Http::timeout(3)->post($url, [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ]
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $text = trim($json['candidates'][0]['content']['parts'][0]['text'] ?? '');
                    if ($text) {
                        return $this->normalizeToCanonical($text);
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('Gemini classification error: ' . $e->getMessage());
            }
            return null;
        });
    }
}
