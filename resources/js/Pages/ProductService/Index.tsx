import { createIndexPage } from '@/Components/ResourcePage';

export default createIndexPage({
  title: 'Products & Services',
  collectionKey: 'items',
  resourcePath: '/product-service',
  columns: ['name', 'sku', 'barcode', 'type', 'sale_price', 'is_active'],
});
