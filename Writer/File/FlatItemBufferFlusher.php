<?php

namespace Niji\AkeneoLabelizedExportBundle\Writer\File;

use Akeneo\Component\Batch\Model\StepExecution;
use Akeneo\Component\Batch\Step\StepExecutionAwareInterface;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Writer\WriterInterface;
use Pim\Component\Catalog\Repository\AttributeRepositoryInterface;
use Pim\Component\Connector\Writer\File\ColumnSorterInterface;
use Pim\Component\Connector\Writer\File\FlatItemBuffer;
use Pim\Component\Connector\Writer\File\FlatItemBufferFlusher as BaseFlatItemBufferFlusher;

/**
 * Flushes the flat item buffer into one or multiple output files.
 * @see Pim\Component\Connector\Writer\File\FlatItemBuffer
 *
 * Several output files are created if the buffer contains more items that maximum lines authorized per output file.
 *
 * @author    Julien Janvier <jjanvier@akeneo.com>
 * @copyright 2016 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class FlatItemBufferFlusher extends BaseFlatItemBufferFlusher
{

    /** @var AttributeRepositoryInterface */
    protected $attributeRepository;

    /**
     * @param ColumnSorterInterface $columnSorter
     */
    public function __construct(AttributeRepositoryInterface $attributeRepository, ColumnSorterInterface $columnSorter = null)
    {
        parent::__construct($columnSorter);

        $this->attributeRepository = $attributeRepository;
    }

    /**
     * @param FlatItemBuffer $buffer
     * @param array          $writerOptions
     * @param string         $filePath
     *
     * @return array
     */
    protected function writeIntoSingleFile(FlatItemBuffer $buffer, array $writerOptions, $filePath)
    {
        $writtenFiles = [];

        $headers = $this->sortHeaders($buffer->getHeaders());
        $hollowItem = array_fill_keys($headers, '');

        $jobFilters = $writerOptions['job_parameters']->get('filters');
        $locale = $jobFilters['structure']['locales'][0];
        unset($writerOptions['job_parameters']);

        $headersLabels = $this->getHeadersLabels($headers, $locale);

        $writer = $this->getWriter($filePath, $writerOptions);
        $writer->addRow($headersLabels);

        foreach ($buffer as $incompleteItem) {
            $item = array_replace($hollowItem, $incompleteItem);
            $writer->addRow($item);

            if (null !== $this->stepExecution) {
                $this->stepExecution->incrementSummaryInfo('write');
            }
        }

        $writer->close();
        $writtenFiles[] = $filePath;

        return $writtenFiles;
    }

    /**
     * @param FlatItemBuffer $buffer
     * @param array          $writerOptions
     * @param int            $maxLinesPerFile
     * @param string         $basePathname
     *
     * @return array
     */
    protected function writeIntoSeveralFiles(
      FlatItemBuffer $buffer,
      array $writerOptions,
      $maxLinesPerFile,
      $basePathname
    ) {
        $writtenFiles = [];
        $basePathPattern = $this->getNumberedPathname($basePathname);
        $writtenLinesCount = 0;
        $fileCount = 1;

        $headers = $this->sortHeaders($buffer->getHeaders());
        $hollowItem = array_fill_keys($headers, '');

        $jobFilters = $writerOptions['job_parameters']->get('filters');
        $locale = $jobFilters['structure']['locales'][0];
        unset($writerOptions['job_parameters']);

        $headersLabels = $this->getHeadersLabels($headers, $locale);

        foreach ($buffer as $count => $incompleteItem) {
            if (0 === $writtenLinesCount % $maxLinesPerFile) {
                $filePath = $this->resolveFilePath(
                  $buffer,
                  $maxLinesPerFile,
                  $basePathPattern,
                  $fileCount
                );
                $writtenLinesCount = 0;
                $writer = $this->getWriter($filePath, $writerOptions);
                $writer->addRow($headersLabels);
            }

            $item = array_replace($hollowItem, $incompleteItem);
            $writer->addRow($item);
            $writtenLinesCount++;

            if (null !== $this->stepExecution) {
                $this->stepExecution->incrementSummaryInfo('write');
            }

            if (0 === $writtenLinesCount % $maxLinesPerFile || $buffer->count() === $count + 1) {
                $writer->close();
                $writtenFiles[] = $filePath;
                $fileCount++;
            }
        }

        return $writtenFiles;
    }

    /**
     * Get headers labels.
     *
     * @param $headers
     *   List of headers code (attribute codes)
     *
     * @return array
     *   Headers labels.
     */
    private function getHeadersLabels($headers, $locale) {
        $headersLabels = [];
        foreach($headers as $attributeCode) {

            // Specific case for localized headers (e.g: [attCode]-[locale_code])
            if (preg_match('/^(.*)-([a-z]{2}_[A-Z]{2})$/', $attributeCode, $localizedAttributeCodes)) {
                $attributeCode = $localizedAttributeCodes[1];
                $locale = $localizedAttributeCodes[2];
            }

            /** @var \Pim\Component\Catalog\Model\AttributeInterface $attribute */
            $attribute = $this->attributeRepository->findOneByIdentifier($attributeCode);

            if (isset($attribute) && !empty($attribute->getTranslation($locale)->getLabel())) {
                $headersLabels[$attributeCode] = $attribute->getTranslation($locale)->getLabel();
            }
            else {
                $headersLabels[$attributeCode] = $attributeCode;
            }
        }

        return $headersLabels;
    }
}