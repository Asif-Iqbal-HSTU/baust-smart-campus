import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { create, status } from '@/routes/apply';

/**
 * Header and footer for the public admission pages.
 */
export default function PublicShell({ children }: { children: ReactNode }) {
    const { name } = usePage().props;

    return (
        <div className="flex min-h-screen flex-col bg-muted/40">
            <header className="sticky top-0 z-40 border-b bg-background">
                <div className="mx-auto flex h-16 max-w-5xl items-center justify-between gap-2 px-4">
                    <Link href={home()} className="flex items-center gap-2">
                        <span className="flex size-8 items-center justify-center rounded-md bg-brand text-brand-foreground">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        <span className="font-semibold">{name}</span>
                    </Link>
                    <nav className="flex items-center gap-1">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={create()}>Apply</Link>
                        </Button>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={status()}>Check status</Link>
                        </Button>
                    </nav>
                </div>
            </header>
            <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
                {children}
            </main>
            <footer className="border-t bg-background">
                <div className="mx-auto max-w-5xl px-4 py-6 text-sm text-muted-foreground">
                    Bangladesh Army University of Science and Technology
                    (BAUST), Saidpur Cantonment.
                </div>
            </footer>
        </div>
    );
}
