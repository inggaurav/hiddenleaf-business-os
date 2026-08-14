import { createIndexPage } from '@/Components/ResourcePage';

export default createIndexPage({
  title: 'Units of Measure',
  collectionKey: 'units',
  resourcePath: '/product-service/units',
  columns: ['name', 'abbreviation', 'description'],
});
