export type WebAuthnAssertionCredentialPayload = {
    id: string;
    rawId: string;
    type: string;
    response: {
        clientDataJSON: string;
        authenticatorData: string;
        signature: string;
        userHandle?: string | null;
    };
};

export type WebAuthnCredentialPayload = {
    id: string;
    rawId: string;
    type: string;
    response: {
        clientDataJSON: string;
        attestationObject: string;
        transports?: PublicKeyCredentialTransport[];
    };
};

export function isWebAuthnSupported(): boolean {
    return typeof window !== 'undefined' && window.isSecureContext && typeof window.PublicKeyCredential !== 'undefined';
}

export function base64UrlToBuffer(base64UrlValue: string): ArrayBuffer {
    const base64 = base64UrlValue.replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
    const binary = atob(padded);
    const bytes = new Uint8Array(binary.length);

    for (let index = 0; index < binary.length; index += 1) {
        bytes[index] = binary.charCodeAt(index);
    }

    return bytes.buffer;
}

export function bufferToBase64Url(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    let binary = '';

    for (let index = 0; index < bytes.length; index += 1) {
        binary += String.fromCharCode(bytes[index]);
    }

    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

export function normalizeAttestationCredential(credential: Credential): WebAuthnCredentialPayload {
    if (!(credential instanceof PublicKeyCredential)) {
        throw new Error('تعذر قراءة بيانات Passkey من المتصفح.');
    }

    if (!(credential.response instanceof AuthenticatorAttestationResponse)) {
        throw new Error('تعذر إكمال إعداد Passkey على هذا الجهاز.');
    }

    return {
        id: credential.id,
        rawId: bufferToBase64Url(credential.rawId),
        type: credential.type,
        response: {
            clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
            attestationObject: bufferToBase64Url(credential.response.attestationObject),
            transports: typeof credential.response.getTransports === 'function' ? credential.response.getTransports() : undefined,
        },
    };
}

export function normalizeAssertionCredential(credential: Credential): WebAuthnAssertionCredentialPayload {
    if (!(credential instanceof PublicKeyCredential)) {
        throw new Error('تعذر قراءة بيانات Passkey من المتصفح.');
    }

    if (!(credential.response instanceof AuthenticatorAssertionResponse)) {
        throw new Error('تعذر التحقق من Passkey على هذا الجهاز.');
    }

    const encodedId = bufferToBase64Url(credential.rawId);

    return {
        id: encodedId,
        rawId: encodedId,
        type: credential.type,
        response: {
            clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
            authenticatorData: bufferToBase64Url(credential.response.authenticatorData),
            signature: bufferToBase64Url(credential.response.signature),
            userHandle: credential.response.userHandle ? bufferToBase64Url(credential.response.userHandle) : null,
        },
    };
}

export function isSilentAutoRestoreFailure(error: unknown): boolean {
    return error instanceof DOMException && (error.name === 'NotAllowedError' || error.name === 'AbortError');
}

export function webAuthnErrorMessage(error: unknown): string {
    if (error instanceof DOMException) {
        if (error.name === 'NotAllowedError') {
            return 'تم إلغاء طلب Passkey أو انتهت مهلة التأكيد.';
        }

        if (error.name === 'SecurityError') {
            return 'تعذر التحقق من Passkey. تأكد أنك تفتح الموقع عبر HTTPS ومن نفس النطاق المسجّل.';
        }

        if (error.name === 'InvalidStateError') {
            return 'Passkey مسجّل مسبقاً على هذا الجهاز. إذا استمرت المشكلة، اطلب رمز توثيق جديد من المدير.';
        }
    }

    return error instanceof Error ? error.message : 'تعذر إكمال العملية باستخدام Passkey.';
}
