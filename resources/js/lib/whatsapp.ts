export function whatsappUrl(phoneNumber: string, message?: string): string {
    const normalized = phoneNumber.replace(/\D/g, '').replace(/^0/, '');
    const base = `https://wa.me/966${normalized}`;

    if (!message) {
        return base;
    }

    return `${base}?text=${encodeURIComponent(message)}`;
}

interface PendingBonusWhatsappData {
    phone_number: string;
    children_count: number;
    session_duration: {
        name: string;
    };
}

export function pendingBonusWhatsappMessage(bonus: PendingBonusWhatsappData, validityDays: number): string {
    return `⭐️🎁 *مكافأة لعب مجانية!* 🎁⭐️

مرحبًا عميلنا العزيز 👋

يسر مركز أرض البراعم للترفيه أن يهديكم هذه المكافأة:

📱 *رقم الجوال:* ( ${bonus.phone_number} )
👧👦 *عدد الأطفال:* ( ${bonus.children_count} )
⏰ *مدة اللعب:* ( ${bonus.session_duration.name} )

✨ المكافأة صالحة لمدة ${validityDays} يوم فقط ✨

✨ استمتعوا بتجربة ترفيهية ممتعة وآمنة لأطفالكم معنا! ✨

يرجى إبراز هذه الرسالة للكاشير عند الوصول.
نفرح لرؤيتكم قريبًا! 🌟

*مركز أرض البراعم للترفيه*`;
}

export function pendingBonusWhatsappUrl(bonus: PendingBonusWhatsappData, validityDays: number): string {
    return whatsappUrl(bonus.phone_number, pendingBonusWhatsappMessage(bonus, validityDays));
}
