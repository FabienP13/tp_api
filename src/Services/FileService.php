<?php

namespace App\Services;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class FileService
{
    private array $formatsFile = ['json', 'csv'];
    private string $filePath = './entreprises/';
    private string $filePathSiren = './siren/entreprises.txt';

    public function __construct(private Filesystem $filesystem = new Filesystem(), private NormalizerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function verifyingFile($siren, $datas)
    {
        if ($this->filesystem->exists($this->filePath . $siren . '.json')) {
            //Si le fichier siren.json existe, on ne fait rien
        } elseif (($this->filesystem->exists($this->filePathSiren)) && (!$this->filesystem->exists($this->filePath . $siren . '.json'))) {
            $this->writeInFile($siren);
            $this->createFileWithDatas($siren, $datas);
        } else {
            $this->createFileWithDatas($siren, $datas);
            $this->createFileSiren($siren);
        }
    }

    public function createFileWithDatas(string $siren, $datas)
    {
        foreach ($this->formatsFile as $format) {
            if ($format == 'csv') {
                $dataSerialized = $this->serializer->serialize($datas, $format, ['csv_delimiter' => ';']);
            } else {
                $dataSerialized = $this->serializer->serialize($datas, $format);
            }
            $this->filesystem->dumpFile($this->filePath . $siren . '.' . $format, $dataSerialized);
        }
    }
    public function createFileSiren($siren)
    {
        $this->filesystem->dumpFile($this->filePathSiren, $siren . '-');
    }

    public function writeInFile(string $siren)
    {
        $this->filesystem->appendToFile($this->filePathSiren, $siren . '-');
    }
}
