import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Shield, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface RoleRow {
    id: number;
    name: string;
    description: string | null;
    color: string;
    users_count: number;
}

const DEFAULT_COLOR = '#64748b';

export default function Roles({ roles }: { roles: RoleRow[] }) {
    const [editing, setEditing] = useState<RoleRow | null>(null);
    const [open, setOpen] = useState(false);

    const { data, setData, reset, clearErrors, errors, processing, post, put } =
        useForm({
            name: '',
            description: '',
            color: DEFAULT_COLOR,
        });

    const openCreate = () => {
        setEditing(null);
        reset();
        clearErrors();
        setData('color', DEFAULT_COLOR);
        setOpen(true);
    };

    const openEdit = (role: RoleRow) => {
        setEditing(role);
        clearErrors();
        setData({
            name: role.name,
            description: role.description ?? '',
            color: role.color,
        });
        setOpen(true);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        const opts = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        };

        if (editing) {
            put(`/users/roles/${editing.id}`, opts);
        } else {
            post('/users/roles', opts);
        }
    };

    const remove = (role: RoleRow) => {
        const warn =
            role.users_count > 0
                ? `\n\n${role.users_count} user${role.users_count === 1 ? '' : 's'} will lose this label (they keep all page access).`
                : '';

        if (window.confirm(`Delete the "${role.name}" role?${warn}`)) {
            router.delete(`/users/roles/${role.id}`, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Manage Roles" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Manage Roles</h1>
                        <p className="text-sm text-muted-foreground">
                            Labels for what a user manages — Network, Server, IP
                            Phone, and so on. Roles don&apos;t grant access;
                            that stays with the Permission Menu on each user.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/users">Back to Users</Link>
                        </Button>
                        <Button onClick={openCreate}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Role
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-teal-100 p-2 dark:bg-teal-900/30">
                            <Shield className="h-4 w-4 text-teal-600 dark:text-teal-400" />
                        </div>
                        <CardTitle>All Roles ({roles.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {roles.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No roles yet. Every user is a general user
                                until you add one.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">
                                                Role
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Description
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Users
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {roles.map((role) => (
                                            <tr
                                                key={role.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3">
                                                    <span className="inline-flex items-center gap-2 font-medium">
                                                        <span
                                                            className="h-2.5 w-2.5 rounded-full"
                                                            style={{
                                                                backgroundColor:
                                                                    role.color,
                                                            }}
                                                        />
                                                        {role.name}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {role.description || '—'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {role.users_count}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="mr-2"
                                                        onClick={() =>
                                                            openEdit(role)
                                                        }
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            remove(role)
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit Role' : 'Add Role'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="role-name">
                                Name <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="role-name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="e.g. Network"
                                required
                            />
                            {errors.name && (
                                <p className="text-sm text-red-500">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="role-description">
                                Description
                            </Label>
                            <Input
                                id="role-description"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                placeholder="What this person looks after"
                            />
                            {errors.description && (
                                <p className="text-sm text-red-500">
                                    {errors.description}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="role-color">Badge colour</Label>
                            <div className="flex items-center gap-2">
                                <input
                                    id="role-color"
                                    type="color"
                                    className="h-9 w-10 shrink-0 cursor-pointer rounded border border-input bg-transparent p-1"
                                    value={data.color}
                                    onChange={(e) =>
                                        setData('color', e.target.value)
                                    }
                                />
                                <Input
                                    aria-label="Badge colour hex"
                                    className="font-mono"
                                    value={data.color}
                                    onChange={(e) =>
                                        setData('color', e.target.value)
                                    }
                                    placeholder="#64748b"
                                />
                            </div>
                            {errors.color && (
                                <p className="text-sm text-red-500">
                                    {errors.color}
                                </p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {editing ? 'Save changes' : 'Add role'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
