import { Sparkles } from 'lucide-react';
import { ImgHTMLAttributes, useState } from 'react';

import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/** ضع أيقونة مربعة هنا (PNG أو SVG)، مقاس مقترح: 128×128 */
export const LOGO_ICON_PATH = '/images/logo-icon.png';

/** ضع الشعار الكامل هنا (اختياري)، يُستخدم في صفحات الدخول */
export const LOGO_FULL_PATH = '/images/logo.png';

const APP_TAGLINE = 'نظام إدارة المركز';

function LogoImage({
    src,
    alt,
    className,
    ...props
}: ImgHTMLAttributes<HTMLImageElement> & { src: string; alt: string }) {
    const [failed, setFailed] = useState(false);

    if (failed) {
        return (
            <div
                className={cn(
                    'flex shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-violet-600 shadow-md shadow-primary/30',
                    className,
                )}
            >
                <Sparkles className="text-primary-foreground size-5" />
            </div>
        );
    }

    return (
        <img
            src={src}
            alt={alt}
            className={cn('shrink-0 object-contain', className)}
            onError={() => setFailed(true)}
            {...props}
        />
    );
}

export function SidebarBrandIcon() {
    const { name } = usePage<SharedData>().props;

    return (
        <LogoImage
            src={LOGO_ICON_PATH}
            alt={name}
            className="size-10 rounded-xl group-data-[collapsible=icon]:size-8"
        />
    );
}

export function AuthBrandLogo() {
    const { name } = usePage<SharedData>().props;

    return <LogoImage src={LOGO_FULL_PATH} alt={name} className="h-16 max-w-[220px]" />;
}

export default function AppLogo() {
    const { name } = usePage<SharedData>().props;

    return (
        <>
            <SidebarBrandIcon />
            <div className="ms-2 grid min-w-0 flex-1 text-start leading-tight group-data-[collapsible=icon]:hidden">
                <span className="truncate text-base font-bold">{name}</span>
                <span className="text-muted-foreground truncate text-xs">{APP_TAGLINE}</span>
            </div>
        </>
    );
}

export function useAppBrand() {
    const { name } = usePage<SharedData>().props;

    return { name, tagline: APP_TAGLINE };
}
