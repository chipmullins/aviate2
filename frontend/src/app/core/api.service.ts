import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import {
  AppNotification,
  Comment,
  DemoInfo,
  Conversation,
  Counts,
  CursorPage,
  FriendRequest,
  FriendshipStatus,
  Me,
  Message,
  Page,
  Post,
  Profile,
  SearchResult,
  Trend,
  User,
} from './models';

type Data<T> = { data: T };
type StatusResponse = { friendship_status: FriendshipStatus };

/** Thin typed wrapper around every Aviate API endpoint. */
@Injectable({ providedIn: 'root' })
export class Api {
  private http = inject(HttpClient);
  private base = '/api';

  private cursor(cursor?: string | null): HttpParams {
    return cursor ? new HttpParams().set('cursor', cursor) : new HttpParams();
  }

  // ---- Auth & account
  csrf(): Observable<void> {
    return this.http.get<void>('/sanctum/csrf-cookie');
  }
  me(): Observable<Data<Me>> {
    return this.http.get<Data<Me>>(`${this.base}/me`);
  }
  login(body: { email: string; password: string; remember: boolean }): Observable<Data<Me>> {
    return this.http.post<Data<Me>>(`${this.base}/login`, body);
  }
  register(body: Record<string, string>): Observable<Data<Me>> {
    return this.http.post<Data<Me>>(`${this.base}/register`, body);
  }
  logout(): Observable<void> {
    return this.http.post<void>(`${this.base}/logout`, {});
  }
  demo(): Observable<DemoInfo> {
    return this.http.get<DemoInfo>(`${this.base}/demo`);
  }
  demoLogin(username: string): Observable<Data<Me>> {
    return this.http.post<Data<Me>>(`${this.base}/demo/login`, { username });
  }
  allowedDomains(): Observable<Data<string[]>> {
    return this.http.get<Data<string[]>>(`${this.base}/allowed-domains`);
  }
  realtimeConfig(): Observable<{ key: string | null }> {
    return this.http.get<{ key: string | null }>(`${this.base}/realtime-config`);
  }
  counts(): Observable<Counts> {
    return this.http.get<Counts>(`${this.base}/me/counts`);
  }
  updateProfile(body: { first_name: string; last_name: string; email: string }): Observable<Data<Me>> {
    return this.http.put<Data<Me>>(`${this.base}/me/profile`, body);
  }
  updatePassword(body: Record<string, string>): Observable<void> {
    return this.http.put<void>(`${this.base}/me/password`, body);
  }
  uploadAvatar(file: Blob): Observable<Data<Me>> {
    const form = new FormData();
    form.append('avatar', file, 'avatar.png');
    return this.http.post<Data<Me>>(`${this.base}/me/avatar`, form);
  }
  removeAvatar(): Observable<Data<Me>> {
    return this.http.delete<Data<Me>>(`${this.base}/me/avatar`);
  }
  closeAccount(password: string): Observable<void> {
    return this.http.post<void>(`${this.base}/me/close`, { password });
  }

  // ---- Posts
  feed(cursor?: string | null): Observable<CursorPage<Post>> {
    return this.http.get<CursorPage<Post>>(`${this.base}/feed`, { params: this.cursor(cursor) });
  }
  userPosts(username: string, cursor?: string | null): Observable<CursorPage<Post>> {
    return this.http.get<CursorPage<Post>>(`${this.base}/users/${encodeURIComponent(username)}/posts`, {
      params: this.cursor(cursor),
    });
  }
  post(id: number): Observable<Data<Post>> {
    return this.http.get<Data<Post>>(`${this.base}/posts/${id}`);
  }
  createPost(body: string, image: File | null, wall: string | null): Observable<Data<Post>> {
    const form = new FormData();
    if (body) form.append('body', body);
    if (image) form.append('image', image);
    if (wall) form.append('wall', wall);
    return this.http.post<Data<Post>>(`${this.base}/posts`, form);
  }
  deletePost(id: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/posts/${id}`);
  }
  like(id: number): Observable<{ liked: boolean; likes_count: number }> {
    return this.http.post<{ liked: boolean; likes_count: number }>(`${this.base}/posts/${id}/like`, {});
  }
  unlike(id: number): Observable<{ liked: boolean; likes_count: number }> {
    return this.http.delete<{ liked: boolean; likes_count: number }>(`${this.base}/posts/${id}/like`);
  }
  comments(postId: number, cursor?: string | null): Observable<CursorPage<Comment>> {
    return this.http.get<CursorPage<Comment>>(`${this.base}/posts/${postId}/comments`, { params: this.cursor(cursor) });
  }
  addComment(postId: number, body: string): Observable<Data<Comment>> {
    return this.http.post<Data<Comment>>(`${this.base}/posts/${postId}/comments`, { body });
  }
  deleteComment(id: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/comments/${id}`);
  }

  // ---- People
  profile(username: string): Observable<Data<Profile>> {
    return this.http.get<Data<Profile>>(`${this.base}/users/${encodeURIComponent(username)}`);
  }
  friendsOf(username: string, page = 1): Observable<Page<User>> {
    return this.http.get<Page<User>>(`${this.base}/users/${encodeURIComponent(username)}/friends`, {
      params: new HttpParams().set('page', page),
    });
  }
  search(q: string, limit = 20): Observable<Data<SearchResult[]>> {
    return this.http.get<Data<SearchResult[]>>(`${this.base}/search/users`, {
      params: new HttpParams().set('q', q).set('limit', limit),
    });
  }
  trends(): Observable<Data<Trend[]>> {
    return this.http.get<Data<Trend[]>>(`${this.base}/trends`);
  }
  friendRequests(): Observable<Data<FriendRequest[]>> {
    return this.http.get<Data<FriendRequest[]>>(`${this.base}/friend-requests`);
  }
  sendFriendRequest(username: string): Observable<StatusResponse> {
    return this.http.post<StatusResponse>(`${this.base}/users/${encodeURIComponent(username)}/friend-request`, {});
  }
  acceptFriendRequest(username: string): Observable<StatusResponse> {
    return this.http.post<StatusResponse>(`${this.base}/users/${encodeURIComponent(username)}/friend-request/accept`, {});
  }
  cancelFriendRequest(username: string): Observable<StatusResponse> {
    return this.http.delete<StatusResponse>(`${this.base}/users/${encodeURIComponent(username)}/friend-request`);
  }
  unfriend(username: string): Observable<StatusResponse> {
    return this.http.delete<StatusResponse>(`${this.base}/users/${encodeURIComponent(username)}/friend`);
  }

  // ---- Messages
  conversations(): Observable<Data<Conversation[]>> {
    return this.http.get<Data<Conversation[]>>(`${this.base}/conversations`);
  }
  thread(username: string, cursor?: string | null): Observable<CursorPage<Message>> {
    return this.http.get<CursorPage<Message>>(`${this.base}/conversations/${encodeURIComponent(username)}`, {
      params: this.cursor(cursor),
    });
  }
  sendMessage(username: string, body: string): Observable<Data<Message>> {
    return this.http.post<Data<Message>>(`${this.base}/conversations/${encodeURIComponent(username)}`, { body });
  }
  markThreadRead(username: string): Observable<void> {
    return this.http.post<void>(`${this.base}/conversations/${encodeURIComponent(username)}/read`, {});
  }
  deleteMessage(id: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/messages/${id}`);
  }

  // ---- Notifications
  notifications(page = 1): Observable<Page<AppNotification>> {
    return this.http.get<Page<AppNotification>>(`${this.base}/notifications`, { params: new HttpParams().set('page', page) });
  }
  markNotificationRead(id: string): Observable<void> {
    return this.http.post<void>(`${this.base}/notifications/${encodeURIComponent(id)}/read`, {});
  }
  markAllNotificationsRead(): Observable<void> {
    return this.http.post<void>(`${this.base}/notifications/read-all`, {});
  }

  /** Reverb asks here whether we may join a private channel. */
  broadcastingAuth(socketId: string, channelName: string): Observable<{ auth: string }> {
    return this.http.post<{ auth: string }>(`${this.base}/broadcasting/auth`, {
      socket_id: socketId,
      channel_name: channelName,
    });
  }
}
