import { createIndexPage } from '@/Components/ResourcePage';

export default createIndexPage({
  title: 'Tax Rates',
  collectionKey: 'taxes',
  resourcePath: '/product-service/taxes',
  columns: ['name', 'rate', 'type', 'description'],
});
