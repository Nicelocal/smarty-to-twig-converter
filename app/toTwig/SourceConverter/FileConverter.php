<?php

/**
 * This file is part of the PHP ST utility.
 *
 * (c) Sankar suda <sankar.suda@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace toTwig\SourceConverter;

use ArrayIterator;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo as FinderSplFileInfo;
use toTwig\ConversionResult;
use toTwig\Converter\ConverterAbstract;
use toTwig\Finder\DefaultFinder;
use toTwig\Finder\FinderInterface;

/**
 * Class FileConverter
 */
class FileConverter extends SourceConverter
{
    private Finder $finder;
    private ?string $dir = null;
    private string $outputExtension;

    /**
     * FileConverter constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->finder = new DefaultFinder();
    }

    /**
     * @param bool                $dryRun
     * @param bool                $diff
     * @param ConverterAbstract[] $converters
     *
     * @return ConversionResult[]
     */
    public function convert(bool $dryRun, bool $diff, array $converters): array
    {
        $changed = [];
        foreach ($this->getFinder() as $file) {
            if ($file->isDir()) {
                continue;
            }

            echo("Converting ".$file->getPathName().PHP_EOL);
            $changed += $this->convertFile($file, $dryRun, $diff, $converters);
        }

        return $changed;
    }

    /**
     * @param SplFileInfo         $file
     * @param bool                $dryRun
     * @param bool                $diff
     * @param ConverterAbstract[] $converters
     *
     * @return array
     */
    private function convertFile(SplFileInfo $file, bool $dryRun, bool $diff, array $converters): array
    {
        $changed = [];

        $conversionResult = $this->convertTemplate(file_get_contents($file->getRealpath()), $diff, $converters);
        if ($conversionResult->hasAppliedConverters()) {
            if (!$dryRun) {
                $filename = $this->getConvertedFilename($file);

                file_put_contents($filename, $conversionResult->getConvertedTemplate());
            }

            if ($file instanceof FinderSplFileInfo) {
                $changed[$file->getRelativePathname()] = $conversionResult;
            } else {
                $changed[$file->getPathname()] = $conversionResult;
            }
        }

        return $changed;
    }

    public function setFinder(Finder $finder): self
    {
        $this->finder = $finder;

        return $this;
    }

    public function getFinder(): Finder
    {
        if ($this->finder instanceof FinderInterface && $this->dir !== null) {
            $this->finder->setDir($this->dir);
        }

        return $this->finder;
    }

    public function setDir(string $dir): void
    {
        $this->dir = $dir;
    }

    public function setPath(string $path): self
    {
        if (is_file($path)) {
            $iterator = new ArrayIterator([new SplFileInfo($path)]);
            $this->setFinder(Finder::create()->files()->append($iterator));
        } else {
            $this->setDir($path);
        }

        return $this;
    }

    public function setOutputExtension(string $outputExtension): self
    {
        $this->outputExtension = $outputExtension;

        return $this;
    }

    public function getConvertedFilename(SplFileInfo $file): string
    {
        $filename = $file->getRealpath();

        $ext = strrchr($filename, '.');
        if ($this->outputExtension) {
            $filename = substr($filename, 0, -strlen($ext)) . '.' . trim($this->outputExtension, '.');
        }

        return $filename;
    }
}
