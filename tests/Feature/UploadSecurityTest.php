<?php

declare(strict_types=1);

namespace Rentivo\Tests\Feature;

use Rentivo\Services\FileStorageService;
use Rentivo\Services\ImageService;
use Rentivo\Services\UploadException;
use Rentivo\Tests\TestCase;

/**
 * Upload validation (SRS §70).
 *
 * The extension and the browser-supplied content type are both ignored: what
 * matters is what finfo reports, whether the bytes decode as an image, and
 * what GD re-encodes.
 */
final class UploadSecurityTest extends TestCase
{
    private string $scratch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratch = sys_get_temp_dir() . '/rentivo-upload-tests';

        if (!is_dir($this->scratch)) {
            mkdir($this->scratch, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->scratch . '/*') ?: [] as $file) {
            @unlink($file);
        }

        // Remove anything the successful-upload tests wrote.
        $storage = new FileStorageService(dirname(__DIR__, 2));
        $storage->deleteDirectory(FileStorageService::DISK_PUBLIC, 'cars/9999');
        $storage->deleteDirectory(FileStorageService::DISK_PRIVATE, 'documents/9999');

        parent::tearDown();
    }

    private function images(): ImageService
    {
        return $this->app->get(ImageService::class);
    }

    /**
     * Builds an upload array. The declared name and type are attacker
     * controlled, exactly as they are in a real request.
     *
     * @return array{name:string,type:string,tmp_name:string,error:int,size:int}
     */
    private function upload(string $filename, string $contents, string $declaredType): array
    {
        $path = $this->scratch . '/' . $filename;
        file_put_contents($path, $contents);

        return [
            'name'     => $filename,
            'type'     => $declaredType,
            'tmp_name' => $path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($contents),
        ];
    }

    /** A real, small PNG. */
    private function realPng(): string
    {
        $image = imagecreatetruecolor(40, 30);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 20, 20));

        ob_start();
        imagepng($image);
        return (string) ob_get_clean();
    }

    // -----------------------------------------------------------------
    // Rejections
    // -----------------------------------------------------------------

    /** PHP source renamed to .jpg must never be accepted. */
    public function testPhpDisguisedAsAnImageIsRejected(): void
    {
        $file = $this->upload(
            'evil.jpg',
            "<?php echo shell_exec(\$_GET['c']); ?>",
            'image/jpeg'
        );

        $this->expectException(UploadException::class);

        $this->images()->storeUploadedImage($file, FileStorageService::DISK_PUBLIC, 'cars/9999');
    }

    /** A lying Content-Type header carries no weight. */
    public function testDeclaredMimeTypeIsIgnored(): void
    {
        $file = $this->upload('notes.jpg', 'this is plain text, not an image', 'image/jpeg');

        $this->expectException(UploadException::class);

        $this->images()->storeUploadedImage($file, FileStorageService::DISK_PUBLIC, 'cars/9999');
    }

    /** An SVG can carry script, so it is not an accepted image type. */
    public function testSvgIsRejected(): void
    {
        $file = $this->upload(
            'icon.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            'image/svg+xml'
        );

        $this->expectException(UploadException::class);

        $this->images()->storeUploadedImage($file, FileStorageService::DISK_PUBLIC, 'cars/9999');
    }

    public function testHtmlIsRejected(): void
    {
        $file = $this->upload('page.png', '<html><body><script>alert(1)</script></body></html>', 'image/png');

        $this->expectException(UploadException::class);

        $this->images()->storeUploadedImage($file, FileStorageService::DISK_PUBLIC, 'cars/9999');
    }

    public function testTruncatedImageIsRejected(): void
    {
        // A valid PNG signature followed by nothing usable.
        $file = $this->upload('broken.png', "\x89PNG\r\n\x1a\n" . str_repeat("\x00", 40), 'image/png');

        $this->expectException(UploadException::class);

        $this->images()->storeUploadedImage($file, FileStorageService::DISK_PUBLIC, 'cars/9999');
    }

    public function testFailedUploadIsRejected(): void
    {
        $file = $this->upload('photo.png', $this->realPng(), 'image/png');
        $file['error'] = UPLOAD_ERR_PARTIAL;

        $this->expectException(UploadException::class);

        $this->images()->storeUploadedImage($file, FileStorageService::DISK_PUBLIC, 'cars/9999');
    }

    public function testOversizedUploadIsRejected(): void
    {
        $file = $this->upload('photo.png', $this->realPng(), 'image/png');

        // Report a size beyond the configured maximum.
        $file['size'] = ((int) config('uploads.max_image_bytes', 8388608)) + 1;

        $this->expectException(UploadException::class);

        $this->images()->storeUploadedImage($file, FileStorageService::DISK_PUBLIC, 'cars/9999');
    }

    public function testMissingFileIsRejected(): void
    {
        $file = [
            'name'     => 'nothing.png',
            'type'     => 'image/png',
            'tmp_name' => $this->scratch . '/does-not-exist.png',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 100,
        ];

        $this->expectException(UploadException::class);

        $this->images()->storeUploadedImage($file, FileStorageService::DISK_PUBLIC, 'cars/9999');
    }

    // -----------------------------------------------------------------
    // Acceptance and normalisation
    // -----------------------------------------------------------------

    /**
     * A genuine image is accepted, stored under a random server-generated
     * name, and re-encoded rather than copied byte for byte.
     */
    public function testGenuineImageIsStoredSafely(): void
    {
        $file = $this->upload('My Holiday Photo!.png', $this->realPng(), 'image/png');

        $path = $this->images()->storeUploadedImage($file, FileStorageService::DISK_PUBLIC, 'cars/9999');

        // The original filename is never used to build the path.
        self::assertStringNotContainsString('Holiday', $path);
        self::assertStringNotContainsString(' ', $path);
        self::assertStringNotContainsString('!', $path);
        self::assertMatchesRegularExpression('#^cars/9999/[a-f0-9]{32}\.(webp|jpg)$#', $path);

        /** @var FileStorageService $storage */
        $storage = $this->app->get(FileStorageService::class);
        self::assertTrue($storage->exists(FileStorageService::DISK_PUBLIC, $path));

        // The stored bytes are the re-encoded output, not the upload.
        $stored = file_get_contents($storage->absolutePath(FileStorageService::DISK_PUBLIC, $path));
        self::assertNotSame($this->realPng(), $stored);

        // And it is still a decodable image.
        self::assertNotFalse(@getimagesizefromstring($stored));
    }

    /**
     * Trailing payload appended after valid image data does not survive
     * re-encoding.
     */
    public function testAppendedPayloadIsStrippedByReEncoding(): void
    {
        $payload = '<?php echo "pwned"; ?>';
        $file = $this->upload('polyglot.png', $this->realPng() . $payload, 'image/png');

        $path = $this->images()->storeUploadedImage($file, FileStorageService::DISK_PUBLIC, 'cars/9999');

        /** @var FileStorageService $storage */
        $storage = $this->app->get(FileStorageService::class);
        $stored = file_get_contents($storage->absolutePath(FileStorageService::DISK_PUBLIC, $path));

        self::assertStringNotContainsString($payload, $stored);
        self::assertStringNotContainsString('<?php', $stored);
    }

    /** Two uploads of identical bytes still get distinct filenames. */
    public function testStoredFilenamesAreUnpredictableAndUnique(): void
    {
        $bytes = $this->realPng();

        $first = $this->images()->storeUploadedImage(
            $this->upload('a.png', $bytes, 'image/png'),
            FileStorageService::DISK_PUBLIC,
            'cars/9999'
        );

        $second = $this->images()->storeUploadedImage(
            $this->upload('b.png', $bytes, 'image/png'),
            FileStorageService::DISK_PUBLIC,
            'cars/9999'
        );

        self::assertNotSame($first, $second);
    }

    /** Documents are written to the private disk, never the public one. */
    public function testPrivateDocumentIsStoredOnThePrivateDisk(): void
    {
        $file = $this->upload('licence.png', $this->realPng(), 'image/png');

        $result = $this->images()->storePrivateDocument($file, 'documents/9999');

        /** @var FileStorageService $storage */
        $storage = $this->app->get(FileStorageService::class);

        self::assertTrue($storage->exists(FileStorageService::DISK_PRIVATE, $result['path']));
        self::assertFalse($storage->exists(FileStorageService::DISK_PUBLIC, $result['path']));

        $absolute = str_replace('\\', '/', $storage->absolutePath(FileStorageService::DISK_PRIVATE, $result['path']));
        self::assertStringContainsString('/storage/private/', $absolute);
        self::assertStringNotContainsString('/public/', $absolute);
    }

    public function testExecutableDocumentUploadIsRejected(): void
    {
        $file = $this->upload('licence.pdf', "<?php system(\$_GET['c']);", 'application/pdf');

        $this->expectException(UploadException::class);

        $this->images()->storePrivateDocument($file, 'documents/9999');
    }
}
