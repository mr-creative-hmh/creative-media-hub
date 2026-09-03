<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import {
    BookOpen, Search, Sparkles, Film, Tv, FolderSync,
    ScanLine, Subtitles, Play, ShieldCheck, Database,
    Layers, Sliders, Cpu, HelpCircle, ArrowRight, ArrowLeft,
    CheckCircle2, Terminal, Info, ExternalLink, Activity,
    HardDrive, Network, GitBranch, RefreshCw, Wand2, Eye,
    Clock, MonitorPlay, AlertTriangle, FileText, Code2, Globe
} from 'lucide-vue-next';

const { t, isRTL } = useI18n();

const activeTab = ref<'getting_started' | 'system_architecture' | 'core_processes' | 'scanner_vs_organizer' | 'metadata_providers' | 'player_streaming' | 'naming_rules' | 'faq'>('system_architecture');
const searchQuery = ref('');

const tabs = [
    { key: 'system_architecture', icon: Cpu, nameAr: '١. البنية الهيكلية المعمارية (Architecture)', nameEn: '1. System Architecture & Structure' },
    { key: 'core_processes', icon: Activity, nameAr: '٢. خطوط المعالجة وسير العمليات (Pipelines)', nameEn: '2. Core Processes & Pipelines' },
    { key: 'getting_started', icon: Sparkles, nameAr: '٣. دليل البداية السريعة والتشغيل', nameEn: '3. Getting Started & Setup' },
    { key: 'scanner_vs_organizer', icon: FolderSync, nameAr: '٤. الفاحص الافتراضي مقابل المنظم الفعلي', nameEn: '4. Virtual Scanner vs Organizer' },
    { key: 'metadata_providers', icon: Database, nameAr: '٥. مجمّع الميتاداتا ومزودات API', nameEn: '5. Metadata Waterfall Providers' },
    { key: 'player_streaming', icon: Play, nameAr: '٦. محرك البث والترميز الفوري', nameEn: '6. Streaming & Remuxing Engine' },
    { key: 'naming_rules', icon: Layers, nameAr: '٧. قواعد ومعايير تسمية الملفات', nameEn: '7. Scene Naming & Arabic Rules' },
    { key: 'faq', icon: HelpCircle, nameAr: '٨. الأسئلة الشائعة والحلول التقنية', nameEn: '8. FAQ & Troubleshooting' },
];
</script>

<template>
    <Head :title="isRTL ? 'دليل المعمارية والتوثيق التقني' : 'Architecture & Technical Documentation'" />

    <AppLayout v-slot="{ play }">
        <!-- Header Banner -->
        <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-cyan-500 to-blue-600 text-slate-950 flex items-center justify-center shadow-lg shadow-cyan-500/20">
                    <BookOpen class="w-6 h-6 stroke-[2.5]" />
                </div>
                <div>
                    <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full bg-cyan-500/10 text-cyan-400 text-[10px] font-black uppercase tracking-wider mb-1">
                        <span>{{ isRTL ? 'التوثيق التقني الشامل v2.0' : 'Technical Documentation v2.0' }}</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                        {{ isRTL ? 'دليل المعمارية والتوثيق التقني' : 'System Architecture & Technical Guide' }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-400 mt-0.5 max-w-3xl">
                        {{ isRTL 
                            ? 'توثيق معماري عميق للبنية الطبقية، مسارات المعالجة الخلفية، خوارزمية تحليل المشهد متعددة اللغات، محرك البث الهجين، ونظام الروابط الصلبة.' 
                            : 'In-depth architectural specifications, layered system design, background processing pipelines, hybrid zero-lag streaming, and hardlink organization.' 
                        }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Documentation Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Navigation Sidebar -->
            <div class="lg:col-span-1 space-y-2">
                <div class="glass-panel rounded-3xl p-4 border border-white/10 space-y-1 sticky top-20 bg-slate-900/60 backdrop-blur-md">
                    <div class="px-3 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400 border-b border-white/10 mb-2">
                        {{ isRTL ? 'فهرس الفصول المعمارية' : 'Architectural Chapters' }}
                    </div>

                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="activeTab = tab.key as any"
                        class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-xs font-bold text-left transition-all cursor-pointer text-slate-300 hover:text-white"
                        :class="activeTab === tab.key
                            ? 'bg-gradient-to-r from-cyan-500 to-blue-600 text-slate-950 font-black shadow-md shadow-cyan-500/20'
                            : 'hover:bg-white/5'"
                    >
                        <component :is="tab.icon" class="w-4 h-4 shrink-0" />
                        <span class="truncate">{{ isRTL ? tab.nameAr : tab.nameEn }}</span>
                    </button>

                    <!-- Quick Metadata Studio Link -->
                    <div class="pt-4 mt-2 border-t border-white/10">
                        <a
                            href="/metadata"
                            class="flex items-center justify-between p-3 rounded-2xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-300 border border-cyan-500/20 text-xs font-bold transition-all"
                        >
                            <span class="flex items-center gap-2">
                                <Wand2 class="w-3.5 h-3.5" />
                                <span>{{ isRTL ? 'استوديو تصحيح الميتاداتا' : 'Fix Match Studio' }}</span>
                            </span>
                            <ExternalLink class="w-3.5 h-3.5" />
                        </a>
                    </div>
                </div>
            </div>

            <!-- Content Body Area -->
            <div class="lg:col-span-3 space-y-8">

                <!-- 1. SYSTEM ARCHITECTURE & STRUCTURE TAB -->
                <section v-if="activeTab === 'system_architecture'" class="space-y-6">
                    <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6 bg-slate-900/40">
                        <div class="flex items-center gap-3 border-b border-white/10 pb-4">
                            <Cpu class="w-6 h-6 text-cyan-400" />
                            <div>
                                <h2 class="text-xl sm:text-2xl font-black text-white">
                                    {{ isRTL ? 'البنية الهيكلية المعمارية للنظام (System Architecture)' : 'Deep System Architecture & Layered Structure' }}
                                </h2>
                                <p class="text-xs text-slate-400">Clean Layered Architecture • Separation of Concerns • Service-Oriented Backend</p>
                            </div>
                        </div>

                        <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                            {{ isRTL 
                                ? 'يتبع نظام Creative Media Hub نموذج Clean Architecture الصارم عبر أربع طبقات رئيسية، مما يضمن قابلية التوسع، استقلالية المحركات، وسهولة الصيانة والفحص:' 
                                : 'Creative Media Hub follows strict Clean Layered Architecture principles across 4 discrete layers, ensuring high throughput, decoupled processing, and robust maintainability:' 
                            }}
                        </p>

                        <!-- ASCII Layer Diagram -->
                        <div class="p-5 rounded-2xl bg-slate-950 border border-cyan-500/30 font-mono text-[11px] text-cyan-300 overflow-x-auto leading-relaxed shadow-inner">
                            <pre>
+-----------------------------------------------------------------------------------+
|                                PRESENTATION LAYER                                 |
|   Inertia.js + Vue 3.5 SPA • Tailwind CSS v4 • Lucide Icons • HTML5 Cinema Player  |
+-----------------------------------------+-----------------------------------------+
                                          | JSON / Inertia Props
                                          v
+-----------------------------------------------------------------------------------+
|                             HTTP & CONTROLLER LAYER                               |
|   DashboardController • MediaController • SeriesController • CollectionController  |
|   StreamController • MetadataManagementController • PhysicalOrganizerController   |
+-----------------------------------------+-----------------------------------------+
                                          | Domain Calls
                                          v
+-----------------------------------------------------------------------------------+
|                             APPLICATION SERVICE LAYER                             |
|  +--------------------------------+  +------------------------------------------+  |
|  | VirtualLibraryScannerService   |  | SceneNameParserService (Arabic + En)     |  |
|  | - Parallel Directory Traversal |  | - Eastern Numeral Normalization          |  |
|  | - Non-Blocking Batch Queues    |  | - Folder Ancestor Context Inheritance    |  |
|  +--------------------------------+  +------------------------------------------+  |
|  +--------------------------------+  +------------------------------------------+  |
|  | MetadataAggregator (Waterfall) |  | FfmpegLocatorService & Stream Engine     |  |
|  | - TMDb, OMDb, TVMaze, AniList  |  | - HTTP 206 Byte-Range Partial Content    |  |
|  | - Arabic Translation Engine    |  | - Background FastStart Disk Caching      |  |
|  +--------------------------------+  +------------------------------------------+  |
|  +--------------------------------+  +------------------------------------------+  |
|  | EmbeddedSubtitleDetector       |  | PhysicalOrganizerService                 |  |
|  | - FFprobe Stream Analysis      |  | - Zero-Copy NTFS Hardlink Engine         |  |
|  | - WebVTT Conversion Pipeline   |  | - Replay Protection & Conflict Matrix    |  |
|  +--------------------------------+  +------------------------------------------+  |
+-----------------------------------------+-----------------------------------------+
                                          | Eloquent ORM & Storage I/O
                                          v
+-----------------------------------------------------------------------------------+
|                        DOMAIN MODELS & INFRASTRUCTURE LAYER                       |
|   MediaItem • Series • Season • Episode • WatchHistory • Subtitle • Person • Genre |
|   SQLite WAL Engine • FFmpeg/FFprobe CLI • Local Filesystem • External REST APIs  |
+-----------------------------------------------------------------------------------+
                            </pre>
                        </div>

                        <!-- Directory Map Grid -->
                        <div class="space-y-3">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                                <Layers class="w-4 h-4 text-purple-400" />
                                <span>{{ isRTL ? 'توزيع الوحدات والمسؤوليات البرمجية' : 'Module Responsibilities & Directory Map' }}</span>
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-1.5">
                                    <span class="font-mono text-cyan-400 font-bold block">app/Services/Scanner/</span>
                                    <p class="text-slate-300">
                                        {{ isRTL ? 'إدارة الفحص المتوازي، تقسيم المسارات إلى حزم غير حاجزة (Non-blocking Chunks)، وحفظ السجلات في الذاكرة.' : 'Non-blocking parallel directory crawler, chunked queue execution, and live state store.' }}
                                    </p>
                                </div>
                                <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-1.5">
                                    <span class="font-mono text-purple-400 font-bold block">app/Services/Organizer/</span>
                                    <p class="text-slate-300">
                                        {{ isRTL ? 'خوارزمية SceneNameParserService الذكية متعددة اللغات ومحرك إنشاء الروابط الصلبة NTFS.' : 'Intelligent multi-lingual scene parser and atomic zero-copy NTFS hardlink executor.' }}
                                    </p>
                                </div>
                                <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-1.5">
                                    <span class="font-mono text-amber-400 font-bold block">app/Services/Metadata/</span>
                                    <p class="text-slate-300">
                                        {{ isRTL ? 'شلال جلب البيانات الوصفية (TMDb, OMDb, AniList) مع التعريب التلقائي وتجميع السلاسل (Boxsets).' : 'Metadata waterfall aggregator with Arabic translator and movie franchise clustering.' }}
                                    </p>
                                </div>
                                <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-1.5">
                                    <span class="font-mono text-emerald-400 font-bold block">app/Services/Subtitles/</span>
                                    <p class="text-slate-300">
                                        {{ isRTL ? 'محلل المسارات الصوتية والترجمات المدمجة FFprobe ومحرك تنزيل الترجمات من SubDL و OpenSubtitles.' : 'Embedded subtitle stream extractor (FFprobe -> WebVTT) and SubDL/OpenSubtitles synchronization.' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 2. CORE PROCESSES & PIPELINES TAB -->
                <section v-if="activeTab === 'core_processes'" class="space-y-6">
                    <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6 bg-slate-900/40">
                        <div class="flex items-center gap-3 border-b border-white/10 pb-4">
                            <Activity class="w-6 h-6 text-indigo-400" />
                            <div>
                                <h2 class="text-xl sm:text-2xl font-black text-white">
                                    {{ isRTL ? 'خطوط المعالجة وسير العمليات (Core Pipelines)' : 'Core Processing Pipelines & Workflows' }}
                                </h2>
                                <p class="text-xs text-slate-400">Step-by-step Execution Cycles • Concurrency Models • Lifecycle Diagrams</p>
                            </div>
                        </div>

                        <!-- Pipeline 1: Arabic & Scene Parser -->
                        <div class="p-5 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-lg bg-cyan-500/20 text-cyan-400 font-black text-xs flex items-center justify-center">1</span>
                                    <span>{{ isRTL ? 'خوارزمية تحليل المشهد وسياق المجلدات (Scene & Folder Parser)' : 'Scene & Folder-Context Parsing Pipeline' }}</span>
                                </h3>
                                <span class="text-[10px] font-mono text-cyan-400 px-2 py-0.5 rounded bg-cyan-500/10">SceneNameParserService</span>
                            </div>
                            <p class="text-xs text-slate-300 leading-relaxed">
                                {{ isRTL 
                                    ? 'تعالج الخوارزمية الأسماء المعقدة، الترقيم الشرقي (١, ٢, ٣)، كلمات المواسم العربية (الموسم الأول، الجزء 2)، وتقوم بالوراثة الذكية من المجلد الأب عند وجود ملفات بأرقام فقط (مثل: مسلسل الاختيار\الحلقة 01.mp4).' 
                                    : 'Parses complex scene release tags, normalizes Eastern Arabic numerals (١, ٢, ٣ -> 1, 2, 3), resolves Arabic season/episode keywords, and inherits context from ancestor directory names when filenames are purely numeric.' 
                                }}
                            </p>
                            <div class="p-3 rounded-xl bg-slate-950 font-mono text-[11px] text-slate-300 space-y-1">
                                <div class="text-cyan-400 font-bold">// Raw Filename Input:</div>
                                <div class="text-slate-400">"H:\Series\باب الحارة\الموسم ٢\الحلقة ٠٥ (1080p Web-DL).mkv"</div>
                                <div class="text-emerald-400 font-bold mt-2">// Parsed Domain Structure:</div>
                                <div class="text-emerald-300">{ title: "باب الحارة", season: 2, episode: 5, resolution: "1080p", audio: "Web-DL" }</div>
                            </div>
                        </div>

                        <!-- Pipeline 2: Hybrid Streaming -->
                        <div class="p-5 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-lg bg-indigo-500/20 text-indigo-400 font-black text-xs flex items-center justify-center">2</span>
                                    <span>{{ isRTL ? 'محرك البث الهجين والترميز الفوري (Hybrid Streaming Engine)' : 'Hybrid Byte-Range & Remuxing Pipeline' }}</span>
                                </h3>
                                <span class="text-[10px] font-mono text-indigo-400 px-2 py-0.5 rounded bg-indigo-500/10">StreamController</span>
                            </div>
                            <p class="text-xs text-slate-300 leading-relaxed">
                                {{ isRTL 
                                    ? 'يتحقق المشغل من توافق ترميز الفيديو؛ إن كان متوافقاً (MP4/H.264) يتم البث المباشر عبر HTTP 206 Byte-Range باستهلاك 0% معالج. وإن كان بتنسيق قديم (AVI/MPEG-4) أو صوت غير مدعوم، يبدأ تحويل فوري مع تخزين كاش بالخلفية.' 
                                    : 'Inspects media container and codecs. Compatible formats stream via zero-CPU HTTP 206 Partial Content in 256KB chunks. Incompatible audio/containers trigger on-the-fly fragmented MP4 remuxing with asynchronous background caching.' 
                                }}
                            </p>
                        </div>

                        <!-- Pipeline 3: Boxsets & Collections -->
                        <div class="p-5 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-lg bg-purple-500/20 text-purple-400 font-black text-xs flex items-center justify-center">3</span>
                                    <span>{{ isRTL ? 'محرك تجميع سلاسل الأفلام (Franchise Boxsets & Collections)' : 'Franchise Boxset Clustering Pipeline' }}</span>
                                </h3>
                                <span class="text-[10px] font-mono text-purple-400 px-2 py-0.5 rounded bg-purple-500/10">CollectionController</span>
                            </div>
                            <p class="text-xs text-slate-300 leading-relaxed">
                                {{ isRTL 
                                    ? 'تجميع الأفلام تلقائياً ضمن سلاسلها الرسمية (مثل: Harry Potter Collection، Lord of the Rings، Fast & Furious) وعرضها بتسلسل زمني دقيق في صفحة /collections.' 
                                    : 'Clusters franchise installments automatically via TMDb collection IDs and offline heuristic keyword clustering, presenting complete chronological sagas under /collections.' 
                                }}
                            </p>
                        </div>

                        <!-- Pipeline 4: Zero-Copy Hardlink Organizer -->
                        <div class="p-5 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-lg bg-emerald-500/20 text-emerald-400 font-black text-xs flex items-center justify-center">4</span>
                                    <span>{{ isRTL ? 'محرك التنظيم والروابط الصلبة (Zero-Copy NTFS Hardlinks)' : 'Zero-Copy NTFS Hardlink Organizer' }}</span>
                                </h3>
                                <span class="text-[10px] font-mono text-emerald-400 px-2 py-0.5 rounded bg-emerald-500/10">PhysicalOrganizerService</span>
                            </div>
                            <p class="text-xs text-slate-300 leading-relaxed">
                                {{ isRTL 
                                    ? 'إعادة هيكلة وتسمية مكتبة الوسائط بدون استهلاك أي مساحة إضافية على القرص وبدون قطع مسارات التورنت النشطة عبر روابط NTFS الصلبة مع حماية من تكرار التنفيذ.' 
                                    : 'Restructures media into standard directory hierarchies using zero-copy NTFS hardlinks without consuming extra disk space or breaking active torrent seeding.' 
                                }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- 3. GETTING STARTED & SETUP TAB -->
                <section v-if="activeTab === 'getting_started'" class="space-y-6">
                    <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6 bg-slate-900/40">
                        <div class="flex items-center gap-3 border-b border-white/10 pb-4">
                            <Sparkles class="w-6 h-6 text-cyan-400" />
                            <div>
                                <h2 class="text-xl sm:text-2xl font-black text-white">
                                    {{ isRTL ? 'البداية السريعة ومتطلبات التشغيل' : 'Getting Started & System Setup' }}
                                </h2>
                                <p class="text-xs text-slate-400">Installation • Environment Config • Desktop App Build</p>
                            </div>
                        </div>

                        <div class="space-y-4 text-xs sm:text-sm text-slate-300">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">{{ isRTL ? 'متطلبات النظام الأساسية' : 'System Prerequisites' }}</h3>
                            <ul class="list-disc list-inside space-y-1.5 text-slate-300">
                                <li><strong>PHP 8.2+</strong> (with extensions: pdo_sqlite, curl, mbstring, fileinfo, gd)</li>
                                <li><strong>Node.js 20+ & npm</strong></li>
                                <li><strong>Composer 2.x</strong></li>
                                <li><strong>FFmpeg / FFprobe</strong> (automatically located in PATH or storage/bin)</li>
                            </ul>

                            <h3 class="text-sm font-bold text-white uppercase tracking-wider pt-4">{{ isRTL ? 'أوامر التثبيت والتشغيل' : 'Installation Commands' }}</h3>
                            <div class="p-4 rounded-2xl bg-slate-950 border border-white/10 font-mono text-xs text-cyan-300 space-y-2">
                                <div>composer install</div>
                                <div>npm install</div>
                                <div>php artisan migrate</div>
                                <div>npm run build</div>
                                <div>npm run desktop:build <span class="text-slate-500">// Builds standalone Windows .exe</span></div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 4. SCANNER VS ORGANIZER TAB -->
                <section v-if="activeTab === 'scanner_vs_organizer'" class="space-y-6">
                    <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6 bg-slate-900/40">
                        <div class="flex items-center gap-3 border-b border-white/10 pb-4">
                            <FolderSync class="w-6 h-6 text-cyan-400" />
                            <div>
                                <h2 class="text-xl sm:text-2xl font-black text-white">
                                    {{ isRTL ? 'الفاحص الافتراضي مقابل المنظم الفعلي' : 'Virtual Scanner vs Physical Organizer' }}
                                </h2>
                                <p class="text-xs text-slate-400">Virtual In-Memory Indexing vs Physical Zero-Copy Hardlink Relocation</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <div class="p-5 rounded-2xl bg-cyan-500/5 border border-cyan-500/20 space-y-2">
                                <h4 class="font-bold text-cyan-400 text-sm flex items-center gap-2">
                                    <ScanLine class="w-4 h-4" />
                                    <span>{{ isRTL ? 'الفاحص الافتراضي (Virtual Scanner)' : 'Virtual Scanner (Read-Only)' }}</span>
                                </h4>
                                <p class="text-slate-300 leading-relaxed">
                                    {{ isRTL 
                                        ? 'يقوم بقراءة المجلدات وفهرستها داخل قاعدة بيانات SQLite بدون لمس أو تحريك أي ملف من مكانه الأصلي على الإطلاق. آمن 100% للتصفح السريع.' 
                                        : 'Reads directories and stores metadata into the SQLite database without moving, renaming, or touching original files. 100% read-only.' 
                                    }}
                                </p>
                            </div>

                            <div class="p-5 rounded-2xl bg-purple-500/5 border border-purple-500/20 space-y-2">
                                <h4 class="font-bold text-purple-400 text-sm flex items-center gap-2">
                                    <FolderSync class="w-4 h-4" />
                                    <span>{{ isRTL ? 'المنظم الفعلي (Physical Organizer)' : 'Physical Hardlink Organizer' }}</span>
                                </h4>
                                <p class="text-slate-300 leading-relaxed">
                                    {{ isRTL 
                                        ? 'يقوم بإنشاء روابط صلبة NTFS في مجلد جديد منظم (Movies / Series) بدون مضاعفة المساحة، مع إمكانية المعاينة قبل التنفيذ وتأكيد كل خطوة.' 
                                        : 'Creates zero-copy NTFS hardlinks inside standard destination directories without doubling disk space usage or interrupting active torrent seeds.' 
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 5. METADATA PROVIDERS TAB -->
                <section v-if="activeTab === 'metadata_providers'" class="space-y-6">
                    <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6 bg-slate-900/40">
                        <div class="flex items-center gap-3 border-b border-white/10 pb-4">
                            <Database class="w-6 h-6 text-amber-400" />
                            <div>
                                <h2 class="text-xl sm:text-2xl font-black text-white">
                                    {{ isRTL ? 'شلال مزودات الميتاداتا ومصادر البيانات' : 'Metadata Waterfall Providers' }}
                                </h2>
                                <p class="text-xs text-slate-400">Hierarchical Resolution • Arabic Translation • Fallback Cascades</p>
                            </div>
                        </div>

                        <div class="space-y-3 text-xs">
                            <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 flex items-center justify-between gap-4">
                                <div>
                                    <h4 class="font-bold text-white">1. The Movie Database (TMDb)</h4>
                                    <p class="text-slate-400">Primary provider for official posters, backdrops, cast, genres, and collections.</p>
                                </div>
                                <span class="px-2.5 py-1 rounded-xl bg-cyan-500/20 text-cyan-300 font-bold text-[10px]">Primary API</span>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 flex items-center justify-between gap-4">
                                <div>
                                    <h4 class="font-bold text-white">2. Open Movie Database (OMDb / IMDb)</h4>
                                    <p class="text-slate-400">Enriches exact IMDb ratings, Metascores, age ratings, and awards.</p>
                                </div>
                                <span class="px-2.5 py-1 rounded-xl bg-amber-500/20 text-amber-300 font-bold text-[10px]">IMDb Scores</span>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 flex items-center justify-between gap-4">
                                <div>
                                    <h4 class="font-bold text-white">3. AniList GraphQL</h4>
                                    <p class="text-slate-400">Japanese anime specialist for romaji titles, episode schedules, and studios.</p>
                                </div>
                                <span class="px-2.5 py-1 rounded-xl bg-purple-500/20 text-purple-300 font-bold text-[10px]">Anime Engine</span>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 flex items-center justify-between gap-4">
                                <div>
                                    <h4 class="font-bold text-white">4. Arabic Translation Waterfall</h4>
                                    <p class="text-slate-400">Translates overviews, titles, and tags into clean Arabic automatically.</p>
                                </div>
                                <span class="px-2.5 py-1 rounded-xl bg-emerald-500/20 text-emerald-300 font-bold text-[10px]">Bilingual Hub</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 6. PLAYER & STREAMING TAB -->
                <section v-if="activeTab === 'player_streaming'" class="space-y-6">
                    <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6 bg-slate-900/40">
                        <div class="flex items-center gap-3 border-b border-white/10 pb-4">
                            <Play class="w-6 h-6 text-cyan-400" />
                            <div>
                                <h2 class="text-xl sm:text-2xl font-black text-white">
                                    {{ isRTL ? 'المشغل السينمائي ومحرك البث والترميز' : 'Cinema Player & Streaming Engine' }}
                                </h2>
                                <p class="text-xs text-slate-400">Byte-Range HTTP 206 • WebVTT Subtitles • Audio Track Selection</p>
                            </div>
                        </div>

                        <div class="space-y-4 text-xs sm:text-sm text-slate-300">
                            <p class="leading-relaxed">
                                {{ isRTL 
                                    ? 'يتميز المشغل بدعم اختصارات لوحة المفاتيح الكاملة، استئناف التشغيل الذكي، التحكم بحجم الصوت وسرعة العرض، وتحميل الترجمات العربية بضغطة زر.' 
                                    : 'Features rich keyboard shortcuts, smart progress resume, audio booster (up to 200%), custom playback speeds, and instant Arabic subtitle matching.' 
                                }}
                            </p>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 font-mono text-xs pt-2">
                                <div class="p-3 rounded-xl bg-slate-950 border border-white/10 text-center">
                                    <div class="text-cyan-400 font-bold">Space / K</div>
                                    <div class="text-[10px] text-slate-400">{{ isRTL ? 'تشغيل / إيقاف' : 'Play / Pause' }}</div>
                                </div>
                                <div class="p-3 rounded-xl bg-slate-950 border border-white/10 text-center">
                                    <div class="text-cyan-400 font-bold">F</div>
                                    <div class="text-[10px] text-slate-400">{{ isRTL ? 'شاشة كاملة' : 'Fullscreen' }}</div>
                                </div>
                                <div class="p-3 rounded-xl bg-slate-950 border border-white/10 text-center">
                                    <div class="text-cyan-400 font-bold">Left / Right</div>
                                    <div class="text-[10px] text-slate-400">{{ isRTL ? 'تقديم / ترجيع 5ث' : 'Seek 5s' }}</div>
                                </div>
                                <div class="p-3 rounded-xl bg-slate-950 border border-white/10 text-center">
                                    <div class="text-cyan-400 font-bold">M</div>
                                    <div class="text-[10px] text-slate-400">{{ isRTL ? 'كتم الصوت' : 'Mute Audio' }}</div>
                                </div>
                            </div>

                            <!-- Advanced Features List -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-3">
                                <div class="p-4 rounded-2xl bg-white/5 border border-white/10 space-y-1.5">
                                    <div class="text-xs font-bold text-cyan-400">{{ isRTL ? 'بث مباشر فوري (Direct Stream)' : 'Zero-CPU Direct Streaming' }}</div>
                                    <p class="text-[11px] text-slate-400">{{ isRTL ? 'تشغيل فوري بصيغ H.264 و AAC و Dolby Digital بدون أي حمل على المعالج أو تأخير في الصوت.' : 'Hardware-accelerated HTTP 206 streaming for H.264/AAC/AC3 with zero server transcode latency.' }}</p>
                                </div>
                                <div class="p-4 rounded-2xl bg-white/5 border border-white/10 space-y-1.5">
                                    <div class="text-xs font-bold text-purple-400">{{ isRTL ? 'استوديو الخطوط والترجمة' : 'Subtitle Typography Studio' }}</div>
                                    <p class="text-[11px] text-slate-400">{{ isRTL ? 'خطوط عربية مخصصة (Cairo, Jakarta) مع تباين سينمائي فائق ودعم كامل لاتجاه RTL.' : 'Native Cairo & Jakarta fonts, high-contrast text outlines, and full RTL menu alignment.' }}</p>
                                </div>
                                <div class="p-4 rounded-2xl bg-white/5 border border-white/10 space-y-1.5">
                                    <div class="text-xs font-bold text-emerald-400">{{ isRTL ? 'إدارة الذاكرة المؤقتة' : 'Media Cache Maintenance' }}</div>
                                    <p class="text-[11px] text-slate-400">{{ isRTL ? 'مراقبة سعة تخزين البث وتنظيف ملفات الترميز المؤقتة بضغطة زر من صفحة الإعدادات.' : 'Real-time cache monitoring and 1-click transcode cache purge from the Settings dashboard.' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 7. NAMING RULES TAB -->
                <section v-if="activeTab === 'naming_rules'" class="space-y-6">
                    <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6 bg-slate-900/40">
                        <div class="flex items-center gap-3 border-b border-white/10 pb-4">
                            <Layers class="w-6 h-6 text-purple-400" />
                            <div>
                                <h2 class="text-xl sm:text-2xl font-black text-white">
                                    {{ isRTL ? 'معايير وقواعد تسمية الملفات والمجلدات' : 'Scene Naming Rules & Arabic Patterns' }}
                                </h2>
                                <p class="text-xs text-slate-400">Naming Standards • Eastern Numerals • Folder Context Resolution</p>
                            </div>
                        </div>

                        <div class="space-y-4 text-xs sm:text-sm text-slate-300">
                            <p class="leading-relaxed">
                                {{ isRTL 
                                    ? 'يتعامل المحرك بمرونة فائقة مع كافة أنماط التسمية؛ ومع ذلك يُنصح باتباع الأنماط القياسية للحصول على دقة مطابقة 100%:' 
                                    : 'While the engine handles almost any chaotic naming pattern, following standard scene conventions ensures 100% instant match accuracy:' 
                                }}
                            </p>

                            <div class="space-y-2 font-mono text-xs">
                                <div class="p-3 rounded-xl bg-slate-950 border border-emerald-500/30">
                                    <span class="text-emerald-400 font-bold block">// Movies Standard:</span>
                                    <span class="text-slate-300">Movies/Inception (2010)/Inception (2010) [1080p Bluray].mkv</span>
                                </div>
                                <div class="p-3 rounded-xl bg-slate-950 border border-cyan-500/30">
                                    <span class="text-cyan-400 font-bold">// TV Series Standard:</span>
                                    <span class="text-slate-300">Series/Breaking Bad/Season 01/Breaking Bad S01E01.mkv</span>
                                </div>
                                <div class="p-3 rounded-xl bg-slate-950 border border-purple-500/30">
                                    <span class="text-purple-400 font-bold">// Arabic TV Series Pattern:</span>
                                    <span class="text-slate-300">Series/الاختيار/الموسم 1/الاختيار الحلقة 01.mp4</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 8. FAQ TAB -->
                <section v-if="activeTab === 'faq'" class="space-y-6">
                    <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/10 space-y-6 bg-slate-900/40">
                        <div class="flex items-center gap-3 border-b border-white/10 pb-4">
                            <HelpCircle class="w-6 h-6 text-amber-400" />
                            <div>
                                <h2 class="text-xl sm:text-2xl font-black text-white">
                                    {{ isRTL ? 'الأسئلة الشائعة والحلول التقنية' : 'FAQ & Technical Troubleshooting' }}
                                </h2>
                                <p class="text-xs text-slate-400">Common Questions • FFmpeg Setup • Error Resolution</p>
                            </div>
                        </div>

                        <div class="space-y-3 text-xs sm:text-sm text-slate-300">
                            <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-1.5">
                                <h4 class="font-bold text-white text-xs">
                                    {{ isRTL ? 'س: ماذا أفعل إذا تم التعرف على اسم فيلم أو مسلسل بشكل خاطئ؟' : 'Q: What should I do if a file was incorrectly identified?' }}
                                </h4>
                                <p class="text-slate-400 text-xs leading-relaxed">
                                    {{ isRTL 
                                        ? 'افتح صفحة "استوديو الميتاداتا" (/metadata) أو انقر على أيقونة "Fix Match" على بطاقة الفيلم، يمكنك البحث بالاسم أو وضع رقم TMDb / IMDb مباشرة لجلب البيانات الصحيحة في ثانية واحدة.' 
                                        : 'Open the Metadata Studio (/metadata) or click "Fix Match" on the media card. You can search online or input a direct TMDb/IMDb ID to fetch exact details instantly.' 
                                    }}
                                </p>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-1.5">
                                <h4 class="font-bold text-white text-xs">
                                    {{ isRTL ? 'س: هل يستهلك المنظم الفعلي مساحة إضافية على الهارد ديسك؟' : 'Q: Does the physical organizer duplicate files and consume extra space?' }}
                                </h4>
                                <p class="text-slate-400 text-xs leading-relaxed">
                                    {{ isRTL 
                                        ? 'كلا! يستخدم النظام الروابط الصلبة NTFS (Hardlinks) التي تشير إلى نفس كتل البيانات على القرص بدون استهلاك بايت واحد إضافي، وبدون التأثير على ملفات التورنت الأصلية.' 
                                        : 'No! The system creates zero-copy NTFS hardlinks pointing to the exact same disk sectors with 0% extra space usage.' 
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Footer Engineering Credits -->
                <div class="p-4 rounded-2xl bg-slate-900/60 border border-white/10 text-center text-xs text-slate-400">
                    <span class="text-slate-300 font-bold">Creative Media Hub</span> — {{ isRTL ? 'تم التصميم والتطوير بواسطة م. حسن محمد حسن' : 'Engineered & Developed by Eng. Hasan Mohammad Hasan' }}
                </div>
            </div>
        </div>
    </AppLayout>
</template>
