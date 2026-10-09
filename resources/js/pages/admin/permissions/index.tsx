import { Head } from '@inertiajs/react';
import { ManagementPage } from '@/components/admin/management';
import { index } from '@/routes/admin/permissions';
import type { PermissionGroup } from '@/types/admin-management';

export default function PermissionIndex({
    groups,
}: {
    groups: PermissionGroup[];
}) {
    return (
        <>
            <Head title="Permission catalog" />
            <ManagementPage
                title="Permission catalog"
                description="Available permissions, grouped by purpose. Assign permissions through roles."
            >
                {groups.length === 0 && (
                    <p className="rounded-xl border p-8 text-muted-foreground">
                        No permissions are available.
                    </p>
                )}
                <div className="grid gap-6 lg:grid-cols-2">
                    {groups.map((group) => (
                        <section
                            key={group.id}
                            className="rounded-xl border p-6"
                        >
                            <h2 className="mb-4 font-semibold">{group.name}</h2>
                            <ul className="space-y-3 text-sm">
                                {group.permissions.map((permission) => (
                                    <li
                                        key={permission.id}
                                        className="rounded-md bg-muted/50 px-3 py-2 font-mono"
                                    >
                                        {permission.name}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            </ManagementPage>
        </>
    );
}
PermissionIndex.layout = {
    breadcrumbs: [{ title: 'Permission catalog', href: index() }],
};
