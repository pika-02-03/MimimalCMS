<?php

declare(strict_types=1);

namespace MimimalCMS\Composer;

/**
 * Copies the MimimalCMS framework files from vendor/ into the project, keeping the same layout
 * as this repository (shadow/, shared/MimimalCMS_*.php, shared/Exceptions/).
 *
 * The project commits the copied files and runs them from there, so production needs nothing from vendor/.
 * Add to the project's composer.json:
 *
 *     "scripts": {
 *         "post-update-cmd": ["MimimalCMS\\Composer\\Installer::sync"],
 *         "mimimalcms-sync": "MimimalCMS\\Composer\\Installer::sync",
 *         "mimimalcms-check": "MimimalCMS\\Composer\\Installer::check"
 *     }
 *
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/pika-02-03/MimimalCMS/blob/master/LICENSE.md
 */
final class Installer
{
    /** Framework-owned directories. Mirrored: files that no longer exist in the package are deleted. */
    public const MIRROR_DIRS = [
        'shadow',
    ];

    /** Framework-owned files and directories. Overwritten with the package version (nothing is deleted). */
    public const COPY_PATHS = [
        'shared/Exceptions',
        'shared/MimimalCMS_Enums.php',
        'shared/MimimalCMS_ExceptionHandler.php',
        'shared/MimimalCMS_HelperFunctions.php',
    ];

    /** Application-owned files. Created from the package only when the project does not have them yet. */
    public const STUB_PATHS = [
        'shared/MimimalCmsConfig.php',
        'shared/MimimalCMS_Settings.php',
    ];

    /**
     * Composer script: copy the framework files into the project.
     *
     * @param \Composer\Script\Event $event
     */
    public static function sync($event): void
    {
        [$packageDir, $projectDir] = self::resolveDirs($event);
        $io = $event->getIO();

        if ($packageDir === $projectDir) {
            $io->write('<info>MimimalCMS: running inside the framework repository, nothing to sync.</info>');
            return;
        }

        $changes = self::syncFiles($packageDir, $projectDir);
        if ($changes === []) {
            $io->write('<info>MimimalCMS: framework files are up to date.</info>');
            return;
        }

        $io->write('<info>MimimalCMS: framework files synced (' . count($changes) . ')</info>');
        foreach ($changes as $change) {
            $io->write('  ' . $change);
        }
    }

    /**
     * Composer script: fail when the project's framework files differ from the installed package.
     *
     * @param \Composer\Script\Event $event
     */
    public static function check($event): void
    {
        [$packageDir, $projectDir] = self::resolveDirs($event);
        if ($packageDir === $projectDir) {
            return;
        }

        $changes = self::syncFiles($packageDir, $projectDir, dryRun: true);
        if ($changes === []) {
            $event->getIO()->write('<info>MimimalCMS: framework files match the installed package.</info>');
            return;
        }

        throw new \RuntimeException(
            "MimimalCMS: framework files differ from the installed package. Run `composer mimimalcms-sync`.\n  "
                . implode("\n  ", $changes)
        );
    }

    /**
     * Copy the framework files from $packageDir into $projectDir.
     *
     * @return list<string> What changed (or would change with $dryRun), e.g. "updated: shadow/Kernel/Route.php".
     */
    public static function syncFiles(string $packageDir, string $projectDir, bool $dryRun = false): array
    {
        $packageDir = rtrim($packageDir, '/');
        $projectDir = rtrim($projectDir, '/');
        $changes = [];

        foreach ([...self::MIRROR_DIRS, ...self::COPY_PATHS] as $path) {
            foreach (self::listFiles($packageDir, $path) as $file) {
                $change = self::copyFile("{$packageDir}/{$file}", "{$projectDir}/{$file}", $dryRun);
                if ($change !== null) {
                    $changes[] = "{$change}: {$file}";
                }
            }
        }

        foreach (self::MIRROR_DIRS as $dir) {
            $packageFiles = self::listFiles($packageDir, $dir);
            foreach (self::listFiles($projectDir, $dir) as $file) {
                if (in_array($file, $packageFiles, true)) {
                    continue;
                }

                $dryRun || unlink("{$projectDir}/{$file}");
                $changes[] = "deleted: {$file}";
            }

            $dryRun || self::removeEmptyDirs("{$projectDir}/{$dir}");
        }

        foreach (self::STUB_PATHS as $file) {
            if (!file_exists("{$projectDir}/{$file}") && file_exists("{$packageDir}/{$file}")) {
                $dryRun || self::copyFile("{$packageDir}/{$file}", "{$projectDir}/{$file}", false);
                $changes[] = "created: {$file}";
            }
        }

        return $changes;
    }

    /**
     * @return 'created'|'updated'|null
     */
    private static function copyFile(string $source, string $target, bool $dryRun): ?string
    {
        $content = file_get_contents($source);
        if ($content === false) {
            throw new \RuntimeException("MimimalCMS: could not read {$source}");
        }

        $exists = file_exists($target);
        if ($exists && file_get_contents($target) === $content) {
            return null;
        }

        if (!$dryRun) {
            $dir = dirname($target);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException("MimimalCMS: could not create {$dir}");
            }

            if (file_put_contents($target, $content) === false) {
                throw new \RuntimeException("MimimalCMS: could not write {$target}");
            }
        }

        return $exists ? 'updated' : 'created';
    }

    /**
     * @return list<string> Paths relative to $baseDir, sorted.
     */
    private static function listFiles(string $baseDir, string $path): array
    {
        $full = "{$baseDir}/{$path}";
        if (is_file($full)) {
            return [$path];
        }

        if (!is_dir($full)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($full, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile()) {
                $files[] = $path . substr($file->getPathname(), strlen($full));
            }
        }

        sort($files);
        return $files;
    }

    private static function removeEmptyDirs(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir() && count(scandir($item->getPathname())) === 2) {
                rmdir($item->getPathname());
            }
        }
    }

    /**
     * @param \Composer\Script\Event $event
     * @return array{string, string} [package root, project root]
     */
    private static function resolveDirs($event): array
    {
        $vendorDir = realpath($event->getComposer()->getConfig()->get('vendor-dir'));
        $packageDir = realpath(dirname(__DIR__, 2));
        if ($vendorDir === false || $packageDir === false) {
            throw new \RuntimeException('MimimalCMS: could not resolve the vendor or package directory.');
        }

        return [$packageDir, dirname($vendorDir)];
    }
}
