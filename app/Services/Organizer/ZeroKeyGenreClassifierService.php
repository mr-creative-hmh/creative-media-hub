<?php

namespace App\Services\Organizer;

class ZeroKeyGenreClassifierService
{
    /**
     * Official Canonical TMDB & Regional Genres.
     */
    public const GENRES = [
        'Arabic',
        'Indian',
        'Animation',
        'Action',
        'Adventure',
        'Comedy',
        'Crime',
        'Documentary',
        'Drama',
        'Family',
        'Fantasy',
        'History',
        'Horror',
        'Music',
        'Mystery',
        'Romance',
        'Science Fiction',
        'Thriller',
        'War',
        'Western',
    ];

    /**
     * Offline known titles mapping directly to canonical genres.
     */
    protected array $offlineTitleDb = [
        // Action
        'the dark knight' => 'Action',
        'the dark knight rises' => 'Action',
        'batman begins' => 'Action',
        'the batman' => 'Action',
        'mad max fury road' => 'Action',
        'furiosa' => 'Action',
        'furiosa a mad max saga' => 'Action',
        'die hard' => 'Action',
        'john wick' => 'Action',
        'gladiator' => 'Action',
        'gladiator ii' => 'Action',
        'top gun maverick' => 'Action',
        'mission impossible' => 'Action',
        'fast and furious' => 'Action',
        'furious 7' => 'Action',
        'fast x' => 'Action',
        'transformers' => 'Action',
        'bad boys' => 'Action',
        'bad boys ride or die' => 'Action',
        'twisters' => 'Action',
        'fall guy' => 'Action',
        'the fall guy' => 'Action',
        'monkey man' => 'Action',
        'godzilla x kong' => 'Action',
        'kingdom of the planet of the apes' => 'Action',
        '300' => 'Action',
        '300 rise of an empire' => 'Action',
        'the beekeeper' => 'Action',
        'wrath of man' => 'Action',
        'the gray man' => 'Action',
        'operation fortune' => 'Action',
        'the equalizer' => 'Action',
        'the expendables' => 'Action',
        'nobody' => 'Action',
        'bloodshot' => 'Action',
        'kate' => 'Action',

        // Animation (Dominates Action, Adventure, Family, Comedy)
        'shrek' => 'Animation',
        'toy story' => 'Animation',
        'finding nemo' => 'Animation',
        'finding dory' => 'Animation',
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
        'despicable me 2' => 'Animation',
        'despicable me 3' => 'Animation',
        'despicable me 4' => 'Animation',
        'minions' => 'Animation',
        'minions the rise of gru' => 'Animation',
        'minions & monsters' => 'Animation',
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
        'the bad guys' => 'Animation',
        'the bad guys 2' => 'Animation',
        'the garfield movie' => 'Animation',
        'garfield' => 'Animation',
        'hoppers' => 'Animation',
        'the twits' => 'Animation',
        'ice age' => 'Animation',
        'madagascar' => 'Animation',
        'puss in boots' => 'Animation',
        'puss in boots the last wish' => 'Animation',
        'cars' => 'Animation',
        'sing' => 'Animation',
        'the boss baby' => 'Animation',
        'hotel transylvania' => 'Animation',
        'elemental' => 'Animation',
        'elio' => 'Animation',
        'encanto' => 'Animation',
        'soul' => 'Animation',
        'luca' => 'Animation',
        'turning red' => 'Animation',
        'lightyear' => 'Animation',
        'trolls' => 'Animation',
        'spongebob' => 'Animation',
        'tom and jerry' => 'Animation',
        'the super mario' => 'Animation',
        'chicken run' => 'Animation',

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

        // Crime
        'the godfather' => 'Crime',
        'the godfather part ii' => 'Crime',
        'the godfather part iii' => 'Crime',
        'pulp fiction' => 'Crime',
        'goodfellas' => 'Crime',
        'the departed' => 'Crime',
        'breaking bad' => 'Crime',
        'better call saul' => 'Crime',
        'peaky blinders' => 'Crime',
        'the accountant' => 'Crime',
        'the irishman' => 'Crime',
        'scarface' => 'Crime',
        'casino' => 'Crime',

        // Mystery
        'knives out' => 'Mystery',
        'glass onion' => 'Mystery',
        'se7en' => 'Mystery',
        'zodiac' => 'Mystery',
        'shutter island' => 'Mystery',
        'prisoners' => 'Mystery',
        'sherlock holmes' => 'Mystery',
        'true detective' => 'Mystery',
        'the woman in cabin 10' => 'Mystery',

        // Drama
        'the shawshank redemption' => 'Drama',
        'forrest gump' => 'Drama',
        "schindler's list" => 'Drama',
        'fight club' => 'Drama',
        'the green mile' => 'Drama',
        'the prestige' => 'Drama',
        'whiplash' => 'Drama',
        'parasite' => 'Drama',
        'the wolf of wall street' => 'Drama',
        'catch me if you can' => 'Drama',
        'a beautiful mind' => 'Drama',
        'the pianist' => 'Drama',
        'killers of the flower moon' => 'Drama',
        'succession' => 'Drama',
        'the crown' => 'Drama',

        // War & History
        'oppenheimer' => 'History',
        '1917' => 'War',
        'dunkirk' => 'War',
        'saving private ryan' => 'War',
        'the zone of interest' => 'History',
        'band of brothers' => 'War',
        'chernobyl' => 'Drama',

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
        'the shining' => 'Horror',
        'psycho' => 'Horror',
        'alien' => 'Horror',
        'the thing' => 'Horror',
        'halloween' => 'Horror',
        'a nightmare on elm street' => 'Horror',
        'scream' => 'Horror',
        'saw' => 'Horror',
        'the conjuring' => 'Horror',
        'insidious' => 'Horror',
        'hereditary' => 'Horror',
        'midsommar' => 'Horror',
        'get out' => 'Horror',
        'a quiet place' => 'Horror',
        'a quiet place day one' => 'Horror',
        'longlegs' => 'Horror',
        'smile' => 'Horror',
        'smile 2' => 'Horror',
        'alien romulus' => 'Horror',
        'stranger things' => 'Horror',
        'the last of us' => 'Horror',
        'the menu' => 'Horror',

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

        // Science Fiction
        'interstellar' => 'Science Fiction',
        'inception' => 'Science Fiction',
        'the matrix' => 'Science Fiction',
        'blade runner' => 'Science Fiction',
        'blade runner 2049' => 'Science Fiction',
        '2001 a space odyssey' => 'Science Fiction',
        'star wars' => 'Science Fiction',
        'dune' => 'Science Fiction',
        'dune part two' => 'Science Fiction',
        'the martian' => 'Science Fiction',
        'arrival' => 'Science Fiction',
        'ex machina' => 'Science Fiction',
        'avatar' => 'Science Fiction',
        'avatar the way of water' => 'Science Fiction',
        'tenet' => 'Science Fiction',
        'the creator' => 'Science Fiction',
        'black mirror' => 'Science Fiction',
        'severance' => 'Science Fiction',
        'fallout' => 'Science Fiction',
        '3 body problem' => 'Science Fiction',

        'the wolf of wall street' => 'Crime',
        'houdini' => 'History',
        'up in the air' => 'Drama',
        'upgraded' => 'Romance',
        'pokemon detective pikachu' => 'Fantasy',
        'detective pikachu' => 'Fantasy',

        // Arabic
        'bahebek' => 'Arabic',
        'captain hema' => 'Arabic',
        'captain hima' => 'Arabic',
        'el badla' => 'Arabic',
        'the suit' => 'Arabic',
        'omar and salma' => 'Arabic',
        'nour einy' => 'Arabic',
        'light of my eyes' => 'Arabic',
        'sayed el atefy' => 'Arabic',
        'romantic sayed' => 'Arabic',
        'taj' => 'Arabic',
        'tesbah ala kheir' => 'Arabic',
        'goodnight' => 'Arabic',
        'west beirut' => 'Arabic',
        'al fussool al arbaa' => 'Arabic',
        'bab al-hara' => 'Arabic',
        'al hayba' => 'Arabic',
        'maraya' => 'Arabic',
        'al ikhtiyar' => 'Arabic',
        'rashash' => 'Arabic',
        'kira wal jin' => 'Arabic',
        'dungeon halab' => 'Arabic',
        'fanya wa tatabadad' => 'Arabic',
        'decaying and vanishing' => 'Arabic',
        'syrians' => 'Arabic',
        'habbit loulou' => 'Arabic',
        'maryam' => 'Arabic',
        'mariam' => 'Arabic',

        // Indian
        '3 idiots' => 'Indian',
        'dhoom' => 'Indian',
        'pk' => 'Indian',
        'bajrangi bhaijaan' => 'Indian',
        'dangal' => 'Indian',
        'housefull' => 'Indian',
        'mardaani' => 'Indian',
        'ra one' => 'Indian',
        'ra.one' => 'Indian',
        'dilwale' => 'Indian',
        'chennai express' => 'Indian',
        'pathaan' => 'Indian',
        'jawan' => 'Indian',
        'rrr' => 'Indian',
        'baahubali' => 'Indian',
        '102 not out' => 'Indian',
        'holiday' => 'Indian',
        'holiday a soldier is never off duty' => 'Indian',
    ];

    /**
     * Known franchise to canonical genre mapping.
     */
    protected array $franchiseGenreMap = [
        'Fast & Furious' => 'Action',
        'Fast and the Furious' => 'Action',
        'The Fast and the Furious' => 'Action',
        'Transformers' => 'Action',
        'Marvel Cinematic Universe' => 'Action',
        'Mission: Impossible' => 'Action',
        'James Bond 007' => 'Action',
        'John Wick' => 'Action',
        'Bad Boys' => 'Action',
        'Die Hard' => 'Action',
        'Pirates of the Caribbean' => 'Adventure',
        'Indiana Jones' => 'Adventure',
        'The Dark Knight' => 'Action',
        'Mad Max' => 'Action',
        'Bourne' => 'Action',
        '300' => 'Action',
        'Ip Man' => 'Action',
        'Kingsman' => 'Action',
        'Rambo' => 'Action',
        'The Expendables' => 'Action',
        'Mechanic' => 'Action',
        'Spider-Man' => 'Action',
        'Spider-Man (MCU)' => 'Action',

        // Animation Franchises
        'Toy Story' => 'Animation',
        'Shrek' => 'Animation',
        'Ice Age' => 'Animation',
        'Despicable Me' => 'Animation',
        'Kung Fu Panda' => 'Animation',
        'How to Train Your Dragon' => 'Animation',
        'Cars' => 'Animation',
        'Spider-Verse' => 'Animation',
        'Minions' => 'Animation',
        'Moana' => 'Animation',
        'The Bad Guys' => 'Animation',
        'The Garfield Movie' => 'Animation',
        'The Wild Robot' => 'Animation',
        'Hotel Transylvania' => 'Animation',
        'Inside Out' => 'Animation',
        'Finding Nemo' => 'Animation',
        'Madagascar' => 'Animation',
        'Puss in Boots' => 'Animation',
        'Demon Slayer - Kimetsu no Yaiba' => 'Animation',
        'Demon Slayer: Kimetsu no Yaiba' => 'Animation',
        'Tom and Jerry' => 'Animation',
        'The Smurfs' => 'Animation',
        'SpongeBob' => 'Animation',
        'Zootopia' => 'Animation',

        'The Hangover' => 'Comedy',

        'The Godfather' => 'Crime',
        'Knives Out' => 'Mystery',
        'Enola Holmes' => 'Mystery',
        'Now You See Me' => 'Crime',

        'Harry Potter' => 'Fantasy',
        'The Lord of the Rings' => 'Fantasy',
        'The Hobbit' => 'Fantasy',
        'Fantastic Beasts' => 'Fantasy',
        'The Chronicles of Narnia' => 'Fantasy',
        'Wonka' => 'Fantasy',
        'Ghostbusters' => 'Fantasy',

        'Saw' => 'Horror',
        'The Conjuring' => 'Horror',
        'Insidious' => 'Horror',
        'Scream' => 'Horror',
        'Halloween' => 'Horror',
        'A Quiet Place' => 'Horror',
        'Alien' => 'Horror',
        'Predator' => 'Horror',
        'The Shining' => 'Horror',
        'Sijjin' => 'Horror',
        'Talk to Me' => 'Horror',
        'The Backrooms' => 'Horror',
        'Backrooms' => 'Horror',
        'Ready or Not' => 'Horror',
        'Crawl' => 'Horror',
        'Don\'t Breathe' => 'Horror',
        'The Boy' => 'Horror',
        'Weapons' => 'Horror',
        'X' => 'Horror',

        'The Matrix' => 'Science Fiction',
        'Star Wars' => 'Science Fiction',
        'Star Trek' => 'Science Fiction',
        'Dune' => 'Science Fiction',
        'Back to the Future' => 'Science Fiction',
        'Planet of the Apes' => 'Science Fiction',
        'Men in Black' => 'Science Fiction',
        'Jurassic Park' => 'Science Fiction',
        'The Terminator' => 'Science Fiction',
        'The Hunger Games' => 'Science Fiction',
        'X-Men' => 'Science Fiction',

        'The Twilight Saga' => 'Romance',
        'Twilight' => 'Romance',
        'Fifty Shades' => 'Romance',
        '365 Days' => 'Romance',
        'The Kissing Booth' => 'Romance',

        'Unbreakable' => 'Thriller',
        'Ice Road' => 'Action',
        'Basic Instinct' => 'Thriller',

        'Minecraft Movie' => 'Family',
        'Sharkboy and Lavagirl' => 'Family',
        'Paddington' => 'Family',
        'Sonic the Hedgehog' => 'Family',
        'How to Train Your Dragon (Live-Action)' => 'Fantasy',
        'Lilo & Stitch (Live-Action)' => 'Fantasy',
        'Houdini' => 'History',

        'American Pie' => 'Comedy',
        'Daddy\'s Home' => 'Comedy',
        'Crazy Rich Asians' => 'Comedy',
        'Jackass' => 'Comedy',
        'Murder Mystery' => 'Comedy',
        'Ted' => 'Comedy',
        'My Spy' => 'Comedy',

        // Arabic Franchises
        'Omar and Salma' => 'Arabic',
        'Omar & Salma' => 'Arabic',

        // Indian Franchises
        'Dhoom' => 'Indian',
        'Housefull' => 'Indian',
        'Mardaani' => 'Indian',
        'Dabangg' => 'Indian',
        'Baby' => 'Indian',
        'Race' => 'Indian',
        'Raid' => 'Indian',
        'Tiger' => 'Indian',
        'Student of the Year' => 'Indian',
        'Taare Zameen Par' => 'Indian',
        '3 Idiots' => 'Indian',
        'Aashiqui' => 'Indian',
        'Goodachari' => 'Indian',
    ];

    /**
     * Resolve the dominant physical genre for disk directory placement.
     * Priority: Arabic > Indian > Animation > Canonical TMDB Genre
     */
    public function resolveDominantGenre(
        string $title,
        ?string $collection = null,
        array $existingGenres = [],
        array $parsed = []
    ): string {
        // Priority 1: Arabic
        if ($this->isArabic($title, $existingGenres, $parsed)) {
            return 'Arabic';
        }

        // Priority 2: Indian
        if ($this->isIndian($title, $existingGenres, $parsed)) {
            return 'Indian';
        }

        // Priority 3: Animation (Takes precedence over Action, Adventure, Family, Comedy)
        if ($this->isAnimation($title, $collection, $existingGenres, $parsed)) {
            return 'Animation';
        }

        // Priority 4: Franchise inheritance if known
        if ($collection) {
            $normColl = $this->cleanFranchiseName($collection);
            foreach ($this->franchiseGenreMap as $fName => $genre) {
                if (strcasecmp($normColl, $this->cleanFranchiseName($fName)) === 0) {
                    return $this->normalizeToCanonical($genre);
                }
            }
        }

        // Priority 5: Curated Offline Title DB lookup
        $cleanTitle = strtolower(trim(preg_replace('/[^a-z0-9\s]/i', '', $title)));
        $shortKeys = ['up', 'fan', 'ted', 'saw', 'cars', 'sing', 'soul', 'wish', 'flow', 'luca', 'dune', '1917', '300', 'x', 'pk', 'rrr'];
        foreach ($this->offlineTitleDb as $key => $genre) {
            if ($cleanTitle === $key) {
                return $this->normalizeToCanonical($genre);
            }
            if (! in_array($key, $shortKeys) && strlen($key) > 3 && str_starts_with($cleanTitle, $key)) {
                return $this->normalizeToCanonical($genre);
            }
        }

        // Priority 6: Evaluate existing genre list according to canonical specificity
        if (! empty($existingGenres)) {
            // Check if any genre in the list is Animation
            foreach ($existingGenres as $g) {
                if ($this->isAnimationGenreName($g)) {
                    return 'Animation';
                }
            }

            // Otherwise, pick the most specific canonical genre (avoiding overly generic Drama/Action if possible)
            foreach ($existingGenres as $g) {
                $canon = $this->normalizeToCanonical($g);
                if (! in_array($canon, ['Drama', 'Action'])) {
                    return $canon;
                }
            }

            return $this->normalizeToCanonical($existingGenres[0]);
        }

        // Priority 7: Secondary offline title matching (contains with word boundaries)
        foreach ($this->offlineTitleDb as $key => $genre) {
            if (! in_array($key, $shortKeys) && strlen($key) > 4 && preg_match('/\b'.preg_quote($key, '/').'\b/i', $cleanTitle)) {
                return $this->normalizeToCanonical($genre);
            }
        }

        // Priority 8: Keyword heuristics
        $heuristic = $this->classifyByKeywords($cleanTitle);
        if ($heuristic) {
            return $this->normalizeToCanonical($heuristic);
        }

        return 'Action';
    }

    /**
     * Resolve genres with detailed breakdown for plans and UI.
     */
    public function resolveGenres(string $title, ?string $collection = null, array $existing = []): array
    {
        $dominant = $this->resolveDominantGenre($title, $collection, $existing);
        $canonicalList = array_map([$this, 'normalizeToCanonical'], $existing);

        if (! in_array($dominant, $canonicalList)) {
            array_unshift($canonicalList, $dominant);
        }

        $allUnique = array_values(array_unique($canonicalList));
        $joined = count($allUnique) > 1 ? "{$allUnique[0]} & {$allUnique[1]}" : $allUnique[0];

        return [
            'primary' => $dominant,
            'joined' => $joined,
            'all' => $allUnique,
        ];
    }

    /**
     * Map any genre string to official canonical TMDB / Media Hub genre.
     */
    public function normalizeToCanonical(string $rawGenre): string
    {
        $g = strtolower(trim($rawGenre));

        if (str_contains($g, 'arab')) {
            return 'Arabic';
        }
        if (str_contains($g, 'india') || str_contains($g, 'bolly')) {
            return 'Indian';
        }
        if (str_contains($g, 'anim') || str_contains($g, 'anime') || str_contains($g, 'cartoon')) {
            return 'Animation';
        }
        if (str_contains($g, 'comed') || str_contains($g, 'funny')) {
            return 'Comedy';
        }
        if (str_contains($g, 'dram')) {
            return 'Drama';
        }
        if (str_contains($g, 'fantas')) {
            return 'Fantasy';
        }
        if (str_contains($g, 'sci') || str_contains($g, 'science')) {
            return 'Science Fiction';
        }
        if (str_contains($g, 'myster')) {
            return 'Mystery';
        }
        if (str_contains($g, 'crime') || str_contains($g, 'gangster') || str_contains($g, 'detective')) {
            return 'Crime';
        }
        if (str_contains($g, 'horror') || str_contains($g, 'spooky')) {
            return 'Horror';
        }
        if (str_contains($g, 'thrill') || str_contains($g, 'suspense')) {
            return 'Thriller';
        }
        if (str_contains($g, 'biograph')) {
            return 'History';
        }
        if (str_contains($g, 'hist')) {
            return 'History';
        }
        if (str_contains($g, 'war')) {
            return 'War';
        }
        if (str_contains($g, 'roman') || str_contains($g, 'love')) {
            return 'Romance';
        }
        if (str_contains($g, 'docu')) {
            return 'Documentary';
        }
        if (str_contains($g, 'family') || str_contains($g, 'kids')) {
            return 'Family';
        }
        if (str_contains($g, 'music') || str_contains($g, 'musical')) {
            return 'Music';
        }
        if (str_contains($g, 'western')) {
            return 'Western';
        }
        if (str_contains($g, 'adventur')) {
            return 'Adventure';
        }
        if (str_contains($g, 'action')) {
            return 'Action';
        }

        return 'Action';
    }

    /**
     * Check if item is of Arabic origin / language.
     */
    public function isArabic(string $title, array $genres = [], array $parsed = []): bool
    {
        if (preg_match('/\p{Arabic}/u', $title)) {
            return true;
        }

        $lang = strtolower($parsed['original_language'] ?? ($parsed['language'] ?? ''));
        if ($lang === 'ar' || $lang === 'arabic') {
            return true;
        }

        if (! empty($parsed['original_title']) && preg_match('/\p{Arabic}/u', (string) $parsed['original_title'])) {
            return true;
        }

        $country = strtoupper((string) ($parsed['origin_country'] ?? ''));
        if ($country && preg_match('/\b(EG|SA|SY|LB|AE|JO|IQ|KW|QA|OM|BH|YE|PS|SD|LY|TN|DZ|MA)\b/', $country) && $lang !== 'en') {
            return true;
        }

        foreach ($genres as $g) {
            if (stripos($g, 'arab') !== false) {
                return true;
            }
        }

        $lowerTitle = str_replace('&', 'and', strtolower($title));
        $arabicKeywords = [
            'bahebek', 'captain hema', 'captain hima', 'el badla', 'the suit', 'omar and salma',
            'omar & salma', 'nour einy', 'light of my eyes', 'sayed el atefy', 'romantic sayed',
            'taj', 'tesbah ala kheir', 'goodnight', 'west beirut', 'al fussool al arbaa', 'bab al-hara',
            'al hayba', 'maraya', 'al ikhtiyar', 'rashash', 'kira wal jin', 'dungeon halab',
            'fanya wa tatabadad', 'decaying and vanishing', 'syrians', 'habbit loulou', 'maryam', 'mariam',
        ];

        foreach ($arabicKeywords as $kw) {
            if (preg_match('/\b'.preg_quote($kw, '/').'\b/i', $lowerTitle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if item is of Indian / Bollywood origin / language.
     */
    public function isIndian(string $title, array $genres = [], array $parsed = []): bool
    {
        $lang = strtolower($parsed['original_language'] ?? ($parsed['language'] ?? ''));
        if (in_array($lang, ['hi', 'hindi', 'ta', 'tamil', 'te', 'telugu', 'ml', 'malayalam', 'kn', 'kannada', 'bn', 'bengali', 'pa', 'punjabi', 'mr', 'marathi', 'gu', 'gujarati'])) {
            return true;
        }

        $country = strtoupper((string) ($parsed['origin_country'] ?? ''));
        if ($country && str_contains($country, 'IN') && ! in_array($lang, ['en', 'es', 'ar'])) {
            return true;
        }

        foreach ($genres as $g) {
            if (stripos($g, 'india') !== false || stripos($g, 'bolly') !== false) {
                return true;
            }
        }

        $lowerTitle = strtolower($title);
        $indianKeywords = [
            '3 idiots', 'dhoom', 'pk', 'bajrangi bhaijaan', 'dangal', 'housefull', 'mardaani',
            'ra one', 'ra.one', 'dilwale', 'chennai express', 'pathaan', 'jawan', 'rrr', 'baahubali',
            'aashiqui', 'ae dil hai mushkil', 'bang bang', 'bbuddah', 'bhooth bangla', 'dabangg',
            'dunki', 'fan', 'gabbar', 'ghajini', 'goodachari', 'happy new year', 'hichki',
            'holiday a soldier', 'holiday', '102 not out', 'jab harry met sejal', 'jab tak hai jaan', 'kick', 'mission mangal',
            'naam shabana', 'pyaar impossible', 'rab ne bana di jodi', 'race 3', 'raid 2',
            'secret superstar', 'sitaare zameen par', 'student of the year', 'super 30', 'the white tiger',
            'the zoya factor', 'tiger zinda hai', 'tu yaa main', 'zero',
        ];

        foreach ($indianKeywords as $kw) {
            if (preg_match('/\b'.preg_quote($kw, '/').'\b/i', $lowerTitle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if item is Animation / Anime / Cartoon.
     */
    public function isAnimation(string $title, ?string $collection = null, array $genres = [], array $parsed = []): bool
    {
        $cleanT = strtolower($title);
        $coll = strtolower($collection ?? '');

        // 1. Explicit Exclusions (Live Action films or Miniseries)
        if (str_contains($cleanT, 'detective pikachu')) {
            return false;
        }
        if (str_contains($cleanT, 'up in the air')) {
            return false;
        }
        if (str_contains($cleanT, 'upgraded')) {
            return false;
        }
        if (str_contains($cleanT, 'houdini')) {
            return false;
        }
        if (str_contains($coll, 'live-action') || str_contains($cleanT, 'live-action')) {
            return false;
        }

        // 2. Existing genre verification
        foreach ($genres as $g) {
            if ($this->isAnimationGenreName($g)) {
                return true;
            }
        }

        $combined = strtolower("{$title} ".($collection ?? ''));

        // Known animation studios or tags
        if (preg_match('/\b(pixar|dreamworks animation|illumination|walt disney animation|studio ghibli|ghibli|anime|cartoon|animated)\b/i', $combined)) {
            return true;
        }

        // Short single-word titles that must match title closely
        $exactTitles = ['up', 'sing', 'soul', 'wish', 'cars', 'trolls', 'luck', 'vivo', 'flow', 'coco'];
        $cleanTitleOnly = strtolower(trim(preg_replace('/[^a-z0-9\s]/i', '', $title)));
        foreach ($exactTitles as $et) {
            if ($cleanTitleOnly === $et || preg_match('/^'.$et.'(?:\s+\d+|\s+two|\s+three)?$/i', $cleanTitleOnly)) {
                return true;
            }
        }

        // Specific handling for Frozen (exclude Ghostbusters: Frozen Empire)
        if (preg_match('/\bfrozen(?:\s+ii|\s+2)?\b/i', $title) && ! str_contains($combined, 'ghostbuster')) {
            return true;
        }

        // Known animated franchises / multi-word keywords
        $animatedKeywords = [
            'despicable me', 'minions', 'kung fu panda', 'moana', 'the bad guys', 'garfield',
            'the garfield movie', 'hoppers', 'the twits', 'the wild robot', 'toy story', 'shrek',
            'ice age', 'how to train your dragon', 'spider-verse', 'spider-man into the spider-verse',
            'spider-man across the spider-verse', 'inside out', 'zootopia', 'finding nemo',
            'finding dory', 'the lion king', 'spirited away', 'coco', 'wall-e', 'ratatouille',
            'madagascar', 'puss in boots', 'the boss baby', 'hotel transylvania',
            'elemental', 'elio', 'encanto', 'turning red', 'lightyear',
            'spongebob', 'tom and jerry', 'the super mario', 'chicken run', 'chickenhare',
            'cloudy with a chance of meatballs', 'demon slayer', 'dragonkeeper', 'fireheart',
            'moonbound', 'orion and the dark', 'paws of fury',
            'rons gone wrong', 'ruby gillman', 'rumble', 'spellbound', 'the addams family animated',
            'the mitchells vs the machines', 'the sea beast', 'the smurfs', 'the tigers apprentice',
            'thelma the unicorn', 'transformers one', 'arcane',
        ];

        foreach ($animatedKeywords as $kw) {
            if (preg_match('/\b'.preg_quote($kw, '/').'\b/i', $combined)) {
                return true;
            }
        }

        return false;
    }

    protected function isAnimationGenreName(string $raw): bool
    {
        $g = strtolower(trim($raw));

        return str_contains($g, 'anim') || str_contains($g, 'anime') || str_contains($g, 'cartoon');
    }

    public function cleanFranchiseName(string $name): string
    {
        return trim(preg_replace('/\b(collection|trilogy|saga|anthology|boxset)\b/i', '', $name));
    }

    public function getFranchiseGenreMap(): array
    {
        return $this->franchiseGenreMap;
    }

    protected function classifyByKeywords(string $clean): string
    {
        if (preg_match('/\b(animation|animated|anime|cartoon|pokemon|naruto|one piece|dragon ball|pixar|disney)\b/i', $clean)) {
            return 'Animation';
        }
        if (preg_match('/\b(zombie|ghost|demon|evil|dead|haunted|blood|slasher|paranormal|creep|kill|dracula|vampire)\b/i', $clean)) {
            return 'Horror';
        }
        if (preg_match('/\b(space|alien|galaxy|robot|cyber|future|matrix|time travel|apocalypse|star wars|planet)\b/i', $clean)) {
            return 'Science Fiction';
        }
        if (preg_match('/\b(wizard|magic|dragon|quest|lord|sword|kingdom|witch|elf|sorcerer)\b/i', $clean)) {
            return 'Fantasy';
        }
        if (preg_match('/\b(murder|detective|cop|police|heist|gangster|mafia|fbi|cia|mystery|investigation)\b/i', $clean)) {
            return 'Crime';
        }
        if (preg_match('/\b(comedy|funny|laugh|joke|parody|spoof)\b/i', $clean)) {
            return 'Comedy';
        }
        if (preg_match('/\b(war|soldier|battle|military|combat|frontline)\b/i', $clean)) {
            return 'War';
        }
        if (preg_match('/\b(biography|biopic|true story)\b/i', $clean)) {
            return 'History';
        }
        if (preg_match('/\b(love|romance|valentine|wedding|kiss|heart)\b/i', $clean)) {
            return 'Romance';
        }

        return '';
    }
}
