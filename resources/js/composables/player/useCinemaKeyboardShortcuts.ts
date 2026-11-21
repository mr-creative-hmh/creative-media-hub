import { onMounted, onBeforeUnmount } from 'vue';

export interface UseCinemaKeyboardShortcutsActions {
    onTogglePlay: () => void;
    onSeekRelative: (seconds: number) => void;
    onVolumeUp: () => void;
    onVolumeDown: () => void;
    onToggleMute: () => void;
    onToggleFullscreen: () => void;
    onToggleSubtitles: () => void;
    onToggleEqualizer: () => void;
    onToggleDiagnostics: () => void;
    onClose: () => void;
    isModalOpen?: () => boolean;
    closeTopModal?: () => boolean;
}

export function useCinemaKeyboardShortcuts(
    actions: UseCinemaKeyboardShortcutsActions,
) {
    const handleKeyDown = (e: KeyboardEvent) => {
        // Do not intercept if focused on inputs
        const target = e.target as HTMLElement;
        if (
            target &&
            (target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.tagName === 'SELECT')
        ) {
            return;
        }

        switch (e.code) {
            case 'Space':
            case 'KeyK':
                e.preventDefault();
                actions.onTogglePlay();
                break;
            case 'ArrowLeft':
                e.preventDefault();
                actions.onSeekRelative(-5);
                break;
            case 'ArrowRight':
                e.preventDefault();
                actions.onSeekRelative(5);
                break;
            case 'KeyJ':
                e.preventDefault();
                actions.onSeekRelative(-10);
                break;
            case 'KeyL':
                e.preventDefault();
                actions.onSeekRelative(10);
                break;
            case 'ArrowUp':
                e.preventDefault();
                actions.onVolumeUp();
                break;
            case 'ArrowDown':
                e.preventDefault();
                actions.onVolumeDown();
                break;
            case 'KeyM':
                e.preventDefault();
                actions.onToggleMute();
                break;
            case 'KeyF':
                e.preventDefault();
                actions.onToggleFullscreen();
                break;
            case 'KeyS':
                e.preventDefault();
                actions.onToggleSubtitles();
                break;
            case 'KeyC':
                e.preventDefault();
                actions.onToggleEqualizer();
                break;
            case 'KeyD':
                e.preventDefault();
                actions.onToggleDiagnostics();
                break;
            case 'Escape':
                e.preventDefault();
                if (
                    actions.isModalOpen &&
                    actions.isModalOpen() &&
                    actions.closeTopModal
                ) {
                    actions.closeTopModal();
                } else {
                    actions.onClose();
                }
                break;
        }
    };

    onMounted(() => {
        window.addEventListener('keydown', handleKeyDown);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', handleKeyDown);
    });
}
