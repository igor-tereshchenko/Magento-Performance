<?php

/**
 * Class Company_Performance_Model_Catalog_Product_Type_Configurable
 *
 * @category Company
 * @package Company_Performance
 */
class Company_Performance_Model_Catalog_Product_Type_Configurable extends Mage_Catalog_Model_Product_Type_Configurable
{
    /**
     * @var array
     */
    protected static $_cacheArray = array();

    /**
     * Initialize caches
     */
    public function init() {
        if (empty(self::$_cacheArray)) {
            self::$_cacheArray = [
                'USED_PRODUCTS_CACHE_KEY'      => Mage::getStoreConfig('company_performance/used_products/cache_key'),
                'USED_PRODUCTS_CACHE_TAG'      => Mage::getStoreConfig('company_performance/used_products/cache_tag'),
                'USED_PRODUCTS_CACHE_LIFETIME' => Mage::getStoreConfig('dev/performance/used_products_lifetime'),
                'CONF_ATTR_CACHE_KEY'          => Mage::getStoreConfig('company_performance/configurable_attributes/cache_key'),
                'CONF_ATTR_CACHE_TAG'          => Mage::getStoreConfig('company_performance/configurable_attributes/cache_tag'),
                'CONF_ATTR_CACHE_LIFETIME'     => Mage::getStoreConfig('dev/performance/configurable_attributes_lifetime'),
            ];
        }
    }

    public function __construct() {
        $this->init();
    }

    /**
     * Retrieve Selected Attributes info
     *
     * @param Mage_Catalog_Model_Product $product
     *
     * @return array
     */
    public function getSelectedAttributesInfo($product = null) {
        Varien_Profiler::start('CONFIGURABLE:' . __METHOD__);

        $attributes = array();
        if ($attributesOption = $this->getProduct($product)->getCustomOption('attributes')) {
            $data = unserialize($attributesOption->getValue());
            $this->getUsedProductAttributeIds($product);

            $usedAttributes = $this->getProduct($product)->getData($this->_usedAttributes);

            foreach ($data as $attributeId => $attributeValue) {
                if (isset($usedAttributes[$attributeId])) {
                    $attribute = $usedAttributes[$attributeId];
                    $label = $attribute->getLabel();
                    $value = $attribute->getProductAttribute();
                    if ($value->getSourceModel()) {
                        if (!Mage::app()->getStore()->isAdmin()) {
                            $value = $value->getSource()->getNeededOptionText($attributeValue);
                        }
                        else {
                            $value = $value->getSource()->getOptionText($attributeValue);
                        }
                    }
                    else {
                        $value = '';
                    }

                    $attributes[] = array('label' => $label, 'value' => $value);
                }
            }
        }

        Varien_Profiler::stop('CONFIGURABLE:' . __METHOD__);

        return $attributes;
    }

    /**
     * Check is product available for sale
     *
     * @param Mage_Catalog_Model_Product $product
     *
     * @return bool
     */
    public function isSalable($product = null) {
        $salable = Mage_Catalog_Model_Product_Type_Abstract::isSalable($product);

        if ($salable !== false) {
            $salable = false;
            if (!is_null($product)) {
                $this->setStoreFilter($product->getStoreId(), $product);
            }

            if (!Mage::app()->getStore()->isAdmin() && $product) {
                $collection = $this->getUsedProductCollection($product)
                                   ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                                   ->setPageSize(1);
                if ($collection->getFirstItem()->getId()) {
                    $salable = true;
                }
            }
            else {
                foreach ($this->getUsedProducts(null, $product) as $child) {
                    if ($child->isSalable()) {
                        $salable = true;
                        break;
                    }
                }
            }
        }

        return $salable;
    }

    /**
     * Retrieve array of "subproducts"
     *
     * @param                             array
     * @param  Mage_Catalog_Model_Product $product
     *
     * @return array
     */
    public function getUsedProducts($requiredAttributeIds = null, $product = null) {
        Varien_Profiler::start('CONFIGURABLE:' . __METHOD__);
        if (!$this->getProduct($product)->hasData($this->_usedProducts)) {
            if (is_null($requiredAttributeIds)
                and is_null($this->getProduct($product)->getData($this->_configurableAttributes))
            ) {
                // If used products load before attributes, we will load attributes.
                $this->getConfigurableAttributes($product);
                // After attributes loading products loaded too.
                Varien_Profiler::stop('CONFIGURABLE:' . __METHOD__);

                return $this->getProduct($product)->getData($this->_usedProducts);
            }

            $usedProducts = array();
            if (!Mage::app()->getStore()->isAdmin() && $product) {
                $locale = Mage::app()->getLocale();
                $cacheKeyData = array(
                    self::$_cacheArray['USED_PRODUCTS_CACHE_KEY'],
                    $locale->getLocaleCode(),
                    $product->getId()
                );
                $cacheId = implode('_', $cacheKeyData);
                $usedProducts = Mage::app()->loadCache($cacheId);
            }

            if (empty($usedProducts)) {
                $collection = $this->getUsedProductCollection($product)
                                   ->addAttributeToSelect('*')
                                   ->addFilterByRequiredOptions();

                if (is_array($requiredAttributeIds)) {
                    foreach ($requiredAttributeIds as $attributeId) {
                        $attribute = $this->getAttributeById($attributeId, $product);
                        if (!is_null($attribute)) {
                            $collection->addAttributeToFilter($attribute->getAttributeCode(), array('notnull' => 1));
                        }
                    }
                }
                foreach ($collection as $item) {
                    $item->unsetData('stock_item');
                    $usedProducts[] = $item;
                }
                try {
                    if (!Mage::app()->getStore()->isAdmin() && $product) {
                        Mage::app()->saveCache(
                            serialize($usedProducts),
                            $cacheId,
                            array((string) self::$_cacheArray['USED_PRODUCTS_CACHE_TAG']),
                            (string) self::$_cacheArray['USED_PRODUCTS_CACHE_LIFETIME']
                        );
                    }
                }
                catch (Exception $e) {
                    Mage::log('error while saving used products to cache: ' . $e->getMessage());
                }

            }
            else {
                $usedProducts = unserialize($usedProducts);
            }

            $this->getProduct($product)->setData($this->_usedProducts, $usedProducts);
        }
        Varien_Profiler::stop('CONFIGURABLE:' . __METHOD__);

        return $this->getProduct($product)->getData($this->_usedProducts);
    }

    /**
     * Retrieve configurable attributes data
     *
     * @param  Mage_Catalog_Model_Product $product
     *
     * @return array
     */
    public function getConfigurableAttributes($product = null) {
        Varien_Profiler::start('CONFIGURABLE:' . __METHOD__);

        if (!$this->getProduct($product)->hasData($this->_configurableAttributes)) {
            $configurableAttributes = array();
            if (!Mage::app()->getStore()->isAdmin() && $product) {
                $locale = Mage::app()->getLocale();
                $cacheKeyData = array(
                    self::$_cacheArray['CONF_ATTR_CACHE_KEY'],
                    $locale->getLocaleCode(),
                    $product->getId()
                );
                $cacheId = implode('_', $cacheKeyData);
                $configurableAttributes = Mage::app()->loadCache($cacheId);
            }

            if (empty($configurableAttributes)) {
                $collection = $this->getConfigurableAttributeCollection($product)
                                   ->orderByPosition()
                                   ->load();

                foreach ($collection as $item) {
                    $configurableAttributes[] = $item;
                }

                try {
                    if (!Mage::app()->getStore()->isAdmin() && $product) {
                        Mage::app()->saveCache(
                            serialize($configurableAttributes),
                            $cacheId,
                            array(self::$_cacheArray['CONF_ATTR_CACHE_TAG']),
                            self::$_cacheArray['CONF_ATTR_CACHE_LIFETIME']
                        );
                    }
                }
                catch (Exception $e) {
                    Mage::log('error while saving to the cache: ' . $e->getMessage());
                }
            }
            else {
                $configurableAttributes = unserialize($configurableAttributes);
            }
            $this->getProduct($product)->setData($this->_configurableAttributes, $configurableAttributes);
        }

        Varien_Profiler::stop('CONFIGURABLE:' . __METHOD__);

        return $this->getProduct($product)->getData($this->_configurableAttributes);
    }
}