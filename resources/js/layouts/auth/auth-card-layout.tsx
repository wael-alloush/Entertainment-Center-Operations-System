import { SidebarBrandIcon } from '@/components/app-logo';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/react';

export default function AuthCardLayout({
    children,
    title,
    description,
}: {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}) {
    return (
        <div className="raad-page-bg flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div className="flex w-full max-w-md flex-col gap-6">
                <Link href={route('login')} className="flex flex-col items-center gap-3 self-center">
                    <SidebarBrandIcon />
                    <div className="text-center">
                        <span className="text-2xl font-bold">رعد</span>
                        <p className="text-muted-foreground text-sm">نظام إدارة المركز</p>
                    </div>
                </Link>

                <Card className="raad-glass rounded-2xl border-border/60 shadow-xl">
                    <CardHeader className="px-8 pt-8 pb-0 text-center">
                        <CardTitle className="text-xl">{title}</CardTitle>
                        <CardDescription>{description}</CardDescription>
                    </CardHeader>
                    <CardContent className="px-8 py-8">{children}</CardContent>
                </Card>
            </div>
        </div>
    );
}
