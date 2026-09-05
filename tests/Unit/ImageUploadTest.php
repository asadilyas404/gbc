<?php

namespace Tests\Unit;

use App\CentralLogics\Helpers;
use App\Exceptions\ImageUploadException;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\TestCase;

class ImageUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(new Container());
        Storage::swap(\Mockery::mock());
        Log::swap(\Mockery::mock());
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function testFailedReplacementDoesNotDeleteOldImage(): void
    {
        $disk = \Mockery::mock();
        Storage::shouldReceive('disk')->with('public')->once()->andReturn($disk);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        $disk->shouldNotReceive('delete');
        Log::shouldReceive('error')->once();
        $this->expectException(ImageUploadException::class);
        UploadTestHelpers::update('product/', 'old.png', 'png', 'file');
    }

    public function testStorageExceptionIsReportedAsUploadFailure(): void
    {
        Storage::shouldReceive('disk')->andThrow(new \RuntimeException('Unavailable'));
        Log::shouldReceive('error')->once();
        $this->expectException(ImageUploadException::class);
        UploadTestHelpers::upload('product/', 'png', 'file');
    }

    public function testSuccessfulReplacementWritesBeforeDeleting(): void
    {
        $disk = \Mockery::mock();
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        $disk->shouldReceive('putFileAs')->once()->ordered()->andReturn('product/new.png');
        $disk->shouldReceive('exists')->with('product/old.png')->once()->ordered()->andReturn(true);
        $disk->shouldReceive('delete')->with('product/old.png')->once()->ordered()->andReturn(true);
        $this->assertNotSame('old.png', UploadTestHelpers::update('product/', 'old.png', 'png', 'file'));
    }

    public function testMissingReplacementKeepsExistingImage(): void
    {
        $this->assertSame('old.png', UploadTestHelpers::update('product/', 'old.png', 'png'));
    }

    public function testPhpUploadErrorIsLoggedAndExplained(): void
    {
        $file = new \Symfony\Component\HttpFoundation\File\UploadedFile('', 'food.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);
        $request = new \Illuminate\Http\Request([], [], [], [], ['image' => $file]);
        Log::shouldReceive('warning')->once()->with('Food image rejected by PHP', \Mockery::on(function ($context) {
            return $context['upload_error'] === UPLOAD_ERR_INI_SIZE;
        }));
        $this->expectException(ImageUploadException::class);
        $this->expectExceptionMessage('server upload limit');
        Helpers::validateFoodImageUpload($request);
    }
}

class UploadTestHelpers extends Helpers
{
    public static function getDisk()
    {
        return 'public';
    }
}
