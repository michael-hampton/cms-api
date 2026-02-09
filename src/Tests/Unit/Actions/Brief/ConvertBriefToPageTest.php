<?php

namespace App\Tests\Unit\Actions\Brief;

use App\Actions\Brief\ConvertBriefToPage;
use App\Framework\Database\Database;
use App\Models\Author;
use App\Models\Brief;
use App\Models\BriefAttachment;
use App\Models\Image;
use App\Models\Page;
use App\Models\User;
use App\Repositories\Cms\AuthorRepository;
use App\Repositories\Cms\Briefs\BriefRepository;
use App\Repositories\Cms\CollaboratorRepository;
use App\Repositories\Cms\ImageRepository;
use App\Repositories\Cms\UserRepository;
use App\Services\Cms\Pages\PageService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class ConvertBriefToPageTest extends FunctionalTestCase
{
    private $databaseMock;
    private $briefRepository;
    private $pageService;
    private $authorRepository;
    private $action;
    private $userRepository;
    private $imageRepository;
    private $collaboratorRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseMock = Mockery::mock(Database::class);
        $this->briefRepository = Mockery::mock(BriefRepository::class);
        $this->pageService = Mockery::mock(PageService::class);
        $this->authorRepository = Mockery::mock(AuthorRepository::class);
        $this->userRepository = Mockery::mock(UserRepository::class);
        $this->imageRepository = Mockery::mock(ImageRepository::class);
        $this->collaboratorRepository = Mockery::mock(CollaboratorRepository::class);

        $this->action = new ConvertBriefToPage(
            $this->databaseMock,
            $this->briefRepository,
            $this->pageService,
            $this->authorRepository,
            $this->userRepository,
            $this->imageRepository,
            $this->collaboratorRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testHandleThrowsExceptionWhenBriefNotFound()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Brief not found');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->briefRepository->shouldReceive('getCompleteBriefData')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->action->handle(999, []);
    }

    public function testHandleCreatesBasicPage()
    {
        $briefId = 1;
        $siteId = 10;

        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->id = $briefId;
        $brief->title = 'Test Brief';
        $brief->site_id = $siteId;

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 100;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->briefRepository->shouldReceive('getCompleteBriefData')
            ->with($briefId)
            ->once()
            ->andReturn($brief);

        $this->pageService->shouldReceive('createPageWithAllData')
            ->once()
            ->with(Mockery::on(function ($pageData) {
                return $pageData['forms']['main']['title'] === 'Test Brief'
                    && $pageData['forms']['meta']['status'] === 'draft'
                    && $pageData['forms']['meta']['slug'] === 'test-brief';
            }), $siteId)
            ->andReturn($page);

        $this->briefRepository->shouldReceive('markAsConverted')
            ->with($briefId, 100)
            ->once();

        $this->setCollaboratorExpectations($briefId, 1, 'reviewer');

        $conversionData = [
            'images' => [],
            'products' => []
        ];

        $result = $this->action->handle($briefId, $conversionData);

        $this->assertTrue($result['success']);
        $this->assertEquals(100, $result['page_id']);
        $this->assertEquals($briefId, $result['brief_id']);
    }

    public function testHandleConvertsImagesCorrectly()
    {
        $briefId = 1;
        $siteId = 10;

        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->id = $briefId;
        $brief->title = 'Test Brief';
        $brief->site_id = $siteId;

        $image = Mockery::mock(Image::class)->makePartial();
        $image->id = 50;
        $image->file_path = '/path/to/image.jpg';

        $attachment = Mockery::mock(BriefAttachment::class)->makePartial();
        $attachment->id = 25;
        $attachment->type = 'image';

        $this->imageRepository->shouldReceive('find')->with(50)->andReturn($image);
        $this->briefRepository->shouldReceive('getAttachment')->with(25)->andReturn($attachment);

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 100;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->setCollaboratorExpectations($briefId, 1, 'reviewer');

        $this->briefRepository->shouldReceive('getCompleteBriefData')
            ->with($briefId)
            ->once()
            ->andReturn($brief);

        $this->pageService->shouldReceive('createPageWithAllData')
            ->once()
            ->with(Mockery::on(function ($pageData) {
                return count($pageData['blocks']) === 1
                    && $pageData['blocks'][0]['type'] === 'image'
                    && $pageData['blocks'][0]['src'] === '/path/to/image.jpg'
                    && $pageData['blocks'][0]['alt'] === 'Test Alt'
                    && $pageData['blocks'][0]['image_id'] === 50;
            }), $siteId)
            ->andReturn($page);

        $this->briefRepository->shouldReceive('markAsConverted')
            ->with($briefId, 100)
            ->once();

        $conversionData = [
            'images' => [
                [
                    'attachment_id' => 25,
                    'image_id' => 50,
                    'alt_text' => 'Test Alt',
                    'credit' => 'Test Credit',
                    'caption' => 'Test Caption'
                ]
            ],
            'products' => []
        ];

        $result = $this->action->handle($briefId, $conversionData);

        $this->assertTrue($result['success']);
    }

    public function testHandleConvertsProductsCorrectly()
    {
        $briefId = 1;
        $siteId = 10;

        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->id = $briefId;
        $brief->title = 'Test Brief';
        $brief->site_id = $siteId;

        $attachment = new BriefAttachment();
        $attachment->id = 30;
        $attachment->type = 'product';
        $attachment->url = 'http://example.com/product';
        $attachment->metadata = [
            'productName' => 'Test Product',
            'product_price' => '$99.99'
        ];

        $this->setCollaboratorExpectations($briefId, 1, 'reviewer');

        $this->briefRepository->shouldReceive('getAttachment')->with(30)->andReturn($attachment);

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 100;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->briefRepository->shouldReceive('getCompleteBriefData')
            ->with($briefId)
            ->once()
            ->andReturn($brief);

        $this->pageService->shouldReceive('createPageWithAllData')
            ->once()
            ->with(Mockery::on(function ($pageData) {
                return count($pageData['blocks']) === 1
                    && $pageData['blocks'][0]['type'] === 'product'
                    && $pageData['blocks'][0]['productName'] === 'Test Product';
            }), $siteId)
            ->andReturn($page);

        $this->briefRepository->shouldReceive('markAsConverted')
            ->with($briefId, 100)
            ->once();

        $conversionData = [
            'images' => [],
            'products' => [
                [
                    'attachment_id' => 30,
                    'product_id' => 123,
                    'conversion_type' => 'product'
                ]
            ]
        ];

        $result = $this->action->handle($briefId, $conversionData);

        $this->assertTrue($result['success']);
    }

    public function testHandleAssignsAuthorFromOwner()
    {
        $briefId = 1;
        $siteId = 10;
        $ownerId = 5;

        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->id = $briefId;
        $brief->title = 'Test Brief';
        $brief->site_id = $siteId;

        $user = Mockery::mock(User::class)->makePartial();
        $user->id = $ownerId;
        $user->email = 'owner@example.com';
        $user->name = 'Test Owner';

        $author = Mockery::mock(Author::class)->makePartial();
        $author->id = 15;

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 100;

        $this->setCollaboratorExpectations($briefId, $ownerId, 'owner');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->briefRepository->shouldReceive('getCompleteBriefData')
            ->with($briefId)
            ->once()
            ->andReturn($brief);

        $this->userRepository->shouldReceive('find')
            ->with($ownerId)
            ->once()
            ->andReturn($user);

        $this->authorRepository->shouldReceive('findOrCreateFromUser')
            ->with($user, $siteId)
            ->once()
            ->andReturn($author);

        $this->pageService->shouldReceive('createPageWithAllData')
            ->once()
            ->with(Mockery::on(function ($pageData) {
                return $pageData['forms']['meta']['authors'][0] === 15;
            }), $siteId)
            ->andReturn($page);

        $this->briefRepository->shouldReceive('markAsConverted')
            ->with($briefId, 100)
            ->once();

        $conversionData = [
            'owner_id' => $ownerId,
            'images' => [],
            'products' => []
        ];

        $result = $this->action->handle($briefId, $conversionData);

        $this->assertTrue($result['success']);
    }

    public function testHandleAssignsCategoryCorrectly()
    {
        $briefId = 1;
        $siteId = 10;
        $categoryId = 7;

        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->id = $briefId;
        $brief->title = 'Test Brief';
        $brief->site_id = $siteId;

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 100;

        $this->setCollaboratorExpectations($briefId, 1, 'reviewer');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->briefRepository->shouldReceive('getCompleteBriefData')
            ->with($briefId)
            ->once()
            ->andReturn($brief);

        $this->pageService->shouldReceive('createPageWithAllData')
            ->once()
            ->with(Mockery::on(function ($pageData) use ($categoryId) {
                return isset($pageData['forms']['tags']['categories'])
                    && $pageData['forms']['tags']['categories'][0] === $categoryId;
            }), $siteId)
            ->andReturn($page);

        $this->briefRepository->shouldReceive('markAsConverted')
            ->with($briefId, 100)
            ->once();

        $conversionData = [
            'category_id' => $categoryId,
            'images' => [],
            'products' => []
        ];

        $result = $this->action->handle($briefId, $conversionData);

        $this->assertTrue($result['success']);
    }

    private function setCollaboratorExpectations($briefId, $userId, $role)
    {
        $this->collaboratorRepository->shouldReceive('getForCollaboratable')
            ->with(Brief::class, $briefId)
            ->once()
            ->andReturn(collect([]));
    }
}