<?php

namespace Niji\AkeneoLabelizedExportBundle\Normalizer\Product\Standard;

use Akeneo\Pim\Enrichment\Component\Product\Model\ValueInterface;
use Akeneo\Pim\Permission\Bundle\Entity\Repository\AttributeRepository;
use Akeneo\Pim\Structure\Component\Model\AttributeOption;
use Akeneo\Pim\Enrichment\Bundle\Filter\CollectionFilterInterface;
use Akeneo\Pim\Structure\Component\AttributeTypes;
use Akeneo\Pim\Enrichment\Component\Product\Model\WriteValueCollection;
use Akeneo\Pim\Enrichment\Component\Product\Normalizer\Standard\Product\PropertiesNormalizer as BasePropertiesNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PropertiesNormalizer extends BasePropertiesNormalizer
{
    use NormalizerAwareTrait;

    /** @var CollectionFilterInterface */
    private $filter;

    /** @var AttributeRepository */
    private $attributeRepository;

    /**
     * @param CollectionFilterInterface $filter The collection filter
     * @param NormalizerInterface $normalizer
     * @param AttributeRepository $attributeRepository
     */
    public function __construct(CollectionFilterInterface $filter, NormalizerInterface $normalizer, AttributeRepository $attributeRepository)
    {
        $this->filter = $filter;
        $this->normalizer = $normalizer;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($product, $format = null, array $context = [])
    {
        if (!$this->normalizer instanceof NormalizerInterface) {
            throw new \LogicException('The normalizer must implement NormalizerInterface');
        }

        $context = array_merge(['filter_types' => ['pim.transform.product_value.structured']], $context);
        $data = [];

        $data[self::FIELD_IDENTIFIER] = $product->getIdentifier();
        $data[self::FIELD_FAMILY] = $product->getFamily() ? $product->getFamily()->getCode() : null;
        if ($product->isVariant() && null !== $product->getParent()) {
            $data[self::FIELD_PARENT] = $product->getParent()->getCode();
        } else {
            $data[self::FIELD_PARENT] = null;
        }
        $data[self::FIELD_GROUPS] = $product->getGroupCodes();
        $data[self::FIELD_CATEGORIES] = $product->getCategoryCodes();
        $data[self::FIELD_ENABLED] = (bool) $product->isEnabled();
        $data[self::FIELD_VALUES] = $this->normalizeValues($product->getValues(), $format, $context);
        $data[self::FIELD_CREATED] = $this->normalizer->normalize($product->getCreated(), $format);
        $data[self::FIELD_UPDATED] = $this->normalizer->normalize($product->getUpdated(), $format);

        return $data;
    }

    /**
     * Normalize the values of the product
     *
     * @param WriteValueCollection $values
     * @param string                   $format
     * @param array                    $context
     *
     * @return array
     */
    private function normalizeValues(WriteValueCollection $values, $format, array $context = [])
    {
        foreach ($context['filter_types'] as $filterType) {
            $values = $this->filter->filterCollection($values, $filterType, $context);
        }

        $data = [];
        /** @var ValueInterface $value */
        foreach ($values as $value) {
            $data[$value->getAttributeCode()][] = $this->normalizer->normalize($value, $format, $context);

            //skip value if no data
            $valueData = $value->getData();

            $attributeType = $this->attributeRepository->findOneBy(["code" => $value->getAttributeCode()])->getType();

            //translate select attribute
            if (!empty($valueData)
              && (AttributeTypes::OPTION_MULTI_SELECT == $attributeType
                || AttributeTypes::OPTION_SIMPLE_SELECT == $attributeType)) {
                $data[$value->getAttributeCode()] = [];

                //get AttributeOption object from value
                $attributeOptions = $value;
                $attributeOptionData = [];
                if (AttributeTypes::OPTION_SIMPLE_SELECT == $attributeType) {
                    $attributeOptions = [$value];
                    $attributeOptionData = '';
                }

                foreach ($attributeOptions as $attributeOption) {
                    $attributeOptionNormalized = (array) $attributeOption;

                    $locale = $context['values_locale'];
                    if (!empty($attributeOptionNormalized['labels'][$locale])) {
                        if (AttributeTypes::OPTION_SIMPLE_SELECT == $attributeType) {
                            $attributeOptionData = $attributeOptionNormalized['labels'][$locale];
                        }
                        else {
                            $attributeOptionData[] = $attributeOptionNormalized['labels'][$locale];
                        }
                    }
                    else {
                        if (AttributeTypes::OPTION_SIMPLE_SELECT == $attributeType) {
                            $attributeOptionData = $attributeOption->getAttributeCode();
                        }
                        else {
                            $attributeOptionData[] = $attributeOption->getAttributeCode();
                        }
                    }
                }

                $data[$value->getAttributeCode()][] = [
                  'locale' => null,
                  'scope' => null,
                  'data' => $attributeOptionData,
                ];
            }
        }

        return $data;
    }
}
