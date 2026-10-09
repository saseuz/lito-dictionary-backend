import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    ManagementPage,
    Pagination,
    DeleteConfirmation,
} from '@/components/admin/management';
import * as routes from '@/routes/admin/admins';
import type { Page } from '@/types/admin-management';

type Row = {
    id: string;
    name: string;
    email: string;
    role: string | null;
    can_edit: boolean;
    can_delete: boolean;
};
export default function AdminIndex({
    admins,
    search,
}: {
    admins: Page<Row>;
    search: string;
}) {
    const { capabilities } = usePage().props;
    const [query, setQuery] = useState(search);
    return (
        <>
            <Head title="Admins" />
            <ManagementPage
                title="Admins"
                description="Manage administrator accounts and their roles."
                action={
                    capabilities['admins.create'] &&
                    capabilities['admins.assign-role'] && (
                        <Button asChild>
                            <Link href={routes.create()}>Create Admin</Link>
                        </Button>
                    )
                }
            >
                <form
                    className="flex max-w-lg gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(
                            routes.index.url(),
                            { search: query },
                            { preserveState: true },
                        );
                    }}
                >
                    <Input
                        aria-label="Search Admins by name or email"
                        placeholder="Search name or email"
                        value={query}
                        maxLength={255}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                    <Button variant="outline">Search</Button>
                </form>
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                {['Name', 'Email', 'Role', 'Actions'].map(
                                    (h) => (
                                        <th key={h} className="p-4 font-medium">
                                            {h}
                                        </th>
                                    ),
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {admins.data.map((admin) => (
                                <tr key={admin.id} className="border-t">
                                    <td className="p-4 font-medium">
                                        {admin.name}
                                    </td>
                                    <td className="p-4">{admin.email}</td>
                                    <td className="p-4">
                                        {admin.role ?? 'No role assigned'}
                                    </td>
                                    <td className="p-4">
                                        <div className="flex gap-2">
                                            {admin.can_edit && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={routes.edit(
                                                            admin.id,
                                                        )}
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                            )}
                                            {admin.can_delete && (
                                                <DeleteConfirmation
                                                    url={routes.destroy.url(
                                                        admin.id,
                                                    )}
                                                    name={admin.email}
                                                />
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {admins.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="p-10 text-center text-muted-foreground"
                                    >
                                        No Admins match your search.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination page={admins} />
            </ManagementPage>
        </>
    );
}
AdminIndex.layout = {
    breadcrumbs: [{ title: 'Admins', href: routes.index() }],
};
