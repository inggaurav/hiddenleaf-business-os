export interface SuggestionCategoryItem {
  id: number;
  name: string;
  color: string;
  description?: string | null;
  is_active: boolean;
  display_order: number;
  suggestions_count?: number;
}

export interface SuggestionStatusHistoryItem {
  id: number;
  suggestion_id: number;
  old_status: string;
  new_status: string;
  comment?: string | null;
  created_at: string;
  changed_by?: {
    id: number;
    name: string;
  } | null;
}

export interface SuggestionItem {
  id: number;
  title: string;
  description: string;
  category_id?: number | null;
  status: 'new' | 'under_review' | 'accepted' | 'rejected' | 'complete';
  user_id?: number | null;
  is_anonymous: boolean;
  author_display?: string;
  votes_count: number;
  views_count: number;
  admin_response?: string | null;
  responded_by?: {
    id: number;
    name: string;
  } | null;
  responded_at?: string | null;
  created_at: string;
  updated_at: string;
  category?: SuggestionCategoryItem | null;
  user?: {
    id: number;
    name: string;
  } | null;
  has_voted?: boolean;
  status_histories?: SuggestionStatusHistoryItem[];
}
