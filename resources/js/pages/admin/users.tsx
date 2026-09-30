import { Head, router } from '@inertiajs/react';
import { MagnifyingGlassIcon } from '@phosphor-icons/react';
import { useMemo, useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { Input, Select } from '@/components/sdpc/input';
import { PageNumbers, usePagination } from '@/components/sdpc/page-numbers';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy as destroyUser } from '@/routes/admin/users';
import { update as updateUserStatus } from '@/routes/admin/users/status';
import type { AdminUserRow } from '@/types/admin';

type Props = {
    users: AdminUserRow[];
};

type RoleFilter = 'all' | 'client' | 'student';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/** How many accounts the roster shows at once. */
const USERS_PER_PAGE = 15;

const ACTION_BUTTON = { fontSize: 12, padding: '4px 10px' } as const;

export default function AdminUsers({ users }: Props) {
    const [query, setQuery] = useState('');
    const [role, setRole] = useState<RoleFilter>('all');
    const [toDelete, setToDelete] = useState<AdminUserRow | null>(null);
    const [deleting, setDeleting] = useState(false);

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return users.filter(
            (user) =>
                (role === 'all' || user.role === role) &&
                (!needle ||
                    user.name.toLowerCase().includes(needle) ||
                    user.email.toLowerCase().includes(needle)),
        );
    }, [users, query, role]);

    /* Fifteen to a page, paged in place: no request and no reload. */
    const { page, pageCount, setPage, pageItems } = usePagination(
        filtered,
        USERS_PER_PAGE,
    );

    /* One button: a deactivated account is reactivated, any other deactivated. */
    const toggleActive = (user: AdminUserRow) =>
        router.patch(
            updateUserStatus.url(user.id),
            {
                status:
                    user.status === 'deactivated' ? 'approved' : 'deactivated',
            },
            { preserveScroll: true, preserveState: true },
        );

    const confirmDelete = () => {
        if (toDelete === null) {
            return;
        }

        router.delete(destroyUser.url(toDelete.id), {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setDeleting(true),
            onFinish: () => {
                setDeleting(false);
                setToDelete(null);
            },
        });
    };

    return (
        <div
            style={{
                maxWidth: 'clamp(1180px, 100vw - 320px, 1600px)',
                margin: '0 auto',
                padding: '30px clamp(16px, 4vw, 32px) 72px',
            }}
        >
            <Head title="User account management" />

            <div
                style={{
                    display: 'flex',
                    alignItems: 'flex-end',
                    gap: 16,
                    marginBottom: 20,
                }}
            >
                <div style={{ marginRight: 'auto' }}>
                    <h3 style={{ margin: 0 }}>User account management</h3>
                    <div style={{ fontSize: 13, color: MUTED(55) }}>
                        Deactivate, reactivate and delete accounts
                    </div>
                </div>

                <div style={{ position: 'relative', width: 240 }}>
                    <MagnifyingGlassIcon
                        style={{
                            position: 'absolute',
                            left: 10,
                            top: 9,
                            fontSize: 15,
                            opacity: 0.45,
                        }}
                    />
                    <Input
                        value={query}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setPage(1);
                        }}
                        placeholder="Search name or email"
                        aria-label="Search users"
                        style={{ paddingLeft: 31 }}
                    />
                </div>

                <Select
                    value={role}
                    onChange={(event) => {
                        setRole(event.target.value as RoleFilter);
                        setPage(1);
                    }}
                    aria-label="Filter by role"
                    data-test="role-filter"
                    style={{ width: 'auto', minWidth: 130 }}
                >
                    <option value="all">All roles</option>
                    <option value="client">Client</option>
                    <option value="student">Student</option>
                </Select>
            </div>

            <div className="card elev-sm" style={{ padding: '14px 6px' }}>
                {/* The roster keeps its columns and scrolls; squeezed into a
                    phone the cells wrap into something unreadable. */}
                <div className="table-wrap">
                    <table className="table">
                        <thead>
                            <tr>
                                <th style={{ paddingLeft: 16 }}>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th
                                    style={{
                                        textAlign: 'right',
                                        paddingRight: 16,
                                    }}
                                >
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        style={{
                                            paddingLeft: 16,
                                            color: MUTED(55),
                                            fontSize: 13,
                                        }}
                                    >
                                        No accounts match.
                                    </td>
                                </tr>
                            )}

                            {pageItems.map((user) => (
                                <tr key={user.id}>
                                    <td style={{ paddingLeft: 16 }}>
                                        <span
                                            style={{
                                                display: 'inline-grid',
                                                placeItems: 'center',
                                                width: 26,
                                                height: 26,
                                                borderRadius: '50%',
                                                overflow: 'hidden',
                                                marginRight: 8,
                                                verticalAlign: 'middle',
                                                background:
                                                    'var(--color-accent-800)',
                                                color: 'var(--color-accent-200)',
                                                fontSize: 11,
                                            }}
                                        >
                                            {user.avatarUrl ? (
                                                <img
                                                    src={user.avatarUrl}
                                                    alt=""
                                                    style={{
                                                        width: '100%',
                                                        height: '100%',
                                                        objectFit: 'cover',
                                                    }}
                                                />
                                            ) : (
                                                user.name
                                                    .charAt(0)
                                                    .toUpperCase()
                                            )}
                                        </span>
                                        {user.name}
                                        {user.credentialStatusLabel && (
                                            <span
                                                className="tag tag-outline"
                                                style={{ marginLeft: 8 }}
                                            >
                                                {user.credentialStatusLabel}
                                            </span>
                                        )}
                                    </td>
                                    <td style={{ color: MUTED(65) }}>
                                        {user.email}
                                    </td>
                                    <td style={{ color: MUTED(65) }}>
                                        {user.roleLabel}
                                    </td>
                                    <td style={{ paddingRight: 16 }}>
                                        <div
                                            style={{
                                                display: 'flex',
                                                gap: 6,
                                                justifyContent: 'flex-end',
                                            }}
                                        >
                                            {/* Administrators cannot change or delete
                                            their own account; the server refuses it too. */}
                                            {user.isSelf ? (
                                                <span
                                                    style={{
                                                        fontSize: 12,
                                                        color: MUTED(45),
                                                    }}
                                                >
                                                    This is you
                                                </span>
                                            ) : (
                                                <>
                                                    <Btn
                                                        variant={
                                                            user.status ===
                                                            'deactivated'
                                                                ? 'primary'
                                                                : 'ghost'
                                                        }
                                                        data-test="toggle-active"
                                                        style={{
                                                            ...ACTION_BUTTON,
                                                            border: `1px solid ${MUTED(18)}`,
                                                        }}
                                                        onClick={() =>
                                                            toggleActive(user)
                                                        }
                                                    >
                                                        {user.status ===
                                                        'deactivated'
                                                            ? 'Reactivate'
                                                            : 'Deactivate'}
                                                    </Btn>
                                                    {user.role !== 'admin' && (
                                                        <Btn
                                                            variant="ghost"
                                                            data-test="delete-user"
                                                            style={{
                                                                ...ACTION_BUTTON,
                                                                border: `1px solid ${MUTED(18)}`,
                                                            }}
                                                            onClick={() =>
                                                                setToDelete(
                                                                    user,
                                                                )
                                                            }
                                                        >
                                                            Delete
                                                        </Btn>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <PageNumbers
                page={page}
                pageCount={pageCount}
                onChange={setPage}
                label="Users"
            />

            {/* Deleting is permanent, so it always asks first. */}
            <Dialog
                open={toDelete !== null}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        setToDelete(null);
                    }
                }}
            >
                <DialogContent data-test="delete-user-confirmation">
                    <DialogHeader>
                        <DialogTitle>Delete account</DialogTitle>
                        <DialogDescription>
                            Are you sure that you want to delete this user
                            account?
                        </DialogDescription>
                    </DialogHeader>

                    {toDelete && (
                        <p className="text-sm text-muted-foreground">
                            {toDelete.name} ({toDelete.email}) and their data
                            will be removed permanently. This cannot be undone.
                        </p>
                    )}

                    <DialogFooter className="gap-3">
                        <DialogClose asChild>
                            <Button variant="secondary" disabled={deleting}>
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            data-test="confirm-delete-user"
                            disabled={deleting}
                            onClick={confirmDelete}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
