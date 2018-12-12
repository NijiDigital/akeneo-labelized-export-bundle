<?php

namespace Niji\AkeneoLabelizedExportBundle\Normalizer\Product\Standard;

use Doctrine\Common\Collections\ArrayCollection;
use Pim\Bundle\CatalogBundle\Entity\AttributeOption;
use Pim\Bundle\CatalogBundle\Filter\CollectionFilterInterface;
use Pim\Component\Catalog\AttributeTypes;
use Pim\Component\Catalog\Model\ValueCollectionInterface;
use Pim\Component\Catalog\Normalizer\Standard\AttributeOptionNormalizer;
use Pim\Component\Catalog\Normalizer\Standard\Product\PropertiesNormalizer as BasePropertiesNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

class PropertiesNormalizer extends BasePropertiesNormalizer
{
    use SerializerAwareTrait;

    /** @var CollectionFilterInterface */
    private $filter;

    /** @var \Pim\Component\Catalog\Normalizer\Standard\AttributeOptionNormalizer */
    private $attributeOptionNormalizer;

    /**
     * @param CollectionFilterInterface $filter The collection filter
     */
    public function __construct(CollectionFilterInterface $filter, AttributeOptionNormalizer $attributeOptionNormalizer)
    {
        $this->filter = $filter;
        $this->attributeOptionNormalizer = $attributeOptionNormalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($product, $format = null, array $context = [])
    {
        if (!$this->serializer instanceof NormalizerInterface) {
            throw new \LogicException('Serializer must be a normalizer');
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
        $data[self::FIELD_CREATED] = $this->serializer->normalize($product->getCreated(), $format);
        $data[self::FIELD_UPDATED] = $this->serializer->normalize($product->getUpdated(), $format);

        return $data;
    }

    /**
     * Normalize the values of the product
     *
     * @param ValueCollectionInterface $values
     * @param string                   $format
     * @param array                    $context
     *
     * @return array
     */
    private function normalizeValues(ValueCollectionInterface $values, $format, array $context = [])
    {
        foreach ($context['filter_types'] as $filterType) {
            $values = $this->filter->filterCollection($values, $filterType, $context);
        }

        $data = [];
        /** @var \Pim\Component\Catalog\Model\ValueInterface $value */
        foreach ($values as $value) {
            $data[$value->getAttribute()->getCode()][] = $this->serializer->normalize($value, $format, $context);

            //skip value if no data
            $valueData = $value->getData();

            //translate select attribute
            if (!empty($valueData)
              && (AttributeTypes::OPTION_MULTI_SELECT == $value->getAttribute()->getType()
                || AttributeTypes::OPTION_SIMPLE_SELECT == $value->getAttribute()
                  ->getType())) {

                //get AttributeOption object from value
                $attributeOptions = $valueData;
                if (AttributeTypes::OPTION_SIMPLE_SELECT == $value->getAttribute()->getType()) {
                    $attributeOptions = [$valueData];
                }

                foreach ($attributeOptions as $attributeOption) {
                    $attributeOptionNormalized = $this->attributeOptionNormalizer->normalize(
                      $attributeOption,
                      'flat',
                      $context
                    );

                    $locale = $context['values_locale'];
                    if (!empty($attributeOptionNormalized['labels'][$locale])) {
                        $data[$value->getAttribute()->getCode()][] = [
                          'locale' => null,
                          'scope' => null,
                          'data' => $attributeOptionNormalized['labels'][$locale],
                        ];
                    }

                    if (count($data[$value->getAttribute()->getCode()]) > 1) {
                        unset($data[$value->getAttribute()->getCode()][0]);
                    }
                }
            }
        }

        return $data;
    }
}