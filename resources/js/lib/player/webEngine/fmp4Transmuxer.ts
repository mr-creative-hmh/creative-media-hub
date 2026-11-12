/**
 * ISO BMFF Fragmented MP4 (fMP4) Transmuxer for Web Media Engine
 * Transmuxes H.264/AVC NAL units into standard fMP4 segments for browser MediaSource Extensions.
 * REA tool design pattern: strict byte alignment and zero-copy packing.
 */

export interface VideoTrackParams {
    id: number;
    width: number;
    height: number;
    timescale?: number; // default: 90000 (standard 90kHz video clock)
    codecPrivate?: Uint8Array; // AVC decoder configuration or raw SPS/PPS
}

export interface VideoSample {
    dts: number; // in timescale units (e.g. 90kHz)
    pts: number; // in timescale units
    duration: number; // in timescale units
    isKeyframe: boolean;
    data: Uint8Array; // AVCC length-prefixed NAL units
}

export class Fmp4Transmuxer {
    private sequenceNumber: number = 1;
    public timescale: number = 90000;

    constructor(timescale: number = 90000) {
        this.timescale = timescale;
    }

    /**
     * Helper to write a 4-byte box header and payload.
     */
    public static box(type: string, ...payloads: Uint8Array[]): Uint8Array {
        let payloadLen = 0;
        for (const p of payloads) payloadLen += p.length;
        const totalLen = 8 + payloadLen;

        const out = new Uint8Array(totalLen);
        const view = new DataView(out.buffer);
        view.setUint32(0, totalLen, false);

        for (let i = 0; i < 4; i++) {
            out[4 + i] = type.charCodeAt(i);
        }

        let offset = 8;
        for (const p of payloads) {
            out.set(p, offset);
            offset += p.length;
        }

        return out;
    }

    /**
     * Generate fMP4 Initialization Segment (ftyp + moov).
     */
    public generateInitSegment(track: VideoTrackParams): Uint8Array {
        // 1. ftyp box (isom, iso2, avc1, mp41)
        const ftypPayload = new Uint8Array(16);
        const ftypView = new DataView(ftypPayload.buffer);
        ftypPayload.set([0x69, 0x73, 0x6F, 0x6D], 0); // major_brand: 'isom'
        ftypView.setUint32(4, 1, false); // minor_version: 1
        ftypPayload.set([0x69, 0x73, 0x6F, 0x32], 8); // compatible: 'iso2'
        ftypPayload.set([0x61, 0x76, 0x63, 0x31], 12); // compatible: 'avc1'
        const ftyp = Fmp4Transmuxer.box('ftyp', ftypPayload);

        // 2. moov box
        // mvhd (Movie Header)
        const mvhdPayload = new Uint8Array(100);
        const mvhdView = new DataView(mvhdPayload.buffer);
        mvhdView.setUint32(12, this.timescale, false); // timescale
        mvhdView.setUint32(16, 0, false); // duration = 0 (fragmented)
        mvhdView.setInt32(20, 0x00010000, false); // rate = 1.0
        mvhdView.setInt16(24, 0x0100, false); // volume = 1.0
        // matrix: identity
        mvhdView.setInt32(36, 0x00010000, false);
        mvhdView.setInt32(52, 0x00010000, false);
        mvhdView.setInt32(80, 0x40000000, false);
        mvhdView.setUint32(96, track.id + 1, false); // next_track_ID
        const mvhd = Fmp4Transmuxer.box('mvhd', mvhdPayload);

        // trak -> tkhd
        const tkhdPayload = new Uint8Array(84);
        const tkhdView = new DataView(tkhdPayload.buffer);
        tkhdView.setUint32(0, 0x00000007, false); // flags: track_enabled | track_in_movie
        tkhdView.setUint32(12, track.id, false); // track_id
        tkhdView.setUint32(20, 0, false); // duration = 0
        // matrix
        tkhdView.setInt32(36, 0x00010000, false);
        tkhdView.setInt32(52, 0x00010000, false);
        tkhdView.setInt32(80, 0x40000000, false);
        tkhdView.setUint32(84 - 8, track.width << 16, false); // width (16.16)
        tkhdView.setUint32(84 - 4, track.height << 16, false); // height (16.16)
        const tkhd = Fmp4Transmuxer.box('tkhd', tkhdPayload);

        // mdia -> mdhd
        const mdhdPayload = new Uint8Array(24);
        const mdhdView = new DataView(mdhdPayload.buffer);
        mdhdView.setUint32(12, this.timescale, false);
        mdhdView.setUint32(16, 0, false); // duration
        mdhdView.setUint16(20, 0x55C4, false); // language: und
        const mdhd = Fmp4Transmuxer.box('mdhd', mdhdPayload);

        // mdia -> hdlr
        const hdlrPayload = new Uint8Array(37);
        hdlrPayload.set([0x76, 0x69, 0x64, 0x65], 8); // 'vide'
        const hdlrName = new TextEncoder().encode('VideoHandler\0');
        hdlrPayload.set(hdlrName, 24);
        const hdlr = Fmp4Transmuxer.box('hdlr', hdlrPayload);

        // minf -> vmhd
        const vmhdPayload = new Uint8Array(12);
        vmhdPayload[3] = 1; // flags
        const vmhd = Fmp4Transmuxer.box('vmhd', vmhdPayload);

        // minf -> dinf -> dref
        const drefEntry = Fmp4Transmuxer.box('url ', new Uint8Array([0, 0, 0, 1]));
        const drefPayload = new Uint8Array(8);
        new DataView(drefPayload.buffer).setUint32(4, 1, false); // entry_count = 1
        const dref = Fmp4Transmuxer.box('dref', drefPayload, drefEntry);
        const dinf = Fmp4Transmuxer.box('dinf', dref);

        // minf -> stbl -> stsd (Sample Description)
        const avcC = this.generateAvcC(track.codecPrivate);
        const avc1Payload = new Uint8Array(78);
        const avc1View = new DataView(avc1Payload.buffer);
        avc1View.setUint16(6, 1, false); // data_reference_index = 1
        avc1View.setUint16(24, track.width, false);
        avc1View.setUint16(26, track.height, false);
        avc1View.setUint32(28, 0x00480000, false); // horiz resolution 72 dpi
        avc1View.setUint32(32, 0x00480000, false); // vert resolution 72 dpi
        avc1View.setUint16(40, 1, false); // frame_count = 1
        avc1View.setUint16(74, 0x0018, false); // depth = 24
        avc1View.setInt16(76, -1, false); // pre_defined = -1
        const avc1 = Fmp4Transmuxer.box('avc1', avc1Payload, avcC);

        const stsdPayload = new Uint8Array(8);
        new DataView(stsdPayload.buffer).setUint32(4, 1, false); // entry_count = 1
        const stsd = Fmp4Transmuxer.box('stsd', stsdPayload, avc1);

        // empty stts, stsc, stsz, stco
        const emptyBox = (type: string) => {
            const p = new Uint8Array(8);
            return Fmp4Transmuxer.box(type, p);
        };
        const stbl = Fmp4Transmuxer.box('stbl', stsd, emptyBox('stts'), emptyBox('stsc'), emptyBox('stsz'), emptyBox('stco'));
        const minf = Fmp4Transmuxer.box('minf', vmhd, dinf, stbl);
        const mdia = Fmp4Transmuxer.box('mdia', mdhd, hdlr, minf);
        const trak = Fmp4Transmuxer.box('trak', tkhd, mdia);

        // mvex -> trex
        const trexPayload = new Uint8Array(24);
        const trexView = new DataView(trexPayload.buffer);
        trexView.setUint32(4, track.id, false); // track_ID
        trexView.setUint32(8, 1, false); // default_sample_description_index
        trexView.setUint32(12, 3000, false); // default_sample_duration (approx 30fps)
        trexView.setUint32(16, 0, false); // default_sample_size
        trexView.setUint32(20, 0x00010000, false); // default_sample_flags
        const trex = Fmp4Transmuxer.box('trex', trexPayload);
        const mvex = Fmp4Transmuxer.box('mvex', trex);

        const moov = Fmp4Transmuxer.box('moov', mvhd, trak, mvex);

        const out = new Uint8Array(ftyp.length + moov.length);
        out.set(ftyp, 0);
        out.set(moov, ftyp.length);
        return out;
    }

    /**
     * Generate avcC (AVC Configuration Box) from CodecPrivate or default standard Profile.
     */
    private generateAvcC(codecPrivate?: Uint8Array): Uint8Array {
        if (codecPrivate && codecPrivate.length > 7 && codecPrivate[0] === 1) {
            // Already an AVCDecoderConfigurationRecord
            return Fmp4Transmuxer.box('avcC', codecPrivate);
        }

        // Standard H.264 High Profile @ Level 4.1 default avcC
        const fallback = new Uint8Array([
            0x01, // configurationVersion
            0x64, // AVCProfileIndication: High (100)
            0x00, // profile_compatibility
            0x29, // AVCLevelIndication: 4.1
            0xFF, // lengthSizeMinusOne: 3 (4 bytes)
            0xE1, // numOfSequenceParameterSets: 1
            0x00, 0x1B, // SPS length: 27
            0x67, 0x64, 0x00, 0x29, 0xAC, 0xD9, 0x40, 0x78,
            0x02, 0x27, 0xE5, 0xC0, 0x44, 0x00, 0x00, 0x03,
            0x00, 0x04, 0x00, 0x00, 0x03, 0x00, 0xCA, 0x3C,
            0x60, 0xC4, 0x48,
            0x01, // numOfPictureParameterSets: 1
            0x00, 0x04, // PPS length: 4
            0x68, 0xEB, 0xE3, 0xCB
        ]);

        return Fmp4Transmuxer.box('avcC', fallback);
    }

    /**
     * Generate Media Segment (moof + mdat) for a set of video samples.
     */
    public generateMediaSegment(trackId: number, samples: VideoSample[]): Uint8Array | null {
        if (!samples || samples.length === 0) return null;

        const firstSample = samples[0];
        const baseDecodeTime = firstSample.dts;

        // mfhd (Movie Fragment Header)
        const mfhdPayload = new Uint8Array(8);
        new DataView(mfhdPayload.buffer).setUint32(4, this.sequenceNumber++, false);
        const mfhd = Fmp4Transmuxer.box('mfhd', mfhdPayload);

        // tfhd (Track Fragment Header)
        const tfhdPayload = new Uint8Array(8);
        const tfhdView = new DataView(tfhdPayload.buffer);
        tfhdView.setUint32(0, 0x020000, false); // default-base-is-moof
        tfhdView.setUint32(4, trackId, false);
        const tfhd = Fmp4Transmuxer.box('tfhd', tfhdPayload);

        // tfdt (Track Fragment Base Media Decode Time - 64-bit)
        const tfdtPayload = new Uint8Array(12);
        const tfdtView = new DataView(tfdtPayload.buffer);
        tfdtPayload[0] = 1; // version 1 (64-bit)
        // High 32 bits
        tfdtView.setUint32(4, Math.floor(baseDecodeTime / 0x100000000), false);
        // Low 32 bits
        tfdtView.setUint32(8, baseDecodeTime >>> 0, false);
        const tfdt = Fmp4Transmuxer.box('tfdt', tfdtPayload);

        // Calculate total mdat data size
        let mdatSize = 0;
        for (const s of samples) {
            mdatSize += s.data.length;
        }

        // trun (Track Fragment Run)
        // flags: data-offset-present (0x01), sample-duration (0x100), sample-size (0x200), sample-flags (0x400), sample-cts (0x800)
        const trunFlags = 0x000F01;
        const entrySize = 16; // 4 + 4 + 4 + 4
        const trunPayload = new Uint8Array(12 + (samples.length * entrySize));
        const trunView = new DataView(trunPayload.buffer);
        trunView.setUint32(0, trunFlags, false);
        trunView.setUint32(4, samples.length, false); // sample_count

        // data_offset will be patched after calculating moof length
        let sampleOffset = 12;
        for (const s of samples) {
            trunView.setUint32(sampleOffset, Math.max(1, s.duration), false);
            trunView.setUint32(sampleOffset + 4, s.data.length, false);
            // keyframe flag: 0x02000000 vs inter-frame: 0x01010000
            const flag = s.isKeyframe ? 0x02000000 : 0x01010000;
            trunView.setUint32(sampleOffset + 8, flag, false);
            // Composition time offset (PTS - DTS)
            trunView.setInt32(sampleOffset + 12, s.pts - s.dts, false);
            sampleOffset += entrySize;
        }

        const trunPlaceholder = Fmp4Transmuxer.box('trun', trunPayload);
        const trafPlaceholder = Fmp4Transmuxer.box('traf', tfhd, tfdt, trunPlaceholder);
        const moofPlaceholder = Fmp4Transmuxer.box('moof', mfhd, trafPlaceholder);

        // Patch exact data_offset into trun (moof length + 8 for mdat header)
        const exactDataOffset = moofPlaceholder.length + 8;
        trunView.setInt32(8, exactDataOffset, false);

        const trun = Fmp4Transmuxer.box('trun', trunPayload);
        const traf = Fmp4Transmuxer.box('traf', tfhd, tfdt, trun);
        const moof = Fmp4Transmuxer.box('moof', mfhd, traf);

        // mdat box
        const mdatHeader = new Uint8Array(8);
        new DataView(mdatHeader.buffer).setUint32(0, 8 + mdatSize, false);
        mdatHeader.set([0x6D, 0x64, 0x61, 0x74], 4); // 'mdat'

        const mediaSegment = new Uint8Array(moof.length + 8 + mdatSize);
        mediaSegment.set(moof, 0);
        mediaSegment.set(mdatHeader, moof.length);

        let curPos = moof.length + 8;
        for (const s of samples) {
            mediaSegment.set(s.data, curPos);
            curPos += s.data.length;
        }

        return mediaSegment;
    }
}

