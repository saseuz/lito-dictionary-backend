import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import type { Page } from '@/types/admin-management';

export function ManagementPage({
    title,
    description,
    action,
    children,
}: {
    title: string;
    description: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <main className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <header className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
                {action}
            </header>
            {children}
        </main>
    );
}
export function Errors({ errors }: { errors: Record<string, string> }) {
    return Object.keys(errors).length ? (
        <div
            role="alert"
            className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive"
        >
            {Object.entries(errors).map(([key, message]) => (
                <p key={key}>{message}</p>
            ))}
        </div>
    ) : null;
}
export function Pagination({ page }: { page: Omit<Page<unknown>, 'data'> }) {
    return (
        <nav
            aria-label="Pagination"
            className="flex flex-wrap items-center justify-between gap-3"
        >
            <p className="text-sm text-muted-foreground">
                {page.total} results · Page {page.current_page} of{' '}
                {page.last_page}
            </p>
            <div className="flex gap-2">
                {page.prev_page_url ? (
                    <Button variant="outline" asChild>
                        <Link href={page.prev_page_url}>Previous</Link>
                    </Button>
                ) : (
                    <Button variant="outline" disabled>
                        Previous
                    </Button>
                )}
                {page.next_page_url ? (
                    <Button variant="outline" asChild>
                        <Link href={page.next_page_url}>Next</Link>
                    </Button>
                ) : (
                    <Button variant="outline" disabled>
                        Next
                    </Button>
                )}
            </div>
        </nav>
    );
}
export function DeleteConfirmation({
    url,
    name,
}: {
    url: string;
    name: string;
}) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    function remove() {
        setBusy(true);
        router.delete(url, {
            data: { confirmation: true },
            preserveScroll: true,
            onError: setErrors,
            onSuccess: () => setOpen(false),
            onFinish: () => setBusy(false),
        });
    }
    return (
        <>
            <Button
                variant="destructive"
                size="sm"
                onClick={() => {
                    setErrors({});
                    setOpen(true);
                }}
            >
                Delete
            </Button>
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (!busy) setOpen(value);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete {name}?</DialogTitle>
                        <DialogDescription>
                            This permanently deletes this record. This action
                            cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <Errors errors={errors} />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={busy}
                            onClick={remove}
                        >
                            {busy ? 'Deleting…' : 'Confirm deletion'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
