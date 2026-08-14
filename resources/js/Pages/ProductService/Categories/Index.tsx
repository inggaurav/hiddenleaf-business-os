import { createIndexPage } from '@/Components/ResourcePage';

export default createIndexPage({
  title: 'Product Categories',
  collectionKey: 'categories',
  resourcePath: '/product-service/categories',
  columns: ['name', 'color', 'description'],
});
