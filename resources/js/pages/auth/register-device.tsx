import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, MonitorSmartphone } from 'lucide-react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { FlashAlert } from '@/components/raad/flash-alert';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import {
    base64UrlToBuffer,
    isWebAuthnSupported,
    normalizeAssertionCredential,
    normalizeAttestationCredential,
    webAuthnErrorMessage,
} from '@/lib/webauthn';

interface RegisterDeviceProps {
    isAuthenticated: boolean;
    canAttemptPasskeyRestore: boolean;
}

type WebAuthnRequestOptions = {
    challenge: string;
    timeout?: number;
    rpId: string;
    allowCredentials: Array<{
        type: string;
        id: string;
        transports?: PublicKeyCredentialTransport[];
    }>;
    userVerification?: UserVerificationRequirement;
};

type WebAuthnCreationOptions = {
    challenge: string;
    timeout?: number;
    attestation?: AttestationConveyancePreference;
    rp: PublicKeyCredentialRpEntity;
    user: {
        id: string;
        name: string;
        displayName: string;
    };
    pubKeyCredParams: PublicKeyCredentialParameters[];
    authenticatorSelection?: AuthenticatorSelectionCriteria;
};

export default function RegisterDevice({ isAuthenticated, canAttemptPasskeyRestore }: RegisterDeviceProps) {
    const { flash } = usePage<{
        flash: { success?: string; error?: string };
    }>().props;

    const webAuthnAvailable = isWebAuthnSupported();
    const shouldAttemptPasskeyRestore = isAuthenticated && canAttemptPasskeyRestore && webAuthnAvailable;

    const [passkeyError, setPasskeyError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [passkeyRestorePending, setPasskeyRestorePending] = useState(shouldAttemptPasskeyRestore);
    const [passkeyRestoreFinished, setPasskeyRestoreFinished] = useState(!shouldAttemptPasskeyRestore);
    const passkeyRestoreStarted = useRef(false);

    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        code: '',
        name: '',
    });

    useEffect(() => {
        if (!shouldAttemptPasskeyRestore || passkeyRestoreStarted.current) {
            return;
        }

        passkeyRestoreStarted.current = true;
        let cancelled = false;

        const attemptPasskeyRestore = async () => {
            try {
                const optionsResponse = await fetch(route('device.webauthn.assertion-options'), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });

                if (!optionsResponse.ok || cancelled) {
                    return;
                }

                const requestOptions = (await optionsResponse.json()) as WebAuthnRequestOptions;

                const credential = await navigator.credentials.get({
                    publicKey: {
                        ...requestOptions,
                        challenge: base64UrlToBuffer(requestOptions.challenge),
                        allowCredentials: requestOptions.allowCredentials.map((descriptor) => ({
                            ...descriptor,
                            id: base64UrlToBuffer(descriptor.id),
                        })),
                    },
                });

                if (!credential || cancelled) {
                    return;
                }

                await new Promise<void>((resolve) => {
                    router.post(
                        route('device.webauthn.restore'),
                        { credential: normalizeAssertionCredential(credential) },
                        {
                            preserveScroll: true,
                            onFinish: () => resolve(),
                            onError: () => resolve(),
                        },
                    );
                });
            } catch {
                // Passkey prompt cancelled or failed — fall through to device registration form.
            } finally {
                if (!cancelled) {
                    setPasskeyRestorePending(false);
                    setPasskeyRestoreFinished(true);
                }
            }
        };

        void attemptPasskeyRestore();

        return () => {
            cancelled = true;
        };
    }, [shouldAttemptPasskeyRestore]);

    const submit: FormEventHandler = async (event) => {
        event.preventDefault();

        setPasskeyError(null);
        clearErrors();

        if (!isAuthenticated) {
            post(route('device.register.store'));
            return;
        }

        if (!webAuthnAvailable) {
            setError('code', 'Passkey غير متاح. افتح الموقع عبر HTTPS واستخدم متصفحاً حديثاً.');
            return;
        }

        try {
            const optionsUrl = `${route('device.webauthn.options')}?name=${encodeURIComponent(data.name)}`;
            const optionsResponse = await fetch(optionsUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!optionsResponse.ok) {
                throw new Error('تعذر بدء توثيق Passkey. أعد تحميل الصفحة ثم حاول مجدداً.');
            }

            const creationOptions = (await optionsResponse.json()) as WebAuthnCreationOptions;
            const credential = await navigator.credentials.create({
                publicKey: {
                    ...creationOptions,
                    challenge: base64UrlToBuffer(creationOptions.challenge),
                    user: {
                        ...creationOptions.user,
                        id: base64UrlToBuffer(creationOptions.user.id),
                    },
                },
            });

            setSubmitting(true);

            router.post(
                route('device.webauthn.register'),
                {
                    code: data.code,
                    name: data.name,
                    credential: normalizeAttestationCredential(credential),
                },
                {
                    preserveScroll: true,
                    onFinish: () => setSubmitting(false),
                    onError: (formErrors) => {
                        if (typeof formErrors.credential === 'string') {
                            setPasskeyError(formErrors.credential);
                        } else if (typeof formErrors.code === 'string') {
                            setPasskeyError(formErrors.code);
                        } else {
                            setPasskeyError('تعذر إكمال توثيق الجهاز. تحقق من الرمز ثم أعد المحاولة.');
                        }
                    },
                },
            );
        } catch (error) {
            setSubmitting(false);
            setPasskeyError(webAuthnErrorMessage(error));
        }
    };

    if (passkeyRestorePending) {
        return (
            <AuthLayout title="تسجيل الدخول" description="">
                <Head title="تسجيل الدخول" />
            </AuthLayout>
        );
    }

    return (
        <AuthLayout
            title="توثيق الجهاز"
            description="أدخل رمز التوثيق الذي أنشأه مدير النظام لاستخدام هذا الجهاز لأول مرة"
        >
            <Head title="توثيق الجهاز" />

            <div className="mb-4 flex justify-center">
                <span className="raad-icon-chip bg-amber-500/12 text-amber-600 dark:text-amber-400">
                    <MonitorSmartphone className="size-6" strokeWidth={2.25} />
                </span>
            </div>

            <FlashAlert message={flash?.success} />
            <FlashAlert message={flash?.error} variant="error" />
            <FlashAlert message={passkeyError} variant="error" />

            {passkeyRestoreFinished && (
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
                            <InputError message={errors.credential} />
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

                        {isAuthenticated && (
                            <p className="text-muted-foreground text-xs">
                                بعد إدخال الرمز، سيُطلب منك Passkey لإكمال التوثيق على هذا الجهاز.
                            </p>
                        )}

                        {isAuthenticated && !webAuthnAvailable && (
                            <p className="text-xs text-red-600 dark:text-red-400">
                                Passkey غير متاح. افتح الموقع عبر HTTPS واستخدم متصفحاً حديثاً.
                            </p>
                        )}

                        <Button type="submit" className="mt-2 w-full rounded-xl" disabled={processing || submitting}>
                            {(processing || submitting) && <LoaderCircle className="size-4 animate-spin" />}
                            توثيق الجهاز
                        </Button>
                    </div>

                    <div className="text-muted-foreground text-center text-sm">
                        {isAuthenticated ? (
                            <TextLink href={route('invoices.active')}>العودة للفواتير النشطة</TextLink>
                        ) : (
                            <>
                                بعد التوثيق <TextLink href={route('login')}>سجّل الدخول</TextLink>
                            </>
                        )}
                    </div>
                </form>
            )}
        </AuthLayout>
    );
}
