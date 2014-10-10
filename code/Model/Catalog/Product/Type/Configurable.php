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
    public static function init() {
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
        self::init();
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
                if ($collection->getSize()) {
                    $salable = true;
                }
            } else {
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
                $locale       = Mage::app()->getLocale();
                $cacheKeyData = array(
                    self::$_cacheArray['USED_PRODUCTS_CACHE_KEY'],
                    $locale->getLocaleCode(),
                    $product->getId()
                );
                $cacheId      = implode('_', $cacheKeyData);
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
                            urlencode(serialize($usedProducts)),
                            $cacheId,
                            array((string) self::$_cacheArray['USED_PRODUCTS_CACHE_TAG']),
                            (string) self::$_cacheArray['USED_PRODUCTS_CACHE_LIFETIME']
                        );
                    }
                }
                catch (Exception $e) {
                    Mage::log('error while saving used products to cache: ' . $e->getMessage());
                }

            } else {
                $usedProducts = unserialize(urldecode($usedProducts));
            }

            $this->getProduct($product)->setData($this->_usedProducts, $usedProducts);
        }

        Varien_Profiler::stop('CONFIGURABLE:' . __METHOD__);

        return $this->getProduct($product)->getData($this->_usedProducts);
    }
}