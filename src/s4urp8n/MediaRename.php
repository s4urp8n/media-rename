<?php

namespace s4urp8n;

use s4urp8n\Media\Information;

class MediaRename
{
    const DATE_FORMAT       = "Y-m-d H-i-s";
    const FULL_DATE_PATTERN = "/^\d{4}-\d{2}-\d{2} \d{2}-\d{2}-\d{2}$/";
    const DATE_PATTERN      = "/^\d{4}-\d{2}-\d{2}$/";

    protected $paths = [];

    public function addPath($path)
    {
        if (!file_exists($path)) {
            throw new \Exception(sprintf('Path %s is not exists', $path));
        }
        $this->paths[] = $path;
        return $this;
    }

    public function rename()
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                $this->processFile($path);
                return;
            }
            foreach (\Zver\Common::getDirectoryContentRecursive($path) as $file) {
                if (is_file($file)) {
                    $this->processFile($file);
                }
            }
        }
    }

    private function processFile($file)
    {
        $filename = pathinfo($file, PATHINFO_FILENAME);
        $pattern = '/^\d{4}-\d{2}-\d{2} \d{2}-\d{2}-\d{2} \w{32}$/';
        if (preg_match($pattern, $filename) == 1) {
            return;
        }

        $desiredName = $this->getDisaredNameForFile($file);
        if (!$desiredName || $desiredName == basename($file)) {
            return;
        }

        $destination = dirname($file) . DIRECTORY_SEPARATOR . $desiredName;
        if (file_exists($destination)) {
            unlink($file);
            return;
        }

        echo basename($file), ' -> ', basename($destination), "\n";
        rename($file, $destination);
    }

    private function getArrayValue($keys, array $array)
    {
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        foreach ($keys as $arrayKey) {
            if (!array_key_exists($arrayKey, $array)) {
                return null;
            }
            $array = $array[$arrayKey];
        }
        return $array;
    }

    private function getDisaredNameForFile($file)
    {
        $desiredName = mb_strtolower(basename($file));
        $mediainfo = Information::getInformation($file);
        if (!$mediainfo) {
            return $desiredName;
        }

        $creationTime = $this->getCreationTimeFromMediaInfo($mediainfo);
        if (!$creationTime) {
            return $desiredName;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $desiredName = array_shift($creationTime) . ' ' . md5_file($file) . '.' . $ext;
        return $desiredName;
    }

    private function getCreationTimeFromMediaInfo(array $mediainfo)
    {
        $formats = [
            [
                'keys'     => [
                    ['General', "Encoded date"],
                    ['Video', "Encoded date"],
                    ['Video', "Tagged date"],
                    ['Audio', "Encoded date"],
                    ['Audio', "Tagged date"],
                    ['General', "Tagged date"],
                ],
                'callback' => function ($value) {
                    $value = trim($value);
                    if (preg_match('/^UTC \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1) {
                        return date(static::DATE_FORMAT, strtotime($value));
                    }
                    throw new \Exception("Wrong value format for UTC parser");
                },
            ],
            [
                'keys'     => [
                    ['_EXIF_', 'FILE', 'FileDateTime'],
                ],
                'callback' => function ($value) {
                    if (!is_int($value)) {
                        throw new \Exception("Wrong value format for FileDateTime");
                    }
                    return date(static::DATE_FORMAT, $value);
                },
            ],
            [
                'keys'     => [
                    ['_EXIF_', 'IFP0', 'DateTime'],
                    ['_EXIF_', 'EXIF', 'DateTimeDigitized'],
                    ['_EXIF_', 'EXIF', 'DateTimeOriginal'],
                ],
                'callback' => function ($value) {
                    $value = str_replace(':', '-', trim($value));
                    if (preg_match(static::FULL_DATE_PATTERN, $value) === 1) {
                        return $value;
                    }
                    return null;
                },
            ],
            [
                'keys'     => [
                    ['_EXIF_', 'GPS', 'GPSDateStamp'],
                ],
                'callback' => function ($value) {
                    $value = str_replace(':', '-', $value);
                    if (preg_match(static::DATE_PATTERN, $value) === 1) {
                        return $value . ' 00-00-00';
                    }
                    return null;
                },
            ],
        ];

        $times = [];

        foreach ($formats as $format) {
            $keys = $format['keys'];
            $callback = $format['callback'];
            foreach ($keys as $key) {
                $value = static::getArrayValue($key, $mediainfo);
                if (!$value) {
                    continue;
                }
                $value = $callback($value);
                if (!$value) {
                    continue;
                }
                if (preg_match(self::FULL_DATE_PATTERN, $value) === 1) {
                    $times[] = $value;
                }
            }
        }

        $times = array_count_values($times);
        arsort($times);

        return array_keys($times);
    }

}