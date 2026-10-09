import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    ManagementPage,
    Pagination,
    DeleteConfirmation,
} from '@/components/admin/management';
import * as routes from '@/routes/admin/roles';
import type { Page } from '@/types/admin-management';

type Row = {
    id: number;
    name: string;
    permissions_count: number;
    can_edit: boolean;
    can_delete: boolean;
};
export default function RoleIndex({ roles }: { roles: Page<Row> }) {
    const { capabilities } = usePage().props;
    return (
        <>
            <Head title="Roles" />
            <ManagementPage
                title="Roles"
                description="Manage the permissions assigned to each administrator role."
                action={
                    capabilities['roles.create'] && (
                        <Button asChild>
                            <Link href={routes.create()}>Create role</Link>
                        </Button>
                    )
                }
            >
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                {['Role', 'Permissions', 'Actions'].map((h) => (
                                    <th key={h} className="p-4 font-medium">
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {roles.data.map((role) => (
                                <tr key={role.id} className="border-t">
                                    <td className="p-4 font-medium">
                                        {role.name}
                                        {role.name === 'super-admin' && (
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                Protected
                                            </span>
                                        )}
                                    </td>
                                    <td className="p-4">
                                        {role.name === 'super-admin'
                                            ? 'All abilities (super-admin)'
                                            : role.permissions_count}
                                    </td>
                                    <td className="p-4">
                                        <div className="flex gap-2">
                                            {role.can_edit && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={routes.edit(
                                                            role.id,
                                                        )}
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                            )}
                                            {role.can_delete && (
                                                <DeleteConfirmation
                                                    url={routes.destroy.url(
                                                        role.id,
                                                    )}
                                                    name={role.name}
                                                />
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {roles.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={3}
                                        className="p-10 text-center text-muted-foreground"
                                    >
                                        No roles are available.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination page={roles} />
            </ManagementPage>
        </>
    );
}
RoleIndex.layout = { breadcrumbs: [{ title: 'Roles', href: routes.index() }] };
