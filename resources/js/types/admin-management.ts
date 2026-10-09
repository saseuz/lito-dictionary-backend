export type Capability =
    | 'dictionary.view'
    | 'admins.view'
    | 'admins.create'
    | 'admins.update'
    | 'admins.delete'
    | 'admins.assign-role'
    | 'roles.view'
    | 'roles.create'
    | 'roles.update'
    | 'roles.delete'
    | 'roles.assign-permissions'
    | 'permissions.view';
export type Capabilities = Record<Capability, boolean>;
export type RoleOption = { id: number; name: string };
export type PermissionGroup = {
    id: number;
    name: string;
    permissions: { id: number; name: string }[];
};
export type AdminRecord = {
    id: string;
    name: string;
    email: string;
    role_id: number | null;
};
export type RoleRecord = { id: number; name: string; permission_ids: number[] };
export type Page<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
