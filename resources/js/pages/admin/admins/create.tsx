import { Head } from '@inertiajs/react';
import { ManagementPage } from '@/components/admin/management';
import AdminForm from '@/components/admin/admin-form';
import type { AdminRecord, RoleOption } from '@/types/admin-management';
import { index } from '@/routes/admin/admins';

export default function Page(props: {
    admin?: AdminRecord;
    roles: RoleOption[];
}) {
    return (
        <>
            <Head title="Create Admin" />
            <ManagementPage
                title="Create Admin"
                description="Create an administrator with one role."
            >
                <AdminForm {...props} />
            </ManagementPage>
        </>
    );
}
Page.layout = {
    breadcrumbs: [
        { title: 'Admins', href: index() },
        { title: 'Create Admin', href: '#' },
    ],
};
