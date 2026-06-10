import { Sparkles } from 'lucide-react';

export function SidebarBrandIcon() {
    return (
        <div className="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-violet-600 shadow-md shadow-primary/30">
            <Sparkles className="text-primary-foreground size-5" />
        </div>
    );
}

export default function AppLogo() {
    return (
        <>
            <SidebarBrandIcon />
            <div className="mr-2 grid flex-1 text-right leading-tight group-data-[collapsible=icon]:hidden">
                <span className="truncate text-base font-bold">رعد</span>
                <span className="text-muted-foreground truncate text-xs">مركز أرض البراعم</span>
            </div>
        </>
    );
}
