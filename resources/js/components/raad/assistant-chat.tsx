import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Bot, LoaderCircle, MessageCircle, SendHorizontal, X } from 'lucide-react';
import { FormEvent, useEffect, useRef, useState } from 'react';

type ChatRole = 'user' | 'assistant';

type ChatMessage = {
    role: ChatRole;
    content: string;
};

type ChatResponse = {
    reply?: string;
    message?: string;
    tools_used?: string[];
};

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

const SUGGESTIONS = ['كم فاتورة نشطة الآن؟', 'ملخص اليوم', 'ما إعدادات البونص؟'];

export function AssistantChat() {
    const [open, setOpen] = useState(false);
    const [input, setInput] = useState('');
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const listRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        listRef.current?.scrollTo({ top: listRef.current.scrollHeight, behavior: 'smooth' });
    }, [messages, open, loading]);

    const sendMessage = async (rawMessage: string) => {
        const message = rawMessage.trim();

        if (message === '' || loading) {
            return;
        }

        const history = messages.slice(-20);
        setMessages((current) => [...current, { role: 'user', content: message }]);
        setInput('');
        setError(null);
        setLoading(true);

        try {
            const response = await fetch(route('assistant.chat'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message, history }),
            });

            const payload = (await response.json().catch(() => ({}))) as ChatResponse;

            if (!response.ok) {
                throw new Error(payload.message || 'تعذّر إرسال الرسالة.');
            }

            setMessages((current) => [
                ...current,
                {
                    role: 'assistant',
                    content: payload.reply?.trim() || 'لم يصل رد واضح من المساعد.',
                },
            ]);
        } catch (sendError) {
            const messageText = sendError instanceof Error ? sendError.message : 'تعذّر إرسال الرسالة.';
            setError(messageText);
        } finally {
            setLoading(false);
        }
    };

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();
        void sendMessage(input);
    };

    return (
        <div className="pointer-events-none fixed bottom-4 end-4 z-[80] flex flex-col items-end gap-3">
            {open && (
                <div className="pointer-events-auto flex h-[min(32rem,calc(100dvh-6rem))] w-[min(24rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-border/70 bg-background shadow-2xl">
                    <div className="flex items-center justify-between gap-3 border-b border-border/60 bg-muted/40 px-4 py-3">
                        <div className="flex items-center gap-2">
                            <span className="flex size-8 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                                <Bot className="size-4" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold">مساعد buds</p>
                                <p className="text-muted-foreground text-xs">اسأل عن الزيارات والفواتير والبونص</p>
                            </div>
                        </div>
                        <Button type="button" variant="ghost" size="icon" onClick={() => setOpen(false)} aria-label="إغلاق المساعد">
                            <X className="size-4" />
                        </Button>
                    </div>

                    <div ref={listRef} className="flex-1 space-y-3 overflow-y-auto px-4 py-3">
                        {messages.length === 0 && (
                            <div className="space-y-3">
                                <p className="text-muted-foreground text-sm">مرحباً. يمكنني المساعدة بأسئلة مثل:</p>
                                <div className="flex flex-wrap gap-2">
                                    {SUGGESTIONS.map((suggestion) => (
                                        <button
                                            key={suggestion}
                                            type="button"
                                            className="rounded-full border border-border/70 bg-muted/40 px-3 py-1.5 text-xs transition hover:bg-muted"
                                            onClick={() => void sendMessage(suggestion)}
                                            disabled={loading}
                                        >
                                            {suggestion}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {messages.map((message, index) => (
                            <div
                                key={`${message.role}-${index}`}
                                className={cn('flex', message.role === 'user' ? 'justify-start' : 'justify-end')}
                            >
                                <div
                                    className={cn(
                                        'max-w-[90%] rounded-2xl px-3 py-2 text-sm leading-6 whitespace-pre-wrap',
                                        message.role === 'user'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-foreground',
                                    )}
                                >
                                    {message.content}
                                </div>
                            </div>
                        ))}

                        {loading && (
                            <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                <LoaderCircle className="size-3.5 animate-spin" />
                                جاري التفكير...
                            </div>
                        )}

                        {error && <p className="text-destructive text-xs">{error}</p>}
                    </div>

                    <form onSubmit={handleSubmit} className="flex items-center gap-2 border-t border-border/60 p-3">
                        <Input
                            value={input}
                            onChange={(event) => setInput(event.target.value)}
                            placeholder="اكتب سؤالك..."
                            disabled={loading}
                            maxLength={1000}
                            className="text-sm"
                            autoComplete="off"
                        />
                        <Button type="submit" size="icon" disabled={loading || input.trim() === ''} aria-label="إرسال">
                            {loading ? <LoaderCircle className="size-4 animate-spin" /> : <SendHorizontal className="size-4" />}
                        </Button>
                    </form>
                </div>
            )}

            <Button
                type="button"
                size="lg"
                className="pointer-events-auto h-12 rounded-full px-4 shadow-lg"
                onClick={() => setOpen((current) => !current)}
                aria-expanded={open}
                aria-label={open ? 'إغلاق المساعد' : 'فتح المساعد'}
            >
                <MessageCircle className="size-4" />
                مساعد
            </Button>
        </div>
    );
}
