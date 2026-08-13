import { createFormPage } from '@/Components/ResourcePage';

export default createFormPage({
  title: 'Edit Product or Service',
  submitPath: (item) => `/product-service/${item.id}`,
  method: 'put',
  recordKey: 'item',
  backPath: '/product-service',
  fields: [
    { name: 'name', label: 'Name', required: true },
    { name: 'sku', label: 'SKU' },
    { name: 'barcode', label: 'Barcode' },
    { name: 'type', label: 'Type', type: 'select', required: true, optionsProp: 'itemTypes', optionValue: 'value', optionLabel: 'label' },
    { name: 'sale_price', label: 'Sale price', type: 'number', required: true },
    { name: 'purchase_price', label: 'Purchase price', type: 'number', required: true },
    { name: 'reorder_level', label: 'Reorder level', type: 'number' },
    { name: 'unit', label: 'Unit' },
    { name: 'category_id', label: 'Category', type: 'select', optionsProp: 'categories' },
    { name: 'description', label: 'Description', type: 'textarea' },
    { name: 'is_active', label: 'Active', type: 'checkbox' },
  ],
});
