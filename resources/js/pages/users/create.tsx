import { Head, Link, useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Create({ pages }: { pages: Record<string, string> }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        is_admin: false,
        permissions: Object.keys(pages),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/users');
    };

    const togglePermission = (key: string, checked: boolean) => {
        setData(
            'permissions',
            checked
                ? [...data.permissions, key]
                : data.permissions.filter((p) => p !== key),
        );
    };

    return (
        <>
            <Head title="Add User" />
            <div className="mx-auto flex h-full w-full max-w-2xl flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Add User</h1>
                    <Button variant="outline" asChild>
                        <Link href="/users">Back to Users</Link>
                    </Button>
                </div>

                <Card>
                    <form onSubmit={submit}>
                        <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                            <div className="rounded-lg bg-teal-100 p-2 dark:bg-teal-900/30">
                                <UserPlus className="h-4 w-4 text-teal-600 dark:text-teal-400" />
                            </div>
                            <CardTitle>User Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">
                                    Name <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Jane Doe"
                                    required
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-500">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="email">
                                    Email{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    placeholder="jane@example.com"
                                    required
                                />
                                {errors.email && (
                                    <p className="text-sm text-red-500">
                                        {errors.email}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="password">
                                        Password{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={(e) =>
                                            setData('password', e.target.value)
                                        }
                                        required
                                    />
                                    {errors.password && (
                                        <p className="text-sm text-red-500">
                                            {errors.password}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirm Password{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) =>
                                            setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-2 rounded-lg border p-3">
                                <Checkbox
                                    id="is_admin"
                                    checked={data.is_admin}
                                    onCheckedChange={(checked) =>
                                        setData('is_admin', checked === true)
                                    }
                                />
                                <Label
                                    htmlFor="is_admin"
                                    className="font-normal"
                                >
                                    Administrator{' '}
                                    <span className="text-xs text-muted-foreground">
                                        (full access to every page, including
                                        user management)
                                    </span>
                                </Label>
                            </div>

                            <div className="space-y-2">
                                <Label>Page Access</Label>
                                <p className="text-xs text-muted-foreground">
                                    {data.is_admin
                                        ? 'Administrators have access to every page automatically.'
                                        : 'Choose which pages this user can open.'}
                                </p>
                                <div
                                    className={`grid gap-2 sm:grid-cols-2 ${data.is_admin ? 'opacity-50' : ''}`}
                                >
                                    {Object.entries(pages).map(
                                        ([key, label]) => (
                                            <div
                                                key={key}
                                                className="flex items-center gap-2 rounded-lg border p-3"
                                            >
                                                <Checkbox
                                                    id={`permission-${key}`}
                                                    checked={
                                                        data.is_admin ||
                                                        data.permissions.includes(
                                                            key,
                                                        )
                                                    }
                                                    disabled={data.is_admin}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        togglePermission(
                                                            key,
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                                <Label
                                                    htmlFor={`permission-${key}`}
                                                    className="font-normal"
                                                >
                                                    {label}
                                                </Label>
                                            </div>
                                        ),
                                    )}
                                </div>
                                {errors.permissions && (
                                    <p className="text-sm text-red-500">
                                        {errors.permissions}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                        <CardFooter>
                            <Button type="submit" disabled={processing}>
                                Create User
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </>
    );
}
