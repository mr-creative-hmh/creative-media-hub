<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import {
    BookOpen, Search, Sparkles, Film, Tv, FolderSync,
    ScanLine, Subtitles, Play, ShieldCheck, Database,
    Layers, Sliders, Cpu, HelpCircle, ArrowRight, ArrowLeft,
    CheckCircle2, Terminal, Info, ExternalLink
} from 'lucide-vue-next';

const { t, isRTL } = useI18n();

const activeTab = ref<'getting_started' | 'scanner_vs_organizer' | 'metadata_providers' | 'player_streaming' | 'naming_rules' | 'faq'>('getting_started');
const searchQuery = ref('');

const tabs = [
    { key: 'getting_started', icon: Sparkles, nameAr: '١. البداية ونظرة عامة', nameEn: '1. Getting Started' },
    { key: 'scanner_vs_organizer', icon: FolderSync, nameAr: '٢. الفاحص مقابل المنظم', nameEn: '2. Scanner vs Organizer' },
    { key: 'metadata_providers', icon: Database, nameAr: '٣. مجمّع الميتاداتا ومزودات API', nameEn: '3. Metadata Providers' },
    { key: 'player_streaming', icon: Play, nameAr: '٤. المشغل والبث والصوتيات', nameEn: '4. Player & Streaming' },
    { key: 'naming_rules', icon: Layers, nameAr: '٥. معايير تسمية الملفات', nameEn: '5. Scene Naming Rules' },
    { key: 'faq', icon: HelpCircle, nameAr: '٦. الأسئلة الشائعة والحلول', nameEn: '6. FAQ & Troubleshooting' },
];
</script>

<template>
    <Head :title="isRTL ? 'دليل المستخدم والتوثيق' : 'Documentation & User Guide'" />

    <AppLayout v-slot="{ play }">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 flex items-center justify-center">
                    <BookOpen class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                        {{ isRTL ? 'دليل المستخدم والتوثيق التقني' : 'Documentation & User Guide' }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                        {{ isRTL ? 'مرجع شامل لكل ميزات المكتبة، استراتيجيات التنظيم، محرك البث، وفاحص الميتاداتا الذكي.' : 'Complete technical guide and workflow reference for Creative Media Streaming Library.' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Documentation Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Navigation Sidebar -->
            <div class="lg:col-span-1 space-y-2">
                <div class="glass-panel rounded-3xl p-4 border border-white/10 space-y-1 sticky top-20">
                    <div class="px-3 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400 border-b border-white/10 mb-1">
                        {{ isRTL ? 'فهرس الموضوعات' : 'Table of Contents' }}
                    </div>

                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="activeTab = tab.key as any"
                        class="w-full px-3 py-2.5 rounded-xl text-xs font-bold text-left flex items-center gap-3 transition-all cursor-pointer"
                        :class="activeTab === tab.key
                            ? 'bg-cyan-500 text-slate-950 font-black shadow-lg shadow-cyan-500/20'
                            : 'text-slate-400 hover:text-white hover:bg-white/5'"
                    >
                        <component :is="tab.icon" class="w-4 h-4 shrink-0" />
                        <span class="truncate">{{ isRTL ? tab.nameAr : tab.nameEn }}</span>
                    </button>
                </div>
            </div>

            <!-- Content Panel -->
            <div class="lg:col-span-3">
                <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-8">
                    <!-- 1. GETTING STARTED -->
                    <div v-if="activeTab === 'getting_started'" class="space-y-6 animate-in fade-in">
                        <div class="border-b border-white/10 pb-4">
                            <h2 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                                <Sparkles class="w-6 h-6 text-cyan-400" />
                                <span>{{ isRTL ? 'البداية السريعة ونظرة عامة على النظام' : 'Quick Start & Architecture Overview' }}</span>
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                                {{ isRTL ? 'منظومة وسائط منزلية وسيرفر بث متكامل مبني ليعمل محلياً بسرعة البرق دون أي تبعات سحابية.' : 'A local-first cinema streaming hub designed for high-bitrate media and instant multi-provider scraping.' }}
                            </p>
                        </div>

                        <div class="space-y-4 text-xs sm:text-sm text-slate-300 leading-relaxed">
                            <div class="p-4 rounded-2xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-200 space-y-1">
                                <h4 class="font-bold flex items-center gap-2">
                                    <Info class="w-4 h-4 text-cyan-400" />
                                    <span>{{ isRTL ? 'المفهوم الأساسي للنظام' : 'Core System Philosophy' }}</span>
                                </h4>
                                <p class="text-xs text-slate-300 leading-relaxed">
                                    {{ isRTL ? 'تم بناء النظام ليفصل تماماً بين "الفهرسة الافتراضية لقراءة الوسائط دون لمس الملفات" وبين "إعادة تنظيم القرص الصلب وتسميته". يتيح لك النظام فحص أي مسار على حاسوبك أو خادمك المنزلي وجلب بوسترات 4K باللغتين العربية والإنجليزية وترجمات فورية.' : 'Creative Media Streaming Library cleanly separates virtual metadata indexing from physical disk restructuring. You get zero-touch media exploration alongside powerful on-demand disk organization.' }}
                                </p>
                            </div>

                            <h3 class="text-base font-extrabold text-white mt-4">{{ isRTL ? 'خطوات الإعداد في ٣ دقائق:' : 'Setup in 3 Simple Steps:' }}</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-2">
                                    <span class="w-6 h-6 rounded-full bg-cyan-500 text-slate-950 font-black text-xs flex items-center justify-center">1</span>
                                    <h4 class="font-bold text-sm text-white">{{ isRTL ? 'إضافة مسارات المراقبة' : 'Add Watch Folders' }}</h4>
                                    <p class="text-xs text-slate-400">{{ isRTL ? 'ادخل صفحة فاحص المكتبة (/scanner) وأضف مسار مجلد الأفلام أو التنزيلات.' : 'Go to Scanner (/scanner) and add your local or NAS media folder paths.' }}</p>
                                </div>

                                <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-2">
                                    <span class="w-6 h-6 rounded-full bg-indigo-500 text-white font-black text-xs flex items-center justify-center">2</span>
                                    <h4 class="font-bold text-sm text-white">{{ isRTL ? 'الفحص وجلب البوسترات' : 'Scan & Auto-Enrich' }}</h4>
                                    <p class="text-xs text-slate-400">{{ isRTL ? 'اضغط "بدء الفحص السريع". سيتولى النظام مطابقة العناوين وتنزيل البوسترات محلياً.' : 'Click Start Scan. The 7-tier metadata engine will download posters & backdrops locally.' }}</p>
                                </div>

                                <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-2">
                                    <span class="w-6 h-6 rounded-full bg-emerald-500 text-slate-950 font-black text-xs flex items-center justify-center">3</span>
                                    <h4 class="font-bold text-sm text-white">{{ isRTL ? 'المشاهدة والتحكم' : 'Stream & Enjoy' }}</h4>
                                    <p class="text-xs text-slate-400">{{ isRTL ? 'شاهد أفلامك مباشرة بدقة أصلية، تحكم بحجم ولون الترجمات، واستأنف المشاهدة من حيث وقفت.' : 'Stream directly in full quality with custom subtitle colors, sync offset, and resume points.' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. SCANNER VS ORGANIZER -->
                    <div v-else-if="activeTab === 'scanner_vs_organizer'" class="space-y-6 animate-in fade-in">
                        <div class="border-b border-white/10 pb-4">
                            <h2 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                                <FolderSync class="w-6 h-6 text-cyan-400" />
                                <span>{{ isRTL ? 'الفرق بين فاحص المكتبة ومنظم القرص الصلب' : 'Virtual Scanner vs. Physical Disk Organizer' }}</span>
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                                {{ isRTL ? 'فهم الفرق الجوهري لتجنب التعديل غير المقصود على بنية ملفاتك الأصلية.' : 'Understanding non-destructive virtual indexing vs physical disk relocation.' }}
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border border-white/10 rounded-2xl overflow-hidden">
                                <thead class="bg-white/5 text-slate-300 font-bold border-b border-white/10">
                                    <tr>
                                        <th class="p-3">{{ isRTL ? 'الميزة / المعيار' : 'Feature / Criterion' }}</th>
                                        <th class="p-3 text-cyan-400">{{ isRTL ? 'فاحص المكتبة الافتراضي (Scanner)' : 'Virtual Scanner (/scanner)' }}</th>
                                        <th class="p-3 text-indigo-400">{{ isRTL ? 'منظم القرص الفعلي (Disk Organizer)' : 'Physical Organizer (/organizer)' }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-slate-300">
                                    <tr>
                                        <td class="p-3 font-bold text-white">{{ isRTL ? 'التأثير على الملفات الأصلية' : 'Filesystem Mutation' }}</td>
                                        <td class="p-3 text-emerald-400 font-bold">❌ {{ isRTL ? 'صفر تعديل (قراءة فقط - آمن 100%)' : 'Read-only (Zero file changes)' }}</td>
                                        <td class="p-3 text-amber-400 font-bold">⚠️ {{ isRTL ? 'نقل أو نسخ وإعادة تسمية فعلية' : 'Physical move/copy and rename' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 font-bold text-white">{{ isRTL ? 'مكان تخزين البيانات' : 'Data Storage' }}</td>
                                        <td class="p-3">{{ isRTL ? 'قاعدة بيانات SQLite محلية فائقة السرعة' : 'Local SQLite database indices' }}</td>
                                        <td class="p-3">{{ isRTL ? 'مجلدات القرص الصلب الفعلية على نظام التشغيل' : 'Physical folders on your disk/NAS' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 font-bold text-white">{{ isRTL ? 'قوالب التسمية والتنظيم' : 'Naming Templates' }}</td>
                                        <td class="p-3">{{ isRTL ? 'عرض العناوين النظيفة داخل التطبيق' : 'Displays clean titles in UI' }}</td>
                                        <td class="p-3">{{ isRTL ? 'قوالب Plex, Jellyfin, Kodi أو مخصصة' : 'Plex, Jellyfin, Kodi, Flat, A-Z presets' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 font-bold text-white">{{ isRTL ? 'معالجة ملفات الترجمة' : 'Subtitle Handling' }}</td>
                                        <td class="p-3">{{ isRTL ? 'ربط تلقائي بالفيلم مع قراءة اللغة' : 'Auto-links .srt/.vtt with media' }}</td>
                                        <td class="p-3">{{ isRTL ? 'نقل وإعادة تسمية ملف الترجمة مع الفيلم' : 'Renames & moves sub with media file' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 3. METADATA PROVIDERS -->
                    <div v-else-if="activeTab === 'metadata_providers'" class="space-y-6 animate-in fade-in">
                        <div class="border-b border-white/10 pb-4">
                            <h2 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                                <Database class="w-6 h-6 text-cyan-400" />
                                <span>{{ isRTL ? 'سلسلة مزودات الميتاداتا وسرعة الاستجابة' : '7-Tier Metadata Scraper & Provider Priority' }}</span>
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                                {{ isRTL ? 'كيف يقوم النظام بجلب البوسترات والعناوين العربية حتى لو كانت بعض مفاتيح API غير متوفرة.' : 'How the multi-tier scraper automatically falls back across providers without failing.' }}
                            </p>
                        </div>

                        <div class="space-y-3 text-xs sm:text-sm text-slate-300">
                            <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="font-black text-cyan-400">1. TMDb (The Movie Database)</span>
                                    <span class="cinema-badge bg-cyan-500/20 text-cyan-300 border-cyan-500/30">Primary (Key Supported)</span>
                                </div>
                                <p class="text-xs text-slate-400">المصدر الرئيسي للأفلام والمسلسلات مع دعم كامل للعناوين والقصص باللغة العربية (ar-SA) وبوسترات 4K.</p>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="font-black text-indigo-400">2. TVMaze API</span>
                                    <span class="cinema-badge bg-indigo-500/20 text-indigo-300 border-indigo-500/30">Free / No Key Required</span>
                                </div>
                                <p class="text-xs text-slate-400">محرك مجاني دقيق جداً للمسلسلات التلفزيونية، الحلقات، مواعيد العرض، وأسماء مواسم المسلسلات العالمية.</p>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="font-black text-amber-400">3. OMDb API (Open Movie Database)</span>
                                    <span class="cinema-badge bg-amber-500/20 text-amber-300 border-amber-500/30">API Key Activated</span>
                                </div>
                                <p class="text-xs text-slate-400">جلب تقييمات IMDb المباشرة، تقييم Rotten Tomatoes، المخرجين، وطاقم الممثلين بدقة متناهية.</p>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="font-black text-rose-400">4. AniList GraphQL API</span>
                                    <span class="cinema-badge bg-rose-500/20 text-rose-300 border-rose-500/30">Free / Anime Dedicated</span>
                                </div>
                                <p class="text-xs text-slate-400">محرك متخصص لمسلسلات وأفلام الأنمي اليابانية، يدعم البحث بالعناوين الرومانية والإنجليزية واليابانية مع بوسترات أصلية عالية الدقة.</p>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="font-black text-emerald-400">5. Bing Visuals & Wikipedia / Wikidata API</span>
                                    <span class="cinema-badge bg-emerald-500/20 text-emerald-300 border-emerald-500/30">Zero Key Fallback</span>
                                </div>
                                <p class="text-xs text-slate-400">عند غياب أي مفتاح API، يقوم المحرك بالبحث في ويكيبيديا العربية والإنكليزية ومحرك بينغ للصور لجلب البوستر والقصة بدون أي انقطاع.</p>
                            </div>
                        </div>
                    </div>

                    <!-- 4. PLAYER & STREAMING -->
                    <div v-else-if="activeTab === 'player_streaming'" class="space-y-6 animate-in fade-in">
                        <div class="border-b border-white/10 pb-4">
                            <h2 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                                <Play class="w-6 h-6 text-cyan-400" />
                                <span>{{ isRTL ? 'المشغل السينمائي، الصوتيات والترجمة' : 'Cinema Player, Audio Compatibility & Subtitles' }}</span>
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                                {{ isRTL ? 'تفاصيل دعم صيغ الفيديو، تحويل الصوت الفوري (AAC)، واختصارات لوحة المفاتيح.' : 'Direct HTTP 206 streaming, real-time AAC audio compatibility, and gesture shortcuts.' }}
                            </p>
                        </div>

                        <div class="space-y-4 text-xs sm:text-sm text-slate-300">
                            <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-2">
                                <h4 class="font-bold text-white flex items-center gap-2">
                                    <Sliders class="w-4 h-4 text-cyan-400" />
                                    <span>{{ isRTL ? 'حل مشكلة انعدام الصوت في متصفحات الويب (AAC Transcode Mode)' : 'Silent Audio Fix (AAC Transcode Mode)' }}</span>
                                </h4>
                                <p class="text-xs text-slate-400 leading-relaxed">
                                    {{ isRTL ? 'بعض ملفات الفيديو تأتي بصوت محيطي EAC3 أو DTS أو AC3 لا تدعمه متصفحات الويب افتراضياً. عند مواجهة ملف بدون صوت، اضغط زر الصوت في المشغل واختر "تحويل الصوت إلى AAC (AAC Transcode)". يقوم السيرفر بتحويل مسار الصوت فورياً في الخلفية وبسرعة فائقة دون إعادة ترميز الفيديو.' : 'Certain browser engines cannot decode 6-channel EAC3 or DTS audio natively. Simply click the audio button in the player and switch to "AAC Transcode Mode" for instant background audio streaming.' }}
                                </p>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-2">
                                <h4 class="font-bold text-white flex items-center gap-2">
                                    <Subtitles class="w-4 h-4 text-indigo-400" />
                                    <span>{{ isRTL ? 'استوديو تخصيص مظهر الترجمات ومزامنتها' : 'Subtitle Styling Studio & Sync Offset' }}</span>
                                </h4>
                                <p class="text-xs text-slate-400 leading-relaxed">
                                    {{ isRTL ? 'يدعم المشغل خطوط عربية فائقة الوضوح (Cairo)، إمكانية تغيير حجم الخط (صغير، متوسط، كبير، ضخم)، تغيير لون الخط (أصفر سينمائي، أبيض، سماوي، أخضر)، مع إمكانية تأخير أو تقديم توقيت الترجمة بزيادات 0.5 ثانية لحل أي عدم تطابق.' : 'Customize subtitle font sizes, colors (cinema yellow, white, cyan, green), background contrast, and adjust sync timing offsets (-5s to +5s).' }}
                                </p>
                            </div>

                            <h3 class="text-base font-extrabold text-white mt-4">{{ isRTL ? 'اختصارات لوحة المفاتيح وإيماءات اللمس:' : 'Keyboard Shortcuts & Touch Gestures:' }}</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono text-xs">
                                <div class="p-2.5 rounded-xl bg-black/40 border border-white/10"><span class="text-cyan-400 font-bold">Space / K:</span> Play / Pause</div>
                                <div class="p-2.5 rounded-xl bg-black/40 border border-white/10"><span class="text-cyan-400 font-bold">← / J:</span> -10s Seek</div>
                                <div class="p-2.5 rounded-xl bg-black/40 border border-white/10"><span class="text-cyan-400 font-bold">→ / L:</span> +10s Seek</div>
                                <div class="p-2.5 rounded-xl bg-black/40 border border-white/10"><span class="text-cyan-400 font-bold">F:</span> Fullscreen</div>
                                <div class="p-2.5 rounded-xl bg-black/40 border border-white/10"><span class="text-cyan-400 font-bold">M:</span> Mute Audio</div>
                                <div class="p-2.5 rounded-xl bg-black/40 border border-white/10"><span class="text-cyan-400 font-bold">P:</span> Picture-in-Picture</div>
                                <div class="p-2.5 rounded-xl bg-black/40 border border-white/10"><span class="text-cyan-400 font-bold">↑ / ↓:</span> Volume +/-</div>
                                <div class="p-2.5 rounded-xl bg-black/40 border border-white/10"><span class="text-cyan-400 font-bold">Double Tap:</span> ±10s Seek</div>
                            </div>
                        </div>
                    </div>

                    <!-- 5. NAMING RULES -->
                    <div v-else-if="activeTab === 'naming_rules'" class="space-y-6 animate-in fade-in">
                        <div class="border-b border-white/10 pb-4">
                            <h2 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                                <Layers class="w-6 h-6 text-cyan-400" />
                                <span>{{ isRTL ? 'معايير تسمية الملفات ومطابقة Scene Releases' : 'Scene Release Naming Conventions & Rules' }}</span>
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                                {{ isRTL ? 'كيف يقوم المحلّل (Parser) باستخراج العنوان وسنة الإنتاج والموسم والحلقة بدقة 100%.' : 'How the SceneNameParserService extracts clean titles, years, season numbers, and codecs.' }}
                            </p>
                        </div>

                        <div class="space-y-3 text-xs sm:text-sm text-slate-300">
                            <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-2">
                                <span class="font-bold text-cyan-400">🎬 {{ isRTL ? 'تسمية الأفلام القياسية' : 'Standard Movie Formats' }}</span>
                                <div class="p-2.5 rounded-xl bg-black/40 font-mono text-xs text-slate-300 space-y-1">
                                    <div>Gladiator.II.2024.1080p.WEBRip.x264-MEDIA.mkv <span class="text-emerald-400">→ Gladiator II (2024) [1080p]</span></div>
                                    <div>The.Matrix.1999.REMASTERED.2160p.UHD.HDR.HEVC-GROUP.mkv <span class="text-emerald-400">→ The Matrix (1999) [4K]</span></div>
                                </div>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-2">
                                <span class="font-bold text-indigo-400">📺 {{ isRTL ? 'تسمية المسلسلات والحلقات' : 'Standard TV Series Formats' }}</span>
                                <div class="p-2.5 rounded-xl bg-black/40 font-mono text-xs text-slate-300 space-y-1">
                                    <div>Breaking.Bad.S01E01.Pilot.1080p.BluRay.x264.mkv <span class="text-emerald-400">→ Breaking Bad / Season 01 / S01E01</span></div>
                                    <div>Game.of.Thrones.1x02.The.Kingsroad.720p.mkv <span class="text-emerald-400">→ Game of Thrones / Season 01 / S01E02</span></div>
                                    <div>[Group] Attack on Titan - S04E28 [1080p].mkv <span class="text-emerald-400">→ Attack on Titan / Season 04 / S04E28</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 6. FAQ & TROUBLESHOOTING -->
                    <div v-else-if="activeTab === 'faq'" class="space-y-6 animate-in fade-in">
                        <div class="border-b border-white/10 pb-4">
                            <h2 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                                <HelpCircle class="w-6 h-6 text-cyan-400" />
                                <span>{{ isRTL ? 'الأسئلة الشائعة والحلول التقنية' : 'Frequently Asked Questions & Troubleshooting' }}</span>
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                                {{ isRTL ? 'إجابات وحلول سريعة لأكثر الاستفسارات شيوعاً.' : 'Quick solutions for common questions and library operations.' }}
                            </p>
                        </div>

                        <div class="space-y-3 text-xs sm:text-sm text-slate-300">
                            <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-1">
                                <h4 class="font-bold text-white">{{ isRTL ? 'س: ماذا أفعل إذا ظهر فيلم ببوستر خاطئ أو غير مكتمل؟' : 'Q: What if a movie matches with the wrong poster or title?' }}</h4>
                                <p class="text-slate-400 text-xs leading-relaxed">
                                    {{ isRTL ? 'افتح صفحة تفاصيل الفيلم، واضغط على زر "إصلاح المطابقة (Fix Match)". يمكنك كتابة اسم الفيلم أو وضع رقم IMDb ID (مثل tt3896198) لجلب البيانات فورياً وحفظها.' : 'Open the movie details page and click "Fix Match". You can search by custom title or paste an exact IMDb ID (e.g. tt3896198).' }}
                                </p>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-1">
                                <h4 class="font-bold text-white">{{ isRTL ? 'س: كيف يمكنني تشغيل مقاطع الفيديو في تطبيق VLC أو IINA الخارجي؟' : 'Q: Can I play streams in external video players like VLC or IINA?' }}</h4>
                                <p class="text-slate-400 text-xs leading-relaxed">
                                    {{ isRTL ? 'نعم! عند فتح المشغل، اضغط على أيقونة "نسخ رابط البث" في أعلى المشغل، ثم الصق الرابط في VLC (Open Network Stream).' : 'Yes! In the top-right toolbar of the player, click "Copy Direct Stream URL" and paste into VLC (Media -> Open Network Stream).' }}
                                </p>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-1">
                                <h4 class="font-bold text-white">{{ isRTL ? 'س: كيف أقوم بحذف وسائط مجلد معين فقط وإعادة فحصه نظيفاً؟' : 'Q: How can I wipe and rescan a single folder?' }}</h4>
                                <p class="text-slate-400 text-xs leading-relaxed">
                                    {{ isRTL ? 'في صفحة فاحص المكتبة (/scanner)، انقر على زر "فحص جديد (Fresh Rescan)" بجانب بطاقة المجلد الذي ترغب في إعادة فحص ملفاته.' : 'In the Scanner page (/scanner), click the "Fresh Rescan" button on that specific monitored folder card.' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
