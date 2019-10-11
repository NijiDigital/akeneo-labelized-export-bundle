<?php

namespace Niji\AkeneoLabelizedExportBundle\Writer\File\Xlsx;

use Akeneo\Tool\Component\Buffer\BufferFactory;
use Niji\AkeneoLabelizedExportBundle\Writer\File\FlatItemBufferFlusher;
use Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface;
use Akeneo\Tool\Component\Connector\ArrayConverter\ArrayConverterInterface;
use Akeneo\Tool\Component\Connector\Writer\File\FileExporterPathGeneratorInterface;
use Akeneo\Pim\Enrichment\Component\Product\Connector\Writer\File\Xlsx\ProductWriter as BaseProductWriter;
use Symfony\Component\Filesystem\Filesystem;

class ProductWriter extends BaseProductWriter
{
    protected const DEFAULT_FILE_PATH = 'filePath';

    /** @var \Niji\AkeneoLabelizedExportBundle\Writer\File\FlatItemBufferFlusher */
    protected $flusher;

    /**
     * Constructor for ProductWriter.
     *
     * @param ArrayConverterInterface            $arrayConverter
     * @param BufferFactory                      $bufferFactory
     * @param FlatItemBufferFlusher              $flusher
     * @param AttributeRepositoryInterface       $attributeRepository
     * @param FileExporterPathGeneratorInterface $fileExporterPath
     * @param array                              $mediaAttributeTypes
     * @param String                             $jobParamFilePath
     */
    public function __construct(
      ArrayConverterInterface $arrayConverter,
      BufferFactory $bufferFactory,
      FlatItemBufferFlusher $flusher,
      AttributeRepositoryInterface $attributeRepository,
      FileExporterPathGeneratorInterface $fileExporterPath,
      array $mediaAttributeTypes,
      $jobParamFilePath = self::DEFAULT_FILE_PATH
    ) {
        $this->arrayConverter = $arrayConverter;
        $this->bufferFactory = $bufferFactory;
        $this->flusher = $flusher;
        $this->attributeRepository = $attributeRepository;
        $this->mediaAttributeTypes = $mediaAttributeTypes;
        $this->fileExporterPath = $fileExporterPath;
        $this->jobParamFilePath = $jobParamFilePath;

        $this->localFs = new Filesystem();
    }

    /**
     * Flush items into a file
     */
    public function flush()
    {
        $this->flusher->setStepExecution($this->stepExecution);

        $parameters = $this->stepExecution->getJobParameters();

        $writerConfiguration = $this->getWriterConfiguration();
        $writerConfiguration['job_parameters'] = $parameters;

        $writtenFiles = $this->flusher->flush(
          $this->flatRowBuffer,
          $writerConfiguration,
          $this->getPath(),
          ($parameters->has('linesPerFile') ? $parameters->get('linesPerFile') : -1)
        );

        foreach ($writtenFiles as $writtenFile) {
            $this->writtenFiles[$writtenFile] = basename($writtenFile);
        }

        $this->exportMedias();
    }
}
