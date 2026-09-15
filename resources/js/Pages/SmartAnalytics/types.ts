

/* Executive Overview */
export interface RevenueTrendItem {
  label: string;
  value: number;
  formattedValue: string;
}

export interface KpiCards {
  revenue: { current: number; previous: number; growth: number; trend: RevenueTrendItem[] };
  profit: { revenue: number; expenses: number; net: number; margin: number };
  employees: { active: number; new_hires: number };
  projects: { active: number; completed: number };
  sales_pipeline: { active_leads: number; pipeline_value: number; conversion_rate: number };
}

export interface QuickInsight {
  type: 'critical' | 'warning' | 'info' | 'positive';
  title: string;
  message: string;
  module: string;
}

export interface ModuleSummaries {
  hrm: { total_employees: number; new_hires: number };
  crm: { active_leads: number; pipeline_value: number; conversion_rate: number };
  account: { revenue: number; expenses: number; net: number };
  projects: { active_projects: number; completed_projects: number };
}

export interface TopCustomer {
  id: number;
  name: string;
  email: string;
  balance: number;
  status: string;
}

export interface RecentTransaction {
  id: number;
  date: string;
  type: string;
  amount: number;
  reference: string;
  status: string;
}

export interface OverviewData {
  kpi_cards: KpiCards;
  quick_insights: QuickInsight[];
  module_summaries: ModuleSummaries;
  top_customers: TopCustomer[];
  recent_transactions: RecentTransaction[];
}

/* Financial Analytics */
export interface ChartItem {
  label: string;
  value: number;
  formattedValue?: string;
  secondaryValue?: number;
  secondaryFormattedValue?: string;
}

export interface DistributionItem {
  name: string;
  value: number;
  formattedValue?: string;
}

export interface FinancialTransaction {
  id: number;
  date: string;
  type: string;
  category: string;
  amount: number;
  reference: string;
  payment_method: string;
}

export interface FinancialData {
  revenue_analysis: {
    current: number;
    previous: number;
    growth: number;
    categories: DistributionItem[];
    trend: ChartItem[];
  };
  expense_analysis: {
    current: number;
    previous: number;
    growth: number;
    categories: DistributionItem[];
  };
  profitability: {
    net_profit: number;
    margin: number;
    trend: ChartItem[];
  };
  cash_flow: { inflow: number; outflow: number; net: number };
  transactions: FinancialTransaction[];
}

/* Team Performance */
export interface TeamOverview {
  total_employees: number;
  active_employees: number;
  new_hires: number;
  tasks_completed_month: number;
  attendance_rate: number;
}

export interface TeamEmployee {
  id: number;
  name: string;
  employee_number: string;
  department: string;
  designation: string;
  tasks_completed: number;
  status: string;
}

export interface TeamData {
  overview: TeamOverview;
  departments: DistributionItem[];
  attendance: { present: number; late: number; absent: number; rate: number };
  top_performers: TeamEmployee[];
}

/* Sales Analytics */
export interface SalesKpis {
  pipeline_value: number;
  won_value: number;
  total_deals: number;
  open_deals: number;
  won_deals: number;
  win_rate: number;
  avg_deal_size: number;
  total_leads: number;
  open_leads: number;
  conversion_rate: number;
}

export interface SalesDeal {
  id: number;
  name: string;
  value: number;
  status: string;
  expected_close_date: string;
  created_at: string;
}

export interface SalesLead {
  id: number;
  name: string;
  email: string;
  status: string;
  estimated_value: number;
  created_at: string;
}

export interface SalesData {
  kpis: SalesKpis;
  pipeline_funnel: DistributionItem[];
  monthly_trend: ChartItem[];
  recent_deals: SalesDeal[];
  recent_leads: SalesLead[];
}

/* Operational Analytics */
export interface ProjectMetrics {
  total: number;
  active: number;
  completed: number;
  total_budget: number;
}

export interface TaskMetrics {
  total: number;
  completed: number;
  open: number;
  overdue: number;
  completion_rate: number;
}

export interface OverdueTask {
  id: number;
  title: string;
  project_name: string;
  priority: string;
  due_on: string;
}

export interface ActiveProject {
  id: number;
  name: string;
  status: string;
  budget: number;
  total_tasks: number;
  completed_tasks: number;
  progress: number;
  due_on: string;
}

export interface OperationsData {
  project_metrics: ProjectMetrics;
  task_metrics: TaskMetrics;
  priority_distribution: DistributionItem[];
  overdue_tasks: OverdueTask[];
  active_projects: ActiveProject[];
}
