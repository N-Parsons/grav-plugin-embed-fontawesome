<?php

namespace Grav\Plugin\EmbedFontawesome;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Framework\File\File;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class Fontawesome
{
    const FONTAWESOME_DIR = USER_DIR . 'data/fontawesome/';

    protected $grav;

    protected $npm;

    public function __construct()
    {
        $this->grav = Grav::instance();
        $this->npm = new FontawesomeNpm();
    }

    public function versionInstalled(): ?FontawesomeVersion
    {
        return FontawesomeVersion::fromFile(self::FONTAWESOME_DIR . 'version.yaml');
    }

    public function versionLatest(): FontawesomeVersion
    {
        return new FontawesomeVersion($this->npm->getLatest());
    }

    public function regenerate(): void
    {
        $this->backupCustom();
        $this->clear();
        $this->installLatest();
        $this->restoreCustom();
    }

    /**
     * Backup custom uploaded icons to temporary directory.
     */
    protected function backupCustom(): void
    {
        // TODO: extract source and target
        $source = 'user-data://fontawesome/custom/';
        $target = 'tmp://fontawesome-backup/custom/';
        $this->moveFolderIfExists($source, $target);
    }

    protected function moveFolderIfExists($source, $target): void
    {
        if (is_file($source) && is_dir($source)) {
            Folder::move($source, $target);
        }
    }

    /**
     * Remove generated fontawesome icons.
     */
    protected function clear(): void
    {
        Folder::delete(self::FONTAWESOME_DIR);
    }

    protected function installLatest(): void
    {
        [$version, $file] = $this->download();
        $this->extract($file);
        $version->save();
    }

    protected function download()
    {
        $file = new File('tmp://fontawesome_dist.tgz');
        [$version, $content] = $this->npm->downloadLatest();
        $file->save($content);
        return [$version, $file];
    }

    protected function extract(File $file): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav->get('locator');

        Folder::delete($locator->findResource('tmp://') . '/fontawesome');

        // extract needed files from tarball (only svg and license)
        $data = new \PharData($locator->findResource($file->getFilePath()));
        $data->extractTo($locator->findResource('tmp://') . '/fontawesome', [
            'package/svgs/',
            'package/LICENSE.txt',
        ]);

        // remove tarball in tmp folder
        $file->delete();

        Folder::move('tmp://fontawesome/package/svgs/', self::FONTAWESOME_DIR);
        // TODO: license move
        // TODO: adjust fieldset upload fields
    }

    /**
     * Restore custom uploaded icons from temporary directory.
     */
    protected function restoreCustom(): void
    {
        // TODO: extract source and target
        $source = 'tmp://fontawesome-backup/custom/';
        $target = 'user-data://fontawesome/custom/';
        $this->moveFolderIfExists($source, $target);
    }
}
