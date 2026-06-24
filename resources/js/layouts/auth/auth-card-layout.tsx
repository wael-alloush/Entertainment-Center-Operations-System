import { AuthBrandLogo, useAppBrand } from '@/components/app-logo';
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
    const { name, tagline } = useAppBrand();

    return (
        <div className="raad-page-bg flex min-h-svh flex-col items-center justify-center gap-6 p-4 sm:p-6 md:p-10">
            <div className="flex w-full max-w-md min-w-0 flex-col gap-6">
                <Link href={route('login')} className="flex flex-col items-center gap-3 self-center">
                    <AuthBrandLogo />
                    <div className="text-center">
                        <span className="text-2xl font-bold">{name}</span>
                        <p className="text-muted-foreground text-sm">{tagline}</p>
                    </div>
                </Link>

                <Card className="raad-glass rounded-2xl border-border/60 shadow-xl">
                    <CardHeader className="px-4 pt-6 pb-0 text-center sm:px-8 sm:pt-8">
                        <CardTitle className="text-xl">{title}</CardTitle>
                        <CardDescription>{description}</CardDescription>
                    </CardHeader>
                    <CardContent className="px-4 py-6 sm:px-8 sm:py-8">{children}</CardContent>
                </Card>
            </div>
        </div>
    );
}
