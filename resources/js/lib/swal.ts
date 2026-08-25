import Swal from 'sweetalert2';

/**
 * A toast-style SweetAlert mixin, themed to match the current light/dark
 * appearance (read live from the `dark` class `useAppearance` toggles on
 * <html>, since this helper is called from plain event handlers rather
 * than component render, where the appearance hook isn't available).
 */
function themedToast() {
    const isDark = document.documentElement.classList.contains('dark');

    return Swal.mixin({
        toast: true,
        position: 'top-end',
        timer: 3500,
        timerProgressBar: true,
        showConfirmButton: false,
        background: isDark ? '#1f2937' : '#ffffff',
        color: isDark ? '#f3f4f6' : '#111827',
    });
}

export function notifySuccess(message: string, title = 'สำเร็จ'): void {
    void themedToast().fire({ icon: 'success', title, text: message });
}

export function notifyError(message: string, title = 'เกิดข้อผิดพลาด'): void {
    void themedToast().fire({ icon: 'error', title, text: message });
}

export function notifyInfo(message: string, title?: string): void {
    void themedToast().fire({ icon: 'info', title, text: message });
}

/**
 * A centered, must-acknowledge modal (not the auto-dismissing toast the
 * helpers above use) — for messages the user needs to actually read, like
 * the maintenance-mode notice shown before login.
 */
export function alertMaintenance(
    message: string,
    title = 'ระบบอยู่ระหว่างการปรับปรุง',
): void {
    const isDark = document.documentElement.classList.contains('dark');

    void Swal.fire({
        icon: 'warning',
        title,
        text: message,
        confirmButtonText: 'เข้าใจแล้ว',
        background: isDark ? '#1f2937' : '#ffffff',
        color: isDark ? '#f3f4f6' : '#111827',
    });
}
