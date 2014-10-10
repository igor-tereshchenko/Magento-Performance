Magento-Performance
===================

This is the module for Magento. Its main goal is to boost performance for large stores with a huge amount of
configurable products with a tons of attributes. It uses Magento's cache for storing one time defined variables
and other optimized techniques which lead Magento to be more faster and kind :)

Files description:

Model/Catalog/Product/Type/Configurable.php:
    - isSalable(): search for the first enabled child product instead of using getUsedProducts() method;
    - getUsedProducts(): added cache support;
    
Model/Eav/Entity/Attribute/Source/Table.php:
    - getAllOptions(): on the first call it will preload all attribute values for current store into array format.
      It use collection getData() method to retrieve an array of items instead of collection of objects.
  
Model/Resource/Catalog/Product/Type/Configurable/Attribute/Collection.php:
    - _loadPrices(): return prices from the static variable instead of running _loadPrices() in the loop.
    
Model/CatalogRule/Observer.php:
    - beforeCollectTotals(): pushing results per quote object into the local cache.