# K-Lite Codec Pack LAV-Inspired Web Engine & Subtitle System Enhancement Plan

> Reference inspection grounded in installed **K-Lite Codec Pack** (`C:\Program Files (x86)\K-Lite Codec Pack`) via REA MCP binary inspection (`LAVSplitter.ax`, `LAVVideo.ax`, `LAVAudio.ax`, `Tools\mediainfo.exe`, `MPC-HC64\Shaders11`).

## 1. Executive Summary

This upgrade resolves four core areas across the Cinema Player and Subtitle subsystem:
1. **Player Subtitle Search Modal (`CinemaSubtitleSearchModal.vue` & `SubtitleController.php`)**: Restores Language filters (`All — AR First`, `Arabic`, `English`) and Source/Provider filters (`All Sources`, `SubSense`, `OpenSubtitles`, `SubDL`, `YTS`), restores Provider/Format/Rating/Download badges, fixes `subtitle_id`/`release`/`file_name`/`source` field alignment, and sorts Arabic results first.
2. **Default Subtitle Priority & Auto-Selection (`useCinemaSubtitles.ts`, `CinemaPlayer.vue`, `SubtitleController.php`)**: Always selects **Arabic (`ar`/`ara`)** by default with automatic **English (`en`/`eng`)** fallback when Arabic is absent (preventing `localStorage` fallback pollution), and automatically activates any newly downloaded subtitle immediately in the player.
3. **K-Lite LAV-Inspired Next-Gen Web Engine**:
   - Eliminates the synchronous blocking `ffprobe` (`findPrecedingKeyframe`) subprocess in `StreamMseService::buildFfmpegCommand()` that added 1.0s–3.5s of dead latency before FFmpeg even launched.
   - Enables `0.5s` micro-fragmented fMP4 (`-frag_duration 500000 -min_frag_duration 200000 -flush_packets 1`) and two-stage (`64 KB` -> `256 KB`) stdout pipe streaming for sub-200ms first-frame startup.
   - Implements K-Lite `LAVAudio.ax` ITU-R BS.775 stereo downmix (`-center_mix_level 0.707 -surround_mix_level 0.707 -lfe_mix_level 0.0`) so dialog in 5.1/7.1 AC3/EAC3/DTS streams stays clear.
   - Adds `0ms` in-buffer seeking in `MseBackendClient.seekTo()` + `120ms` out-of-buffer scrub debouncing in `useCinemaPlayback.ts`.
   - Prioritizes Direct HTTP 206 Range playback for browser-native MP4/WebM/MKV streams with automatic decode/no-audio fallback to the `'web'` MSE engine, plus registers K-Lite `mediainfo.exe` in `FfmpegLocatorService`.
4. **K-Lite MPC-HC Shader Presets (`CinemaPlayer.vue`)**: Adds `Standard`, `K-Lite Clarity (LumaSharpen + 0-255 Full Range)`, `HDR Vivid`, and `Night Vision` shader presets modeled on `MPC-HC64\Shaders11`.

---

## 2. REA Investigation Summary (K-Lite Codec Pack Architecture)

| K-Lite Binary / Filter | REA Evidence Digest (`SHA-256`) | Architectural Principle Applied to Web Engine |
| :--- | :--- | :--- |
| `Filters\LAV\LAVSplitter.ax` | `cb0e0a23deb584b913cd2e1aa6ec3a2bf43b240db8844e2102033cc0ba3825ec` | Seeks directly via container `Cues`/`moov` index without external pre-probing subprocesses; enforces `ar,ara;en,eng` track priority. |
| `Filters\LAV\LAVVideo.ax` | `c32d237dc5b0cf655e38ece522a06f776d37cdecd89cae8a005fa0af00ac7379` | Direct bitstream pass-through (`-c:v copy` / Direct HTTP 206) when supported by browser/GPU, avoiding CPU re-encoding. |
| `Filters\LAV\LAVAudio.ax` | `4ba93498905fb438606b33dca622c824e52612696d3e365d8f162b29a0d1d124` | Downmixes 5.1/7.1 surround to 2.0 stereo with `0.707` Center/Surround mix coefficients and async resampling. |
| `Tools\mediainfo.exe` & `Shaders11` | Installed K-Lite Tooling | Fast metadata probe binary location + SVG/CSS LumaSharpen and `16-235 -> 0-255` contrast expansion presets. |

---

## 3. Target Files & Verification

- `app/Http/Controllers/SubtitleController.php`
- `app/Services/Media/FfmpegLocatorService.php`
- `app/Services/Streaming/StreamMseService.php`
- `app/Services/Streaming/StreamRemuxService.php`
- `resources/js/components/player/CinemaSubtitleSearchModal.vue`
- `resources/js/components/player/CinemaSubtitlesModal.vue`
- `resources/js/components/player/CinemaPlayer.vue`
- `resources/js/composables/player/useCinemaSubtitles.ts`
- `resources/js/composables/player/useCinemaPlayback.ts`
- `resources/js/lib/player/codecCapabilities.ts`
- `resources/js/lib/player/webEngine/mseBackendClient.ts`
- `tests/Feature/StreamingAndRoutesTest.php`

**Verification Commands**:
- `php vendor/bin/pint --dirty --format agent`
- `php artisan test --compact`
- `npm run types:check`
- `npm run build`
