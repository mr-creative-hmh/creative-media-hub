import { ref } from 'vue';

export type ActivityTab = 'scanner' | 'organizer' | 'subtitles';

export const isActivityCenterOpen = ref(false);
export const activeActivityTab = ref<ActivityTab>('scanner');

export const openActivityCenter = (tab?: ActivityTab) => {
    if (tab) {
        activeActivityTab.value = tab;
    }
    isActivityCenterOpen.value = true;
};

export const closeActivityCenter = () => {
    isActivityCenterOpen.value = false;
};
