import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    permissions: string[];
    roles: string[];
    managesDepartment: boolean;
    hasWebsiteAssignments: boolean;
    canViewMarketingStatistics: boolean;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    auth: Auth;
    flash: {
        success: string | null;
        error: string | null;
        bulkResults: { id?: number; site_id?: number; site?: string; status: string; error?: string | null }[] | null;
    };
    // Shared on every response by Inertia's base middleware (validation
    // errors, or a manual withErrors() redirect like Google SSO failures) —
    // not something each controller passes explicitly.
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
