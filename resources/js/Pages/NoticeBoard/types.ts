export interface NoticeTarget {
  id: number;
  notice_id: number;
  target_type: string;
  department_id?: number | null;
  role_id?: number | null;
  user_id?: number | null;
}

export interface NoticeCommentItem {
  id: number;
  notice_id: number;
  user_id: number;
  parent_id?: number | null;
  comment: string;
  created_at: string;
  user?: {
    id: number;
    name: string;
    avatar?: string;
  };
  replies?: NoticeCommentItem[];
}

export interface NoticeItem {
  id: number;
  title: string;
  description?: string | null;
  attachments?: string[] | null;
  start_date: string;
  expiry_date?: string | null;
  is_pinned: boolean;
  priority: 'normal' | 'urgent' | 'critical';
  require_acknowledgment: boolean;
  target_type: 'all' | 'department' | 'role' | 'specific_users';
  target_ids?: number[];
  allow_comments: boolean;
  status: 'draft' | 'published' | 'deactivated';
  creator_id?: number | null;
  created_at: string;
  updated_at: string;
  creator?: {
    id: number;
    name: string;
  };
  targets?: NoticeTarget[];
  reads_count?: number;
  comments_count?: number;
}
