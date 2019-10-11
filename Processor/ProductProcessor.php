<?php

namespace Niji\AkeneoLabelizedExportBundle\Processor;

use Akeneo\Tool\Component\Batch\Job\JobInterface;
use Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\Normalization\ProductProcessor as BaseProductProcessor;

class ProductProcessor extends BaseProductProcessor
{
    /**
     * {@inheritdoc}
     */
    public function process($product)
    {
        $parameters = $this->stepExecution->getJobParameters();
        $structure = $parameters->get('filters')['structure'];
        $channel = $this->channelRepository->findOneByIdentifier($structure['scope']);
        $this->productValuesFiller->fillMissingValues($product);

        $productStandard = $this->normalizer->normalize(
          $product,
          'standard',
          [
            'filter_types' => ['pim.transform.product_value.structured'],
            'channels' => [$channel->getCode()],
            'values_locale' => $parameters->get('filters')['structure']['locales'][0],
            'locales'  => array_intersect(
              $channel->getLocaleCodes(),
              $parameters->get('filters')['structure']['locales']
            ),
          ]
        );

        if ($this->areAttributesToFilter($parameters)) {
            $attributesToFilter = $this->getAttributesToFilter($parameters);
            $productStandard['values'] = $this->filterValues($productStandard['values'], $attributesToFilter);
        }

        if ($parameters->has('with_media') && $parameters->get('with_media')) {
            $directory = $this->stepExecution->getJobExecution()->getExecutionContext()
              ->get(JobInterface::WORKING_DIRECTORY_PARAMETER);

            $this->fetchMedia($product, $directory);
        } else {
            $mediaAttributes = $this->attributeRepository->findMediaAttributeCodes();
            $productStandard['values'] = array_filter(
              $productStandard['values'],
              function ($attributeCode) use ($mediaAttributes) {
                  return !in_array($attributeCode, $mediaAttributes);
              },
              ARRAY_FILTER_USE_KEY
            );
        }

        return $productStandard;
    }
}