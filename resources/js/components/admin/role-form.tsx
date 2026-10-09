import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Errors } from '@/components/admin/management';
import * as roles from '@/routes/admin/roles';
import type { PermissionGroup, RoleRecord } from '@/types/admin-management';

export type RoleFormProps = {
    role?: RoleRecord;
    groups: PermissionGroup[];
    grantablePermissions: string[];
};
export default function RoleForm({
    role,
    groups,
    grantablePermissions,
}: RoleFormProps) {
    const { capabilities } = usePage().props;
    const nameEditable =
        (!role || capabilities['roles.update']) && role?.name !== 'super-admin';
    const permissionsEditable = capabilities['roles.assign-permissions'];
    const form = useForm({
        name: role?.name ?? '',
        permission_ids: role?.permission_ids ?? ([] as number[]),
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => {
            const payload: { name?: string; permission_ids?: number[] } = {};
            if (nameEditable) payload.name = data.name;
            if (permissionsEditable)
                payload.permission_ids = data.permission_ids;
            return payload;
        });
        if (role)
            form.patch(roles.update.url(role.id), { preserveScroll: true });
        else form.post(roles.store.url());
    }
    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <Errors errors={form.errors} />
            <div className="max-w-xl space-y-2">
                <Label htmlFor="role-name">Role name</Label>
                <Input
                    id="role-name"
                    required
                    maxLength={255}
                    value={form.data.name}
                    disabled={!nameEditable}
                    onChange={(e) => form.setData('name', e.target.value)}
                />
                {role?.name === 'super-admin' && (
                    <p className="text-sm text-muted-foreground">
                        This role is protected. Its name cannot be changed.
                    </p>
                )}
            </div>
            {permissionsEditable && (
                <>
                    <div>
                        <h2 className="font-semibold">Permissions</h2>
                        <p className="text-sm text-muted-foreground">
                            Select permissions for this role. You can grant only
                            permissions you possess.
                        </p>
                    </div>
                    {groups.length === 0 && (
                        <p className="rounded-lg border p-4 text-muted-foreground">
                            No permissions are available.
                        </p>
                    )}
                    {groups.map((group) => (
                        <fieldset
                            key={group.id}
                            className="rounded-xl border p-5"
                        >
                            <legend className="px-2 font-medium">
                                {group.name}
                            </legend>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {group.permissions.map((permission) => (
                                    <label
                                        key={permission.id}
                                        className="flex items-center gap-3 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data.permission_ids.includes(
                                                permission.id,
                                            )}
                                            disabled={
                                                !grantablePermissions.includes(
                                                    permission.name,
                                                )
                                            }
                                            onCheckedChange={(checked) =>
                                                form.setData(
                                                    'permission_ids',
                                                    checked
                                                        ? [
                                                              ...form.data
                                                                  .permission_ids,
                                                              permission.id,
                                                          ]
                                                        : form.data.permission_ids.filter(
                                                              (id) =>
                                                                  id !==
                                                                  permission.id,
                                                          ),
                                                )
                                            }
                                        />
                                        <span>{permission.name}</span>
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                    ))}
                </>
            )}
            <div className="flex gap-3">
                <Button disabled={form.processing}>
                    {form.processing
                        ? 'Saving…'
                        : role
                          ? 'Save changes'
                          : 'Create role'}
                </Button>
                {capabilities['roles.view'] && (
                    <Button variant="outline" asChild>
                        <Link href={roles.index()}>Cancel</Link>
                    </Button>
                )}
            </div>
        </form>
    );
}
