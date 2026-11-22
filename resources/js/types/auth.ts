export type UserRole = 'admin' | 'user' | (string & {});

export type UserAvatar =
    | 'popcorn'
    | 'director'
    | 'robot'
    | 'clapper'
    | 'star'
    | 'reel'
    | (string & {});

export interface AuthProfile {
    id: number;
    name: string;
    username: string | null;
    role: UserRole;
    avatar: UserAvatar;
    is_kids: boolean;
    max_content_rating: string;
    is_admin: boolean;
    has_pin: boolean;
    pin_length: number;
    watch_history_count?: number;
    is_active?: boolean;
    blocked_genres?: string[] | null;
}

export interface AuthState {
    user: AuthProfile | null;
}

export interface SharedPageProps {
    name: string;
    sidebarOpen: boolean;
    auth: AuthState;
    [key: string]: unknown;
}
