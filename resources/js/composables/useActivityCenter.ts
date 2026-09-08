import { computed } from 'vue';
import { isActivityCenterOpen, activeActivityTab, openActivityCenter as rawOpen, closeActivityCenter as rawClose, type ActivityTab } from './useActivityCenterState';
import { useScanner } from './useScanner';
import { useOrganizerPlan } from './useOrganizerPlan';
import { useSubtitleJob } from './useSubtitleJob';

export function useActivityCenter() {
    const { isScanning, isPaused: isScanPaused, scanStatus, pauseScan, resumeScan, cancelScan } = useScanner();
    const { isAnalyzing, isPaused: isOrgPaused, planJobStatus, pausePlanJob, resumePlanJob, cancelPlanJob } = useOrganizerPlan();
    const { isSubtitleRunning, isSubtitlePaused, subtitleStatus, pauseHealthJob, resumeHealthJob, cancelHealthJob } = useSubtitleJob();

    const activeJobsCount = computed(() => {
        let count = 0;
        if (isScanning.value || isScanPaused.value) count++;
        if (isAnalyzing.value || isOrgPaused.value) count++;
        if (isSubtitleRunning.value || isSubtitlePaused.value) count++;
        return count;
    });

    const isAnyRunning = computed(() => isScanning.value || isAnalyzing.value || isSubtitleRunning.value);
    const isAnyPaused = computed(() => isScanPaused.value || isOrgPaused.value || isSubtitlePaused.value);
    const isAnyActive = computed(() => activeJobsCount.value > 0);

    const openActivityCenter = (tab?: ActivityTab) => {
        if (tab) {
            rawOpen(tab);
        } else {
            // Auto-detect which tab is active
            if (isScanning.value || isScanPaused.value) {
                rawOpen('scanner');
            } else if (isAnalyzing.value || isOrgPaused.value) {
                rawOpen('organizer');
            } else if (isSubtitleRunning.value || isSubtitlePaused.value) {
                rawOpen('subtitles');
            } else {
                rawOpen('scanner');
            }
        }
    };

    const closeActivityCenter = () => {
        rawClose();
    };

    const pauseAll = async () => {
        const promises: Promise<any>[] = [];
        if (isScanning.value) promises.push(pauseScan());
        if (isAnalyzing.value) promises.push(pausePlanJob());
        if (isSubtitleRunning.value) promises.push(pauseHealthJob());
        await Promise.allSettled(promises);
    };

    const resumeAll = async () => {
        const promises: Promise<any>[] = [];
        if (isScanPaused.value) promises.push(resumeScan());
        if (isOrgPaused.value) promises.push(resumePlanJob());
        if (isSubtitlePaused.value) promises.push(resumeHealthJob());
        await Promise.allSettled(promises);
    };

    const cancelAll = async () => {
        const promises: Promise<any>[] = [];
        if (isScanning.value || isScanPaused.value) promises.push(cancelScan());
        if (isAnalyzing.value || isOrgPaused.value) promises.push(cancelPlanJob());
        if (isSubtitleRunning.value || isSubtitlePaused.value) promises.push(cancelHealthJob());
        await Promise.allSettled(promises);
    };

    return {
        isActivityCenterOpen,
        activeTab: activeActivityTab,
        activeJobsCount,
        isAnyRunning,
        isAnyPaused,
        isAnyActive,
        scanStatus,
        planJobStatus,
        subtitleStatus,
        openActivityCenter,
        closeActivityCenter,
        pauseAll,
        resumeAll,
        cancelAll,
    };
}
