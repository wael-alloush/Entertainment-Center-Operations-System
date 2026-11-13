export function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export async function jsonFetch<T>(url: string, options?: RequestInit): Promise<T> {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken(),
            ...(options?.headers ?? {}),
        },
        credentials: 'same-origin',
    });

    const data = (await response.json().catch(() => ({}))) as T & { errors?: Record<string, string[]>; message?: string };

    if (!response.ok) {
        const fallbackMessage =
            response.status === 403
                ? 'ليس لديك صلاحية لتنفيذ هذا الإجراء.'
                : response.status === 419
                  ? 'انتهت الجلسة. حدّث الصفحة ثم حاول مرة أخرى.'
                  : 'Request failed';

        const error = new Error(data.message ?? fallbackMessage) as Error & { errors?: Record<string, string[]>; status: number };
        error.errors = data.errors;
        error.status = response.status;
        throw error;
    }

    return data;
}
