<?php

/**
 * Class Company_Performance_Helper_Data
 *
 * @category Company
 * @package Company_Performance
 */
class Company_Performance_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @param null $cacheKey
     * @param null $product
     */
    public function removeCache($cacheKey = null, $product = null) {
        if (isset($cacheKey) && isset($product)) {
            $locale = Mage::app()->getLocale();
            $cacheUsedProductsData = array(
                $cacheKey,
                $locale->getLocaleCode(),
                $product->getId()
            );
            $cacheId = implode('_', $cacheUsedProductsData);
            Mage::app()->removeCache($cacheId);
        }
        else {
            return;
        }
    }
}