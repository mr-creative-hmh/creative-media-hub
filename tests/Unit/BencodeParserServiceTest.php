<?php

namespace Tests\Unit;

use App\Services\Downloader\BencodeParserService;
use Tests\TestCase;

class BencodeParserServiceTest extends TestCase
{
    protected BencodeParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BencodeParserService();
    }

    public function test_multi_file_torrent_builds_hierarchical_folder_tree(): void
    {
        $info = [
            'name' => 'Chernobyl (2019) 1080p',
            'piece length' => 262144,
            'pieces' => str_repeat('12345678901234567890', 5),
            'files' => [
                // 0-byte folder marker (MUST BE FILTERED OUT!)
                ['length' => 0, 'path' => ['Season 01']],
                // Episode 1
                ['length' => 1500000000, 'path' => ['Season 01', 'Chernobyl.S01E01.mkv']],
                // Padding file (MUST BE FILTERED OUT!)
                ['length' => 45678, 'path' => ['_____padding_file_0_____']],
                // Episode 2
                ['length' => 1600000000, 'path' => ['Season 01', 'Chernobyl.S01E02.mkv']],
                // Nested Subs
                ['length' => 50000, 'path' => ['Season 01', 'Subs', 'S01E01.ar.srt']],
                ['length' => 48000, 'path' => ['Season 01', 'Subs', 'S01E01.en.srt']],
                // Root file
                ['length' => 95000, 'path' => ['poster.jpg']],
            ],
        ];

        $torrentData = $this->parser->encode(['info' => $info, 'announce' => 'udp://tracker.test:1337']);
        $result = $this->parser->parseTorrentString($torrentData);

        $this->assertNotNull($result);
        $this->assertEquals('Chernobyl (2019) 1080p', $result['name']);
        $this->assertEquals(40, strlen($result['info_hash']));

        // Files count must be 5 (excluding 0-byte directory entry and padding file)
        $this->assertCount(5, $result['files']);
        $this->assertEquals(2, $result['folder_count']); // Season 01 and Season 01/Subs

        // Verify 1-based indexing aligned with original info['files']
        // index 2 = E01, index 4 = E02, index 5 = ar.srt, index 6 = en.srt, index 7 = poster.jpg
        $fileIndices = array_column($result['files'], 'index');
        $this->assertEquals([2, 4, 5, 6, 7], $fileIndices);

        // Verify folder tree
        $tree = $result['tree'];
        $this->assertNotEmpty($tree);

        // Find folder Season 01
        $seasonFolder = null;
        $posterFile = null;
        foreach ($tree as $node) {
            if ($node['type'] === 'folder' && $node['name'] === 'Season 01') {
                $seasonFolder = $node;
            }
            if ($node['type'] === 'file' && $node['name'] === 'poster.jpg') {
                $posterFile = $node;
            }
        }

        $this->assertNotNull($seasonFolder);
        $this->assertNotNull($posterFile);
        $this->assertEquals('Season 01', $seasonFolder['name']);
        $this->assertEquals(4, $seasonFolder['file_count']);
        $this->assertEquals(2, $seasonFolder['video_count']);
        $this->assertContains(2, $seasonFolder['file_indexes']);
        $this->assertContains(4, $seasonFolder['file_indexes']);
        $this->assertContains(5, $seasonFolder['file_indexes']);
        $this->assertContains(6, $seasonFolder['file_indexes']);

        // Check nested Subs folder inside Season 01
        $subsFolder = null;
        foreach ($seasonFolder['children'] as $child) {
            if ($child['type'] === 'folder' && $child['name'] === 'Subs') {
                $subsFolder = $child;
            }
        }
        $this->assertNotNull($subsFolder);
        $this->assertEquals('Season 01/Subs', $subsFolder['path']);
        $this->assertEquals(2, $subsFolder['file_count']);
    }

    public function test_single_file_torrent_parses_cleanly(): void
    {
        $info = [
            'name' => 'Inception.2010.1080p.mkv',
            'length' => 2500000000,
            'piece length' => 262144,
            'pieces' => str_repeat('12345678901234567890', 5),
        ];

        $torrentData = $this->parser->encode(['info' => $info]);
        $result = $this->parser->parseTorrentString($torrentData);

        $this->assertNotNull($result);
        $this->assertEquals('Inception.2010.1080p.mkv', $result['name']);
        $this->assertEquals(2500000000, $result['total_size']);
        $this->assertCount(1, $result['files']);
        $this->assertEquals(1, $result['files'][0]['index']);
        $this->assertTrue($result['files'][0]['is_video']);
        $this->assertEquals('mkv', $result['files'][0]['extension']);
    }

    public function test_magnet_link_parses_without_fake_sample_files(): void
    {
        $magnet = 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567&dn=Avatar.Way.of.Water.2022.1080p';
        $result = $this->parser->parseMagnet($magnet);

        $this->assertNotNull($result);
        $this->assertEquals('Avatar.Way.of.Water.2022.1080p', $result['name']);
        $this->assertEquals('0123456789abcdef0123456789abcdef01234567', $result['info_hash']);
        $this->assertTrue($result['is_magnet']);
        $this->assertCount(1, $result['files']);
        $this->assertStringContainsString('Avatar', $result['files'][0]['path']);
    }
}
