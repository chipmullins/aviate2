export interface User {
  id: number;
  username: string;
  first_name: string;
  last_name: string;
  full_name: string;
  avatar_url: string | null;
  airline: string | null;
}

export interface Me extends User {
  email: string;
  joined_at: string;
}

export type FriendshipStatus = 'self' | 'friends' | 'request_sent' | 'request_received' | 'none';

export interface Profile extends User {
  joined_at: string;
  posts_count: number;
  friends_count: number;
  likes_received: number;
  mutual_friends_count: number;
  friendship_status: FriendshipStatus;
}

export interface SearchResult extends User {
  mutual_friends_count: number;
  friendship_status: FriendshipStatus;
}

export interface Post {
  id: number;
  body: string | null;
  image_url: string | null;
  author: User;
  wall_user: User | null;
  likes_count: number;
  comments_count: number;
  liked_by_me: boolean;
  can_delete: boolean;
  created_at: string;
}

export interface Comment {
  id: number;
  post_id: number;
  body: string;
  author: User;
  can_delete: boolean;
  created_at: string;
}

export interface Message {
  id: number;
  sender_id: number;
  recipient_id: number;
  body: string;
  read_at: string | null;
  created_at: string;
}

export interface Conversation {
  user: User;
  last_message: Message;
  unread_count: number;
}

export interface FriendRequest {
  id: number;
  sender: User;
  created_at: string;
}

export type NotificationKind =
  | 'post_liked'
  | 'post_commented'
  | 'wall_post_commented'
  | 'wall_post_created'
  | 'friend_request_received'
  | 'friend_request_accepted';

export interface AppNotification {
  id: string;
  kind: NotificationKind;
  text: string;
  actor: User;
  post_id: number | null;
  read_at: string | null;
  created_at: string;
}

export interface Trend {
  term: string;
  hits: number;
}

export interface Counts {
  friend_requests: number;
  messages: number;
  notifications: number;
}

/** Laravel cursor pagination. */
export interface CursorPage<T> {
  data: T[];
  meta: { next_cursor: string | null; per_page: number };
}

/** Laravel length-aware pagination. */
export interface Page<T> {
  data: T[];
  meta: { current_page: number; last_page: number; total: number };
}

/** Laravel 422 validation error body. */
export interface ValidationErrors {
  message: string;
  errors?: Record<string, string[]>;
}
