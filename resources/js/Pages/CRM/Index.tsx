import ModuleDashboard from '@/Components/ModuleDashboard';

export default function CRMIndex() {
  return <ModuleDashboard title="CRM & Leads" description="Live workspace pipeline, lead, deal, and conversion performance." metricLabels={{ leads: 'Leads', open_leads: 'Open Leads', deals: 'Deals', pipeline_value: 'Pipeline Value', won_value: 'Won Value', conversion_rate: 'Conversion Rate' }} collections={[{ key: 'leads', title: 'Recent Leads', columns: ['name', 'company', 'email', 'status', 'estimated_value'] }, { key: 'deals', title: 'Recent Deals', columns: ['name', 'value', 'status', 'expected_close_date'] }, { key: 'pipelines', title: 'Pipelines', columns: ['name', 'is_default'] }]} />;
}
