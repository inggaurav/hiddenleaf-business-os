export interface HealthScore {
  score: number;
  financial_score: number;
  team_score: number;
  sales_score: number;
  project_score: number;
  operations_score: number;
}

export interface Insight {
  title: string;
  description: string;
  severity: 'positive' | 'info' | 'warning' | 'critical';
  module: string;
}

export interface Recommendation {
  recommendation: string;
  reason: string;
  priority: 'high' | 'medium' | 'low';
  related_module: string;
}

export interface Alert {
  title: string;
  message: string;
  severity: 'warning' | 'critical';
  module: string;
}

export interface AdvisorData {
  health_score: HealthScore;
  metrics: Record<string, unknown>;
  insights: Insight[];
  recommendations: Recommendation[];
  alerts: Alert[];
  last_analysis: string | null;
}

export interface ScoreHistory {
  id: number;
  scored_on: string;
  overall: number;
  financial: number;
  team: number;
  sales: number;
  project: number;
}
