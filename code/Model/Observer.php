<?php

/**
 * @category Compamy
 * @package Compamy_Performance
 */
class Company_CatalogRule_Model_Observer extends Mage_CatalogRule_Model_Observer
{
    /**
     * @var array
     */
    protected $_preloadedPrices = array();

    /**
     * @param Varien_Event_Observer $observer
     */
    public function beforeCollectTotals(Varien_Event_Observer $observer) {
        $quote = $observer->getQuote();
        $date = Mage::app()->getLocale()->storeTimeStamp($quote->getStoreId());
        $websiteId = $quote->getStore()->getWebsiteId();
        $groupId = $quote->getCustomerGroupId();

        $productIds = array();
        foreach ($quote->getAllItems() as $item) {
            $productIds[] = $item->getProductId();
        }

        $cacheKey = spl_object_hash($quote);

        if (!isset($this->_preloadedPrices[$cacheKey])) {
            $this->_preloadedPrices[$cacheKey] = Mage::getResourceSingleton('catalogrule/rule')
                                                     ->getRulePrices($date, $websiteId, $groupId, $productIds);
        }

        foreach ($this->_preloadedPrices[$cacheKey] as $productId => $price) {
            $key = implode('|', array($date, $websiteId, $groupId, $productId));
            $this->_rulePrices[$key] = $price;
        }
    }
} 