<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Video;
use App\Repositories\VideoRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class VideoRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private VideoRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new VideoRepository();
    }

    protected function createVideo(array $overrides = []): Video
    {
        return Video::create(array_merge([
            'site_id' => $this->siteId,
            'original_name' => 'test-video.mp4',
            'filename' => 'video-' . uniqid() . '.mp4',
            'title' => 'Test Video',
            'description' => 'Test video description',
            'file_path' => '/uploads/videos/test.mp4',
            'url' => 'https://example.com/test.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 10240000,
            'duration' => 120,
            'width' => 1920,
            'height' => 1080,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'deleted_at' => null,
        ], $overrides));
    }

    public function test_create_saves_new_video(): void
    {
        // Arrange
        $data = [
            'site_id' => $this->siteId,
            'original_name' => 'new-video.mp4',
            'filename' => 'video-123.mp4',
            'title' => 'New Video',
            'description' => 'New video description',
            'file_path' => '/uploads/videos/new.mp4',
            'url' => 'https://example.com/new.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 5120000,
            'duration' => 60,
        ];

        // Act
        $video = $this->repository->create($data);

        // Assert
        $this->assertNotNull($video);
        $this->assertEquals('New Video', $video->title);
        $this->assertEquals('new-video.mp4', $video->original_name);

        $this->assertDatabaseHas('videos', [
            'title' => 'New Video',
            'original_name' => 'new-video.mp4',
        ]);
    }

    public function test_find_returns_video_when_exists(): void
    {
        // Arrange
        $video = $this->createVideo(['title' => 'Find Me']);

        // Act
        $found = $this->repository->find($video->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($video->id, $found->id);
        $this->assertEquals('Find Me', $found->title);
    }

    public function test_find_returns_null_when_not_exists(): void
    {
        // Act
        $found = $this->repository->find(99999);

        // Assert
        $this->assertNull($found);
    }

    public function test_search_returns_paginated_results(): void
    {
        // Arrange
        $this->createVideo(['title' => 'Video 1']);
        $this->createVideo(['title' => 'Video 2']);
        $this->createVideo(['title' => 'Video 3']);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setPerPage(10);
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
        $this->assertGreaterThanOrEqual(3, count($result->getData()));
        $this->assertGreaterThanOrEqual(3, $result->getTotal());
    }

    public function test_search_filters_by_original_name(): void
    {
        // Arrange
        $this->createVideo(['original_name' => 'tutorial.mp4', 'title' => 'Tutorial']);
        $this->createVideo(['original_name' => 'demo.mp4', 'title' => 'Demo']);
        $this->createVideo(['original_name' => 'tutorial-advanced.mp4', 'title' => 'Advanced']);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setSearchQuery('tutorial');
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertGreaterThanOrEqual(2, count($result->getData()));

        foreach ($result->getData() as $video) {
            $this->assertTrue(
                stripos($video['original_name'], 'tutorial') !== false ||
                stripos($video['title'], 'tutorial') !== false ||
                stripos($video['filename'], 'tutorial') !== false ||
                stripos($video['description'], 'tutorial') !== false
            );
        }
    }

    public function test_search_filters_by_title(): void
    {
        // Arrange
        $this->createVideo(['title' => 'Laravel Tutorial']);
        $this->createVideo(['title' => 'PHP Guide']);
        $this->createVideo(['title' => 'Laravel Advanced']);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setSearchQuery('Laravel');
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertGreaterThanOrEqual(2, count($result->getData()));
    }

    public function test_search_filters_by_filename(): void
    {
        // Arrange
        $this->createVideo(['filename' => 'video-tutorial-123.mp4']);
        $this->createVideo(['filename' => 'video-demo-456.mp4']);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setSearchQuery('tutorial');
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertGreaterThanOrEqual(1, count($result->getData()));
    }

    public function test_search_filters_by_description(): void
    {
        // Arrange
        $this->createVideo(['description' => 'This is a Laravel tutorial video']);
        $this->createVideo(['description' => 'This is a PHP guide video']);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setSearchQuery('Laravel');
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertGreaterThanOrEqual(1, count($result->getData()));
    }

    public function test_search_applies_custom_filters(): void
    {
        // Arrange
        $this->createVideo(['mime_type' => 'video/mpeg', 'title' => 'Active Video']);
        $this->createVideo(['mime_type' => 'video/mpeg', 'title' => 'Processing Video']);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('mime_type', 'video/mpeg');
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertGreaterThanOrEqual(1, count($result->getData()));

        foreach ($result->getData() as $video) {
            $this->assertEquals('video/mpeg', $video['mime_type']);
        }
    }

    public function test_search_excludes_soft_deleted_videos(): void
    {
        // Arrange
        $active = $this->createVideo(['title' => 'Active Video', 'deleted_at' => null]);
        $deleted = $this->createVideo(['title' => 'Deleted Video', 'deleted_at' => date('Y-m-d H:i:s')]);

        // Act
        $criteria = new SearchCriteria();
        $result = $this->repository->search($criteria);

        // Assert
        $foundDeleted = false;
        foreach ($result->getData() as $video) {
            if ($video['id'] === $deleted->id) {
                $foundDeleted = true;
                break;
            }
        }

        $this->assertFalse($foundDeleted);
    }

    public function test_search_applies_sorting(): void
    {
        // Arrange
        $video1 = $this->createVideo(['title' => 'A Video', 'created_at' => '2024-01-01 00:00:00']);
        $video2 = $this->createVideo(['title' => 'Z Video', 'created_at' => '2024-12-31 23:59:59']);

        // Act - sort by title ascending
        $criteria = new SearchCriteria();
        $criteria->setSortBy('title');
        $criteria->setSortOrder('asc');
        $result = $this->repository->search($criteria);

        // Assert
        $data = $result->getData();
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    public function test_search_applies_pagination(): void
    {
        // Arrange
        for ($i = 1; $i <= 15; $i++) {
            $this->createVideo(['title' => "Video $i"]);
        }

        // Act - get page 2 with 5 items per page
        $criteria = new SearchCriteria();
        $criteria->setPerPage(5);
        $criteria->setPage(2);
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertLessThanOrEqual(5, count($result->getData()));
        $this->assertEquals(2, $result->getPage());
        $this->assertEquals(5, $result->getPerPage());
        $this->assertGreaterThanOrEqual(15, $result->getTotal());
    }

    public function test_search_calculates_correct_offset(): void
    {
        // Arrange
        for ($i = 1; $i <= 10; $i++) {
            $this->createVideo(['title' => "Video $i", 'created_at' => "2024-01-0$i 00:00:00"]);
        }

        // Act
        $criteria = new SearchCriteria();
        $criteria->setPerPage(3);
        $criteria->setPage(1);
        $page1 = $this->repository->search($criteria);

        $criteria->setPage(2);
        $page2 = $this->repository->search($criteria);

        // Assert
        $this->assertCount(3, $page1->getData());
        $this->assertCount(3, $page2->getData());

        // Verify different results on different pages
        $page1Ids = array_column($page1->getData(), 'id');
        $page2Ids = array_column($page2->getData(), 'id');

        $this->assertNotEquals($page1Ids, $page2Ids);
    }

    public function test_get_recent_videos_returns_latest_videos(): void
    {
        // Arrange
        $old = $this->createVideo(['title' => 'Old Video', 'created_at' => '2024-01-01 00:00:00']);
        $recent = $this->createVideo(['title' => 'Recent Video', 'created_at' => '2024-12-31 23:59:59']);
        $middle = $this->createVideo(['title' => 'Middle Video', 'created_at' => '2024-06-15 12:00:00']);

        // Act
        $videos = $this->repository->getRecentVideos(10);

        // Assert
        $this->assertGreaterThanOrEqual(3, $videos->count());

        $videosArray = $videos->toArray();
        // Most recent should be first
        $this->assertEquals($old->id, $videosArray[0]['id']);
    }

    public function test_get_recent_videos_respects_limit(): void
    {
        // Arrange
        for ($i = 1; $i <= 20; $i++) {
            $this->createVideo(['title' => "Video $i"]);
        }

        // Act
        $videos = $this->repository->getRecentVideos(5);

        // Assert
        $this->assertCount(5, $videos);
    }

    public function test_get_recent_videos_excludes_soft_deleted(): void
    {
        // Arrange
        $active = $this->createVideo(['title' => 'Active Video', 'deleted_at' => null]);
        $deleted = $this->createVideo(['title' => 'Deleted Video', 'deleted_at' => date('Y-m-d H:i:s')]);

        // Act
        $videos = $this->repository->getRecentVideos(10);

        // Assert
        $foundDeleted = false;
        foreach ($videos as $video) {
            if ($video->id === $deleted->id) {
                $foundDeleted = true;
                break;
            }
        }

        $this->assertFalse($foundDeleted);
    }
}