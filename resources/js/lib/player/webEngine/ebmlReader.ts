/**
 * EBML / Matroska (MKV) Binary Parser for Web Media Engine
 * Zero-copy binary parser built with REA binary analysis principles.
 */

export interface TrackInfo {
    trackNumber: number;
    trackType: 'video' | 'audio' | 'subtitle' | 'other';
    codecId: string;
    codecPrivate?: Uint8Array;
    width?: number;
    height?: number;
    samplingRate?: number;
    channels?: number;
    name?: string;
    language?: string;
    isDefault?: boolean;
}

export interface MediaSample {
    trackNumber: number;
    timecode: number; // in milliseconds
    isKeyframe: boolean;
    data: Uint8Array;
}

export interface CuePoint {
    time: number; // in milliseconds
    clusterOffset: number;
}

export class EbmlReader {
    private buffer: Uint8Array;
    private pos: number = 0;

    constructor(buffer: Uint8Array) {
        this.buffer = buffer;
    }

    public get position(): number {
        return this.pos;
    }

    public set position(p: number) {
        this.pos = Math.max(0, Math.min(this.buffer.length, p));
    }

    public get length(): number {
        return this.buffer.length;
    }

    public hasBytes(count: number): boolean {
        return this.pos + count <= this.buffer.length;
    }

    /**
     * Read Variable-Length Integer (EBML VINT) used for Element IDs and Data Sizes.
     */
    public readVint(
        maskWidth = true,
    ): { value: number; length: number } | null {
        if (!this.hasBytes(1)) return null;
        const firstByte = this.buffer[this.pos];
        if (firstByte === 0) return null; // Invalid VINT

        let length = 1;
        let mask = 0x80;
        while ((firstByte & mask) === 0 && length <= 8) {
            length++;
            mask >>= 1;
        }

        if (length > 8 || !this.hasBytes(length)) return null;

        let value = maskWidth ? firstByte & ~mask : firstByte;
        for (let i = 1; i < length; i++) {
            value = value * 256 + this.buffer[this.pos + i];
        }

        return { value, length };
    }

    /**
     * Read EBML Element ID (preserves the length mask bit).
     */
    public readElementId(): number | null {
        const vint = this.readVint(false);
        if (!vint) return null;
        this.pos += vint.length;
        return vint.value;
    }

    /**
     * Read EBML Element Size (strips the length mask bit).
     */
    public readElementSize(): number | null {
        const vint = this.readVint(true);
        if (!vint) return null;
        this.pos += vint.length;
        return vint.value;
    }

    public readUint(size: number): number {
        let val = 0;
        for (let i = 0; i < size; i++) {
            val = val * 256 + this.buffer[this.pos++];
        }
        return val;
    }

    public readFloat(size: number): number {
        const view = new DataView(
            this.buffer.buffer,
            this.buffer.byteOffset + this.pos,
            size,
        );
        this.pos += size;
        if (size === 4) return view.getFloat32(0, false);
        if (size === 8) return view.getFloat64(0, false);
        return 0;
    }

    public readString(size: number): string {
        const bytes = this.buffer.subarray(this.pos, this.pos + size);
        this.pos += size;
        return new TextDecoder('utf-8').decode(bytes).replace(/\0/g, '');
    }

    public readBytes(size: number): Uint8Array {
        const bytes = this.buffer.subarray(this.pos, this.pos + size);
        this.pos += size;
        return bytes;
    }

    public skip(bytes: number) {
        this.pos = Math.min(this.buffer.length, this.pos + bytes);
    }
}

/**
 * Matroska Demuxer: Parses headers, tracks, and clusters into WebMediaEngine samples.
 */
export class MatroskaDemuxer {
    public timecodeScale: number = 1000000; // default: 1ms (1,000,000 ns)
    public duration: number = 0;
    public tracks: Map<number, TrackInfo> = new Map();
    public cues: CuePoint[] = [];

    public parseHeaderAndTracks(data: Uint8Array): boolean {
        const reader = new EbmlReader(data);

        while (reader.hasBytes(4)) {
            const id = reader.readElementId();
            const size = reader.readElementSize();
            if (id === null || size === null) break;

            if (id === 0x1a45dfa3) {
                // EBML Header
                reader.skip(size);
            } else if (id === 0x18538067) {
                // Segment
                // Descend into Segment elements
                this.parseSegmentChildren(reader, size);
                return this.tracks.size > 0;
            } else {
                reader.skip(size);
            }
        }

        return this.tracks.size > 0;
    }

    private parseSegmentChildren(reader: EbmlReader, segmentSize: number) {
        const endPos = reader.position + segmentSize;

        while (reader.position < endPos && reader.hasBytes(4)) {
            const id = reader.readElementId();
            const size = reader.readElementSize();
            if (id === null || size === null) break;

            if (id === 0x1549a966) {
                // Info
                this.parseInfo(reader, size);
            } else if (id === 0x1654ae6b) {
                // Tracks
                this.parseTracks(reader, size);
            } else if (id === 0x1c53bb6b) {
                // Cues
                this.parseCues(reader, size);
            } else if (id === 0x1f43b675) {
                // First Cluster encountered - header scan complete
                reader.skip(size);
                break;
            } else {
                reader.skip(size);
            }
        }
    }

    private parseInfo(reader: EbmlReader, size: number) {
        const endPos = reader.position + size;
        while (reader.position < endPos) {
            const id = reader.readElementId();
            const elSize = reader.readElementSize();
            if (id === null || elSize === null) break;

            if (id === 0x2ad7b1) {
                // TimecodeScale
                this.timecodeScale = reader.readUint(elSize);
            } else if (id === 0x4489) {
                // Duration
                const rawDur = reader.readFloat(elSize);
                this.duration = (rawDur * this.timecodeScale) / 1000000000; // in seconds
            } else {
                reader.skip(elSize);
            }
        }
    }

    private parseTracks(reader: EbmlReader, size: number) {
        const endPos = reader.position + size;
        while (reader.position < endPos) {
            const id = reader.readElementId();
            const elSize = reader.readElementSize();
            if (id === null || elSize === null) break;

            if (id === 0xae) {
                // TrackEntry
                this.parseTrackEntry(reader, elSize);
            } else {
                reader.skip(elSize);
            }
        }
    }

    private parseTrackEntry(reader: EbmlReader, size: number) {
        const endPos = reader.position + size;
        const track: Partial<TrackInfo> = {
            trackType: 'other',
            isDefault: true,
        };

        while (reader.position < endPos) {
            const id = reader.readElementId();
            const elSize = reader.readElementSize();
            if (id === null || elSize === null) break;

            switch (id) {
                case 0xd7: // TrackNumber
                    track.trackNumber = reader.readUint(elSize);
                    break;
                case 0x83: {
                    // TrackType
                    const typeNum = reader.readUint(elSize);
                    if (typeNum === 1) track.trackType = 'video';
                    else if (typeNum === 2) track.trackType = 'audio';
                    else if (typeNum === 17) track.trackType = 'subtitle';
                    break;
                }
                case 0x86: // CodecID
                    track.codecId = reader.readString(elSize);
                    break;
                case 0x63a2: // CodecPrivate
                    track.codecPrivate = reader.readBytes(elSize);
                    break;
                case 0x536e: // Name
                    track.name = reader.readString(elSize);
                    break;
                case 0x22b59c: // Language
                    track.language = reader.readString(elSize);
                    break;
                case 0xe0: // VideoSettings
                    this.parseVideoSettings(reader, elSize, track);
                    break;
                case 0xe1: // AudioSettings
                    this.parseAudioSettings(reader, elSize, track);
                    break;
                default:
                    reader.skip(elSize);
                    break;
            }
        }

        if (track.trackNumber && track.codecId) {
            this.tracks.set(track.trackNumber, track as TrackInfo);
        }
    }

    private parseVideoSettings(
        reader: EbmlReader,
        size: number,
        track: Partial<TrackInfo>,
    ) {
        const endPos = reader.position + size;
        while (reader.position < endPos) {
            const id = reader.readElementId();
            const elSize = reader.readElementSize();
            if (id === null || elSize === null) break;

            if (id === 0xb0) track.width = reader.readUint(elSize);
            else if (id === 0xba) track.height = reader.readUint(elSize);
            else reader.skip(elSize);
        }
    }

    private parseAudioSettings(
        reader: EbmlReader,
        size: number,
        track: Partial<TrackInfo>,
    ) {
        const endPos = reader.position + size;
        while (reader.position < endPos) {
            const id = reader.readElementId();
            const elSize = reader.readElementSize();
            if (id === null || elSize === null) break;

            if (id === 0xb5) track.samplingRate = reader.readFloat(elSize);
            else if (id === 0x9f) track.channels = reader.readUint(elSize);
            else reader.skip(elSize);
        }
    }

    private parseCues(reader: EbmlReader, size: number) {
        const endPos = reader.position + size;
        while (reader.position < endPos) {
            const id = reader.readElementId();
            const elSize = reader.readElementSize();
            if (id === null || elSize === null) break;

            if (id === 0xbb) {
                // CuePoint
                this.parseCuePoint(reader, elSize);
            } else {
                reader.skip(elSize);
            }
        }
    }

    private parseCuePoint(reader: EbmlReader, size: number) {
        const endPos = reader.position + size;
        let time = 0;
        let clusterOffset = 0;

        while (reader.position < endPos) {
            const id = reader.readElementId();
            const elSize = reader.readElementSize();
            if (id === null || elSize === null) break;

            if (id === 0xb3) {
                // CueTime
                time = (reader.readUint(elSize) * this.timecodeScale) / 1000000; // in ms
            } else if (id === 0xb7) {
                // CueTrackPositions
                const trackPosEnd = reader.position + elSize;
                while (reader.position < trackPosEnd) {
                    const tpId = reader.readElementId();
                    const tpSize = reader.readElementSize();
                    if (tpId === null || tpSize === null) break;
                    if (tpId === 0xf1) {
                        // CueClusterPosition
                        clusterOffset = reader.readUint(tpSize);
                    } else {
                        reader.skip(tpSize);
                    }
                }
            } else {
                reader.skip(elSize);
            }
        }

        if (clusterOffset > 0) {
            this.cues.push({ time, clusterOffset });
        }
    }

    /**
     * Parse Cluster byte buffer and extract all SimpleBlocks / BlockGroups into samples.
     */
    public parseCluster(buffer: Uint8Array): MediaSample[] {
        const reader = new EbmlReader(buffer);
        const samples: MediaSample[] = [];
        let clusterTimecode = 0;

        while (reader.hasBytes(4)) {
            const id = reader.readElementId();
            const size = reader.readElementSize();
            if (id === null || size === null) break;

            if (id === 0x1f43b675) {
                // Cluster Header
                // continue reading cluster elements
                continue;
            } else if (id === 0xe7) {
                // Timecode
                clusterTimecode = reader.readUint(size);
            } else if (id === 0xa3) {
                // SimpleBlock
                const blockSample = this.parseSimpleBlock(
                    reader,
                    size,
                    clusterTimecode,
                );
                if (blockSample) samples.push(blockSample);
            } else if (id === 0xa0) {
                // BlockGroup
                const groupSample = this.parseBlockGroup(
                    reader,
                    size,
                    clusterTimecode,
                );
                if (groupSample) samples.push(groupSample);
            } else {
                reader.skip(size);
            }
        }

        return samples;
    }

    private parseSimpleBlock(
        reader: EbmlReader,
        size: number,
        clusterTimecode: number,
    ): MediaSample | null {
        const startPos = reader.position;
        const trackVint = reader.readVint(true);
        if (!trackVint) return null;
        reader.skip(trackVint.length);

        if (!reader.hasBytes(3)) return null;
        // Relative timecode (signed 16-bit)
        const view = new DataView(reader.readBytes(2).buffer);
        const relTimecode = view.getInt16(0, false);
        const flags = reader.readUint(1);
        const isKeyframe = (flags & 0x80) !== 0;

        const headerBytes = reader.position - startPos;
        const payloadSize = size - headerBytes;
        if (payloadSize <= 0) return null;

        const data = reader.readBytes(payloadSize);
        const absTimeMs =
            ((clusterTimecode + relTimecode) * this.timecodeScale) / 1000000;

        return {
            trackNumber: trackVint.value,
            timecode: absTimeMs,
            isKeyframe,
            data,
        };
    }

    private parseBlockGroup(
        reader: EbmlReader,
        size: number,
        clusterTimecode: number,
    ): MediaSample | null {
        const endPos = reader.position + size;
        let sample: MediaSample | null = null;

        while (reader.position < endPos) {
            const id = reader.readElementId();
            const elSize = reader.readElementSize();
            if (id === null || elSize === null) break;

            if (id === 0xa1) {
                // Block
                sample = this.parseSimpleBlock(reader, elSize, clusterTimecode);
            } else {
                reader.skip(elSize);
            }
        }

        return sample;
    }
}
