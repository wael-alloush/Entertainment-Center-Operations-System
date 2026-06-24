import { Head, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, MonitorSmartphone } from 'lucide-react';
import { FormEventHandler } from 'react';

import { FlashAlert } from '@/components/raad/flash-alert';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface RegisterDeviceProps {
    isAuthenticated: boolean;
}

export default function RegisterDevice({ isAuthenticated }: RegisterDeviceProps) {
    const { flash } = usePage<{
        flash: { success?: string; error?: string };
    }>().props;

    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('device.register.store'));
    };

    return (
        <AuthLayout
            title="توثيق الجهاز"
            description="أدخل رمز التوثيق لمرة واحدة الذي أنشأه مدير النظام لاستخدام هذا الجهاز"
        >
            <Head title="توثيق الجهاز" />

            <div className="mb-4 flex justify-center">
                <span className="raad-icon-chip bg-amber-500/12 text-amber-600 dark:text-amber-400">
                    <MonitorSmartphone className="size-6" strokeWidth={2.25} />
                </span>
            </div>

            <FlashAlert message={flash?.success} />
            <FlashAlert message={flash?.error} variant="error" />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="code">رمز التوثيق</Label>
                        <Input
                            id="code"
                            required
                            autoFocus
                            dir="ltr"
                            className="rounded-xl text-center font-mono text-lg tracking-widest uppercase"
                            value={data.code}
                            onChange={(event) => setData('code', event.target.value.toUpperCase())}
                            placeholder="XXXX-YYYY"
                            maxLength={9}
                        />
                        <InputError message={errors.code} />
                        <p className="text-muted-foreground text-xs">الرمز صالح لمدة 15 دقيقة ويُستخدم مرة واحدة فقط</p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="name">اسم الجهاز</Label>
                        <Input
                            id="name"
                            required
                            className="rounded-xl"
                            value={data.name}
                            onChange={(event) => setData('name', event.target.value)}
                            placeholder="مثال: استقبال 1"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <Button type="submit" className="mt-2 w-full rounded-xl" disabled={processing}>
                        {processing && <LoaderCircle className="size-4 animate-spin" />}
                        توثيق الجهاز
                    </Button>
                </div>

                <div className="text-muted-foreground text-center text-sm">
                    {isAuthenticated ? (
                        <TextLink href={route('invoices.active')}>العودة للفواتير النشطة</TextLink>
                    ) : (
                        <>
                            بعد التوثيق{' '}
                            <TextLink href={route('login')}>سجّل الدخول</TextLink>
                        </>
                    )}
                </div>
            </form>
        </AuthLayout>
    );
}
