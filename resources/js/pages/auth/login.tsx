import { Form, Head, usePage } from '@inertiajs/react';
import { ArrowRight, Lock, Mail } from 'lucide-react';
import { useEffect } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { alertMaintenance } from '@/lib/swal';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

// Icon-in-a-box that fills with the brand colour whenever its sibling
// input has focus — the group-focus-within trick, one instance per field.
function InputIcon({ icon: Icon }: { icon: typeof Mail }) {
    return (
        <div className="pointer-events-none absolute top-1/2 left-1.5 flex size-8 -translate-y-1/2 items-center justify-center rounded-lg bg-muted text-muted-foreground transition-colors group-focus-within:bg-primary group-focus-within:text-primary-foreground">
            <Icon className="size-4" />
        </div>
    );
}

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const { siteSettings } = usePage().props;

    useEffect(() => {
        if (
            siteSettings?.maintenance_enabled &&
            siteSettings.maintenance_message
        ) {
            alertMaintenance(siteSettings.maintenance_message);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <>
            <Head title="Log in" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="group grid gap-2">
                                <Label htmlFor="email">อีเมล</Label>
                                <div className="relative">
                                    <InputIcon icon={Mail} />
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="email"
                                        placeholder="email@example.com"
                                        className="h-12 rounded-xl pl-12"
                                    />
                                </div>
                                <InputError message={errors.email} />
                            </div>

                            <div className="group grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">รหัสผ่าน</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            ลืมรหัสผ่าน?
                                        </TextLink>
                                    )}
                                </div>
                                <div className="relative">
                                    <InputIcon icon={Lock} />
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="Password"
                                        className="h-12 rounded-xl pl-12"
                                    />
                                </div>
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">
                                    จดจำการเข้าสู่ระบบ
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 h-12 w-full rounded-xl text-base font-semibold"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                เข้าสู่ระบบ
                                <ArrowRight className="size-4" />
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'ยินดีต้อนรับ',
    description: 'กรุณาเข้าสู่ระบบด้วยบัญชีของคุณ',
};
