<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n/useI18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import {
    BookOpen,
    Search,
    Sparkles,
    Film,
    Tv,
    FolderSync,
    ScanLine,
    Subtitles,
    Play,
    ShieldCheck,
    Database,
    Layers,
    Sliders,
    Cpu,
    HelpCircle,
    ArrowRight,
    ArrowLeft,
    CheckCircle2,
    Terminal,
    Info,
    ExternalLink,
    Activity,
    HardDrive,
    Network,
    GitBranch,
    RefreshCw,
    Wand2,
    Eye,
    Clock,
    MonitorPlay,
    AlertTriangle,
    FileText,
    Code2,
    Globe,
} from '@lucide/vue';

const { t, isRTL } = useI18n();

const activeTab = ref<
    | 'getting_started'
    | 'system_architecture'
    | 'core_processes'
    | 'scanner_vs_organizer'
    | 'metadata_providers'
    | 'player_streaming'
    | 'naming_rules'
    | 'faq'
>('system_architecture');
const searchQuery = ref('');

const tabs = [
    {
        key: 'system_architecture',
        icon: Cpu,
        nameAr: '١. البنية الهيكلية المعمارية (Architecture)',
        nameEn: '1. System Architecture & Structure',
    },
    {
        key: 'core_processes',
        icon: Activity,
        nameAr: '٢. خطوط المعالجة وسير العمليات (Pipelines)',
        nameEn: '2. Core Processes & Pipelines',
    },
    {
        key: 'getting_started',
        icon: Sparkles,
        nameAr: '٣. دليل البداية السريعة والتشغيل',
        nameEn: '3. Getting Started & Setup',
    },
    {
        key: 'scanner_vs_organizer',
        icon: FolderSync,
        nameAr: '٤. الفاحص الافتراضي مقابل المنظم الفعلي',
        nameEn: '4. Virtual Scanner vs Organizer',
    },
    {
        key: 'metadata_providers',
        icon: Database,
        nameAr: '٥. مجمّع الميتاداتا ومزودات API',
        nameEn: '5. Metadata Waterfall Providers',
    },
    {
        key: 'player_streaming',
        icon: Play,
        nameAr: '٦. محرك البث والترميز الفوري',
        nameEn: '6. Streaming & Remuxing Engine',
    },
    {
        key: 'naming_rules',
        icon: Layers,
        nameAr: '٧. قواعد ومعايير تسمية الملفات',
        nameEn: '7. Scene Naming & Arabic Rules',
    },
    {
        key: 'faq',
        icon: HelpCircle,
        nameAr: '٨. الأسئلة الشائعة والحلول التقنية',
        nameEn: '8. FAQ & Troubleshooting',
    },
];
</script>

<template>
    <AppLayout v-slot="{ play }">
        <Head
            :title="
                isRTL
                    ? 'دليل المعمارية والتوثيق التقني'
                    : 'Architecture & Technical Documentation'
            "
        />
        <!-- Header Banner -->
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-500 to-blue-600 text-slate-950 shadow-lg shadow-cyan-500/20"
                >
                    <BookOpen class="h-6 w-6 stroke-[2.5]" />
                </div>
                <div>
                    <div
                        class="mb-1 inline-flex items-center gap-2 rounded-full bg-cyan-500/10 px-2.5 py-0.5 text-[10px] font-black tracking-wider text-cyan-400 uppercase"
                    >
                        <span>{{
                            isRTL
                                ? 'التوثيق التقني الشامل v2.0'
                                : 'Technical Documentation v2.0'
                        }}</span>
                    </div>
                    <h1
                        class="text-2xl font-black tracking-tight text-white sm:text-3xl"
                    >
                        {{
                            isRTL
                                ? 'دليل المعمارية والتوثيق التقني'
                                : 'System Architecture & Technical Guide'
                        }}
                    </h1>
                    <p
                        class="mt-0.5 max-w-3xl text-xs text-slate-400 sm:text-sm"
                    >
                        {{
                            isRTL
                                ? 'توثيق معماري عميق للبنية الطبقية، مسارات المعالجة الخلفية، خوارزمية تحليل المشهد متعددة اللغات، محرك البث الهجين، ونظام الروابط الصلبة.'
                                : 'In-depth architectural specifications, layered system design, background processing pipelines, hybrid zero-lag streaming, and hardlink organization.'
                        }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Documentation Layout -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <!-- Navigation Sidebar -->
            <div class="space-y-2 lg:col-span-1">
                <div
                    class="glass-panel sticky top-20 space-y-1 rounded-3xl border border-white/10 bg-slate-900/60 p-4 backdrop-blur-md"
                >
                    <div
                        class="mb-2 border-b border-white/10 px-3 py-2 text-[10px] font-black tracking-wider text-slate-400 uppercase"
                    >
                        {{
                            isRTL
                                ? 'فهرس الفصول المعمارية'
                                : 'Architectural Chapters'
                        }}
                    </div>

                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="activeTab = tab.key as any"
                        class="flex w-full cursor-pointer items-center gap-3 rounded-2xl px-3.5 py-2.5 text-left text-xs font-bold text-slate-300 transition-all hover:text-white"
                        :class="
                            activeTab === tab.key
                                ? 'bg-gradient-to-r from-cyan-500 to-blue-600 font-black text-slate-950 shadow-md shadow-cyan-500/20'
                                : 'hover:bg-white/5'
                        "
                    >
                        <component :is="tab.icon" class="h-4 w-4 shrink-0" />
                        <span class="truncate">{{
                            isRTL ? tab.nameAr : tab.nameEn
                        }}</span>
                    </button>

                    <!-- Quick Metadata Studio Link -->
                    <div class="mt-2 border-t border-white/10 pt-4">
                        <Link
                            href="/metadata"
                            class="flex items-center justify-between rounded-2xl border border-cyan-500/20 bg-cyan-500/10 p-3 text-xs font-bold text-cyan-300 transition-all hover:bg-cyan-500/20"
                        >
                            <span class="flex items-center gap-2">
                                <Wand2 class="h-3.5 w-3.5" />
                                <span>{{
                                    isRTL
                                        ? 'استوديو تصحيح الميتاداتا'
                                        : 'Fix Match Studio'
                                }}</span>
                            </span>
                            <ExternalLink class="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Content Body Area -->
            <div class="space-y-8 lg:col-span-3">
                <!-- 1. SYSTEM ARCHITECTURE & STRUCTURE TAB -->
                <section
                    v-if="activeTab === 'system_architecture'"
                    class="space-y-6"
                >
                    <div
                        class="glass-panel space-y-6 rounded-3xl border border-white/10 bg-slate-900/40 p-6 sm:p-8"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-white/10 pb-4"
                        >
                            <Cpu class="h-6 w-6 text-cyan-400" />
                            <div>
                                <h2
                                    class="text-xl font-black text-white sm:text-2xl"
                                >
                                    {{
                                        isRTL
                                            ? 'البنية الهيكلية المعمارية للنظام (System Architecture)'
                                            : 'Deep System Architecture & Layered Structure'
                                    }}
                                </h2>
                                <p class="text-xs text-slate-400">
                                    Clean Layered Architecture • Separation of
                                    Concerns • Service-Oriented Backend
                                </p>
                            </div>
                        </div>

                        <p
                            class="text-xs leading-relaxed text-slate-300 sm:text-sm"
                        >
                            {{
                                isRTL
                                    ? 'يتبع نظام Creative Media Hub نموذج Clean Architecture الصارم عبر أربع طبقات رئيسية، مما يضمن قابلية التوسع، استقلالية المحركات، وسهولة الصيانة والفحص:'
                                    : 'Creative Media Hub follows strict Clean Layered Architecture principles across 4 discrete layers, ensuring high throughput, decoupled processing, and robust maintainability:'
                            }}
                        </p>

                        <!-- ASCII Layer Diagram -->
                        <div
                            class="overflow-x-auto rounded-2xl border border-cyan-500/30 bg-slate-950 p-5 font-mono text-[11px] leading-relaxed text-cyan-300 shadow-inner"
                        >
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
                            <h3
                                class="flex items-center gap-2 text-sm font-bold tracking-wider text-white uppercase"
                            >
                                <Layers class="h-4 w-4 text-purple-400" />
                                <span>{{
                                    isRTL
                                        ? 'توزيع الوحدات والمسؤوليات البرمجية'
                                        : 'Module Responsibilities & Directory Map'
                                }}</span>
                            </h3>

                            <div
                                class="grid grid-cols-1 gap-3 text-xs md:grid-cols-2"
                            >
                                <div
                                    class="space-y-1.5 rounded-2xl border border-white/10 bg-white/[0.03] p-4"
                                >
                                    <span
                                        class="block font-mono font-bold text-cyan-400"
                                        >app/Services/Scanner/</span
                                    >
                                    <p class="text-slate-300">
                                        {{
                                            isRTL
                                                ? 'إدارة الفحص المتوازي، تقسيم المسارات إلى حزم غير حاجزة (Non-blocking Chunks)، وحفظ السجلات في الذاكرة.'
                                                : 'Non-blocking parallel directory crawler, chunked queue execution, and live state store.'
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="space-y-1.5 rounded-2xl border border-white/10 bg-white/[0.03] p-4"
                                >
                                    <span
                                        class="block font-mono font-bold text-purple-400"
                                        >app/Services/Organizer/</span
                                    >
                                    <p class="text-slate-300">
                                        {{
                                            isRTL
                                                ? 'خوارزمية SceneNameParserService الذكية متعددة اللغات ومحرك إنشاء الروابط الصلبة NTFS.'
                                                : 'Intelligent multi-lingual scene parser and atomic zero-copy NTFS hardlink executor.'
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="space-y-1.5 rounded-2xl border border-white/10 bg-white/[0.03] p-4"
                                >
                                    <span
                                        class="block font-mono font-bold text-amber-400"
                                        >app/Services/Metadata/</span
                                    >
                                    <p class="text-slate-300">
                                        {{
                                            isRTL
                                                ? 'شلال جلب البيانات الوصفية (TMDb, OMDb, AniList) مع التعريب التلقائي وتجميع السلاسل (Boxsets).'
                                                : 'Metadata waterfall aggregator with Arabic translator and movie franchise clustering.'
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="space-y-1.5 rounded-2xl border border-white/10 bg-white/[0.03] p-4"
                                >
                                    <span
                                        class="block font-mono font-bold text-emerald-400"
                                        >app/Services/Subtitles/</span
                                    >
                                    <p class="text-slate-300">
                                        {{
                                            isRTL
                                                ? 'محلل المسارات الصوتية والترجمات المدمجة FFprobe ومحرك تنزيل الترجمات من SubDL و OpenSubtitles.'
                                                : 'Embedded subtitle stream extractor (FFprobe -> WebVTT) and SubDL/OpenSubtitles synchronization.'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 2. CORE PROCESSES & PIPELINES TAB -->
                <section
                    v-if="activeTab === 'core_processes'"
                    class="space-y-6"
                >
                    <div
                        class="glass-panel space-y-6 rounded-3xl border border-white/10 bg-slate-900/40 p-6 sm:p-8"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-white/10 pb-4"
                        >
                            <Activity class="h-6 w-6 text-indigo-400" />
                            <div>
                                <h2
                                    class="text-xl font-black text-white sm:text-2xl"
                                >
                                    {{
                                        isRTL
                                            ? 'خطوط المعالجة وسير العمليات (Core Pipelines)'
                                            : 'Core Processing Pipelines & Workflows'
                                    }}
                                </h2>
                                <p class="text-xs text-slate-400">
                                    Step-by-step Execution Cycles • Concurrency
                                    Models • Lifecycle Diagrams
                                </p>
                            </div>
                        </div>

                        <!-- Pipeline 1: Arabic & Scene Parser -->
                        <div
                            class="space-y-3 rounded-2xl border border-white/10 bg-white/[0.02] p-5"
                        >
                            <div class="flex items-center justify-between">
                                <h3
                                    class="flex items-center gap-2 text-sm font-bold text-white"
                                >
                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-lg bg-cyan-500/20 text-xs font-black text-cyan-400"
                                        >1</span
                                    >
                                    <span>{{
                                        isRTL
                                            ? 'خوارزمية تحليل المشهد وسياق المجلدات (Scene & Folder Parser)'
                                            : 'Scene & Folder-Context Parsing Pipeline'
                                    }}</span>
                                </h3>
                                <span
                                    class="rounded bg-cyan-500/10 px-2 py-0.5 font-mono text-[10px] text-cyan-400"
                                    >SceneNameParserService</span
                                >
                            </div>
                            <p class="text-xs leading-relaxed text-slate-300">
                                {{
                                    isRTL
                                        ? 'تعالج الخوارزمية الأسماء المعقدة، الترقيم الشرقي (١, ٢, ٣)، كلمات المواسم العربية (الموسم الأول، الجزء 2)، وتقوم بالوراثة الذكية من المجلد الأب عند وجود ملفات بأرقام فقط (مثل: مسلسل الاختيار\الحلقة 01.mp4).'
                                        : 'Parses complex scene release tags, normalizes Eastern Arabic numerals (١, ٢, ٣ -> 1, 2, 3), resolves Arabic season/episode keywords, and inherits context from ancestor directory names when filenames are purely numeric.'
                                }}
                            </p>
                            <div
                                class="space-y-1 rounded-xl bg-slate-950 p-3 font-mono text-[11px] text-slate-300"
                            >
                                <div class="font-bold text-cyan-400">
                                    // Raw Filename Input:
                                </div>
                                <div class="text-slate-400">
                                    "H:\Series\باب الحارة\الموسم ٢\الحلقة ٠٥
                                    (1080p Web-DL).mkv"
                                </div>
                                <div class="mt-2 font-bold text-emerald-400">
                                    // Parsed Domain Structure:
                                </div>
                                <div class="text-emerald-300">
                                    { title: "باب الحارة", season: 2, episode:
                                    5, resolution: "1080p", audio: "Web-DL" }
                                </div>
                            </div>
                        </div>

                        <!-- Pipeline 2: Hybrid Streaming -->
                        <div
                            class="space-y-3 rounded-2xl border border-white/10 bg-white/[0.02] p-5"
                        >
                            <div class="flex items-center justify-between">
                                <h3
                                    class="flex items-center gap-2 text-sm font-bold text-white"
                                >
                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-lg bg-indigo-500/20 text-xs font-black text-indigo-400"
                                        >2</span
                                    >
                                    <span>{{
                                        isRTL
                                            ? 'محرك البث الهجين والترميز الفوري (Hybrid Streaming Engine)'
                                            : 'Hybrid Byte-Range & Remuxing Pipeline'
                                    }}</span>
                                </h3>
                                <span
                                    class="rounded bg-indigo-500/10 px-2 py-0.5 font-mono text-[10px] text-indigo-400"
                                    >StreamController</span
                                >
                            </div>
                            <p class="text-xs leading-relaxed text-slate-300">
                                {{
                                    isRTL
                                        ? 'يتحقق المشغل من توافق ترميز الفيديو؛ إن كان متوافقاً (MP4/H.264) يتم البث المباشر عبر HTTP 206 Byte-Range باستهلاك 0% معالج. وإن كان بتنسيق قديم (AVI/MPEG-4) أو صوت غير مدعوم، يبدأ تحويل فوري مع تخزين كاش بالخلفية.'
                                        : 'Inspects media container and codecs. Compatible formats stream via zero-CPU HTTP 206 Partial Content in 256KB chunks. Incompatible audio/containers trigger on-the-fly fragmented MP4 remuxing with asynchronous background caching.'
                                }}
                            </p>
                        </div>

                        <!-- Pipeline 3: Boxsets & Collections -->
                        <div
                            class="space-y-3 rounded-2xl border border-white/10 bg-white/[0.02] p-5"
                        >
                            <div class="flex items-center justify-between">
                                <h3
                                    class="flex items-center gap-2 text-sm font-bold text-white"
                                >
                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-lg bg-purple-500/20 text-xs font-black text-purple-400"
                                        >3</span
                                    >
                                    <span>{{
                                        isRTL
                                            ? 'محرك تجميع سلاسل الأفلام (Franchise Boxsets & Collections)'
                                            : 'Franchise Boxset Clustering Pipeline'
                                    }}</span>
                                </h3>
                                <span
                                    class="rounded bg-purple-500/10 px-2 py-0.5 font-mono text-[10px] text-purple-400"
                                    >CollectionController</span
                                >
                            </div>
                            <p class="text-xs leading-relaxed text-slate-300">
                                {{
                                    isRTL
                                        ? 'تجميع الأفلام تلقائياً ضمن سلاسلها الرسمية (مثل: Harry Potter Collection، Lord of the Rings، Fast & Furious) وعرضها بتسلسل زمني دقيق في صفحة /collections.'
                                        : 'Clusters franchise installments automatically via TMDb collection IDs and offline heuristic keyword clustering, presenting complete chronological sagas under /collections.'
                                }}
                            </p>
                        </div>

                        <!-- Pipeline 4: Zero-Copy Hardlink Organizer -->
                        <div
                            class="space-y-3 rounded-2xl border border-white/10 bg-white/[0.02] p-5"
                        >
                            <div class="flex items-center justify-between">
                                <h3
                                    class="flex items-center gap-2 text-sm font-bold text-white"
                                >
                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-500/20 text-xs font-black text-emerald-400"
                                        >4</span
                                    >
                                    <span>{{
                                        isRTL
                                            ? 'محرك التنظيم والروابط الصلبة (Zero-Copy NTFS Hardlinks)'
                                            : 'Zero-Copy NTFS Hardlink Organizer'
                                    }}</span>
                                </h3>
                                <span
                                    class="rounded bg-emerald-500/10 px-2 py-0.5 font-mono text-[10px] text-emerald-400"
                                    >PhysicalOrganizerService</span
                                >
                            </div>
                            <p class="text-xs leading-relaxed text-slate-300">
                                {{
                                    isRTL
                                        ? 'إعادة هيكلة وتسمية مكتبة الوسائط بدون استهلاك أي مساحة إضافية على القرص وبدون قطع مسارات التورنت النشطة عبر روابط NTFS الصلبة مع حماية من تكرار التنفيذ.'
                                        : 'Restructures media into standard directory hierarchies using zero-copy NTFS hardlinks without consuming extra disk space or breaking active torrent seeding.'
                                }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- 3. GETTING STARTED & SETUP TAB -->
                <section
                    v-if="activeTab === 'getting_started'"
                    class="space-y-6"
                >
                    <div
                        class="glass-panel space-y-6 rounded-3xl border border-white/10 bg-slate-900/40 p-6 sm:p-8"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-white/10 pb-4"
                        >
                            <Sparkles class="h-6 w-6 text-cyan-400" />
                            <div>
                                <h2
                                    class="text-xl font-black text-white sm:text-2xl"
                                >
                                    {{
                                        isRTL
                                            ? 'البداية السريعة ومتطلبات التشغيل'
                                            : 'Getting Started & System Setup'
                                    }}
                                </h2>
                                <p class="text-xs text-slate-400">
                                    Installation • Environment Config • Desktop
                                    App Build
                                </p>
                            </div>
                        </div>

                        <div
                            class="space-y-4 text-xs text-slate-300 sm:text-sm"
                        >
                            <h3
                                class="text-sm font-bold tracking-wider text-white uppercase"
                            >
                                {{
                                    isRTL
                                        ? 'متطلبات النظام الأساسية'
                                        : 'System Prerequisites'
                                }}
                            </h3>
                            <ul
                                class="list-inside list-disc space-y-1.5 text-slate-300"
                            >
                                <li>
                                    <strong>PHP 8.2+</strong> (with extensions:
                                    pdo_sqlite, curl, mbstring, fileinfo, gd)
                                </li>
                                <li><strong>Node.js 20+ & npm</strong></li>
                                <li><strong>Composer 2.x</strong></li>
                                <li>
                                    <strong>FFmpeg / FFprobe</strong>
                                    (automatically located in PATH or
                                    storage/bin)
                                </li>
                            </ul>

                            <h3
                                class="pt-4 text-sm font-bold tracking-wider text-white uppercase"
                            >
                                {{
                                    isRTL
                                        ? 'أوامر التثبيت والتشغيل'
                                        : 'Installation Commands'
                                }}
                            </h3>
                            <div
                                class="space-y-2 rounded-2xl border border-white/10 bg-slate-950 p-4 font-mono text-xs text-cyan-300"
                            >
                                <div>composer install</div>
                                <div>npm install</div>
                                <div>php artisan migrate</div>
                                <div>npm run build</div>
                                <div>
                                    npm run desktop:build
                                    <span class="text-slate-500"
                                        >// Builds standalone Windows .exe</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 4. SCANNER VS ORGANIZER TAB -->
                <section
                    v-if="activeTab === 'scanner_vs_organizer'"
                    class="space-y-6"
                >
                    <div
                        class="glass-panel space-y-6 rounded-3xl border border-white/10 bg-slate-900/40 p-6 sm:p-8"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-white/10 pb-4"
                        >
                            <FolderSync class="h-6 w-6 text-cyan-400" />
                            <div>
                                <h2
                                    class="text-xl font-black text-white sm:text-2xl"
                                >
                                    {{
                                        isRTL
                                            ? 'الفاحص الافتراضي مقابل المنظم الفعلي'
                                            : 'Virtual Scanner vs Physical Organizer'
                                    }}
                                </h2>
                                <p class="text-xs text-slate-400">
                                    Virtual In-Memory Indexing vs Physical
                                    Zero-Copy Hardlink Relocation
                                </p>
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-1 gap-4 text-xs md:grid-cols-2"
                        >
                            <div
                                class="space-y-2 rounded-2xl border border-cyan-500/20 bg-cyan-500/5 p-5"
                            >
                                <h4
                                    class="flex items-center gap-2 text-sm font-bold text-cyan-400"
                                >
                                    <ScanLine class="h-4 w-4" />
                                    <span>{{
                                        isRTL
                                            ? 'الفاحص الافتراضي (Virtual Scanner)'
                                            : 'Virtual Scanner (Read-Only)'
                                    }}</span>
                                </h4>
                                <p class="leading-relaxed text-slate-300">
                                    {{
                                        isRTL
                                            ? 'يقوم بقراءة المجلدات وفهرستها داخل قاعدة بيانات SQLite بدون لمس أو تحريك أي ملف من مكانه الأصلي على الإطلاق. آمن 100% للتصفح السريع.'
                                            : 'Reads directories and stores metadata into the SQLite database without moving, renaming, or touching original files. 100% read-only.'
                                    }}
                                </p>
                            </div>

                            <div
                                class="space-y-2 rounded-2xl border border-purple-500/20 bg-purple-500/5 p-5"
                            >
                                <h4
                                    class="flex items-center gap-2 text-sm font-bold text-purple-400"
                                >
                                    <FolderSync class="h-4 w-4" />
                                    <span>{{
                                        isRTL
                                            ? 'المنظم الفعلي (Physical Organizer)'
                                            : 'Physical Hardlink Organizer'
                                    }}</span>
                                </h4>
                                <p class="leading-relaxed text-slate-300">
                                    {{
                                        isRTL
                                            ? 'يقوم بإنشاء روابط صلبة NTFS في مجلد جديد منظم (Movies / Series) بدون مضاعفة المساحة، مع إمكانية المعاينة قبل التنفيذ وتأكيد كل خطوة.'
                                            : 'Creates zero-copy NTFS hardlinks inside standard destination directories without doubling disk space usage or interrupting active torrent seeds.'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 5. METADATA PROVIDERS TAB -->
                <section
                    v-if="activeTab === 'metadata_providers'"
                    class="space-y-6"
                >
                    <div
                        class="glass-panel space-y-6 rounded-3xl border border-white/10 bg-slate-900/40 p-6 sm:p-8"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-white/10 pb-4"
                        >
                            <Database class="h-6 w-6 text-amber-400" />
                            <div>
                                <h2
                                    class="text-xl font-black text-white sm:text-2xl"
                                >
                                    {{
                                        isRTL
                                            ? 'شلال مزودات الميتاداتا ومصادر البيانات'
                                            : 'Metadata Waterfall Providers'
                                    }}
                                </h2>
                                <p class="text-xs text-slate-400">
                                    Hierarchical Resolution • Arabic Translation
                                    • Fallback Cascades
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3 text-xs">
                            <div
                                class="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-white/[0.03] p-4"
                            >
                                <div>
                                    <h4 class="font-bold text-white">
                                        1. The Movie Database (TMDb)
                                    </h4>
                                    <p class="text-slate-400">
                                        Primary provider for official posters,
                                        backdrops, cast, genres, and
                                        collections.
                                    </p>
                                </div>
                                <span
                                    class="rounded-xl bg-cyan-500/20 px-2.5 py-1 text-[10px] font-bold text-cyan-300"
                                    >Primary API</span
                                >
                            </div>

                            <div
                                class="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-white/[0.03] p-4"
                            >
                                <div>
                                    <h4 class="font-bold text-white">
                                        2. Open Movie Database (OMDb / IMDb)
                                    </h4>
                                    <p class="text-slate-400">
                                        Enriches exact IMDb ratings, Metascores,
                                        age ratings, and awards.
                                    </p>
                                </div>
                                <span
                                    class="rounded-xl bg-amber-500/20 px-2.5 py-1 text-[10px] font-bold text-amber-300"
                                    >IMDb Scores</span
                                >
                            </div>

                            <div
                                class="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-white/[0.03] p-4"
                            >
                                <div>
                                    <h4 class="font-bold text-white">
                                        3. AniList GraphQL
                                    </h4>
                                    <p class="text-slate-400">
                                        Japanese anime specialist for romaji
                                        titles, episode schedules, and studios.
                                    </p>
                                </div>
                                <span
                                    class="rounded-xl bg-purple-500/20 px-2.5 py-1 text-[10px] font-bold text-purple-300"
                                    >Anime Engine</span
                                >
                            </div>

                            <div
                                class="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-white/[0.03] p-4"
                            >
                                <div>
                                    <h4 class="font-bold text-white">
                                        4. Arabic Translation Waterfall
                                    </h4>
                                    <p class="text-slate-400">
                                        Translates overviews, titles, and tags
                                        into clean Arabic automatically.
                                    </p>
                                </div>
                                <span
                                    class="rounded-xl bg-emerald-500/20 px-2.5 py-1 text-[10px] font-bold text-emerald-300"
                                    >Bilingual Hub</span
                                >
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 6. PLAYER & STREAMING TAB -->
                <section
                    v-if="activeTab === 'player_streaming'"
                    class="space-y-6"
                >
                    <div
                        class="glass-panel space-y-6 rounded-3xl border border-white/10 bg-slate-900/40 p-6 sm:p-8"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-white/10 pb-4"
                        >
                            <Play class="h-6 w-6 text-cyan-400" />
                            <div>
                                <h2
                                    class="text-xl font-black text-white sm:text-2xl"
                                >
                                    {{
                                        isRTL
                                            ? 'المشغل السينمائي ومحرك البث والترميز'
                                            : 'Cinema Player & Streaming Engine'
                                    }}
                                </h2>
                                <p class="text-xs text-slate-400">
                                    Byte-Range HTTP 206 • WebVTT Subtitles •
                                    Audio Track Selection
                                </p>
                            </div>
                        </div>

                        <div
                            class="space-y-4 text-xs text-slate-300 sm:text-sm"
                        >
                            <p class="leading-relaxed">
                                {{
                                    isRTL
                                        ? 'يتميز المشغل بدعم اختصارات لوحة المفاتيح الكاملة، استئناف التشغيل الذكي، التحكم بحجم الصوت وسرعة العرض، وتحميل الترجمات العربية بضغطة زر.'
                                        : 'Features rich keyboard shortcuts, smart progress resume, audio booster (up to 200%), custom playback speeds, and instant Arabic subtitle matching.'
                                }}
                            </p>

                            <div
                                class="grid grid-cols-2 gap-3 pt-2 font-mono text-xs sm:grid-cols-4"
                            >
                                <div
                                    class="rounded-xl border border-white/10 bg-slate-950 p-3 text-center"
                                >
                                    <div class="font-bold text-cyan-400">
                                        Space / K
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        {{
                                            isRTL
                                                ? 'تشغيل / إيقاف'
                                                : 'Play / Pause'
                                        }}
                                    </div>
                                </div>
                                <div
                                    class="rounded-xl border border-white/10 bg-slate-950 p-3 text-center"
                                >
                                    <div class="font-bold text-cyan-400">F</div>
                                    <div class="text-[10px] text-slate-400">
                                        {{
                                            isRTL ? 'شاشة كاملة' : 'Fullscreen'
                                        }}
                                    </div>
                                </div>
                                <div
                                    class="rounded-xl border border-white/10 bg-slate-950 p-3 text-center"
                                >
                                    <div class="font-bold text-cyan-400">
                                        Left / Right
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        {{
                                            isRTL
                                                ? 'تقديم / ترجيع 5ث'
                                                : 'Seek 5s'
                                        }}
                                    </div>
                                </div>
                                <div
                                    class="rounded-xl border border-white/10 bg-slate-950 p-3 text-center"
                                >
                                    <div class="font-bold text-cyan-400">M</div>
                                    <div class="text-[10px] text-slate-400">
                                        {{ isRTL ? 'كتم الصوت' : 'Mute Audio' }}
                                    </div>
                                </div>
                            </div>

                            <!-- Advanced Features List -->
                            <div
                                class="grid grid-cols-1 gap-3 pt-3 md:grid-cols-3"
                            >
                                <div
                                    class="space-y-1.5 rounded-2xl border border-white/10 bg-white/5 p-4"
                                >
                                    <div
                                        class="text-xs font-bold text-cyan-400"
                                    >
                                        {{
                                            isRTL
                                                ? 'بث مباشر فوري (Direct Stream)'
                                                : 'Zero-CPU Direct Streaming'
                                        }}
                                    </div>
                                    <p class="text-[11px] text-slate-400">
                                        {{
                                            isRTL
                                                ? 'تشغيل فوري بصيغ H.264 و AAC و Dolby Digital بدون أي حمل على المعالج أو تأخير في الصوت.'
                                                : 'Hardware-accelerated HTTP 206 streaming for H.264/AAC/AC3 with zero server transcode latency.'
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="space-y-1.5 rounded-2xl border border-white/10 bg-white/5 p-4"
                                >
                                    <div
                                        class="text-xs font-bold text-purple-400"
                                    >
                                        {{
                                            isRTL
                                                ? 'استوديو الخطوط والترجمة'
                                                : 'Subtitle Typography Studio'
                                        }}
                                    </div>
                                    <p class="text-[11px] text-slate-400">
                                        {{
                                            isRTL
                                                ? 'خطوط عربية مخصصة (Cairo, Jakarta) مع تباين سينمائي فائق ودعم كامل لاتجاه RTL.'
                                                : 'Native Cairo & Jakarta fonts, high-contrast text outlines, and full RTL menu alignment.'
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="space-y-1.5 rounded-2xl border border-white/10 bg-white/5 p-4"
                                >
                                    <div
                                        class="text-xs font-bold text-emerald-400"
                                    >
                                        {{
                                            isRTL
                                                ? 'إدارة الذاكرة المؤقتة'
                                                : 'Media Cache Maintenance'
                                        }}
                                    </div>
                                    <p class="text-[11px] text-slate-400">
                                        {{
                                            isRTL
                                                ? 'مراقبة سعة تخزين البث وتنظيف ملفات الترميز المؤقتة بضغطة زر من صفحة الإعدادات.'
                                                : 'Real-time cache monitoring and 1-click transcode cache purge from the Settings dashboard.'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 7. NAMING RULES TAB -->
                <section v-if="activeTab === 'naming_rules'" class="space-y-6">
                    <div
                        class="glass-panel space-y-6 rounded-3xl border border-white/10 bg-slate-900/40 p-6 sm:p-8"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-white/10 pb-4"
                        >
                            <Layers class="h-6 w-6 text-purple-400" />
                            <div>
                                <h2
                                    class="text-xl font-black text-white sm:text-2xl"
                                >
                                    {{
                                        isRTL
                                            ? 'معايير وقواعد تسمية الملفات والمجلدات'
                                            : 'Scene Naming Rules & Arabic Patterns'
                                    }}
                                </h2>
                                <p class="text-xs text-slate-400">
                                    Naming Standards • Eastern Numerals • Folder
                                    Context Resolution
                                </p>
                            </div>
                        </div>

                        <div
                            class="space-y-4 text-xs text-slate-300 sm:text-sm"
                        >
                            <p class="leading-relaxed">
                                {{
                                    isRTL
                                        ? 'يتعامل المحرك بمرونة فائقة مع كافة أنماط التسمية؛ ومع ذلك يُنصح باتباع الأنماط القياسية للحصول على دقة مطابقة 100%:'
                                        : 'While the engine handles almost any chaotic naming pattern, following standard scene conventions ensures 100% instant match accuracy:'
                                }}
                            </p>

                            <div class="space-y-2 font-mono text-xs">
                                <div
                                    class="rounded-xl border border-emerald-500/30 bg-slate-950 p-3"
                                >
                                    <span
                                        class="block font-bold text-emerald-400"
                                        >// Movies Standard:</span
                                    >
                                    <span class="text-slate-300"
                                        >Movies/Inception (2010)/Inception
                                        (2010) [1080p Bluray].mkv</span
                                    >
                                </div>
                                <div
                                    class="rounded-xl border border-cyan-500/30 bg-slate-950 p-3"
                                >
                                    <span class="font-bold text-cyan-400"
                                        >// TV Series Standard:</span
                                    >
                                    <span class="text-slate-300"
                                        >Series/Breaking Bad/Season 01/Breaking
                                        Bad S01E01.mkv</span
                                    >
                                </div>
                                <div
                                    class="rounded-xl border border-purple-500/30 bg-slate-950 p-3"
                                >
                                    <span class="font-bold text-purple-400"
                                        >// Arabic TV Series Pattern:</span
                                    >
                                    <span class="text-slate-300"
                                        >Series/الاختيار/الموسم 1/الاختيار الحلقة
                                        01.mp4</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 8. FAQ TAB -->
                <section v-if="activeTab === 'faq'" class="space-y-6">
                    <div
                        class="glass-panel space-y-6 rounded-3xl border border-white/10 bg-slate-900/40 p-6 sm:p-8"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-white/10 pb-4"
                        >
                            <HelpCircle class="h-6 w-6 text-amber-400" />
                            <div>
                                <h2
                                    class="text-xl font-black text-white sm:text-2xl"
                                >
                                    {{
                                        isRTL
                                            ? 'الأسئلة الشائعة والحلول التقنية'
                                            : 'FAQ & Technical Troubleshooting'
                                    }}
                                </h2>
                                <p class="text-xs text-slate-400">
                                    Common Questions • FFmpeg Setup • Error
                                    Resolution
                                </p>
                            </div>
                        </div>

                        <div
                            class="space-y-3 text-xs text-slate-300 sm:text-sm"
                        >
                            <div
                                class="space-y-1.5 rounded-2xl border border-white/10 bg-white/[0.03] p-4"
                            >
                                <h4 class="text-xs font-bold text-white">
                                    {{
                                        isRTL
                                            ? 'س: ماذا أفعل إذا تم التعرف على اسم فيلم أو مسلسل بشكل خاطئ؟'
                                            : 'Q: What should I do if a file was incorrectly identified?'
                                    }}
                                </h4>
                                <p
                                    class="text-xs leading-relaxed text-slate-400"
                                >
                                    {{
                                        isRTL
                                            ? 'افتح صفحة "استوديو الميتاداتا" (/metadata) أو انقر على أيقونة "Fix Match" على بطاقة الفيلم، يمكنك البحث بالاسم أو وضع رقم TMDb / IMDb مباشرة لجلب البيانات الصحيحة في ثانية واحدة.'
                                            : 'Open the Metadata Studio (/metadata) or click "Fix Match" on the media card. You can search online or input a direct TMDb/IMDb ID to fetch exact details instantly.'
                                    }}
                                </p>
                            </div>

                            <div
                                class="space-y-1.5 rounded-2xl border border-white/10 bg-white/[0.03] p-4"
                            >
                                <h4 class="text-xs font-bold text-white">
                                    {{
                                        isRTL
                                            ? 'س: هل يستهلك المنظم الفعلي مساحة إضافية على الهارد ديسك؟'
                                            : 'Q: Does the physical organizer duplicate files and consume extra space?'
                                    }}
                                </h4>
                                <p
                                    class="text-xs leading-relaxed text-slate-400"
                                >
                                    {{
                                        isRTL
                                            ? 'كلا! يستخدم النظام الروابط الصلبة NTFS (Hardlinks) التي تشير إلى نفس كتل البيانات على القرص بدون استهلاك بايت واحد إضافي، وبدون التأثير على ملفات التورنت الأصلية.'
                                            : 'No! The system creates zero-copy NTFS hardlinks pointing to the exact same disk sectors with 0% extra space usage.'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Footer Engineering Credits -->
                <div
                    class="rounded-2xl border border-white/10 bg-slate-900/60 p-4 text-center text-xs text-slate-400"
                >
                    <span class="font-bold text-slate-300"
                        >Creative Media Hub</span
                    >
                    —
                    {{
                        isRTL
                            ? 'تم التصميم والتطوير بواسطة م. حسن محمد حسن'
                            : 'Engineered & Developed by Eng. Hasan Mohammad Hasan'
                    }}
                </div>
            </div>
        </div>
    </AppLayout>
</template>
