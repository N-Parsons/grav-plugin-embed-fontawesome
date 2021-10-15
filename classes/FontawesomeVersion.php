<?php

namespace Grav\Plugin\EmbedFontawesome;

use RocketTheme\Toolbox\File\YamlFile;

class FontawesomeVersion
{
    const DEFAULT_PATH = Fontawesome::FONTAWESOME_DIR . 'version.yaml';

    protected $name;

    protected $version;

    protected $path;

    public function __construct(array $content = [], string $path = null)
    {
        $this->name = $content['name'] ?? 'unknown';
        $this->version = $content['version'] ?? 'unknown';
        $this->path = $path ?? self::DEFAULT_PATH;
    }

    public function save()
    {
        $file = YamlFile::instance($this->path);
        $file->save([
            'name' => $this->name,
            'version' => $this->version,
        ]);
    }

    public function __toString()
    {
        return "$this->name ($this->version)";
    }

    /**
     * Reads given version file (yaml) from filesystem and extracts
     * content to manageable object.
     * @param string $path  file path (yaml)
     * @return FontawesomeVersion|null  version object or null if file not found
     */
    public static function fromFile(string $path = self::DEFAULT_PATH): ?FontawesomeVersion
    {
        if (!file_exists($path)) {
            return null;
        }

        $file = YamlFile::instance($path);
        $content = $file->content();
        $file->free();

        return new FontawesomeVersion($content, $path);
    }
}
