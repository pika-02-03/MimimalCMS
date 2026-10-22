<?php

declare(strict_types=1);

use MimimalCMS\Composer\Installer;
use PHPUnit\Framework\TestCase;

class InstallerTest extends TestCase
{
    private string $package;
    private string $project;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/mimimal_installer_' . bin2hex(random_bytes(4));
        $this->package = "{$base}/package";
        $this->project = "{$base}/project";

        $this->put($this->package, 'shadow/Kernel/Route.php', 'route v2');
        $this->put($this->package, 'shadow/Kernel/New.php', 'new');
        $this->put($this->package, 'shared/MimimalCMS_HelperFunctions.php', 'helpers v2');
        $this->put($this->package, 'shared/MimimalCMS_Enums.php', 'enums');
        $this->put($this->package, 'shared/MimimalCMS_ExceptionHandler.php', 'handler');
        $this->put($this->package, 'shared/Exceptions/NotFoundException.php', 'not found');
        $this->put($this->package, 'shared/MimimalCmsConfig.php', 'skeleton config');
        $this->put($this->package, 'shared/MimimalCMS_Settings.php', 'skeleton settings');

        $this->put($this->project, 'shadow/Kernel/Route.php', 'route v1');
        $this->put($this->project, 'shadow/Old/Removed.php', 'removed upstream');
        $this->put($this->project, 'shared/MimimalCMS_HelperFunctions.php', 'helpers v1');
        $this->put($this->project, 'shared/Exceptions/AppOnlyException.php', 'kept');
        $this->put($this->project, 'shared/MimimalCmsConfig.php', 'app config');
        $this->put($this->project, 'shared/bootstrap.php', 'app bootstrap');
    }

    protected function tearDown(): void
    {
        deleteDirectory(dirname($this->package));
    }

    private function put(string $root, string $file, string $content): void
    {
        $dir = dirname("{$root}/{$file}");
        is_dir($dir) || mkdir($dir, 0755, true);
        file_put_contents("{$root}/{$file}", $content);
    }

    private function read(string $file): string
    {
        return (string)file_get_contents("{$this->project}/{$file}");
    }

    public function testDryRunReportsWithoutWriting(): void
    {
        $changes = Installer::syncFiles($this->package, $this->project, dryRun: true);

        $this->assertContains('updated: shadow/Kernel/Route.php', $changes);
        $this->assertContains('deleted: shadow/Old/Removed.php', $changes);
        $this->assertSame('route v1', $this->read('shadow/Kernel/Route.php'));
        $this->assertFileExists("{$this->project}/shadow/Old/Removed.php");
    }

    public function testSyncCopiesFrameworkAndKeepsAppFiles(): void
    {
        Installer::syncFiles($this->package, $this->project);

        $this->assertSame('route v2', $this->read('shadow/Kernel/Route.php'));
        $this->assertSame('new', $this->read('shadow/Kernel/New.php'));
        $this->assertSame('helpers v2', $this->read('shared/MimimalCMS_HelperFunctions.php'));
        $this->assertSame('not found', $this->read('shared/Exceptions/NotFoundException.php'));
        // shadow/ is mirrored
        $this->assertFileDoesNotExist("{$this->project}/shadow/Old/Removed.php");
        $this->assertDirectoryDoesNotExist("{$this->project}/shadow/Old");
        // Application-owned files are never overwritten; missing stubs are created
        $this->assertSame('app config', $this->read('shared/MimimalCmsConfig.php'));
        $this->assertSame('app bootstrap', $this->read('shared/bootstrap.php'));
        $this->assertSame('kept', $this->read('shared/Exceptions/AppOnlyException.php'));
        $this->assertSame('skeleton settings', $this->read('shared/MimimalCMS_Settings.php'));
    }

    public function testSecondSyncChangesNothing(): void
    {
        Installer::syncFiles($this->package, $this->project);

        $this->assertSame([], Installer::syncFiles($this->package, $this->project));
    }
}
