import { Head } from '@inertiajs/react';
import { ManagementPage } from '@/components/admin/management';
import RoleForm from '@/components/admin/role-form';
import type { RoleFormProps } from '@/components/admin/role-form';
import { index } from '@/routes/admin/roles';

export default function Page(props: RoleFormProps) {
    return (
        <>
            <Head title="Edit role" />
            <ManagementPage
                title="Edit role"
                description="Choose a role name and its permissions."
            >
                <RoleForm {...props} />
            </ManagementPage>
        </>
    );
}
Page.layout = {
    breadcrumbs: [
        { title: 'Roles', href: index() },
        { title: 'Edit role', href: '#' },
    ],
};
