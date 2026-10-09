import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import PasswordInput from '@/components/password-input';
import { Errors } from '@/components/admin/management';
import * as admins from '@/routes/admin/admins';
import type { AdminRecord, RoleOption } from '@/types/admin-management';

export default function AdminForm({
    admin,
    roles,
}: {
    admin?: AdminRecord;
    roles: RoleOption[];
}) {
    const { capabilities } = usePage().props;
    const profileEditable = !admin || capabilities['admins.update'];
    const roleEditable = capabilities['admins.assign-role'];
    const form = useForm({
        name: admin?.name ?? '',
        email: admin?.email ?? '',
        password: '',
        password_confirmation: '',
        role_id: admin?.role_id ? String(admin.role_id) : '',
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => {
            const payload: Record<string, string> = {};
            if (profileEditable) {
                payload.name = data.name;
                payload.email = data.email;
            }
            if (roleEditable) payload.role_id = data.role_id;
            if (!admin) {
                payload.password = data.password;
                payload.password_confirmation = data.password_confirmation;
            }
            return payload;
        });
        if (admin)
            form.patch(admins.update.url(admin.id), { preserveScroll: true });
        else
            form.post(admins.store.url(), {
                onFinish: () => form.reset('password', 'password_confirmation'),
            });
    }
    return (
        <form
            onSubmit={submit}
            className="max-w-xl space-y-6 rounded-xl border p-6"
        >
            <Errors errors={form.errors} />
            <div className="space-y-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    autoComplete="name"
                    required
                    maxLength={255}
                    disabled={!profileEditable}
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                />
            </div>
            <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    autoComplete="email"
                    required
                    maxLength={255}
                    disabled={!profileEditable}
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                />
            </div>
            {!admin && (
                <>
                    <div className="space-y-2">
                        <Label htmlFor="password">Password</Label>
                        <PasswordInput
                            id="password"
                            autoComplete="new-password"
                            required
                            minLength={12}
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            Use at least 12 characters.
                        </p>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="password_confirmation">
                            Confirm password
                        </Label>
                        <PasswordInput
                            id="password_confirmation"
                            autoComplete="new-password"
                            required
                            minLength={12}
                            value={form.data.password_confirmation}
                            onChange={(e) =>
                                form.setData(
                                    'password_confirmation',
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                </>
            )}
            {roleEditable && (
                <div className="space-y-2">
                    <Label htmlFor="role">Role</Label>
                    <Select
                        value={form.data.role_id}
                        onValueChange={(value) =>
                            form.setData('role_id', value)
                        }
                    >
                        <SelectTrigger id="role" className="w-full">
                            <SelectValue placeholder="Select one role" />
                        </SelectTrigger>
                        <SelectContent>
                            {roles.map((role) => (
                                <SelectItem
                                    key={role.id}
                                    value={String(role.id)}
                                >
                                    {role.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">
                        Exactly one role. Only roles you can grant are listed.
                    </p>
                    {roles.length === 0 && (
                        <p className="text-sm text-destructive">
                            No assignable roles are available.
                        </p>
                    )}
                </div>
            )}
            <div className="flex gap-3">
                <Button
                    disabled={
                        form.processing ||
                        (roleEditable &&
                            (!form.data.role_id || roles.length === 0))
                    }
                >
                    {form.processing
                        ? 'Saving…'
                        : admin
                          ? 'Save changes'
                          : 'Create Admin'}
                </Button>
                {capabilities['admins.view'] && (
                    <Button variant="outline" asChild>
                        <Link href={admins.index()}>Cancel</Link>
                    </Button>
                )}
            </div>
        </form>
    );
}
