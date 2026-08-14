import ModuleDashboard from '@/Components/ModuleDashboard';

export default function POSIndex() {
  return <ModuleDashboard title="Point of Sale" description="Live register, order, payment, revenue, and stock-alert activity." metricLabels={{ today_orders: "Today's Orders", today_revenue: "Today's Revenue", open_registers: 'Open Registers', low_stock: 'Low Stock' }} collections={[{ key: 'orders', title: 'Recent Orders', columns: ['receipt_number', 'status', 'payment_method', 'grand_total', 'created_at'] }, { key: 'registers', title: 'Registers', columns: ['name', 'is_active', 'warehouse_id'] }, { key: 'sessions', title: 'Register Sessions', columns: ['status', 'opening_cash', 'closing_cash', 'opened_at', 'closed_at'] }]} />;
}
