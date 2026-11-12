/**
 * AC-3 (Dolby Digital) / Multi-Track Audio Bitstream Processor for Web Media Engine
 * Inspects and parses AC-3 and multi-channel audio frames directly in the browser.
 * REA tool design pattern: bitstream syncword alignment and Web Audio API integration.
 */

export interface AudioHeaderInfo {
    codec: 'ac3' | 'eac3' | 'dts' | 'aac' | 'unknown';
    samplingRate: number;
    channels: number;
    bitrate: number;
    frameSize: number;
}

export class Ac3AudioProcessor {
    private audioContext: AudioContext | null = null;
    private audioDecoder: any = null; // WebCodecs AudioDecoder if supported
    private isWebCodecsSupported: boolean = false;

    // AC-3 Sample Rate Lookup table
    private static readonly SAMPLE_RATES = [48000, 44100, 32000];

    // AC-3 Bitrate Lookup table (kbps)
    private static readonly BITRATES = [
        32, 40, 48, 56, 64, 80, 96, 112, 128, 160,
        192, 224, 256, 320, 384, 448, 512, 576, 640
    ];

    constructor(audioContext?: AudioContext) {
        if (audioContext) {
            this.audioContext = audioContext;
        }
        if (typeof window !== 'undefined' && 'AudioDecoder' in window) {
            this.isWebCodecsSupported = true;
        }
    }

    /**
     * Inspect audio byte chunk and detect header / format.
     */
    public static parseAudioFrame(data: Uint8Array): AudioHeaderInfo | null {
        if (!data || data.length < 8) return null;

        for (let i = 0; i < data.length - 7; i++) {
            // 1. Check for AC-3 / E-AC-3 Syncword (0x0B, 0x77)
            if (data[i] === 0x0B && data[i + 1] === 0x77) {
                const fscod = (data[i + 4] >> 6) & 0x03;
                if (fscod === 3) {
                    // E-AC-3 (Enhanced AC-3 / Dolby Digital Plus)
                    const fscod2 = (data[i + 4] >> 4) & 0x03;
                    const sampleRate = fscod2 === 0 ? 24000 : fscod2 === 1 ? 22050 : 16000;
                    const frameSize = (((data[i + 2] & 0x07) << 8) | data[i + 3]) + 1;
                    return {
                        codec: 'eac3',
                        samplingRate: sampleRate,
                        channels: 6,
                        bitrate: 384,
                        frameSize: frameSize * 2,
                    };
                }

                const frmsizecod = data[i + 4] & 0x3F;
                const sampleRate = Ac3AudioProcessor.SAMPLE_RATES[fscod];
                const bitrateIndex = frmsizecod >> 1;
                const bitrate = Ac3AudioProcessor.BITRATES[bitrateIndex] || 384;

                // Standard AC-3 frame size calculation
                let frameSize = 0;
                if (fscod === 0) { // 48kHz
                    frameSize = 4 * bitrate;
                } else if (fscod === 1) { // 44.1kHz
                    frameSize = Math.floor((2 * bitrate * 1536) / 44100);
                } else { // 32kHz
                    frameSize = 6 * bitrate;
                }

                // Channel mode (acmod)
                const acmod = (data[i + 6] >> 5) & 0x07;
                let channels = 2;
                if (acmod === 1) channels = 1; // mono
                else if (acmod === 2) channels = 2; // stereo
                else if (acmod >= 3) channels = 6; // 5.1

                return {
                    codec: 'ac3',
                    samplingRate: sampleRate,
                    channels: channels,
                    bitrate: bitrate,
                    frameSize: frameSize > 0 ? frameSize : 1536,
                };
            }

            // 2. Check for DTS Syncword (0x7F, 0xFE, 0x80, 0x01)
            if (data[i] === 0x7F && data[i + 1] === 0xFE && data[i + 2] === 0x80 && data[i + 3] === 0x01) {
                return {
                    codec: 'dts',
                    samplingRate: 48000,
                    channels: 6,
                    bitrate: 768,
                    frameSize: 2048,
                };
            }

            // 3. Check for AAC ADTS Syncword (0xFF, 0xF0..0xFF)
            if (data[i] === 0xFF && (data[i + 1] & 0xF0) === 0xF0) {
                const sampleRateIndex = (data[i + 2] >> 2) & 0x0F;
                const sampleRates = [96000, 88200, 64000, 48000, 44100, 32000, 24000, 22050, 16000, 12000, 11025, 8000];
                const channels = ((data[i + 2] & 0x01) << 2) | ((data[i + 3] >> 6) & 0x03);
                const frameLength = ((data[i + 3] & 0x03) << 11) | (data[i + 4] << 3) | ((data[i + 5] >> 5) & 0x07);

                return {
                    codec: 'aac',
                    samplingRate: sampleRates[sampleRateIndex] || 48000,
                    channels: channels || 2,
                    bitrate: 192,
                    frameSize: frameLength,
                };
            }
        }

        return null;
    }

    /**
     * Initialize WebCodecs AudioDecoder for hardware/system decoding if available.
     */
    public async initWebCodecsDecoder(codecStr: string, sampleRate = 48000, channels = 2): Promise<boolean> {
        if (!this.isWebCodecsSupported) return false;

        try {
            const config = {
                codec: codecStr === 'ac3' ? 'ac-3' : codecStr === 'eac3' ? 'ec-3' : 'mp4a.40.2',
                sampleRate,
                numberOfChannels: channels,
            };

            const isSupported = await (window as any).AudioDecoder.isConfigSupported(config);
            if (!isSupported.supported) {
                return false;
            }

            this.audioDecoder = new (window as any).AudioDecoder({
                output: (audioData: any) => {
                    this.renderDecodedAudio(audioData);
                },
                error: (err: any) => {
                    console.warn('WebCodecs AudioDecoder error:', err);
                }
            });

            this.audioDecoder.configure(config);
            return true;
        } catch (e) {
            console.warn('Failed to configure AudioDecoder:', e);
            return false;
        }
    }

    private renderDecodedAudio(audioData: any) {
        if (!this.audioContext) return;
        try {
            const channels = audioData.numberOfChannels;
            const length = audioData.numberOfFrames;
            const buffer = this.audioContext.createBuffer(channels, length, audioData.sampleRate);

            for (let c = 0; c < channels; c++) {
                const channelData = buffer.getChannelData(c);
                audioData.copyTo(channelData, { planeIndex: c });
            }

            const source = this.audioContext.createBufferSource();
            source.buffer = buffer;
            source.connect(this.audioContext.destination);
            source.start(this.audioContext.currentTime);

            audioData.close();
        } catch (e) {}
    }
}

