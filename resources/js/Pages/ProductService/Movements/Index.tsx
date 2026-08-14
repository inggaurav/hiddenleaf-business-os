import { createIndexPage } from '@/Components/ResourcePage';

export default createIndexPage({
  title: 'Stock Movements',
  collectionKey: 'movements',
  resourcePath: '/inventory/movements',
  columns: ['type', 'direction', 'product', 'warehouse', 'quantity', 'unit_cost', 'total_cost', 'created_at'],
});
