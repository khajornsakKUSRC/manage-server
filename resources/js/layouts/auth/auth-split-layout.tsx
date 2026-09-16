import { Link, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { bp } from '@/lib/base-path';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative flex min-h-dvh items-center justify-center overflow-hidden bg-background p-4 sm:p-6">
            {/* Decorative backdrop — a faint dot mesh plus two soft blurred
                blobs, same idea as the app's own background tint, just
                turned up for a page with no sidebar/header to anchor it. */}
            <div className="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
                <div
                    className="absolute inset-0 text-primary opacity-[0.05]"
                    style={{
                        backgroundImage:
                            'radial-gradient(currentColor 1px, transparent 1px)',
                        backgroundSize: '26px 26px',
                    }}
                />
                <div className="absolute -top-[10%] -left-[10%] h-[50%] w-[50%] rounded-full bg-primary/10 blur-[120px]" />
                <div className="absolute -right-[10%] -bottom-[20%] h-[60%] w-[60%] rounded-full bg-primary/15 blur-[150px]" />
            </div>

            <div className="relative flex w-full max-w-5xl flex-col overflow-hidden rounded-[2rem] border border-border/60 bg-card shadow-2xl md:flex-row">
                {/* Left: branding */}
                <div className="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-primary to-primary/70 p-10 text-primary-foreground md:flex md:w-1/2">
                    <div className="absolute top-[-10%] right-[-10%] h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div className="absolute bottom-[-5%] left-[-5%] h-48 w-48 rounded-full bg-black/10 blur-2xl" />

                    <Link
                        href={home()}
                        className="relative z-10 flex items-center gap-2 text-lg font-semibold"
                    >
                        <img
                            src={bp('/image/manage-server-logo.png')}
                            alt={name}
                            className="size-8 rounded-md bg-white/90 object-contain p-1"
                        />
                        {name}
                    </Link>

                    <div className="relative z-10">
                        <h1 className="mb-3 text-3xl leading-tight font-extrabold text-balance">
                            Monitor Server KUSRC
                        </h1>
                        <p className="text-lg font-medium text-primary-foreground/80">
                            Server &amp; Infrastructure Management
                        </p>
                    </div>

                    <div className="relative z-10 flex items-center gap-3 text-sm text-primary-foreground/75">
                        <ShieldCheck className="size-5 shrink-0" />
                        <span>สำหรับเจ้าหน้าที่ที่ได้รับอนุญาตเท่านั้น</span>
                    </div>
                </div>

                {/* Right: form */}
                <div className="flex w-full flex-col justify-center p-8 sm:p-10 md:w-1/2 md:p-12">
                    <Link
                        href={home()}
                        className="mb-6 flex items-center justify-center md:hidden"
                    >
                        <img
                            src={bp('/image/manage-server-logo.png')}
                            alt={name}
                            className="h-12 object-contain"
                        />
                    </Link>

                    <div className="mb-8 text-center md:text-left">
                        <h2 className="text-2xl font-bold">{title}</h2>
                        <p className="mt-1.5 text-sm text-balance text-muted-foreground">
                            {description}
                        </p>
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}
