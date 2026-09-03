<?php

namespace App\Services\Subtitles;

class SubtitleLanguageDetectorService
{
    /**
     * Stop-word dictionaries for high-frequency subtitle dialogue.
     */
    protected static array $stopWords = [
        'ar' => [
            'في', 'من', 'على', 'إلى', 'أن', 'لا', 'ما', 'هذا', 'هذه', 'كان', 'كانت',
            'عن', 'مع', 'أنا', 'أنت', 'هو', 'هي', 'نحن', 'هم', 'يا', 'لقد', 'كل',
            'ماذا', 'لماذا', 'كيف', 'أين', 'نعم', 'شكرا', 'حسنا', 'الذي', 'التي',
            'ذلك', 'تلك', 'هنا', 'هناك', 'ليس', 'فقط', 'لكن', 'أيضا', 'حتى', 'إذا',
            'الآن', 'اليوم', 'أريد', 'أعرف', 'تعرف', 'أعتقد', 'يمكن', 'يجب', 'تعال',
            'رجاء', 'صحيح', 'شيء', 'أحد', 'واحد', 'سيد', 'سيدي', 'أخي', 'أمي', 'أبي',
        ],
        'en' => [
            'the', 'and', 'you', 'that', 'was', 'for', 'are', 'with', 'his', 'they',
            'this', 'have', 'from', 'one', 'had', 'word', 'but', 'not', 'what', 'all',
            'were', 'when', 'your', 'can', 'said', 'there', 'use', 'each', 'which', 'she',
            'how', 'their', 'will', 'other', 'about', 'out', 'many', 'then', 'them', 'these',
            'some', 'her', 'would', 'make', 'like', 'him', 'into', 'time', 'has', 'look',
            'two', 'more', 'write', 'go', 'see', 'number', 'no', 'way', 'could', 'people',
            'my', 'than', 'first', 'water', 'been', 'call', 'who', 'oil', 'its', 'now',
            'find', 'long', 'down', 'day', 'did', 'get', 'come', 'made', 'may', 'part',
            'okay', 'yeah', 'hello', 'yes', 'please', 'know', 'think', 'want', 'gonna',
        ],
        'fr' => [
            'le', 'la', 'les', 'de', 'des', 'du', 'un', 'une', 'et', 'est', 'que',
            'qui', 'dans', 'en', 'pour', 'pas', 'sur', 'ce', 'cette', 'il', 'ils',
            'elle', 'elles', 'nous', 'vous', 'avec', 'tout', 'tous', 'faire', 'mais',
            'comme', 'ou', 'si', 'leur', 'leurs', 'bien', 'oui', 'non', 'merci',
            'bonjour', 'pourquoi', 'comment', 'rien', 'ici', 'savoir', 'vouloir', 'suis',
            'sont', 'avoir', 'été', 'très', 'aussi', 'jamais', 'toujours', 'mon', 'ma', 'mes',
        ],
        'es' => [
            'de', 'la', 'que', 'el', 'en', 'y', 'a', 'los', 'se', 'del', 'las',
            'por', 'un', 'para', 'con', 'no', 'una', 'su', 'al', 'lo', 'como',
            'mas', 'pero', 'sus', 'le', 'ya', 'o', 'este', 'si', 'porque', 'esta',
            'entre', 'cuando', 'muy', 'sin', 'sobre', 'también', 'tambien', 'me', 'hasta',
            'hay', 'donde', 'quien', 'desde', 'todo', 'nos', 'hola', 'gracias', 'bueno',
            'adios', 'adiós', 'bien', 'nada', 'ahora', 'saber', 'creo', 'quiero', 'estoy',
        ],
        'de' => [
            'der', 'die', 'das', 'und', 'in', 'den', 'von', 'zu', 'dem', 'mit',
            'sich', 'des', 'auf', 'für', 'fuer', 'ist', 'im', 'nicht', 'eine', 'ein',
            'als', 'auch', 'es', 'an', 'werden', 'aus', 'er', 'hat', 'dass', 'sie',
            'nach', 'wird', 'bei', 'einer', 'um', 'am', 'sind', 'noch', 'wie', 'einem',
            'über', 'ueber', 'einen', 'so', 'ja', 'nein', 'danke', 'bitte', 'gut', 'hallo',
            'hier', 'warum', 'kann', 'ich', 'du', 'wir', 'ihr', 'haben', 'wissen', 'wollen',
        ],
        'it' => [
            'di', 'e', 'il', 'che', 'la', 'a', 'per', 'un', 'in', 'una',
            'mi', 'sono', 'ho', 'ma', 'non', 'ti', 'se', 'lo', 'da', 'come',
            'ci', 'questo', 'questa', 'qui', 'ha', 'bene', 'tutto', 'cosa', 'si', 'grazie',
            'ciao', 'perché', 'perche', 'quando', 'anche', 'molto', 'niente', 'adesso', 'dove',
            'mio', 'mia', 'tuo', 'tua', 'suo', 'sua', 'noi', 'voi', 'loro', 'stato',
        ],
        'pt' => [
            'de', 'a', 'o', 'que', 'e', 'do', 'da', 'em', 'um', 'para',
            'é', 'com', 'não', 'nao', 'uma', 'os', 'no', 'se', 'na', 'por',
            'mais', 'as', 'dos', 'como', 'mas', 'foi', 'ao', 'ele', 'das', 'tem',
            'à', 'seu', 'sua', 'ou', 'quando', 'muito', 'nos', 'já', 'ja', 'eu',
            'também', 'tambem', 'só', 'pelo', 'pela', 'obrigado', 'olá', 'ola', 'sim', 'você',
        ],
        'ru' => [
            'и', 'в', 'не', 'на', 'я', 'что', 'тот', 'быть', 'с', 'он',
            'а', 'как', 'это', 'по', 'но', 'к', 'у', 'ты', 'из', 'мы',
            'за', 'вы', 'все', 'ее', 'его', 'да', 'нет', 'спасибо', 'пожалуйста',
            'привет', 'почему', 'хорошо', 'ничего', 'знаю', 'хочу', 'сейчас', 'здесь', 'где',
        ],
        'tr' => [
            've', 'bir', 'bu', 'da', 'de', 'için', 'icin', 'ne', 'var', 'çok',
            'cok', 'mi', 'mı', 'mu', 'mü', 'ama', 'daha', 'ben', 'sen', 'o',
            'kadar', 'gibi', 'yok', 'ile', 'bana', 'sana', 'bunu', 'evet', 'hayır', 'hayir',
            'tamam', 'merhaba', 'teşekkürler', 'tesekkurler', 'lütfen', 'lutfen', 'neden', 'nasıl',
        ],
        'nl' => [
            'de', 'van', 'het', 'en', 'in', 'een', 'op', 'dat', 'te', 'voor',
            'zijn', 'is', 'niet', 'met', 'om', 'als', 'er', 'maar', 'aan', 'omdat',
            'ja', 'nee', 'dank', 'hallo', 'wat', 'wie', 'waar', 'waarom', 'hoe', 'goed',
        ],
    ];

    /**
     * Language metadata descriptor map (codes to full names and flags).
     */
    public static array $languageInfo = [
        'ar' => ['code' => 'ar', 'name_en' => 'Arabic', 'name_ar' => 'العربية', 'flag' => '🇸🇦', 'ext' => 'ar.srt'],
        'en' => ['code' => 'en', 'name_en' => 'English', 'name_ar' => 'الإنجليزية', 'flag' => '🇬🇧', 'ext' => 'en.srt'],
        'fr' => ['code' => 'fr', 'name_en' => 'French', 'name_ar' => 'الفرنسية', 'flag' => '🇫🇷', 'ext' => 'fr.srt'],
        'es' => ['code' => 'es', 'name_en' => 'Spanish', 'name_ar' => 'الإسبانية', 'flag' => '🇪🇸', 'ext' => 'es.srt'],
        'de' => ['code' => 'de', 'name_en' => 'German', 'name_ar' => 'الألمانية', 'flag' => '🇩🇪', 'ext' => 'de.srt'],
        'it' => ['code' => 'it', 'name_en' => 'Italian', 'name_ar' => 'الإيطالية', 'flag' => '🇮🇹', 'ext' => 'it.srt'],
        'pt' => ['code' => 'pt', 'name_en' => 'Portuguese', 'name_ar' => 'البرتغالية', 'flag' => '🇵🇹', 'ext' => 'pt.srt'],
        'ru' => ['code' => 'ru', 'name_en' => 'Russian', 'name_ar' => 'الروسية', 'flag' => '🇷🇺', 'ext' => 'ru.srt'],
        'tr' => ['code' => 'tr', 'name_en' => 'Turkish', 'name_ar' => 'التركية', 'flag' => '🇹🇷', 'ext' => 'tr.srt'],
        'nl' => ['code' => 'nl', 'name_en' => 'Dutch', 'name_ar' => 'الهولندية', 'flag' => '🇳🇱', 'ext' => 'nl.srt'],
        'ja' => ['code' => 'ja', 'name_en' => 'Japanese', 'name_ar' => 'اليابانية', 'flag' => '🇯🇵', 'ext' => 'ja.srt'],
        'ko' => ['code' => 'ko', 'name_en' => 'Korean', 'name_ar' => 'الكورية', 'flag' => '🇰🇷', 'ext' => 'ko.srt'],
        'zh' => ['code' => 'zh', 'name_en' => 'Chinese', 'name_ar' => 'الصينية', 'flag' => '🇨🇳', 'ext' => 'zh.srt'],
        'he' => ['code' => 'he', 'name_en' => 'Hebrew', 'name_ar' => 'العبرية', 'flag' => '🇮🇱', 'ext' => 'he.srt'],
        'el' => ['code' => 'el', 'name_en' => 'Greek', 'name_ar' => 'اليونانية', 'flag' => '🇬🇷', 'ext' => 'el.srt'],
        'und' => ['code' => 'und', 'name_en' => 'Unknown', 'name_ar' => 'غير محدد', 'flag' => '🌐', 'ext' => 'srt'],
    ];

    /**
     * Detect language from a subtitle file path or direct subtitle content.
     *
     * @return array{language: string, name_en: string, name_ar: string, flag: string, confidence: float, method: string}
     */
    public function detectLanguage(string $filePathOrContent, ?string $filenameHint = null): array
    {
        $raw = '';
        $filename = $filenameHint ?: '';

        if (@file_exists($filePathOrContent)) {
            $filename = $filenameHint ?: basename($filePathOrContent);
            // Read up to first 64KB for robust analysis
            $raw = (string) @file_get_contents($filePathOrContent, false, null, 0, 65536);
        } else {
            $raw = $filePathOrContent;
        }

        if (empty(trim($raw))) {
            return $this->buildResult('und', 0.0, 'empty_file');
        }

        // 1. Sanitize encoding to standard UTF-8
        $cleanUtf8 = $this->sanitizeToUtf8($raw);

        // 2. Extract dialogue text by stripping timestamps, numbers, and markup tags
        $dialogue = $this->extractDialogueText($cleanUtf8);
        if (empty(trim($dialogue))) {
            return $this->buildResult('und', 0.0, 'no_dialogue');
        }

        // 3. Script / Unicode block analysis
        // A. Arabic script check (Highest accuracy for Arabic media)
        preg_match_all('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $dialogue, $arChars);
        $arCharCount = count($arChars[0] ?? []);

        preg_match_all('/\p{L}/u', $dialogue, $allLetters);
        $totalLetterCount = max(1, count($allLetters[0] ?? []));

        $arRatio = $arCharCount / $totalLetterCount;

        if ($arRatio > 0.15 || $arCharCount > 30) {
            // Count Arabic stop words for double validation
            $arStopHits = $this->countStopWords($dialogue, 'ar');
            $confidence = min(0.99, max(0.75, 0.70 + ($arRatio * 0.25) + ($arStopHits * 0.02)));

            return $this->buildResult('ar', $confidence, 'arabic_script');
        }

        // B. Cyrillic script check (Russian / Ukrainian)
        preg_match_all('/[\x{0400}-\x{04FF}]/u', $dialogue, $cyrillicChars);
        $cyrillicCount = count($cyrillicChars[0] ?? []);
        if (($cyrillicCount / $totalLetterCount) > 0.20) {
            return $this->buildResult('ru', 0.95, 'cyrillic_script');
        }

        // C. East Asian scripts check (Japanese / Korean / Chinese)
        // Japanese Hiragana & Katakana
        preg_match_all('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $dialogue, $jaChars);
        if (count($jaChars[0] ?? []) > 20) {
            return $this->buildResult('ja', 0.95, 'japanese_kana');
        }

        // Korean Hangul
        preg_match_all('/[\x{AC00}-\x{D7AF}\x{1100}-\x{11FF}]/u', $dialogue, $koChars);
        if (count($koChars[0] ?? []) > 20) {
            return $this->buildResult('ko', 0.95, 'korean_hangul');
        }

        // Chinese CJK Han
        preg_match_all('/[\x{4E00}-\x{9FFF}]/u', $dialogue, $cjkChars);
        if (count($cjkChars[0] ?? []) > 30) {
            return $this->buildResult('zh', 0.92, 'chinese_cjk');
        }

        // D. Hebrew
        preg_match_all('/[\x{0590}-\x{05FF}]/u', $dialogue, $hebrewChars);
        if (count($hebrewChars[0] ?? []) > 20) {
            return $this->buildResult('he', 0.95, 'hebrew_script');
        }

        // E. Greek
        preg_match_all('/[\x{0370}-\x{03FF}]/u', $dialogue, $greekChars);
        if (count($greekChars[0] ?? []) > 20) {
            return $this->buildResult('el', 0.95, 'greek_script');
        }

        // 4. Latin Script Disambiguation via Stop-Words & Diacritics
        $latinScores = [];
        $words = preg_split('/[^\p{L}\']+/u', mb_strtolower($dialogue, 'UTF-8'));
        $words = array_filter($words, fn ($w) => mb_strlen($w, 'UTF-8') > 1);
        $wordFrequencies = array_count_values($words);

        foreach (self::$stopWords as $langCode => $dictionary) {
            if (in_array($langCode, ['ar', 'ru'])) {
                continue;
            } // Handled above

            $score = 0;
            foreach ($dictionary as $sw) {
                if (isset($wordFrequencies[$sw])) {
                    $score += $wordFrequencies[$sw];
                }
            }
            $latinScores[$langCode] = $score;
        }

        // Character heuristics
        if (preg_match('/[¿¡ñáéíóú]/iu', $dialogue)) {
            $latinScores['es'] = ($latinScores['es'] ?? 0) + 15;
        }
        if (preg_match('/[çàèùéêëîïôû]/iu', $dialogue)) {
            $latinScores['fr'] = ($latinScores['fr'] ?? 0) + 15;
        }
        if (preg_match('/[äöüß]/iu', $dialogue)) {
            $latinScores['de'] = ($latinScores['de'] ?? 0) + 15;
        }
        if (preg_match('/[ğışçöü]/iu', $dialogue)) {
            $latinScores['tr'] = ($latinScores['tr'] ?? 0) + 15;
        }
        if (preg_match('/[ãõçáéíóú]/iu', $dialogue)) {
            $latinScores['pt'] = ($latinScores['pt'] ?? 0) + 15;
        }

        arsort($latinScores);
        $topLang = array_key_first($latinScores);
        $topScore = $latinScores[$topLang] ?? 0;

        if ($topScore > 8) {
            $totalLatinScore = max(1, array_sum($latinScores));
            $confidence = min(0.98, max(0.60, round($topScore / $totalLatinScore, 2)));

            return $this->buildResult($topLang, $confidence, 'stopword_frequency');
        }

        // 5. Filename Tag Fallback if content is ambiguous (e.g. sound effects only [music playing])
        if (! empty($filename)) {
            $fnLang = $this->extractLanguageFromFilename($filename);
            if ($fnLang !== 'und') {
                return $this->buildResult($fnLang, 0.55, 'filename_fallback');
            }
        }

        // Default to English if Latin script is dominant but no specific stop-words matched
        if ($totalLetterCount > 30) {
            return $this->buildResult('en', 0.50, 'latin_default');
        }

        return $this->buildResult('und', 0.20, 'unknown');
    }

    /**
     * Clean and strip subtitle timecodes, headers, formatting tags, and cue numbers.
     */
    public function extractDialogueText(string $subtitleContent): string
    {
        // 1. Remove standard timecode lines (SRT / VTT)
        // e.g. 00:01:20,123 --> 00:01:23,456
        $text = preg_replace('/\d{1,2}:\d{2}:\d{2}[,\.]\d{3}\s*-->\s*\d{1,2}:\d{2}:\d{2}[,\.]\d{3}[^\r\n]*/', ' ', $subtitleContent);

        // 2. Remove ASS / SSA dialogue headers (e.g. Dialogue: 0,0:00:00.00,0:00:05.00,Default,,0,0,0,,)
        $text = preg_replace('/Dialogue:\s*[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]*,/i', ' ', $text);

        // 3. Remove formatting tags (HTML tags like <i>, <b>, <font>, and ASS overrides {\an8}, {\pos(x,y)})
        $text = preg_replace('/<[^>]+>/', ' ', $text);
        $text = preg_replace('/\{[^}]+\}/', ' ', $text);

        // 4. Remove standalone cue numbering lines (e.g. \n1\n or \n42\n)
        $text = preg_replace('/(?:\r?\n)\s*\d+\s*(?:\r?\n)/', ' ', $text);

        // 5. Collapse excessive whitespaces
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Count how many stop-words for a specific language occur in the dialogue.
     */
    protected function countStopWords(string $dialogue, string $langCode): int
    {
        $words = self::$stopWords[$langCode] ?? [];
        if (empty($words)) {
            return 0;
        }

        $hits = 0;
        $clean = ' '.mb_strtolower($dialogue, 'UTF-8').' ';
        foreach ($words as $sw) {
            if (mb_strpos($clean, ' '.$sw.' ', 0, 'UTF-8') !== false) {
                $hits++;
            }
        }

        return $hits;
    }

    /**
     * Accurately convert any raw subtitle text into valid UTF-8.
     */
    public function sanitizeToUtf8(string $raw): string
    {
        // 1. Strip UTF-8 BOM if present
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        // Strip UTF-16 LE BOM
        if (str_starts_with($raw, "\xFF\xFE")) {
            $converted = @mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
            if ($converted) {
                return $converted;
            }
        }
        // Strip UTF-16 BE BOM
        if (str_starts_with($raw, "\xFE\xFF")) {
            $converted = @mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
            if ($converted) {
                return $converted;
            }
        }

        // 2. Check if already valid UTF-8
        if (mb_check_encoding($raw, 'UTF-8')) {
            // Check if it might be CP1256 misread as UTF-8 (Arabic Windows encoding commonly used in subtitles)
            preg_match_all('/[\x{0600}-\x{06FF}]/u', $raw, $arMatches);
            if (count($arMatches[0] ?? []) > 5) {
                return $raw;
            }
        }

        // 3. Try Arabic CP1256 conversion first if contains typical CP1256 high-order bytes
        if (preg_match('/[\xC0-\xFE]/', $raw)) {
            $arabicAttempt = @iconv('WINDOWS-1256', 'UTF-8//IGNORE', $raw);
            if ($arabicAttempt) {
                preg_match_all('/[\x{0600}-\x{06FF}]/u', $arabicAttempt, $matches);
                if (count($matches[0] ?? []) > 15) {
                    return $arabicAttempt;
                }
            }
        }

        // 4. Auto detect encoding across common subtitle encodings supported by mbstring
        $detected = @mb_detect_encoding($raw, ['UTF-8', 'ISO-8859-6', 'Windows-1252', 'Windows-1251', 'ISO-8859-1', 'ASCII'], true);
        if ($detected && $detected !== 'UTF-8') {
            $res = @mb_convert_encoding($raw, 'UTF-8', $detected);
            if ($res) {
                return $res;
            }
        }

        // Fallback iconv sanitization
        $fallback = @iconv('UTF-8', 'UTF-8//IGNORE', $raw);

        return $fallback ?: $raw;
    }

    /**
     * Extract language token from filename (e.g. movie.ar.srt -> ar, movie.Arabic.srt -> ar).
     */
    public function extractLanguageFromFilename(string $filename): string
    {
        $base = strtolower(pathinfo($filename, PATHINFO_FILENAME));
        $tokens = preg_split('/[\._\-\s\[\]\(\)]+/', $base);

        $lookup = [
            'ar' => 'ar', 'ara' => 'ar', 'arabic' => 'ar', 'عربي' => 'ar',
            'en' => 'en', 'eng' => 'en', 'english' => 'en',
            'fr' => 'fr', 'fra' => 'fr', 'fre' => 'fr', 'french' => 'fr',
            'es' => 'es', 'spa' => 'es', 'spanish' => 'es',
            'de' => 'de', 'ger' => 'de', 'deu' => 'de', 'german' => 'de',
            'it' => 'it', 'ita' => 'it', 'italian' => 'it',
            'pt' => 'pt', 'por' => 'pt', 'portuguese' => 'pt',
            'ru' => 'ru', 'rus' => 'ru', 'russian' => 'ru',
            'tr' => 'tr', 'tur' => 'tr', 'turkish' => 'tr',
            'ja' => 'ja', 'jpn' => 'ja', 'japanese' => 'ja',
            'ko' => 'ko', 'kor' => 'ko', 'korean' => 'ko',
            'zh' => 'zh', 'chi' => 'zh', 'zho' => 'zh', 'chinese' => 'zh',
            'nl' => 'nl', 'dut' => 'nl', 'nld' => 'nl', 'dutch' => 'nl',
        ];

        foreach (array_reverse($tokens) as $tok) {
            $tok = trim($tok);
            if (isset($lookup[$tok])) {
                return $lookup[$tok];
            }
        }

        return 'und';
    }

    protected function buildResult(string $code, float $confidence, string $method): array
    {
        $info = self::$languageInfo[$code] ?? self::$languageInfo['und'];

        return [
            'language' => $info['code'],
            'name_en' => $info['name_en'],
            'name_ar' => $info['name_ar'],
            'flag' => $info['flag'],
            'ext' => $info['ext'],
            'confidence' => round($confidence, 2),
            'method' => $method,
        ];
    }
}
