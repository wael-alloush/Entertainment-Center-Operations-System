import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="تسجيل الدخول" description="أدخل بيانات حسابك للوصول إلى لوحة التحكم">
            <Head title="تسجيل الدخول" />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="email">البريد الإلكتروني</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="email"
                            className="rounded-xl"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="email@example.com"
                            dir="ltr"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center">
                            <Label htmlFor="password">كلمة المرور</Label>
                            {canResetPassword && (
                                <TextLink href={route('password.request')} className="mr-auto text-sm" tabIndex={5}>
                                    نسيت كلمة المرور؟
                                </TextLink>
                            )}
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            className="rounded-xl"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="••••••••"
                            dir="ltr"
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="remember"
                            checked={data.remember}
                            onCheckedChange={(checked) => setData('remember', checked === true)}
                            tabIndex={3}
                        />
                        <Label htmlFor="remember">تذكرني</Label>
                    </div>

                    <Button type="submit" className="mt-2 w-full rounded-xl" tabIndex={4} disabled={processing}>
                        {processing && <LoaderCircle className="size-4 animate-spin" />}
                        دخول
                    </Button>
                </div>

                <div className="text-muted-foreground text-center text-sm">
                    ليس لديك حساب؟{' '}
                    <TextLink href={route('register')} tabIndex={5}>
                        إنشاء حساب
                    </TextLink>
                </div>
            </form>

            {status && <div className="text-success text-center text-sm font-medium">{status}</div>}
        </AuthLayout>
    );
}
