import { createIndexPage } from '@/Components/ResourcePage';

export default createIndexPage({
  title: 'Stock Inventory',
  collectionKey: 'stock',
  resourcePath: '/product-service/stock',
  columns: ['product', 'warehouse', 'quantity', 'unit_cost', 'total_cost'],
});
