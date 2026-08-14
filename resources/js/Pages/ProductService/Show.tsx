import { createShowPage } from '@/Components/ResourcePage';

export default createShowPage({
  title: 'Product / Service Detail',
  recordKey: 'item',
  backPath: '/product-service',
  editPath: (record) => `/product-service/${record.id}/edit`,
});
